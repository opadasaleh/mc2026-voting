# Digital Voting System — Documentation

Maker Collective 2026 (CPF Makerspace) · 42 Amman Hackathon. Visitors vote on their phones at the venue (venue Wi-Fi + SMS OTP, one vote per category), a TV shows live standings, and the Makerspace team manages everything from an admin panel with MFA.

| # | Document | What it answers |
|---|---|---|
| 01 | [Architecture](01-architecture.md) | What the parts are, how a request flows, why the servers are stateless |
| 02 | [ERD](02-erd.md) | Tables, constraints, how personal data is stored |
| 03 | [API contract](03-api-contract.md) | Every endpoint, error code and rate limit; on-site rule in detail (§7) |
| 04 | [Data flow (DFD)](04-data-flow.md) | Where data enters, is stored and leaves; personal data map |
| 05 | [Authentication, authorization & anti-fraud](05-auth-and-access-control.md) | Who can do what, OTP and MFA, threat → control → test |
| 06 | [Design decisions](06-design-decisions.md) | The 20 key choices and the alternatives we rejected |
| 07 | [Deployment guide](07-deployment.md) | Requirements, configuration, nginx, updates, event-day checklist |
| 08 | [Scaling to 1,000 users](08-scaling.md) | Load sizing, capacity, single points of failure, growth path |
| 09 | [Requirements traceability](09-requirements-traceability.md) | F1–F14, NFRs and deliverables → code and tests |

`export/` holds image and spreadsheet versions of the ERD and API contract for the frontend developer.

**Stack:** Laravel 13 (PHP 8.3) · Filament 5 admin panel · Supabase Postgres 17 (Frankfurt) · Next.js frontend (separate) · Server-Sent Events for live results.
