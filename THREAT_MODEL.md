# Pro One Control Center — Threat Model (Phase 1)

- Status: Active for Phase 1 implementation
- Date: 2026-09-11
- Scope: vendor authentication, MFA, RBAC, customer/installation registry, audit
- Out of scope: license signing, bootstrap tokens, support sessions, connected transport

This document locks the security rules the Control Center code MUST enforce.
It does not replace ADR-2026-09-11. The signed license contract stays on the
customer node and the existing CLI issuer.

## 1. Trust zones

1. **Customer node (Pro One ERP)** — customer business data, installation UUID,
   public verification keys, imported signed authorizations.
2. **Control Center** — vendor users, customers, installations, and audit.
   Separate deployment, database, session cookie, and artifact. Never stores
   customer sales, inventory, accounting, or employee records.
3. **Signing boundary** — private Ed25519 keys and the existing CLI issuer.
   Phase 1 does not call this boundary. Private key material MUST NOT enter
   the Control Center database, logs, UI, or git.

## 2. Assets

- Vendor credentials, TOTP secrets, hashed recovery codes, and session cookies
- Customer legal names, contacts, contract state, and installation UUIDs
- Append-only audit evidence of privileged vendor actions
- Future signed license files and signing keys (protected by exclusion, not
  by Phase 1 features)

## 3. Adversaries and controls

| Threat | Attack | Phase 1 control |
| --- | --- | --- |
| Stolen vendor session | XSS, cookie theft, leftover laptop | HttpOnly Secure session cookie, CSRF, session revocation, idle timeout, MFA enrollment required before registry access |
| Password guessing | Credential stuffing | Throttle login and MFA, lock after 5 failures / 15 minutes, password minimum 12 characters |
| MFA bypass | Skip TOTP, reuse enrollment | No registry route without `mfa_verified_at`; enrollment consumes a one-time secret; recovery codes are hashed and single-use |
| Stale MFA for high-risk writes | Session left open | Step-up TOTP when last verification is older than 5 minutes |
| Privilege abuse | Weak UI-only roles | Server RBAC on every non-public route; frontend hiding is not authorization |
| Installation cloning | Copy customer server UUID | Registry stores UUID only; clone detection is later. Operators MUST treat UUID as identity, not hardware proof |
| Key leak | Private key in repo, env, or logs | Phase 1 has no signing path; gitignore excludes keys and `*.superpro-license.json` |
| License replay / sequence rollback | Reuse or downgrade a signed file | Customer verifier already rejects this; Control Center must not claim remote disable |
| Fake delivery | Mark a license acknowledged without import proof | Phase 1 has no license states. Later: `issued` until acknowledgement evidence exists |
| Cross-product session mix | Cookie shared with ERP | Distinct `APP_NAME`, cookie name, ports (API 8090, Vite 5190), and database |
| Audit tampering | Update/delete audit rows | Append-only model; updates and deletes throw; tests cover the invariant |
| PII leakage | Logs, error payloads, screenshots | No passwords, TOTP secrets, recovery codes, or private keys in logs or API errors |

## 4. Locked authentication rules

- Authentication is cookie session + CSRF. Long-lived personal access tokens
  are not an authentication factor.
- The first owner is created only by `php artisan control-center:create-owner`.
  No default user, password, or bypass exists in source or seeders used by
  production configuration.
- **Temporary local deferral (owner 2026-09-11):** `CONTROL_CENTER_REQUIRE_MFA`
  may be `false` while the license/customer workflow is unfinished. Password-only
  login is then allowed. Production hosting MUST set this to `true` before go-live.
- When MFA is required: first login MUST complete TOTP enrollment before any
  customer, installation, user-admin, or audit route is usable.
- When MFA is required: TOTP is mandatory for every vendor user.
- Recovery codes are generated at enrollment, stored hashed, shown once, and
  consumed on use.
- Failed password or MFA attempts share a lockout counter keyed by account
  and client IP.
- Logout and "revoke other sessions" invalidate server sessions immediately.
- High-risk writes (create/update/deactivate vendor users; create/update
  customers and installations) require TOTP verified within the last 5 minutes.
  Otherwise the API returns `403` with code `mfa_step_up_required`.

## 5. Roles (least privilege)

- **Owner:** all Control Center decisions and user administration.
- **License administrator:** customer and installation records; cannot manage
  owner accounts.
- **Support engineer:** read customers/installations; cannot change registry
  writes in Phase 1 beyond what later support workflows need.
- **Release manager:** no Phase 1 write surface; overview read only.
- **Auditor:** read-only registry and audit evidence.

Unimplemented navigation (licenses, activations, support, releases) MUST render
an explicit deferred screen and MUST NOT expose APIs.

## 6. Residual risks (accepted)

- An installation UUID is not TPM-backed. A cloned customer server can present
  the same identity until a later hardware claim exists.
- Excluding `control-plane/` from customer artifacts is a packaging policy
  until the Phase 4 pipeline enforces it.
- Phase 1 backups are a documented MySQL dump of the Control Center database,
  not a full vendor operations platform.
- Immediate remote disable of an offline customer remains impossible.
