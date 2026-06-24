# Private-by-default admin path

## TL;DR

- **What:** Change the default `admin.path` from `admin` to the private string `7YDX5h38a6Q2sfrsW2pRv9CoU59RA5YWD2R7K3AuMA`, still overridable via `ADMIN_PATH`.
- **Why:** `/admin` is predictable and gets hit by automated scanners; a non-guessable default prefix adds an extra layer in front of the login + access middleware.
- **Files:** `config/admin.php`, `.env.example`, `tests/Feature/AdminAuthRbacTest.php`, `tests/Unit/AdminPathConfigTest.php`
- **Risk:** Low — single config value; route names unchanged so every `route('admin.*')` link, redirect and login keeps working. Bookmarks to `/admin` now 404.
- **Verification:** `pint --test` OK, `git diff --check` OK, `AdminPathConfigTest|AdminAuthRbacTest` 13/125 OK, full `php artisan test` 258/2372 OK.

## Context

All admin routes hang off a single prefix `config('admin.path')` (see `routes/web.php`), resolved from `env('ADMIN_PATH', $defaultAdminPath)`. There is no hardcoded `/admin` anywhere in `routes/` or `app/` (the only `'admin'` literals are the RBAC role name and entitlement matrix key, unrelated to routing). So flipping the default is a config-only change, not a refactor.

## Change

- `config/admin.php`: `$defaultAdminPath` → the private string. Added a comment explaining it's overridable/rotatable via `ADMIN_PATH` and that the login + access middleware remain the real lock.
- `.env.example`: documented `ADMIN_PATH` (blank = use private default; set a unique secret in prod to rotate without a code deploy).
- Tests flipped to assert the new default: private path is canonical, predictable `/admin` now 404s.

## Production note

This bakes the secret into the repo (private repo, but it lands in git history). To keep the secret out of the repo and rotatable without a redeploy, set `ADMIN_PATH=<secret>` in the prod env + `php artisan config:cache` instead — the env var always wins over the default. The security boundary is still auth + middleware; the private path is a layer, not the wall.
