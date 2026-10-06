# Chiamata

Table-call system for restaurants, bars and any venue with tables.

- One fixed QR per table. The guest enters the code given by the waiter, then can **call the waiter**,
  **ask for the bill** (cash or card) and **read the menu** (PDF or link).
- The code changes every time the bill is closed, so the next customer uses the same QR with a new code.
- Waiter app (installable on the phone) with sound, vibration and push notifications, zones, table codes.
- Multi-venue: each venue configures its tables, staff, logo, colour and menu in a few minutes.

PHP 8.1+, MariaDB/MySQL, Apache with mod_rewrite, `qrencode` for the printable QR codes.

Setup: copy `config/database.example.php` to `config/database.php`, run `php migrate.php`, then
`php bin/create-superadmin.php <user> <password>` and sign in at `/login.php`.
