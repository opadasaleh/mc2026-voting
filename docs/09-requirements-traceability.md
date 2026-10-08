# 09 — Requirements Traceability

Every requirement in the challenge statement, mapped to where it is implemented and how it is verified. Backend = this repository's Laravel app; Frontend = the Next.js voting page and TV screen built against [03 API contract](03-api-contract.md).

Status: ✅ done · 🟡 done with a stated deviation or pending item · 🔵 frontend responsibility (backend support done)

## 1. Functional requirements

| # | Requirement | Status | Implementation | Verified by |
|---|---|---|---|---|
| F1 | Exhibitor listing with photo, name, short description, category | ✅ | `GET /events/{event}/categories` returns active categories with their active exhibitors and absolute `photo_url` (`EventController@categories`) | `PublicReadTest` |
| F2 | One vote per category (3 categories = 3 selections max) | ✅ | `POST …/votes` (`VoteService`); DB `UNIQUE (visitor_id, category_id)`; max 3 categories per event (`Category` guard, admin button disabled) | `VoteTest`, `SchemaConstraintsTest`, `AdminPanelTest` |
| F3 | Confirmation after each vote; see categories already voted | ✅ | `201` response with `remaining_category_ids`; `GET …/me` lists votes and remaining categories | `VoteTest`, `OtpFlowTest` |
| F4 | Mobile-friendly UI via QR code | 🔵 | Frontend. Backend: one QR per event slug (`/events/{slug}`), small JSON payloads, `server_time` for countdowns | — |
| F5 | No login: name + phone only, no password | ✅ | `POST …/auth/otp/request {full_name, phone}`; phone = identity, normalised to E.164 (`PhoneNumber`) | `OtpFlowTest`, `PhoneNumberTest` |
| F6 | SMS OTP before the vote is accepted | ✅ | `POST …/auth/otp/verify` issues the only token that can vote; codes hashed, 5-min expiry, 5 attempts; `SmsSender` interface (gateway from CPF) | `OtpFlowTest`, `VoteTest` |
| F7 | Live standings per category, ranked by votes | ✅ | `GET …/results` + SSE `GET …/results/stream` (`ResultsController`, `Standings`): ties share a rank, zero-vote exhibitors shown; admin dashboard widget | `ResultsApiTest`, `StandingsTest`, `ResultsAdminTest` |
| F8 | Big-screen layout | 🔵 | Frontend. Backend: compact snapshot payload with ranks, totals, voting status; protected by per-screen display tokens (brief: "should be protected webpage") | `ResultsApiTest`, `DisplayTokensAdminTest` |
| F9 | Add/edit/remove exhibitors, assign to categories, upload photo | 🟡 | Filament `ExhibitorResource` (create, edit, soft delete, photo upload, category filter). **Deviation:** each exhibitor is in exactly **one** category (spec: "one or more"); see [06](06-design-decisions.md) D13 | `AdminPanelTest` |
| F10 | Open/close voting | ✅ | Dashboard **Open / Close voting** buttons + optional window (`opens_at`, `closes_at`); enforced on OTP request and vote; status in `GET /events/{event}` | `EventVotingStatusTest`, `AdminPanelTest`, `VoteTest` |
| F11 | On-site access control; reject requests outside | ✅ | Venue Wi-Fi public IP allowlist per event (`EnsureOnSite`, `VenueAccess`), `403 OFF_SITE`, `GET …/access-check` for the "No access" page, trusted-proxy-only forwarding, live editing + "Use my current IP" | `OnSiteHttpTest`, `VenueAccessTest`, `IpOrCidrTest` |
| F12 | One verified phone may cast one vote per category | ✅ | Phone HMAC unique per visitor + OTP + DB `UNIQUE` + composite foreign key + immutable votes + idempotent retry | `VoteTest`, `SchemaConstraintsTest`, `OtpFlowTest` |
| F13 | Export final counts per category | ✅ | Dashboard **Results → Export results (CSV)** (ranked per category, formula-injection safe, audited) | `ResultsAdminTest` |
| F14 | Store name + phone linked to votes, securely, for outreach | ✅ | `visitors` (phone encrypted + HMAC), `event_registrations` (name per event), `votes.visitor_id`; **Export visitors (CSV)** for admins only (audited); RLS closes Supabase's public API | `RowLevelSecurityTest`, `ResultsAdminTest` |

