# Pro One Control Center

Owner-only console for Pro One customers, installations, licensing, and audit.
This directory is **not** part of a customer Pro One ERP artifact. Never ship
`control-plane/` to a customer.

The operational path is now complete: customer -> installation approval ->
signed offline license -> download. The local ERP remains the authority that
verifies and imports that signed file.

## Runtime split

- `backend`: Laravel API, authentication, permissions, audit, registry, and
  license-signing authority.
- `frontend`: React operator interface. This is the default destination when
  opening the Laravel root URL; Laravel redirects there using `FRONTEND_URL`.
- `backend/resources/views`: temporary Blade fallback only. Do not add new
  operator functionality there.

## Boundaries

- One Laravel application with server-rendered Blade pages on port **8090**
- Dedicated MySQL database `pro_one_control_center`
- Session cookie `pro_one_cc_session`
- No Node.js process and no second frontend port are required
- The private signing key is read from an external file and is never stored in
  source control or the database

See `THREAT_MODEL.md` and `PRO_ONE_CONTROL_CENTER_PLAN.md`.

## Backend setup

```powershell
cd control-plane/backend
copy .env.example .env
php artisan key:generate
php scripts/create-database.php
php artisan migrate --seed
php artisan control-center:create-owner owner@example.com --name="المالك"
php artisan serve --host=127.0.0.1 --port=8090
```

Open http://127.0.0.1:8090

Generate the key outside the repository, then set these values in
`control-plane/backend/.env`:

```env
CONTROL_CENTER_LICENSE_KEY_ID=vendor-2026-01
CONTROL_CENTER_LICENSE_PRIVATE_KEY_PATH=C:\secure\pro-one-keys\vendor-2026-01.private.key
```

Local default: `CONTROL_CENTER_REQUIRE_MFA=false` so password login works while
the license workflow is built. Set it to `true` before any production deploy.
There is no default password in source.

Backup in Phase 1: dump `pro_one_control_center` only. Keep signing-key
recovery separate from this database.

`control-plane/frontend` is the superseded React prototype. Keep it only until
the Laravel cutover is accepted; do not run or extend it.

## Tests

```powershell
cd control-plane/backend
php artisan test
```
