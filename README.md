# ReserVibe — How to Run It

A web-based reservation and booking system for **Verde Royale Resort**
(Brgy. Ambonao, Calasiao, Pangasinan). School project — the backend core is built
and tested; the guest-facing booking form is not wired up yet (see
[What works and what doesn't](#what-works-and-what-doesnt)).

This guide takes you from a laptop with nothing installed to a running server in
about 15 minutes. No Composer, no MySQL, and no internet access are required.

---

## What you'll end up with

If everything works, you will be able to:

1. Open a landing page and a package catalogue in your browser.
2. Get JSON back from the API endpoints.
3. Book a slot from the terminal and see a **second** attempt for the same dates
   get refused with HTTP 409 — that refusal is the project's headline feature
   working correctly.

---

## Step 1 — Install PHP

You need **PHP 8.1 or newer** with the `fileinfo`, `json`, `mbstring` and
`session` extensions (all four ship with a standard PHP build).

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

A fresh copy will **not** contain a `.env` file — that's deliberate, it's
gitignored. The project runs without one, so you can skip straight to Step 4. If
you want to tweak settings, see Step 3.

---

## Step 3 — (Optional) create your `.env`

Only needed if you want to change the timezone or the future MySQL credentials.
The defaults are baked into `src/Config/config.php`, so this is skippable.

```bash
cp .env.example .env        # macOS / Linux / Git Bash
copy .env.example .env      # Windows Command Prompt
```

---

## Step 4 — Start the server

Open a terminal **in the project's top folder** — the one containing `public/`,
`src/` and `data/`, not `public/` itself.

```bash
php -S 127.0.0.1:8000 -t public
```

> The `-t public` part matters. It points the server at the `public/` folder so
> that `/api/...` and `/assets/...` resolve. If you run `php -S` from inside
> `public/`, use `php -S 127.0.0.1:8000` with no `-t`.

Leave that terminal running — it's your server. You'll use a **second** terminal
for the commands in Steps 5 and 6. Stop the server with `Ctrl+C`.

### Using VS Code instead

Open the project folder, then `Ctrl+Shift+P` → *Tasks: Run Task* → **Serve
ReserVibe (php -S :8000)** (or just `Ctrl+Shift+B`). Two more tasks are
included: **Lint all PHP files** and **Reset demo data**.

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
| <http://127.0.0.1:8000/?action=book&id=1> | Booking form with a deposit line |
| <http://127.0.0.1:8000/api/catalog/list.php> | JSON listing 4 accommodations |

**Success checklist** — all four should be true:

- [ ] The landing page loads with styling (if it's plain grey text, your
      document root is wrong — see the `-t public` note above).
- [ ] The catalogue shows exactly four rates, from ₱1,500 to ₱5,500.
- [ ] The booking form's "Required 50% Deposit" line reflects the chosen rate.
- [ ] The catalog JSON begins `{"success": true, ...}`.

Note the URLs use `?action=...` rather than clean paths like `/rooms`. That's
because `.htaccess` rewriting only runs under Apache — the PHP built-in server
ignores it by design.

---

## Step 6 — Book a slot from the terminal

This is the part worth showing off. The API needs a **CSRF token** plus the
**session cookie** that goes with it, so these commands use `curl` with a cookie
file and pass the token in a header.

> **Windows PowerShell users:** type `curl.exe`, not `curl`. In PowerShell,
> `curl` is an alias for `Invoke-WebRequest` and the flags below will fail.
> Git Bash, Command Prompt, macOS and Linux are all fine as written.

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
  -d '{"hold_id":1,"guest_name":"Juan Dela Cruz","guest_email":"juan@example.com","guest_phone":"09171234567"}' \
  http://127.0.0.1:8000/api/reservation/create.php
```

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

**6.8 — Staff view.** Reservations are staff-only:

```bash
curl -s -H "X-User-Role: staff" http://127.0.0.1:8000/api/reservation/list.php
```

Without that header you get **403**. (This header is a placeholder for real
login — anyone can send it. It is not security.)

---

## Step 7 — Reset the demo data

Bookings you create are saved to `data/holds.json` and `data/reservations.json`.
To wipe them and start clean:

```bash
printf '[]\n' > data/holds.json && printf '[]\n' > data/reservations.json && rm -f data/.booking.lock
```

In VS Code, run the **Reset demo data** task instead.

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
| `403` on `reservation/list.php` | Add `-H "X-User-Role: staff"`. |
| `409` when you expected a booking | The slot is genuinely held or reserved. Pick other dates or reset the data (Step 7). |
| Blank white page | Check the server terminal for the error. Warnings are logged, never printed into JSON. |
| `curl` errors in PowerShell | Use `curl.exe`, or run the commands in Git Bash. |
| Nothing works and you're not sure why | Run a syntax check across the project — it should print only `No syntax errors`: `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;` |

---

## What works and what doesn't

**Working:** package catalogue, availability check, FullCalendar event feed,
server-side cost calculation, temporary holds with atomic conflict prevention and
lazy expiry, turning a hold into a reservation, staff-only reservation listing,
CSRF protection on every POST, input validation.

**Not built yet:**

- **The website's own booking button doesn't submit.** `booking.js` still does
  its arithmetic client-side and opens a modal; only the terminal commands in
  Step 6 actually write a booking. If the button "does nothing", that's expected.
- Payment proof upload, payment verification, and balance/ledger tracking.
- Cancellation and rescheduling.
- Walk-in management and guest arrival status.
- Google Calendar and email integration.
- The staff and admin screens (the folders exist but are empty).
- **No database** — data lives in JSON files. MySQL is planned, and only
  `src/Repositories/` will change when it lands.
- **No login system** — `X-User-Role` is a stand-in.

---

## Project map

```
public/            web root — only this folder should be publicly served
  index.php        page controller: ?action=home|rooms|book
  api/             JSON endpoints, one file per endpoint
  assets/          CSS and JavaScript
  uploads/         (empty) destination for payment proof images
src/
  Config/          config.php holds every tunable setting
  Helpers/         Config, Validator, Response
  Repositories/    data access — the only layer that talks to storage
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
