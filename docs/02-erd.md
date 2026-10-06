# 02 — Entity Relationship Diagram (ERD)

Digital Voting System (first use: Maker Collective 2026) · Database: Supabase PostgreSQL · Schema owned by Laravel migrations.

> Status: **draft for review** (step 1, revision 2 — multi-event). Column types are Postgres types; Laravel migration names may differ slightly.

## 0. Multi-event model (what changed)

The system hosts **many independent voting events**. Each event has its own categories, exhibitors, voting window, on-site rules, visitors' verification state, votes, results, display tokens and admin panel.

- **The phone number is the UID** (normalised E.164, enforced through the HMAC blind index `phone_hash`); visitors enter only full name + phone.
- **Identity is global, verification is per event.** A visitor (one row per phone number) can take part in many events, which keeps the outreach list de-duplicated (F14). But **OTP verification is required again for every new event**: verification lives on `event_registrations`, not on `visitors`.
- **Categories and exhibitors belong to one event** (no shared catalog).
- **Settings are per event** (columns on `events`), because each event may have a different venue, window and OTP policy.
- **Admins:** one pool of admins; every admin can manage every event (decision). Each event is its own panel in the admin UI.
- The database enforces that categories, exhibitors, registrations and votes of an event can never point at another event's rows (composite foreign keys, §2).

## 1. Diagram

```mermaid
erDiagram
    EVENTS ||--o{ CATEGORIES : has
    EVENTS ||--o{ EXHIBITORS : has
    EVENTS ||--o{ EVENT_REGISTRATIONS : receives
    CATEGORIES ||--o{ CATEGORY_EXHIBITOR : has
    EXHIBITORS ||--o{ CATEGORY_EXHIBITOR : "entered in"
    VISITORS ||--o{ EVENT_REGISTRATIONS : "registers for"
    EVENT_REGISTRATIONS ||--o{ OTP_CODES : requests
    VISITORS ||--o{ VOTES : casts
    CATEGORY_EXHIBITOR ||--o{ VOTES : receives
    USERS ||--o{ AUDIT_LOGS : performs

    EVENTS {
        bigint id PK
        string slug UK
        string name
        text description
        bool is_active
        bool voting_enabled
        timestamptz opens_at
        timestamptz closes_at
        string access_mode
        jsonb allowed_cidrs
        jsonb geofence
        string venue_wifi_name
        int otp_ttl_seconds
        int otp_max_attempts
        int otp_resend_cooldown_seconds
    }
    CATEGORIES {
        bigint id PK
        bigint event_id FK
        string slug
        string name
        text description
        int sort_order
        bool is_active
    }
    EXHIBITORS {
        bigint id PK
        bigint event_id FK
        string name
        text short_description
        string photo_path
        bool is_active
        timestamp deleted_at
    }
    CATEGORY_EXHIBITOR {
        bigint event_id FK
        bigint category_id PK, FK
        bigint exhibitor_id PK, FK
    }
    VISITORS {
        bigint id PK
        string full_name
        text phone_encrypted
        char phone_hash UK
    }
    EVENT_REGISTRATIONS {
        bigint id PK
        bigint event_id FK
        bigint visitor_id FK
        string full_name
        timestamp phone_verified_at
        inet registered_ip
    }
    OTP_CODES {
        bigint id PK
        bigint event_registration_id FK
        string code_hash
        timestamp expires_at
        smallint attempts
        timestamp consumed_at
        inet ip
    }
    VOTES {
        bigint id PK
        bigint event_id FK
        bigint visitor_id FK
        bigint category_id FK
        bigint exhibitor_id FK
        inet ip
        timestamp created_at
    }
    USERS {
        bigint id PK
        string username UK
        string password
        text two_factor_secret
        timestamp two_factor_confirmed_at
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id FK
        bigint event_id FK
        string action
        jsonb meta
        inet ip
        timestamp created_at
    }
```

