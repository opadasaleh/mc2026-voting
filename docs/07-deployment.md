# 07 — Deployment Guide

How to run the backend (Laravel API + Filament admin panel) in production, plus the event-day checklist. The hosting provider is not fixed yet, so this guide is provider-neutral. **Recommended target:** two small Linux VPS instances in Frankfurt (next to the Supabase database) behind an HTTPS load balancer. A single server works for a pilot; a local server at the venue also works (§8).

The frontend (Next.js) is deployed separately by the frontend developer; it only needs the API base URL.

## 1. Requirements

| Item | Requirement |
|---|---|
| OS | Any Linux (Ubuntu 24.04 LTS assumed below). Windows works for development only |
| PHP | 8.3+ with extensions: `pdo_pgsql`, `intl`, `mbstring`, `openssl`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `session`, `tokenizer`, `xml`, OPcache enabled |
| Web server | nginx + PHP-FPM (or any server that can run PHP-FPM and does not buffer streamed responses) |
| Composer | 2.x |
| Node.js | Not needed (admin panel assets ship with Filament) |
| Database | Supabase project (Postgres 17), region **Frankfurt (eu-central-1)**; paid tier recommended for the event (daily backups, more pooler connections) |
| Object storage | S3-compatible bucket for exhibitor photos when running more than one app server (Supabase Storage works) |
| TLS | HTTPS certificate (e.g. Let's Encrypt) for the API domain |
| SMS | Gateway account and credentials from CPF, plus a driver class (§7) |
| Hardware | 2 vCPU / 2–4 GB RAM per app server is enough for 1,000 visitors ([08](08-scaling.md)) |

## 2. Configuration (`backend/.env`)

Start from `.env.example`. Values that matter in production:

| Variable | Production value | Notes |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Never expose debug pages |
| `APP_KEY` | `php artisan key:generate --show` once, **same value on every server** | Encrypts phone numbers and signs OTP hashes. **Back it up**; losing it makes stored phones unreadable |
| `APP_URL` | `https://api.example.org` | Used for absolute photo URLs |
| `PHONE_HASH_KEY` | `php -r "echo base64_encode(random_bytes(32));"` once, same on every server | Blind index for phone lookups. **Back it up**; losing it means existing visitors cannot be found by phone |
| `DB_HOST` / `DB_PORT` / `DB_USERNAME` / `DB_PASSWORD` | Supabase **pooler** host, user `postgres.<project-ref>` | Use the pooler, not the direct host (IPv6-only on many networks) |
| `DB_SSLMODE` | `require` | |
| `DB_EMULATE_PREPARES` | `true` when using the **transaction** pooler (port 6543), `false` for the session pooler (5432) | Transaction pooler recommended with several servers ([08](08-scaling.md)) |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | Keeps servers stateless |
| `TRUSTED_PROXIES` | IP/CIDR of your load balancer or reverse proxy | **Required behind a proxy**, otherwise every visitor appears to come from the proxy and fails the on-site check. Leave empty if clients connect directly |
| `FRONTEND_URLS` | `https://vote.example.org` (comma-separated if several) | Browser origins allowed to call the API (CORS). Must match the frontend's URL exactly |
| `FRONTEND_URL` | `https://vote.example.org` | Address encoded in each event's QR code (`/{event}` is appended). Optional: defaults to the first non-localhost `FRONTEND_URLS` entry. Check it in the admin **QR code → Show QR code** preview before printing |
| `PHOTOS_DISK` | `s3` (configure `AWS_*` for the bucket; needs `composer require league/flysystem-aws-s3-v3`) | `public` only on a single server |
| `SMS_DRIVER` | The gateway driver name once built | `log` writes codes to `storage/logs/sms.log` (demo only) |
| `LOG_LEVEL` | `info` | |
| `SEED_ADMIN_PASSWORD` | Optional | Otherwise the seeder prints a random password |

Tunable limits (defaults in `config/voting.php`): `RATE_LIMIT_VENUE_IP`, `RATE_LIMIT_OFFSITE_IP`, `RATE_LIMIT_VOTES_PER_TOKEN`, `RATE_LIMIT_RESULTS_PER_TOKEN`, `RATE_LIMIT_RESULTS_PER_IP`, `OTP_REQUESTS_PER_PHONE`, `OTP_VERIFICATIONS_PER_PHONE`, `VISITOR_TOKEN_HOURS`, `MAX_CATEGORIES_PER_EVENT`, `RESULTS_CACHE_SECONDS`, `RESULTS_STREAM_POLL_SECONDS`, `RESULTS_STREAM_HEARTBEAT_SECONDS`, `RESULTS_STREAM_MAX_SECONDS`, `ADMIN_TIMEZONE`.

## 3. First deployment

On each app server:

```bash
git clone <repo> /var/www/voting && cd /var/www/voting/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env            # then fill in the values from §2
php artisan storage:link        # only when PHOTOS_DISK=public
php artisan optimize            # caches config, routes, events, views
php artisan filament:optimize   # caches Filament components and icons
sudo chown -R www-data:www-data storage bootstrap/cache
```

Once, from any one server:

```bash
php artisan migrate --force                         # creates tables, constraints, RLS lock-down
php artisan db:seed --class=AdminUserSeeder --force # first admin; prints the password once
```

Do **not** run the full `db:seed` in production: it also loads the MC2026 sample exhibitors. Then sign in at `https://api.example.org/admin`, set up MFA with an authenticator app, store the recovery codes, change the password, and create the event.

## 4. nginx

```nginx
server {
    listen 443 ssl http2;
    server_name api.example.org;
    root /var/www/voting/backend/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/api.example.org/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.example.org/privkey.pem;

    client_max_body_size 10m;   # exhibitor photo uploads

    # Display tokens travel in ?token= for the TV stream: keep query strings out of the access log.
    log_format no_query '$remote_addr - [$time_local] "$request_method $uri" $status $body_bytes_sent $request_time';
    access_log /var/log/nginx/voting.access.log no_query;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_read_timeout 360s;   # SSE streams last up to ~5 minutes
    }
}
```

- The app sends `X-Accel-Buffering: no` on the stream, so nginx passes events through immediately.
- Behind a cloud load balancer, raise its idle timeout above the 15 s heartbeat (most default to 60 s, which is fine) and set `TRUSTED_PROXIES`.

**PHP-FPM pool** (`/etc/php/8.3/fpm/pool.d/www.conf`): `pm = static`, `pm.max_children = 20` on a 2 GB server (about 40–60 MB per worker). Each open TV stream holds one worker for up to 5 minutes, so keep a few spare. Keep `max_execution_time` at 0 or ≥ 330 s for the stream.

## 5. Updating

```bash
php artisan down --retry=15 # optional; skip during voting if the change is safe
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force  # from one server only
php artisan optimize && php artisan filament:optimize
sudo systemctl reload php8.3-fpm
php artisan up
```

With two servers behind the load balancer, update one at a time and voting never stops.

## 6. Backups and monitoring

- Supabase daily backups (paid tier) plus a manual `pg_dump` right after voting closes. Export results CSV and visitor CSV from the admin panel as a second copy (both are audit-logged).
- Store `APP_KEY` and `PHONE_HASH_KEY` in a password manager. Without them a database backup cannot be read or matched.
- Health check endpoint for the load balancer: `GET /up` (Laravel built-in).
- Watch: `storage/logs/laravel.log` (expected client errors such as `OFF_SITE` are not logged), PHP-FPM "max children reached" warnings, Supabase dashboard connections and CPU.

## 7. SMS gateway

1. Create `app/Services/Sms/<Provider>SmsSender.php` implementing `SmsSender::send(string $phoneE164, string $message)`.
2. Add a case for it in `AppServiceProvider::register()` and its credentials to `.env`.
3. Set `SMS_DRIVER=<provider>`; send a test code to a team phone during the rehearsal.

## 8. Local server option (Portability NFR)

The same code runs on any machine at the venue (a laptop or mini PC with PHP 8.3 + nginx, or Docker with a PHP-FPM image). In that case visitors reach it over the venue LAN, so put the **LAN range** (e.g. `192.168.0.0/16`) in the event's allowed IPs instead of the public IP. The database can stay on Supabase (needs internet) or be a local Postgres 17 (`DB_HOST=127.0.0.1`; run the migrations there). `php artisan serve` is for development only: on Windows it is single-threaded, so one open TV stream would block every other request.

## 9. Event-day checklist

**One week before**
- [ ] Venue answers: public IPv4/IPv6 egress addresses, number of internet lines, captive portal or proxy, capacity for 1,000+ devices, client isolation, can Apple Private Relay / VPNs be blocked.
- [ ] Event created; categories and exhibitors entered with photos; voting window set; venue Wi-Fi name entered.
- [ ] SMS gateway live; test code received on Zain, Orange and Umniah numbers.
- [ ] TV display token created for each screen; TV page tested end to end.
- [ ] Voting QR code downloaded (admin dashboard → QR code → SVG), scanned with a phone, printed for posters and exhibitor tables.

**At the venue (rehearsal)**
- [ ] On the venue Wi-Fi: "Use my current IP" in Event settings (or enter the ranges from the venue); `access-check` returns `on_site: true` on an iPhone and an Android phone.
- [ ] On mobile data: the "No access" page appears.
- [ ] Full flow on a real phone: QR → name + phone → SMS code → vote in all categories → TV updates.
- [ ] Remove test data: close voting, **Reset results**, `php artisan voting:demo-votes <event> --clear` if demo votes were added. Remove `127.0.0.1` or other test ranges from the allowed IPs.

**During voting**
- [ ] Open voting from the dashboard (or let the window open it).
- [ ] Keep the admin dashboard open: voting status cards, live results, audit log.
- [ ] If the venue IP changes: Event settings → "Use my current IP" while on the venue Wi-Fi.

**After voting**
- [ ] Close voting; export results CSV (F13) and visitor CSV (F14); store them securely.
- [ ] Revoke TV display tokens; take a database backup.
