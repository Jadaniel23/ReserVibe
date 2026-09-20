# AGENTS.md — ReserVibe project memory

Durable context for any AI agent or developer working in this repo.
**Status:** reviewed 2026-09-19 — Modules 1–4 are now implemented and tested against a live server.
Update this file when scope or architecture changes.

---

## 1. Project identity

- **Name:** ReserVibe — a web-based reservation and booking management system.
- **Client/site:** Verde Royale Resort — Brgy. Ambonao, Calasiao, Pangasinan.
- **Nature:** Academic / school project. Optimize for **clarity and demonstrable good
  practice** (explainable in a defense) over enterprise-grade robustness. It is not a
  production deployment, but it does behave like a real system: real validation, real
  concurrency control, real HTTP semantics.

## 2. Tech stack

- **Backend:** PHP 8.2 (OOP) + MySQL, PDO.
- **Frontend:** HTML5, CSS3, vanilla JavaScript (Fetch API).
- **Calendar:** FullCalendar (JS) + Google Calendar API integration.
- **Payments:** manual-review flow — guest uploads GCash / Maya / bank QR proof.
- **Mail:** PHPMailer (SMTP) for status emails and receipts.

**Coding guidelines the project commits to:**

| Guideline | State |
|---|---|
| Parameterized queries only (PDO), never raw SQL concatenation | N/A yet — no SQL exists. Repositories are the only swap point. |
| Validate/sanitize all input | ✅ `App\Helpers\Validator`, applied in every POST endpoint |
| CSRF on every mutating endpoint | ✅ `App\Services\CsrfProtection`, enforced on all POSTs |
| Uploads: real MIME sniffing (`finfo`), size limits, randomized names | ⬜ not built yet (Module 5) |
| Layering: Repositories / Services / API endpoint files / frontend | ✅ enforced — endpoints contain no business logic, services contain no storage access |

## 3. Scope — 10 core modules

| # | Module | State |
|---|---|---|
| 1 | Accommodation & Package Catalog | ✅ API + data seeded |
| 2 | Availability Calendar | ✅ check + FullCalendar feed |
| 3 | Online Reservation & Cost Calculation | ✅ server-side quote, 50% deposit |
| 4 | Conflict Prevention & Temporary Holds | ✅ atomic, lazy expiry, race-tested |
| 5 | QR Code Payment & Receipt Upload | 🟡 method selection + cancellation done; proof upload and staff verification pending |
| 6 | Payment & Balance Tracking | ⬜ ledger fields already on the reservation record |
| 7 | Cancellation & Rescheduling Engine | ⬜ |
| 8 | Google Calendar API Integration | ⬜ |
| 9 | Walk-In Management & Guest Status | ⬜ `guest_status` field already on the record |
| 10 | Admin Dashboard & Operational Reports | ⬜ |

