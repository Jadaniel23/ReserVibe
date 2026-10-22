# ReserVibe — How to Run It

A web-based reservation and booking system for **Verde Royale Resort**
(Brgy. Ambonao, Calasiao, Pangasinan). This is a school prototype with working
guest booking and front-desk flows, a PHP JSON API, and local JSON-file storage. See
[What works and what doesn't](#what-works-and-what-doesnt) for the current scope.

This guide takes you from a laptop with nothing installed to a running server in
about 15 minutes. No Composer or MySQL are required. An internet connection is
only needed for the remotely hosted photos and fonts; booking functions run locally.

---

## What you'll end up with

If everything works, you will be able to:

1. Open the landing page and search the package catalogue.
2. Complete a booking in the browser, including a quote and temporary date hold.
3. See overlapping bookings rejected by the API with HTTP 409.
4. Submit a demo receipt, review it in the staff desk, and record offline payments.
5. Maintain the package catalogue, manage reservations, register walk-ins, and review reports.

## School project diagram

The lab handout asks for a DFD. [MIDTERM_LAB_ACT_DFD_completed.docx](MIDTERM_LAB_ACT_DFD_completed.docx)
contains the implemented booking and operations workflows after the rubrics;
the individual contribution record remains blank for your team. The diagrams are
available as [booking SVG](ReserVibe_Implemented_DFD.svg),
[booking PNG](ReserVibe_Implemented_DFD.png),
[operations SVG](ReserVibe_All_Modules_DFD.svg), and
[operations PNG](ReserVibe_All_Modules_DFD.png).
The older `ReserVibe_Complete_DFD.png` and `ReserVibe_Simplified_DFD.png` are
concept drafts with incomplete/different process numbering; use the two implementation
figures above for the current build. The SMTP/email service shown in the older
concept has not been connected.

---

## Step 1 — Install PHP

You need **PHP 8.1 or newer** with the `fileinfo`, `json`, `mbstring` and
`session` extensions (all four ship with a standard PHP build). The optional Google
Calendar connection also needs PHP's `curl` extension.

### Windows

Easiest option — **XAMPP** (you don't need MySQL or Apache, just the PHP that
ships with it):

1. Download XAMPP from <https://www.apachefriends.org/> and install it.
2. When done, PHP is at `C:\xampp\php\php.exe`.

If you would rather have `php` available everywhere on your PATH:

1. Download the **Thread Safe x64 ZIP** from <https://windows.php.net/download/>.
2. Extract it to `C:\php`.
3. Add `C:\php` to your **PATH**: Start → search "environment variables" →
   *Edit the system environment variables* → *Environment Variables* →
   select `Path` → *Edit* → *New* → type `C:\php` → OK.
4. **Close and reopen your terminal**, then go to Step 2.

Verify (either install path):

```bash
php -v
```

You should see something like `PHP 8.2.12`. If it says `php is not recognized`,
either the PATH edit hasn't taken effect in this terminal, or you need to call it
by full path: `C:\xampp\php\php.exe -v`.

### macOS

```bash
brew install php
php -v
```

(Recent macOS versions don't ship PHP any more, so Homebrew is the usual route.)

### Linux (Debian/Ubuntu)

```bash
sudo apt update && sudo apt install php-cli php-mbstring
php -v
```

Confirm the extensions are present — this should list four names:

```bash
php -m | grep -iE "^(fileinfo|json|mbstring|session)$"
```

---

## Step 2 — Get the project

**If you were sent a ZIP:** unzip it anywhere. Avoid a path with unusual
characters if you can. On Windows, a folder like `C:\projects\ReserVibe` is
ideal; OneDrive folders work too.

**If you're cloning it:**

```bash
git clone <repository-url>
cd ReserVibe
```

A fresh copy will **not** contain a `.env` file because it is gitignored. Guest booking works without it; staff login requires password hashes configured in Step 3.

---

## Step 3 — Create your private `.env`

The guest pages work without `.env`, but staff login requires local password hashes. Copy the example file, generate a hash for each password, and paste each output into its matching `DEMO_*_PASSWORD_HASH` line. The `.env` file is gitignored and must stay private.

```bash
cp .env.example .env        # macOS / Linux / Git Bash
copy .env.example .env      # Windows Command Prompt
php -r "echo password_hash('choose-a-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Repeat the PHP command for each account. Set `DEMO_ADMIN_USER` and `DEMO_STAFF_USER` in `.env` if you want different usernames. Failed login attempts are limited to five within ten minutes, followed by a five-minute lockout.
## Step 4 — Start the server

Open a terminal **in the project's top folder** — the one containing `public/`,
`src/` and `data/`, not `public/` itself.

```bash
php -S 127.0.0.1:8000 -t public
```

If you installed PHP through XAMPP but did not add it to `PATH`, run this from
the project root instead:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

> The `-t public` part matters. It points the server at the `public/` folder so
> that `/api/...` and `/assets/...` resolve. If you run `php -S` from inside
> `public/`, use `php -S 127.0.0.1:8000` with no `-t`.

Leave that terminal running — it's your server. You'll use a **second** terminal
for the commands in Steps 5 and 6. Stop the server with `Ctrl+C`.

### Using VS Code instead

Open the project folder, then `Ctrl+Shift+P` → *Tasks: Run Task* → **Serve
ReserVibe (PHP :8000)** (or press `Ctrl+Shift+B`). Other tasks are **Lint all PHP files**
and **Reset all local demo data**. The reset task permanently clears local booking,
payment and calendar-token data; stop the server first and run it only when you want a fresh demo.
The VS Code tasks expect `php` to be available on `PATH`.

### Using XAMPP/Apache instead of the built-in server

Point Apache's document root at the project's `public/` folder. If you leave the
document root at the project root instead, `/assets` and `/api` will 404 and the
pages will render unstyled.

---

## Step 5 — Check it works

In your browser:

| URL | What you should see |
|---|---|
| <http://127.0.0.1:8000/?action=home> | The Verde Royale landing page |
| <http://127.0.0.1:8000/?action=rooms> | 4 packages: ₱1,500 / ₱2,500 / ₱3,500 / ₱5,500 |
| <http://127.0.0.1:8000/?action=book&id=1> | Booking form with an estimated deposit and a demo-only method chooser |
| <http://127.0.0.1:8000/api/catalog/list.php> | JSON listing 4 accommodations |

**Success checklist** — all four should be true:

- [ ] The landing page loads with styling (if it's plain grey text, your
      document root is wrong — see the `-t public` note above).
- [ ] The catalogue shows exactly four rates, from ₱1,500 to ₱5,500.
- [ ] The booking form's "Estimated 50% Deposit (not collected)" line reflects the chosen rate.
- [ ] The catalog JSON begins `{"success": true, ...}`.

Note the URLs use `?action=...` rather than clean paths like `/rooms`. That's
because `.htaccess` rewriting only runs under Apache — the PHP built-in server
ignores it by design.

---

## Step 6 — Book a slot from the terminal

This is the part worth showing off. The API needs a **CSRF token** plus the
**session cookie** that goes with it, so these commands use `curl` with a cookie
file and pass the token in a header.

> **Shell note:** these multiline API examples use Bash syntax. On Windows, run
> them in Git Bash. The browser booking flow in Step 5 works without these commands.

**6.1 — Get a token and save it.** Run this in a second terminal:

```bash
curl -s -c cookies.txt -b cookies.txt http://127.0.0.1:8000/api/csrf-token.php
```

It prints something like:

```json
{ "success": true, "data": { "csrf_token": "9f2c...64 hex characters...", "role": "guest" } }
```

Copy the long `csrf_token` value, then paste it in place of `PASTE_TOKEN` below.

**6.2 — Check a date range is free.**

```bash
curl -s "http://127.0.0.1:8000/api/availability/check.php?accommodation_id=1&check_in=2026-10-05&check_out=2026-10-07"
```

Look for `"available": true`.

**6.3 — Get a price.** Two nights in the ₱1,500 cottage with a videoke machine:

```bash
curl -s -X POST -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: PASTE_TOKEN" \
  -d '{"accommodation_id":1,"check_in":"2026-10-05","check_out":"2026-10-07","addons":["videoke"]}' \
  http://127.0.0.1:8000/api/reservation/calculate.php
```

Expect `subtotal: 3000`, `addons_total: 800`, `total: 3800`, `deposit_due: 1900`.

**6.4 — Claim the slot (a "hold").**

```bash
curl -s -X POST -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: PASTE_TOKEN" \
  -d '{"accommodation_id":1,"check_in":"2026-10-05","check_out":"2026-10-07"}' \
  http://127.0.0.1:8000/api/hold/create.php
```

Expect **HTTP 201** and a hold reference like `HOLD-0001`, valid for 90 minutes.

**6.5 — Try to book the same dates again.** Run 6.4 a second time.

Expect **HTTP 409** naming `HOLD-0001` — the slot is protected.

**6.6 — Turn the hold into a reservation.** Use the `id` from 6.4 (not the
reference) as `hold_id`:

```bash
curl -s -X POST -b cookies.txt -H "Content-Type: application/json" -H "X-CSRF-Token: PASTE_TOKEN" \
  -d '{"hold_id":1,"guest_name":"Juan Dela Cruz","guest_email":"juan@example.com","guest_phone":"09171234567","payment_method":"gcash"}' \
  http://127.0.0.1:8000/api/reservation/create.php
```

`payment_method` is required — send one of `gcash`, `maya`, `bank_transfer`, `paypal`,
`card` or `cash`, otherwise you get a 422 listing the valid options.

Expect **HTTP 201** with a reference like `RSV-0001` and status
`pending_payment`.

**6.7 — Prove the conflict prevention is race-proof.** On macOS, Linux or Git
Bash, fire two holds at the same fresh slot *simultaneously*:

```bash
for i in 1 2; do
  curl -s -o /dev/null -w "%{http_code}\n" -X POST -b cookies.txt \
    -H "Content-Type: application/json" -H "X-CSRF-Token: PASTE_TOKEN" \
    -d '{"accommodation_id":3,"check_in":"2026-12-20","check_out":"2026-12-22"}' \
    http://127.0.0.1:8000/api/hold/create.php &
done
wait
```

You should get **one `201` and one `409`** — never two `201`s. That's the whole
point of the project: two guests clicking "Book" at the same instant cannot both
get the same slot.

**6.8 — Staff desk.** Open `http://127.0.0.1:8000/?action=staff-login` and sign in with a local demo account:

The repository does not ship default passwords. Configure staff usernames and password hashes in your private `.env` as described in Step 3. Staff API access uses the signed-in session; sending a role header does not grant access.

Administrators can maintain package names, types, capacity, nightly rates,
descriptions, and whether each package appears for new bookings from the **Maintain
package catalogue** panel. Updates are saved to `data/accommodations.json` and feed
the guest catalogue and server-calculated quotes. Rate changes wait until active
holds expire so an in-progress quote does not unexpectedly change. Hiding a package
prevents new bookings; existing reservation records remain available to staff.

---

## Optional — connect Google Calendar (Module 8)

This module uses Google's OAuth 2.0 web-server flow and Calendar API. It stays
disconnected until you supply your own credentials; the rest of the project works
without a Google account.

1. In Google Cloud, create a project, enable **Google Calendar API**, and create an
   OAuth client for a web application.
2. Set the authorized redirect URI to exactly
   `http://127.0.0.1:8000/?action=google-callback`.
3. Copy `.env.example` to `.env` if needed, then set `GOOGLE_CLIENT_ID` and
   `GOOGLE_CLIENT_SECRET`. Keep the secret private. Set `GOOGLE_CALENDAR_ID=primary`
   or the ID of a calendar shared with the Google account.
4. Sign in as `admin`, choose **Connect Google account**, grant event access, then
   use **Sync reservations**. Syncing is manual; the school demo does not run a
   background scheduler. Reservation events include a reference, package and status,
   but omit guest phone and email.

For a different local port or deployment URL, change `GOOGLE_REDIRECT_URI` and add
that exact URL to the OAuth client's authorized redirect URIs. Google may require
the OAuth consent screen to include your account as a test user while the app is in
testing mode.

---

## Step 7 — Reset the demo data

Bookings and offline payments are saved under `data/`; receipt images and the optional
Google token are private local files there as well. Stop the PHP server first. In VS
Code, run **Reset all local demo data** to start clean. This deletes local holds,
reservations, payment ledger/proof records, uploaded receipt images, and the saved
Google connection token. Or in Git Bash, run:

```bash
printf '[]\n' > data/holds.json && printf '[]\n' > data/reservations.json && printf '[]\n' > data/payments.json && printf '[]\n' > data/payment_proofs.json && rm -f data/payment-proofs/* data/.booking.lock data/google_calendar_token.json
```

In VS Code, run the **Reset all local demo data** task instead. This reset is permanent.

---

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| `php is not recognized` | PHP isn't on your PATH. Reopen the terminal after editing PATH, or call it by full path (`C:\xampp\php\php.exe`). |
| `Failed to listen on 127.0.0.1:8000` | Port busy. Use another port: `php -S 127.0.0.1:8080 -t public`, and change the URLs to match. |
| Pages load but look unstyled, and `/assets/...` or `/api/...` 404 | Wrong document root. Run from the project root with `-t public`, or point Apache at `public/`. |
| Browser shows the PHP source code as text | You opened `index.php` by double-clicking. PHP must go through a server — use the URL from Step 4. |
| Clean URLs like `/rooms` 404 | `.htaccess` only works under Apache. With `php -S`, use `?action=rooms`. |
| `419` on a POST | Missing or stale CSRF token. Re-run 6.1 and re-paste the token. |
| `403` on a staff endpoint | Sign in through `?action=staff-login`; role headers are ignored. |
| Google Calendar is unavailable | The OAuth client ID/secret, API enablement, consent screen, or exact redirect URI still needs setup. |
| `409` when you expected a booking | The slot is genuinely held or reserved. Pick other dates or reset the data (Step 7). |
| Blank white page | Check the server terminal for the error. Warnings are logged, never printed into JSON. |
| API example commands fail in PowerShell | These multiline commands use Bash syntax. Run them in Git Bash, or use the browser booking flow. |
| Nothing works and you're not sure why | Run a syntax check across the project — it should print only `No syntax errors`: `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;` |

---

## What works and what doesn't

### Fix-list progress - October 4, 2026

- Staff passwords now come from password hashes in private `.env`; the repository no longer contains plaintext defaults. Login attempts are limited to five failures per account and IP within ten minutes, followed by a five-minute lockout.
- Booking dates enforce a 30-night maximum and a 365-day booking window. The booking page displays 14:00 check-in and 11:00 check-out.
- Rescheduling requires at least seven days before arrival. Cancellation records whether the three-day deposit rule applies. The demo does not issue refunds; other refund requests are left for staff review.
- PHP `mbstring` and `fileinfo` extensions were already listed in the run guide.

Suggested next items from `ReserVibe-Fix-List.docx`: printable booking confirmation, guest-facing availability calendar, terms/privacy/house rules with booking acknowledgement, resort information, staff action log, reservation search and filters, and a printable arrivals list. Keep payment options clearly marked as demo-only; a GCash QR needs an owner-approved test account or an explicitly labeled sample image. Local room photography is a separate asset task.
### Recent updates - October 3, 2026

- Fixed the booking estimate to calculate the room subtotal from the nightly rate and the selected number of nights.
- Corrected the date fields to use local dates, avoiding a one-day shift caused by UTC conversion.
- Kept a booking hold available for retry if the cancellation request fails.
- Clarified that reservations and offline payments are demo records; no gateway charges real money.
- Changed API error responses to show a general message instead of exposing exception details.
- Fixed the blank booking page after selecting a package and repaired package search filtering.
- Added an implementation-accurate DFD to the school lab handout and included it as a standalone SVG and PNG.
- Added shareable VS Code tasks and clarified Windows versus Git Bash run/reset instructions.
- Clarified that every payment option is demo-only and made booking actions recover from network failures.
- Added receipt uploads with staff review, a payment ledger, reservation cancellation/rescheduling, walk-ins, guest arrival status, and a staff desk with summary and CSV report.
- Added optional Google Calendar OAuth and manual synchronization; credentials remain local in `.env` and are not included.
- Added an admin package-maintenance panel; saved rates feed both the guest catalogue and server-side quotes, and hidden packages are blocked from new bookings.

**Modules 1–10:** package catalogue; availability; reservation and cost calculation;
temporary holds and conflict prevention; payment-proof submission and review; manual
payment ledger and balance tracking; reservation cancellation/rescheduling; optional
Google Calendar sync; walk-ins and guest arrival/departure status; staff dashboard and
CSV report. The system retains JSON storage, CSRF on mutations, validation, and
session-backed demo staff roles.

**The booking page works end to end in a browser.** Open `?action=book&id=1`, fill
in the dates and guest details, pick a payment method, and press **Reserve this
slot**. The page places a temporary hold through the API, shows a live 90-minute
countdown, and **Confirm Reservation** records the reservation. Guests can then open
**Manage reservation** to upload a sample receipt, request a date change, or cancel.
Staff review receipt images and record offline payments in the ledger. **Cancel
Booking** releases the slot immediately, and **Return to Menu** goes back to the
package list. If you leave the page without cancelling, the hold simply expires.

**Payment methods** come from `src/Config/config.php` (the `payment_methods`
list), so the page and the API can never disagree about what is offered:
GCash, Maya, Bank Transfer, PayPal, Credit/Debit Card, and Cash at the Resort.
All six are demo choices only. The page displays no live QR codes or bank details,
connects to no payment gateway, and collects no money. Guests may upload a sample
receipt image for manual review; staff can record payments that were received
outside the app. Neither action transfers or verifies real funds.

**Demo boundaries:**

- **No payment gateway or refunds.** Receipts are reviewed manually; staff-entered
  ledger entries are demo records.
- **Google Calendar needs your OAuth credentials** and a manual sync from the admin desk.
- **No email/SMTP service is connected.** Booking confirmations appear in the site;
  email notifications are not sent.
- **No MySQL database.** Records live in local JSON files; the modules do not require
  a separate database installation for this school demo.
- Staff credentials are simple local demo accounts and are not intended for a public
  or production deployment.

---

## Project map

```
public/            web root — only this folder should be publicly served
  index.php        page controller: ?action=home|rooms|book
  api/             JSON endpoints, one file per endpoint
  assets/          CSS and JavaScript
  api/             guest and staff JSON endpoints
src/
  Config/          config.php holds demo accounts, payment and Google settings
  Helpers/         Config, Validator, Response
  Repositories/    data access — JSON remains behind repositories
  Services/        booking, payments, auth, calendar sync and reservation workflows
views/
  guest/           landing page, packages, booking and reservation management
  staff/           staff login and front-desk/admin dashboard
  Services/        business logic: holds, availability, pricing, CSRF
  Controllers/     page controllers
  Models/          thin facades over repositories
data/              the JSON "database" (accommodations, holds, reservations)
views/             plain PHP templates
```

See `AGENTS.md` for architecture decisions, the concurrency design, and the
test evidence.

---

## For whoever is handing this over

Send the project folder zipped (right-click → *Send to* → *Compressed (zipped)
folder*). The `vendor/` folder isn't needed — nothing reads Composer's autoloader
yet — and `.env` shouldn't be shared. Your friend then follows Steps 1 and 4
onwards. If you publish it to GitHub instead, remove any credentials from the
repository's remote URL first.
