# 08 — Scaling to 1,000 Users

Target from the brief: **up to 1,000 concurrent/total users**, with **no single point of failure that pauses voting** and **smooth handling of network drops**. This document sizes the load, shows where the limits are, and lists what to change if the event grows.

> The capacity figures below are engineering estimates. A k6 load test that replays the full visitor flow (access check → OTP → vote) is the next planned step and will replace them with measured numbers.

## 1. How much load is 1,000 visitors?

API calls per visitor, end to end:

| Step | Calls |
|---|---|
| Open the event page (event, categories, access check) | 3 |
| Request a code (sometimes a resend) | 1–2 |
| Verify the code | 1 |
| Load "my votes" | 1 |
| Vote in 3 categories | 3 |
| **Total** | **≈ 10 small JSON requests** |

**1,000 visitors ≈ 10,000 API requests and at most 3,000 vote rows for the whole event.** What matters is how bunched they are:

| Scenario | Requests per second |
|---|---|
| Spread over a 6-hour event | < 1 |
| Rush: 300 visitors vote within 5 minutes of an announcement | ≈ 10, bursts of 30–50 |
| **Design target:** all 1,000 visitors within 2 minutes | **≈ 85** |

The TV screens add almost nothing: each open stream reads a cached snapshot every 2 s, and the cache means at most **one tally query per second** in total, however many screens are connected.

## 2. Capacity of the design

| Layer | Estimated capacity | Headroom vs. 85 req/s |
|---|---|---|
| **App server** (2 vCPU, PHP-FPM, OPcache, cached config/routes) | ~10 ms CPU + a few 1–2 ms database round trips per request → ~150–200 req/s per server | 2 servers ≈ 4× |
| **Database** (Supabase Postgres) | Every request is a handful of indexed lookups plus at most one small insert. Postgres handles thousands of such operations per second even on small compute | Very large |
| **Votes table** | ≤ 3,000 rows per event; tally = one grouped count over an indexed column | Trivial |
| **SMS** | 1,000 messages per event; gateway throughput is typically tens per second | Sufficient; queue if the gateway is slow |
| **Venue Wi-Fi** | Outside our control: the real bottleneck (§4) | — |

Why it scales:

- **Stateless servers** ([06](06-design-decisions.md) D15): no sessions or files in memory or on local disk, so capacity grows by adding servers behind the load balancer.
- **App servers in the same region as the database (Frankfurt).** Each request makes several database calls; keeping them ~1 ms apart matters more than the distance from Amman to the server (one round trip per request, ~60–80 ms).
- **Correctness does not depend on timing.** One-vote-per-category is a database constraint, OTP checks run under row locks, so more servers and more concurrency never create double votes.
- **Rate limits never block the crowd.** All visitors share the venue's public IP, so limits are per phone and per token; the venue IP only has a 5,000/min safety ceiling.
- **Small payloads.** JSON responses are a few KB; photos come from object storage.

## 3. Things to configure for the event

| Item | Setting | Why |
|---|---|---|
| App servers | 2 × (2 vCPU, 2–4 GB), Frankfurt, behind a load balancer with `/up` health check | Capacity with headroom, and losing one server does not stop voting |
| PHP-FPM | `pm = static`, ~20 workers per 2 GB server | Predictable capacity; each open TV stream holds one worker for up to 5 min |
| Database connections | Supabase **transaction pooler** (port 6543) with `DB_EMULATE_PREPARES=true`; check the plan's pooler client limit is above total workers (2 × 20) | The pooler multiplexes many app connections onto a small pool of real connections |
| Supabase plan | Paid tier during the event | Daily backups and point-in-time recovery, more compute and connections, no free-tier pausing |
| Photos | Object storage, images resized to ~40–100 KB, frontend lazy-loads them | Biggest bandwidth item for the venue Wi-Fi (below) |
| OPcache + `php artisan optimize` | Enabled | Removes framework bootstrap cost |

## 4. The venue network is the real limit

Our requests are tiny; 1,000 phones on one Wi-Fi are not. Ask the venue to confirm:

- Enough access points for 1,000+ devices, with a DHCP pool larger than the crowd and short lease times.
- NAT capacity and internet bandwidth. Photos dominate: 30 exhibitors × 100 KB ≈ 3 MB per visitor. A rush of 300 visitors in 5 minutes ≈ 900 MB ≈ **24 Mbit/s**. With 40 KB thumbnails it is under 10 Mbit/s.
- All public egress IPs (IPv4 and IPv6, every internet line) are known and entered in the event's allowed IPs.

## 5. No single point of failure (Reliability NFR)

| Component | If it fails | Mitigation |
|---|---|---|
| An app server | Load balancer stops sending traffic to it | ≥ 2 servers; stateless, so no session is lost |
| Load balancer | No API access | Use a managed load balancer (redundant by design) or a floating IP / DNS failover |
| Database | Voting pauses | Supabase managed Postgres with backups and PITR on paid tiers; app servers reconnect automatically. Recovery from backup stays possible because keys are stored outside the database |
| SMS gateway | New visitors cannot verify; already-verified visitors keep voting | Gateway chosen by CPF with an SLA; driver interface allows a second provider as fallback |
| Venue internet line | Visitors appear off-site or cannot connect | Second line with its IP also allowlisted; admins can add ranges live ("Use my current IP") |
| A TV screen or its stream | Results screen freezes | Browser reconnects automatically (`retry: 3000`); every message is a full snapshot; polling fallback; voting is unaffected |

## 6. Network drops on phones

- **Votes are safe to retry**: resending the same vote returns `200` with the original vote, never a double count ([03](03-api-contract.md) §5).
- **State is recoverable**: the token survives page reloads (frontend keeps it per event), and `GET …/me` returns which categories are already done.
- **OTP resend** has a 60 s cooldown with a clear `retry_after`, so a lost SMS is easy to recover from.
- **TV**: see above, automatic reconnect with full snapshots.

## 7. Growing beyond 1,000 users

In order of cost:

1. **More app servers** behind the load balancer: no code change.
2. **Redis** for cache, rate-limit counters and sessions (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`): takes those writes off Postgres. A configuration change.
3. **Queue the SMS** (`QUEUE_CONNECTION` already `database`; add a worker): keeps `otp/request` fast regardless of gateway latency.
4. **CDN** in front of photos and the frontend.
5. **Cache event rows** (event, categories) for a few seconds: removes the most repeated read.
6. **Larger Supabase compute or a read replica** for results if many screens or public result pages are added.
7. **Laravel Octane** (persistent workers) to cut per-request bootstrap time further.

## 8. Evidence so far

- **135 automated tests** against a real Postgres, including the constraints that make concurrent double votes impossible, idempotent retries, OTP limits, the shared-venue-IP rate-limit behaviour and the SSE stream.
- **Demo data generator** (`php artisan voting:demo-votes`) fills an event with verified visitors and realistic votes, so the dashboard and TV can be shown under load-like data.
- **Planned:** k6 load test of the full visitor flow at 1,000 virtual users plus TV streams, with results added to this document.
