# Chiamata — handoff notes

`F:\chiamata`, repo `ispbillingai/chiamata` (**public**). Standalone **table-call system** for restaurants
and any venue with tables: the guest scans the table QR, types the code the waiter gave them, then can
call the waiter, ask for the bill (cash/card) and read the menu (PDF). Started on 2026-10-06 from a copy
of the Focacciami POS (`F:\pub`); the POS code was removed the same day, nothing of it is used.

## How it works
- **Multi-venue**: a superadmin (`/super/`) creates venues (name, manager login, N tables in one form)
  and can "Gestisci" any venue. Each venue has its own tables, zones, staff, logo, colour, menu PDF.
- **Fixed QR per table** (`/t/<qr_token>`, rewrite in `.htaccess` → `t.php`). The QR never changes.
- **Code per customer**: each table always has a current `table_sessions` row with a numeric code
  (3–6 digits, per venue). The guest types it once (signed cookie `cg<tableId>`, 16 h). When the
  waiter closes the bill ("Conto fatto" → "chiudi", or tap the table in "Tavoli e codici") the session
  closes, its open calls are closed and a new code is generated: the next customer uses the same QR
  with the new code. Wrong codes are rate-limited (`code_attempts`).
- **Waiter app** `/waiter/` (PWA, add to Home screen): polls `api/waiter.php?a=feed` every 3 s, rings
  (WebAudio) and vibrates on new calls/reminders, wake lock. Waiters can follow only some zones.
- **Push**: Web Push *without payload* (`includes/push.php`, VAPID ES256 via openssl, keys created
  on first use in `app_settings`). The service worker `waiter/sw.js` fetches `?a=push_summary` to
  build the notification. iPhone: push only from the Home-screen app (iOS 16.4+). Needs HTTPS.
- **Admin** `/admin/`: settings (logo, colour, welcome text, menu PDF or link, code digits, bill
  options), tables (bulk create, zones, disable, new code, new QR link), printable QR sheet
  (`qrencode` SVG/PNG), staff (never deleted, only disabled), history with response times.
- Logins: `users.role` superadmin | manager | waiter; "remember me" tokens in `auth_tokens` (180 days).
- Uploads in `storage/v<venueId>/` (gitignored, denied by `.htaccess`), served by `file.php`.
- Menu viewer `menu.php` uses pdf.js from cdnjs (Android Chrome would download a bare PDF).

## Where it runs
- Server: 217.160.131.242 (IONOS Ubuntu 24.04, Apache 2.4, PHP 8.3, MariaDB 10.11), shared with
  Focacciami (see memory `pub-server.md`). SSH root; credentials and the
  plink one-liner are only in Claude's local memory (`C:\Users\magom\.claude\projects\f--chiamata\memory\`).
- App folder: `/var/www/html/chiamata` (git clone, branch `main`).
- Domain: **https://chiamata.upgradesrls.com** (DNS → 217.160.131.242; Let's Encrypt via certbot,
  vhosts `chiamata.conf` + `chiamata-le-ssl.conf`, http→https redirect; auto-renew).
- DB `chiamata`, user `chiamata` (password only in the server's `config/database.php`).
  The old POS tables were dropped on 2026-10-06 (backup `/root/chiamata-pos-backup-2026-10-06.sql.gz`).
- Logs: `/var/log/apache2/chiamata.upgradesrls.com-error.log` (and `-access.log`).
- Superadmin: create/reset with `php bin/create-superadmin.php <user> <password> ["Name"]`.

## Workflow (do this after EVERY change, without being asked)
1. Edit locally in `F:\chiamata`, `git commit`, `git push origin main`.
2. On the server, via plink:
   `cd /var/www/html/chiamata && git pull origin main && php migrate.php`
3. Wait ~3 s (opcache revalidate_freq=2), then test live:
   - `php -l` each changed PHP file on the server;
   - `curl -s -o /dev/null -w '%{http_code}' https://chiamata.upgradesrls.com/login.php`;
   - exercise changed pages/APIs (curl with a cookie jar, or a CLI script);
   - `tail /var/log/apache2/chiamata.upgradesrls.com-error.log`: no new errors.
4. Report the commit hash and the test result.

## Rules
- Deploy this repo **only** to `/var/www/html/chiamata`. Never pull it into Focacciami,
  `/var/www/html/numeratore` or ristorante.
- Never hand-edit tracked files on the server. `config/database.php` is gitignored and server-only.
- The repo is public: never commit passwords, API keys or tokens.
- New tables need `COLLATE utf8mb4_unicode_ci`.
- Users are never deleted, only disabled or enabled.
- Never change the WireGuard tunnel on this server (Focacciami uses it to reach the shop's printer).
- Local setup: copy `config/database.example.php` to `config/database.php`, create the DB, run
  `php migrate.php`, then `php bin/create-superadmin.php`.