**Longer-horizon vision** (from the project's written spec — *not* current build scope):
role tiers (Admin / Front Desk / Cashier / Housekeeping), housekeeping task board, dynamic
pricing & promo codes, invoicing/refunds, reports & analytics, SMS+email automation, and a
post-stay feedback & review system with moderation and satisfaction analytics.

## 4. Architecture decisions already locked

| Area | Decision | Rationale |
|---|---|---|
| Routing | **Hybrid, resolved:** guest pages render through the front controller (`?action=`); every JSON endpoint is its own file under `public/api/` | One-file-per-endpoint is trivially explainable in a defense and needs no router library |
| Hold expiration | **Lazy check on read** — verified on `hold/status`, on `hold/create` and on booking confirmation; no cron job | No server-level cron on shared/school hosting |
| Views | **Plain PHP includes** — no templating engine | One less dependency |
| Database | **Deferred** — a JSON store stands in behind `Repositories/` | User asked to build the API first; `Repositories/` is the only layer that changes when MySQL lands |
| Timezone | `Asia/Manila`, set in `src/Config/config.php` (overridable via `APP_TIMEZONE` in `.env`) | Hold countdowns must read in resort-local time, not hosting default |
| Money | Server-authoritative: the client sends **add-on codes and dates, never prices** | A tampered request cannot change the total |
| Payment methods | Declared once in `config.php` under `payment_methods`; the booking view renders them and the API validates `payment_method` against the same list | The page and the API can never disagree about what is offered, and `payment_method` is required (422 if unknown) |

### The concurrency design (the project's signature feature)

`HoldRepository` and `ReservationRepository` are storage-only. All booking-critical
operations run inside **one exclusive lock** on `data/.booking.lock`, acquired via
`JsonFileStore::withLock()`:

- `HoldManager::place()` — inside the lock it sweeps expired holds, reads reservations,
  runs the conflict check, and only then writes the new hold. There is no window between
  checking and writing.
- `ReservationService::create()` — takes the **same** lock, so confirming a booking cannot
  race a new hold either.

`JsonFileStore` also offers `atomicUpdate()` for single-file read-modify-write, but the
booking path deliberately uses the shared lock so that a decision spanning two collections
(holds + reservations) is atomic.

**Limitation to state honestly:** `flock()` is advisory and local to one host. It is exactly
right for a single-server school deployment and would be replaced by
`SELECT ... FOR UPDATE` / transactions under MySQL.

**Evidence:** two simultaneous `hold/create` requests for the same fresh slot return
`201` and `409`, and exactly one hold is written. Re-runnable — see §8.

### Lifecycles

- Reservation: `pending_payment` → `pending_verification` → `confirmed` | `payment_rejected`
  → (optionally) `cancelled`
- Guest status: `reserved` → `arrived` → `left`
- Hold: `active` → `expired` | `released` | `confirmed`

`ReservationRepository::BLOCKING_STATUSES` = `pending_payment`, `pending_verification`,
`confirmed`. A slot stays occupied until payment is rejected or the booking is cancelled.

## 5. Verified state of THIS repo (2026-09-19)

Commit `f4c23b7 "Initial commit"` plus uncommitted work. **All 36 PHP files pass `php -l`.**

```
public/
  index.php                     front controller: ?action=home|rooms|book
  .htaccess                     rewrites non-existent paths to index.php
  api/bootstrap.php             autoloader + config + session + JSON headers + error handler
  api/csrf-token.php            GET  — mint/return CSRF token
  api/catalog/list.php          GET  — Module 1: catalogue, add-ons, booking settings
  api/availability/check.php    GET  — Module 2: is this slot free?
  api/availability/calendar.php GET  — Module 2: FullCalendar event feed (raw array)
  api/reservation/calculate.php POST — Module 3: quote (side-effect free)
  api/reservation/create.php    POST — Modules 3+4: hold → reservation
  api/reservation/list.php      GET  — staff-only list
  api/hold/create.php           POST — Module 4: claim a slot (201) or refuse (409)
  api/hold/status.php           GET  — Module 4: countdown, lazy expiry
  api/hold/release.php          POST — Module 4: give the slot back
  assets/{css,js}               style.css, booking.js, calendar.js, dashboard.js, site.js
  uploads/payment-proofs/       empty, for Module 5
src/
  Config/config.php             ALL tunables: deposit_rate, hold_minutes, addons, payment_methods, uploads, paths, timezone
  Config/Database.php           still a mock singleton (untouched)
  Helpers/Config.php            dot-notation reader: Config::get('uploads.max_bytes')
  Helpers/Assets.php            versioned asset URLs: Assets::url('/assets/css/style.css') -> ?v=<mtime>
  Helpers/Validator.php         date/int/text/email/phone/in/list — null means invalid
  Helpers/Response.php          success()/error()/raw()/json()/render()/requireMethod()/requireRole()/input()
  Repositories/JsonFileStore.php      flock-based read/replace/atomicUpdate/withLock
  Repositories/AccommodationRepository.php
  Repositories/HoldRepository.php
  Repositories/ReservationRepository.php
  Services/CsrfProtection.php
  Services/CostCalculator.php         quote(), nightsBetween(), unknownAddonCodes()
  Services/AvailabilityService.php    pure overlap/conflict logic + calendar events
  Services/HoldManager.php            place/status/release/sweepExpired/markConfirmed
  Services/ReservationService.php     create from hold, list
  Controllers/, Models/               Accommodation model is now a facade over the repository
data/
  accommodations.json           4 real Verde Royale tiers (single source of prices)
  holds.json, reservations.json empty; .booking.lock gitignored
views/guest/                    home, catalog, book, 404
views/{admin,staff,layouts}/    still empty
```

**API contract:** success → `{"success": true, "data": ...}`; failure →
`{"success": false, "message": "...", "errors": {...}}`. Status codes used: 201 created,
403 role, 404 missing, 405 method, 409 conflict, 419 CSRF, 422 validation.

**End-to-end test results (php -S, re-runnable — see §8):**

Front-controller pages also verified: `?action=home`, `rooms`, `book&id=1|3` all 200, unknown room falls
through to the 404 view, `?action=bogus` is a real 404 that now renders the *styled* 404 page (it used to
return bare text), and the booking page renders its 4 options, both add-ons, its hold window and its 6
payment methods from config. Zero PHP warnings across every run.

| Check | Result |
|---|---|
| Catalog returns 4 accommodations + settings | 200 |
| Quote: 2 nights + videoke + grilling on the ₱1,500 cottage | subtotal 3000, add-ons 1100, total **4100**, deposit **2050** |
| Place hold | 201, `HOLD-0001`, expires in 5400s (90 min) |
| Second hold, overlapping dates | **409**, names `HOLD-0001` |
| Hold countdown | 200, `remaining_label: 89:58`, `usable: true` |
| POST with no CSRF token | 419 |
| Confirm booking from hold | 201, `RSV-0001`, pending_payment, deposit 1900 |
| Hold the same slot again | **409**, now names `RSV-0001` (reservation blocks, not just the hold) |
| Availability check / calendar feed | 200 / 200 with a red "reserved" event |
| Staff list without role / with `X-User-Role: staff` | 403 / 200 |
| Release hold → re-hold same slot | 200 released → 201 granted |
| **Two simultaneous holds, same fresh slot** | **201 + 409, exactly 1 hold written** |
| Stale hold planted → its slot re-booked | status flips to `expired` on read, re-booking returns 201 |
| Booking page renders the chooser | 200, 6 method cards, radios for gcash/maya/bank_transfer/paypal/card/cash |
| `catalog/list.php` exposes `payment_methods` | 200 |
| Booking via `payment_method: paypal` | 201, record stores `payment_method`, `payment_method_label`, `payment_requires_proof: false` |
| Booking via `payment_method: cash` | 201, `settles_on_arrival: true` |
| Booking with `payment_method: bitcoin` / omitted | **422** naming the valid codes |
| Cancel releases the hold, then the same slot re-books | 200 → 201 |
| Asset URLs carry `?v=<mtime>`, version changes on edit | `1789920255` → `1789920436` after touching style.css |
| Versioned `/assets/css/style.css?v=…` and `/assets/js/booking.js?v=…` | 200 |
| Unknown action `?action=bogus` | 404 **with the styled site layout** (was plain text) |
| Invalid room id `?action=book&id=999` | 404 styled page |
| PHP warnings/notices during the run | 0 |

## 6. Bug found and fixed: `getEnv()` was PHP's `getenv()`

The original `src/Config/config.php` defined a helper called `getEnv()` inside a
`function_exists('getEnv')` guard. **PHP function names are case-insensitive**, so
`getEnv()` *is* the built-in `getenv()`: the guard was always true, the definition was
silently skipped, and every lookup hit PHP's `getenv($name, bool $local_only = false)` —
whose second parameter is a bool, not a default. It returns `false` for a missing key.

Consequences that were live in this repo until 2026-09-19:

- All four `db` config values were `false` rather than `localhost` / `reservibe` / `root` / `''`.
- `date_default_timezone_set('')` raised "Timezone ID '' is invalid" and left the server on
  `Europe/Berlin`, so hold timestamps read `+02:00` for a Philippine resort.

Fixed by renaming the helper to `reservibe_env()` (with a comment explaining why), and it now
also falls back to real environment variables so hosting can override `.env`. **Do not
reintroduce a `getEnv()` helper** — it will collide again.

## 7. Discrepancy resolved: the inherited capsule

The inherited `CONTEXT_CAPSULE.md` describes a different, more advanced lineage
(`src/Repositories` + `src/Services` layers, a `/data/*.json` store, stubbed
`GoogleCalendarService`/`MailerService`, 20 endpoints) and references a
`reservibe-project.zip` **that does not exist anywhere on this machine**.

Resolution: this repo is the base. Its locked decisions and layering were adopted, and
Modules 1–4 were rebuilt here from scratch in that shape. The capsule remains accurate as
**design intent** (its endpoint list, lifecycles and JSON-store idea) and is a fair
reference for what Modules 5–10 should look like — but it never described files that exist.

Two conflicts from the capsule, now settled:

- **Routing** — hybrid: pages via the front controller, JSON via one file per endpoint (§4).
- **Hold duration** — `hold_minutes` in config = 90, and the booking view now *renders* that
  value instead of hardcoding "15 minutes".

## 8. How to run and re-test

```bash
cd public
php -S 127.0.0.1:8123
```

```bash
# 1. token (keep the cookie jar — the token lives in the session)
curl -s -c cj -b cj http://127.0.0.1:8123/api/csrf-token.php
TOKEN=<paste csrf_token>

# 2. catalogue
curl -s http://127.0.0.1:8123/api/catalog/list.php

# 3. quote: 2 nights, cottage + videoke
curl -s -X POST -b cj -H "Content-Type: application/json" -H "X-CSRF-Token: $TOKEN" \
  -d '{"accommodation_id":1,"check_in":"2026-10-05","check_out":"2026-10-07","addons":["videoke"]}' \
  http://127.0.0.1:8123/api/reservation/calculate.php

# 4. claim a slot (201)
curl -s -X POST -b cj -H "Content-Type: application/json" -H "X-CSRF-Token: $TOKEN" \
  -d '{"accommodation_id":1,"check_in":"2026-10-05","check_out":"2026-10-07"}' \
  http://127.0.0.1:8123/api/hold/create.php

# 5. the same slot again → 409
# 6. prove the race: fire step 4 twice in parallel; exactly one returns 201

# 7. confirm the booking from the hold id
curl -s -X POST -b cj -H "Content-Type: application/json" -H "X-CSRF-Token: $TOKEN" \
  -d '{"hold_id":1,"guest_name":"Juan Dela Cruz","guest_email":"juan@example.com","guest_phone":"09171234567"}' \
  http://127.0.0.1:8123/api/reservation/create.php

# 8. staff view
curl -s -H "X-User-Role: staff" http://127.0.0.1:8123/api/reservation/list.php
```

Reset the demo data by writing `[]` into `data/holds.json` and `data/reservations.json`.

## 9. Roadmap — next steps

1. **Module 5 — payment proof upload.** Methods are chosen and recorded; what is
   missing is the receipt itself. Needs `FileUploadValidator` with real `finfo` MIME
   sniffing, size cap and randomized filenames (settings already in config under `uploads`),
   plus `payment/upload.php`, `payment/verify.php` (staff) and the
   `pending_payment → pending_verification → confirmed` transitions. Only methods
   flagged `requires_proof` should demand an image.
2. **Module 6 — ledger.** `payment/ledger.php` and balance math off the reservation record
   (`amount_paid`, `deposit_due`, `balance_due` already exist).
3. **Module 10 + staff surfaces.** `views/staff/` and `views/admin/` are empty;
   `dashboard.js` is 0 bytes; KPI endpoint still to write.
4. ~~Wire the booking page to the API.~~ **Done** — `booking.js` now places the hold,
   shows the countdown, confirms the booking, releases on cancel, and surfaces API
   errors. It still computes the *display* total client-side, but the server prices the
   booking independently and its numbers are what get stored.
5. **Modules 7 and 9** — cancellation/reschedule engine, walk-in and guest-status toggles.
6. **Module 8** — Google Calendar + PHPMailer. Add
   `composer require phpmailer/phpmailer` and `composer require google/apiclient`
   (`composer.json` is currently `{}`), then implement the service classes the endpoints call.
7. **MySQL + PDO.** Design the schema and rewrite only `src/Repositories/` — use
   transactions/`SELECT ... FOR UPDATE` to keep the §4 guarantee.
8. **Real authentication.** `X-User-Role` is a placeholder; replace with accounts + sessions
   and keep `Response::requireRole()` as the single gate.

## 10. Housekeeping / risks

- **⚠ Exposed credential:** the git remote URL in `.git/config` embeds a GitHub personal
  access token. Rotate/revoke it and switch to a credential helper or SSH. `.env` is
  correctly gitignored — keep it that way.
- `X-User-Role` is trivially spoofable. It exists only to keep staff endpoints visibly
  separated so real auth is a one-place change. Do not treat it as security.
- **PayPal and card are UI-level only.** No gateway is integrated: choosing them records
  the method on the booking and nothing is charged. Say that plainly rather than implying
  a working payment integration.
- "Cancel Booking" releases the hold, but navigating away with **Return to Menu** does not —
  the hold simply expires on its own. If that matters later, release it on `beforeunload`
  or expire it server-side faster.
- The role header is checked **only** in `reservation/list.php` so far — every new staff
  endpoint must add `Response::requireRole([...])` explicitly.
- The JSON store is fine for one host. It is not a substitute for a database under real load.
- **⚠ Concurrent editor.** Parts of this repo have been written by something other than the
  assistant session that wrote most of this file. The `.booking-shell` rule and the
  `!important` note in `style.css`, plus the matching wrapper in `book.php`, appeared on
  2026-09-21 around 00:02 with no assistant write behind them, and one write was refused
  because a file had changed underneath. A Kilo Code worktree is registered at
  `.kilo/worktrees/quilled-swamp/` (detached HEAD at `1035b2c`). Re-read a file before
  editing it, and settle who owns the working tree before the next round of changes — if
  that worktree gets committed and merged, expect conflicts against uncommitted work here.
- **Asset caching.** The PHP dev server sends no cache headers, so browsers heuristically
  cache CSS/JS and keep serving a stale copy after an edit — this already caused one
  confusing "my fix did nothing" round trip. `App\Helpers\Assets::url()` appends
  `?v=<mtime>` to defeat it; use it for any new asset reference in a view.
- `composer.lock` is tracked but `.gitignore` lists it — still unresolved; pick one policy.
- `views/{staff,admin,layouts}` are empty, so page chrome is duplicated per view. Extract a
  layout when the staff screens land.
- `data/` is runtime state. `accommodations.json` is the seeded catalogue and *should* be
  committed; `.booking.lock` must not be (it is gitignored).