Two relationships are not drawn, to keep the diagram readable, but exist in the schema: `votes.event_id` → `events.id` (every vote belongs to an event; also part of the composite foreign keys in §2) and `audit_logs.event_id` → `events.id` (nullable).

**Delete behaviour:** foreign keys to `events`, `visitors` and `users` are `RESTRICT` (events are archived with `is_active = false`, never deleted once they have data; `audit_logs` is append-only). `category_exhibitor` rows `CASCADE` from their category/exhibitor, and `otp_codes` `CASCADE` from their registration. Everything referenced by `votes` is `RESTRICT`.

Framework tables (not drawn): `personal_access_tokens` (Laravel Sanctum — visitor tokens and TV display tokens, both **bound to one event** through their abilities), `sessions`, `cache` / `cache_locks`, `jobs` / `failed_jobs`. Sessions, cache and queues are **database-backed** so the application holds no state in memory or on local disk and any instance can serve any request.

## 2. Tables

### `events`
One row per voting event; also its configuration (F10, F11, spec §6 "database-driven").

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `slug` | varchar UNIQUE | Used in API paths and in the event's QR code, e.g. `mc2026` |
| `name` | varchar | Display name |
| `description` | text null | |
| `is_active` | bool | Inactive events are hidden from the public API (404) and remain visible to admins (archive instead of delete) |
| `voting_enabled` | bool | Manual open/close switch (F10) |
| `opens_at` / `closes_at` | timestamptz null | Optional window. CHECK `closes_at > opens_at` when both set |
| `access_mode` | varchar | `ip` \| `geo` \| `either` \| `both` (F11) |
| `allowed_cidrs` | jsonb | Array of venue public IP ranges — **IPv4 and IPv6** — e.g. `["203.0.113.0/24", "2001:db8:1::/48"]`. Venue visitors share these addresses (Wi-Fi NAT) |
| `geofence` | jsonb null | `{"lat":…,"lng":…,"radius_m":…}`; optional backup to the IP check |
| `venue_wifi_name` | varchar null | Wi-Fi network name shown on the "No access" page, e.g. `MC2026-Guest` |
| `otp_ttl_seconds` | int default 300 | OTP lifetime |
| `otp_max_attempts` | int default 5 | Wrong-code attempts before the code is invalidated |
| `otp_resend_cooldown_seconds` | int default 60 | Minimum gap between OTP requests |
| `created_at` / `updated_at` | timestamp | |

Voting is **open** iff `voting_enabled = true` AND (`opens_at` is null or now ≥ `opens_at`) AND (`closes_at` is null or now < `closes_at`). Event rows are read through a short-lived cache (≈ 5 s) to keep the hot path off the database. Events with registrations or votes cannot be hard-deleted.

### `categories`
The award categories **of one event** (names to be confirmed by the Makerspace team). Nothing is hard-coded.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | bigint FK → `events.id` | |
| `slug` | varchar | UNIQUE per event: `UNIQUE (event_id, slug)` |
| `name` | varchar | |
| `description` | text null | |
| `sort_order` | int | Display order |
| `is_active` | bool | Inactive categories are hidden and cannot receive votes |

Extra constraint: `UNIQUE (event_id, id)` — target for the same-event composite foreign keys below.

### `exhibitors`
Exhibitors **of one event**.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | bigint FK → `events.id` | |
| `name` | varchar | |
| `short_description` | text | Shown on the voting card |
| `photo_path` | varchar null | Object key in Supabase Storage (S3-compatible); public URL is built by the API |
| `is_active` | bool | |
| `deleted_at` | timestamp null | Soft delete (F9 "remove"); hard delete is blocked once votes exist |

Extra constraint: `UNIQUE (event_id, id)`.

### `category_exhibitor` (many-to-many)
An exhibitor can be entered in **one or more** categories of its event (F9).

| Column | Type | Notes |
|---|---|---|
| `event_id` | bigint | |
| `category_id` | bigint | PK part |
| `exhibitor_id` | bigint | PK part |