Also from §3 of the brief:

| Brief item | Status | Implementation |
|---|---|---|
| Reset results | ✅ | Dashboard **Results → Reset results**: only while voting is closed, requires typing the event slug, audited (`ResultsAdminTest`) |
| Admin: username + password, MFA if possible | ✅ | Username login + **required** TOTP MFA with recovery codes (`AdminPanelTest`); TOTP secret encryption at rest is an open item ([05](05-auth-and-access-control.md) §7) |
| Real-time results via WebSocket/SSE/long polling | ✅ | SSE with polling fallback |
| 3 categories, to be confirmed by the Makerspace team | ✅ | Categories are data (max 3, configurable); sample MC2026 categories seeded for development |

## 2. Non-functional requirements

| Area | Requirement | Status | How |
|---|---|---|---|
| Scale | Up to 1,000 concurrent/total users | 🟡 | Sized in [08](08-scaling.md): ~10 requests per visitor, design target 85 req/s vs. ~400 req/s estimated for 2 small servers. k6 load test planned to measure it |
| Reliability | No single point of failure; handles network drops | ✅ | ≥ 2 stateless app servers, managed database with backups, idempotent votes, full-snapshot SSE with auto-reconnect ([08](08-scaling.md) §5–6) |
| Usability | Non-technical visitor, zero instructions | 🔵 | Frontend. Backend support: only name + phone, human-readable error messages, `access-check` + Wi-Fi name for a helpful "No access" page, `retry_after` on limits |
| Portability | Local server or cloud, no special hardware | ✅ | Plain PHP 8.3 + Postgres; cloud or venue LAN setups in [07](07-deployment.md) §8 |
| Code | Stateless, maintainable, documented (architecture, deployment, design decisions, ERD, DFD, authentication, authorization) | ✅ | Stateless by design ([01](01-architecture.md) §2); docs [01](01-architecture.md)–[09](09-requirements-traceability.md); 135 automated tests |
| Privacy | Name/phone stored securely, admin-only | ✅ | Encryption + blind index, RLS lock-down, admin-only audited exports ([05](05-auth-and-access-control.md) §6) |

## 3. Assumptions & constraints

| Constraint | How it is met |
|---|---|
| All data and settings database-driven (start/end time, IP / geofencing, …) | Events, categories, exhibitors, voting window, venue IP ranges, Wi-Fi name and OTP policy are all rows edited in the admin panel. Geofencing was dropped on purpose in favour of the stronger IP rule ([06](06-design-decisions.md) D6) |
| Exhibitor data may be mock | `Mc2026Seeder` sample categories and exhibitors; `voting:demo-votes` demo voters |
| SMS gateway chosen by CPF | `SmsSender` interface; `log` driver until then ([07](07-deployment.md) §7) |

## 4. Expected deliverables (§7 of the brief)

| Deliverable | Where |
|---|---|
| Working prototype: exhibitor listing → vote per category → live results | API ([03](03-api-contract.md)) + frontend; admin live results widget |
| Basic admin capability to add exhibitors/categories | Filament admin panel (`/admin`) |
| Technical write-up: architecture, stack | [01 Architecture](01-architecture.md) |
| … how access control and anti-fraud were handled | [05 Authentication, authorization & anti-fraud](05-auth-and-access-control.md), [03](03-api-contract.md) §7 |
| … deployment requirements | [07 Deployment](07-deployment.md) |
| … how to scale to 1,000+ users | [08 Scaling](08-scaling.md) |
| Data model, data flow, design decisions | [02 ERD](02-erd.md), [04 DFD](04-data-flow.md), [06 Design decisions](06-design-decisions.md) |
