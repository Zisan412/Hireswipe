# HireSwipe – UI/UX prototype (PHP 8.1+, no database)

Run:  `php -S localhost:8000` inside this folder, then open http://localhost:8000
(or copy the folder into Apache/XAMPP `htdocs`). Make sure `uploads/` and `data/` are writable.

Accounts and profiles are saved to `data/store.json` for now, so you can sign up, sign out and sign in
again while testing. Delete that file to reset. When you are ready for the real database, replace the
small store functions in `config.php` (user_by_email, user_create, user_update, profile_save ...)
with queries against `schema.sql` – the pages themselves won't need to change.

Flow: signin -> signup -> role -> employee_setup | employer_setup -> dashboard

## Demo login (prototype only)
Email `demo@hireswipe.com` / password `Demo@1234` - always restarts the flow at the role screen.
Remove `DEMO_EMAIL`, `DEMO_PASS` and `demo_login()` in config.php before launch.