- PRIMARY KEY `(category_id, exhibitor_id)`; `UNIQUE (event_id, category_id, exhibitor_id)` (target for the votes foreign key).
- `FOREIGN KEY (event_id, category_id) REFERENCES categories (event_id, id)` and `FOREIGN KEY (event_id, exhibitor_id) REFERENCES exhibitors (event_id, id)` — **an exhibitor can only be entered in a category of the same event.**

### `visitors` (global)
One row per phone number, shared by all events. Holds identity only — **no verification state**.

**The phone number is the visitor's UID.** There is no username, password or email; the only data a visitor enters is **full name + phone**.
- The phone is normalised to E.164 before anything else (`0791234567` = `+962791234567`), so one person cannot register twice by formatting the number differently. Parsing/validation uses a phone-number library (libphonenumber).
- The UID is *enforced* by `phone_hash` (UNIQUE, HMAC blind index) and is how every lookup is done. The numeric `id` is an internal join key only, so the number is not copied into `votes` / `event_registrations` and rotating the HMAC secret never breaks relations. Neither the phone nor the internal id is exposed in URLs or API responses.
- Honest limit: one phone = one person is an assumption. Someone with several SIMs or virtual numbers could hold several UIDs; OTP delivery and the on-site rule are what make that costly (an optional SMS-provider line-type check can flag VoIP numbers).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Internal join key |
| `full_name` | varchar | Latest full name given in a *verified* registration (used for the outreach list) |
| `phone_encrypted` | text | E.164 number, encrypted at rest (Laravel `encrypted` cast, app key). Readable in admin context only |
| `phone_hash` | char(64) UNIQUE | HMAC-SHA256 of the E.164 number with a dedicated server secret — the **blind index** used for uniqueness and lookups |
| `created_at` / `updated_at` | timestamp | |

