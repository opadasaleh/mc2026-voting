# MC2026 Digital Voting System

On-site award voting for The Maker Collective 2026 (CPF Makerspace) · 42 Amman Hackathon.

| Folder | What | Stack |
|---|---|---|
| [`Frontend/`](Frontend/) | Visitor voting page (`/{event}`) and TV results screen (`/{event}/leaderboard`) | Next.js 16 |
| [`backend/`](backend/) | REST API (`/api/v1`) and admin panel (`/admin`) | Laravel 13, Filament 5, Supabase Postgres |
| [`docs/`](docs/) | Architecture, ERD, API contract, DFD, auth, design decisions, deployment, scaling, traceability | — |

## Run locally

**Backend** (PHP 8.3, Composer):

```bash
cd backend
composer install
cp .env.example .env          # fill in the Supabase pooler values, PHONE_HASH_KEY, FRONTEND_URLS
php artisan key:generate
php artisan migrate
php artisan db:seed           # first admin (password printed once) + MC2026 sample data
php artisan serve             # API on http://localhost:8000, admin on http://localhost:8000/admin
```

In the admin panel (MC2026 → Event settings) add `127.0.0.1` to the venue IPs and open voting. Without an SMS gateway, codes are written to `backend/storage/logs/sms.log`.

**Frontend** (Node 20+):

```bash
cd Frontend
npm ci
cp .env.example .env.local    # NEXT_PUBLIC_API_URL=http://localhost:8000
npm run dev                   # http://localhost:3000 → /mc2026
```

TV screen: create a token in the admin panel under **TV displays**, then open `http://localhost:3000/mc2026/leaderboard?token=<token>`. With `php artisan serve` (one request at a time on Windows), set `NEXT_PUBLIC_RESULTS_TRANSPORT=poll` so the TV does not hold the server; production uses live Server-Sent Events.

**Testing on a phone over Wi-Fi:** run `php artisan serve --host=0.0.0.0`, set `NEXT_PUBLIC_API_URL=http://<your-computer-ip>:8000` and add `http://<your-computer-ip>:3000` to `FRONTEND_URLS`. Add your LAN range (e.g. `192.168.1.0/24`) to the event's venue IPs, since the phone's address is a LAN address.

**Backend tests** run against a local Postgres in Docker, never Supabase: `docker compose up -d test-db` then `php artisan test` (in `backend/`).

See [docs/README.md](docs/README.md) for the full documentation and [docs/07-deployment.md](docs/07-deployment.md) for production.