### `event_registrations` (per-event verification)
A visitor's participation in **one** event. This is where "OTP must be confirmed again for every new event" is enforced: a visitor may vote in an event only if their registration for **that** event has `phone_verified_at` set.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | bigint FK → `events.id` | |
| `visitor_id` | bigint FK → `visitors.id` | |
| `full_name` | varchar | Full name entered for this event (copied to `visitors.full_name` only after verification, so nobody can rename someone else's record by requesting an OTP for their number) |
| `phone_verified_at` | timestamp null | Set when this event's OTP is verified (F6) |
| `registered_ip` | inet null | Audit |
| `created_at` / `updated_at` | timestamp | |

Constraint: `UNIQUE (event_id, visitor_id)` (also the target for the votes foreign key).

### `otp_codes`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `event_registration_id` | bigint FK → `event_registrations.id` ON DELETE CASCADE | OTPs belong to a registration, i.e. to one event |
| `code_hash` | varchar | Hash of the 6-digit code — the plain code is never stored or logged (except by the dev-only log SMS provider) |
| `expires_at` | timestamp | TTL from `events.otp_ttl_seconds` |
| `attempts` | smallint | Failed verifications; code is invalidated at `events.otp_max_attempts` |
| `consumed_at` | timestamp null | Single use |
| `ip` | inet null | Requesting IP (rate-limit / audit) |
| `created_at` | timestamp | |

Index: `(event_registration_id, created_at DESC)` to find the latest active code.

### `votes`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `event_id` | bigint | |
| `visitor_id` | bigint | |
| `category_id` | bigint | |
| `exhibitor_id` | bigint | |
| `ip` | inet null | Audit |
| `created_at` | timestamp | |

Constraints and indexes:
- **`UNIQUE (visitor_id, category_id)`** — one vote per verified phone per category (F2, F12). A category belongs to exactly one event, so this is automatically "per event". Enforced by the database, so it holds under concurrency, retries and multiple app instances.
- **`FOREIGN KEY (event_id, category_id, exhibitor_id) REFERENCES category_exhibitor (event_id, category_id, exhibitor_id) ON DELETE RESTRICT`** — a vote can only target an exhibitor that is entered in that category of that event. Consequence: an admin cannot un-assign an exhibitor from a category that already has votes for them (the admin panel must show a clear message).
- **`FOREIGN KEY (event_id, visitor_id) REFERENCES event_registrations (event_id, visitor_id) ON DELETE RESTRICT`** — the voter must be registered for that event (that registration must also be verified; checked in the vote transaction).
- Index `(event_id, category_id, exhibitor_id)` — makes tally queries (`COUNT(*) GROUP BY`) cheap.
- Votes are **immutable**: no update path exists. "Reset results" deletes **one event's** votes only (registrations and verification are kept); it is audit-logged and should be preceded by an export.

### `users` (admins)
Makerspace staff only. Visitors are **not** in this table. All admins can manage all events.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `username` | varchar UNIQUE | |
| `password` | varchar | Bcrypt/Argon2 hash |
| `two_factor_secret` | text null | TOTP secret, encrypted |
| `two_factor_confirmed_at` | timestamp null | MFA enrolled |

### `audit_logs`
Append-only record of sensitive admin actions: login, event/settings change, voting opened/closed, **results reset**, **exports** (results / visitor list), display-token creation/revocation.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK → `users.id` null | |
| `event_id` | bigint FK → `events.id` null | Null for global actions (e.g. login) |
| `action` | varchar | e.g. `votes.reset`, `visitors.export` |
| `meta` | jsonb | Details (old/new values, counts) |
| `ip` | inet null | |
| `created_at` | timestamp | |

### Tokens (Sanctum `personal_access_tokens`)
- **Visitor token** — issued after OTP verification **for one event**; abilities `["vote", "event:<event_id>"]`. Using it on another event's endpoints is rejected, which forces the OTP flow for that event.
- **Display token** (TV screen) — created by an admin for one event; abilities `["results:read", "event:<event_id>"]`.

## 3. Privacy & security of stored data (F14, Privacy NFR)

- **Phone numbers** are stored encrypted (`phone_encrypted`); uniqueness and login lookups use only the HMAC `phone_hash`, so a database leak alone does not reveal numbers, and the HMAC secret is held outside the database. Admins read the real number through the admin panel (encrypted cast decrypts server-side).
- **Row Level Security is ENABLED on every table with no policies.** Supabase exposes tables through a public PostgREST API using the anon key; with RLS on and no policies, that API returns nothing. Laravel connects with the privileged database role and is the only access path.
- **OTP codes** and **tokens** are stored hashed. Sanctum tokens are stored as SHA-256 hashes by default.
- Visitor lists (names + phones) are available **only** in the admin panel and its CSV exports (per event, and global de-duplicated), which require admin login + MFA and are audit-logged.
- **Open item:** the registration screen should show a short notice that the name and phone number are stored for future Makerspace outreach (consent wording to be provided by CPF / Makerspace).

## 4. Requirement traceability (data)

| Requirement | Where it lives |
|---|---|
| Multiple events (new) | `events` + `event_id` on categories, exhibitors, registrations, votes |
| OTP re-confirmed per event (new) | `event_registrations.phone_verified_at`, event-bound tokens |
| F1 Exhibitor listing | `exhibitors`, `categories`, `category_exhibitor` |
| F2 One vote per category | `votes` UNIQUE `(visitor_id, category_id)` |
| F3 Already-voted state | `votes` (read by `GET /events/{event}/me`) |
| F5 / F6 Registration + OTP | `visitors`, `event_registrations`, `otp_codes` |
| F9 Exhibitor management | `exhibitors`, `category_exhibitor`, `photo_path` |
| F10 Voting window | `events.voting_enabled / opens_at / closes_at` |
| F11 On-site access control | `events.access_mode / allowed_cidrs / geofence` |
| F12 Duplicate-vote prevention | `votes` UNIQUE + `visitors.phone_hash` UNIQUE + verified registration |
| F13 Results export | aggregate over `votes` per event |
| F14 Visitor data storage | `visitors` (encrypted phone) + `event_registrations`, linked to `votes` |
| Spec §6 "database-driven" | `events`, `categories`, `exhibitors` |
