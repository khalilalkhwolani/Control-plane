# Pro One Control Center — Product and Architecture Plan

- Status: Proposed implementation plan
- Date: 2026-09-11
- Product owner: Pro One owner
- System boundary: vendor control plane; never included in customer artifacts

## 1. Product naming

`Pro One` is the product family.

- `Pro One ERP`: the customer-facing local ERP/POS installation.
- `Pro One Control Center`: the vendor-only web console used to manage
  customers, installations, licenses, releases, activations, and support.
- `Pro One License`: the signed offline authorization document imported by a
  customer installation.

This naming does not turn the local ERP into a multi-tenant application. The
Control Center is a separate deployment and database that stores vendor
operations metadata only; it never stores customer sales, inventory,
accounting, or employee records.

## 2. Outcome and completion criteria

The Control Center must let the vendor owner:

1. register a customer and one or more approved installations;
2. receive an installation request without opening an inbound port at the
   customer;
3. approve first-time setup with a signed, one-time bootstrap authorization;
4. issue a signed license containing the exact allowed product features;
5. renew, suspend, or change a license with an auditable new revision;
6. export files for fully offline customers;
7. monitor delivery and acknowledgement separately, without claiming an
   offline change reached the customer before proof exists;
8. issue customer-visible, time-limited support sessions without a hidden
   permanent account;
9. publish signed product releases and track which installation received them;
10. preserve an append-only security audit for every privileged action.

The first release is complete when an owner can execute the offline activation
workflow end-to-end through the UI and the customer installation accepts the
resulting signed document using the existing verification contract.

## 3. Deployment boundary

The recommended topology is three independent trust zones:

1. **Customer node** — Pro One ERP, the customer database, the public signing
   keys, and imported signed authorizations.
2. **Control Center** — vendor users, customers, installation registry,
   licensing workflow, release metadata, support workflow, and audit database.
3. **Signing boundary** — an isolated signing service backed by KMS/HSM when
   available. The Control Center requests signatures; it does not read or
   return private key material.

For the first internal version, the current offline CLI is the signing boundary.
The web console creates an approved signing request and an authorized operator
signs it on the vendor machine. Moving signing into KMS/HSM later must preserve
the existing `superpro-license-envelope/v1` contract.

## 4. Main navigation

### Overview

The landing dashboard focuses on work requiring attention, not vanity charts:

- installations awaiting approval;
- licenses expiring soon;
- signed files issued but not yet acknowledged;
- installations behind the approved product release;
- open or expiring support sessions;
- recent high-risk actions.

Primary actions: add customer, approve activation, issue license, and publish
release.

### Customers

Customer registry with legal/display name, reference, contacts, contract state,
notes, and linked installations. It must not contain the customer's ERP data.

### Installations

Each installation shows:

- installation UUID and customer;
- friendly device/site label;
- deployment mode: offline or connected;
- activation state;
- last issued and last acknowledged license sequence;
- installed product release when known;
- device claim/fingerprint state;
- last contact only for connected installations;
- warnings for cloning, rollback, or stale acknowledgement.

### Licenses

The operator selects an installation, edition, feature entitlements, expiry,
and grace period. Issuing always creates a new immutable revision with a higher
sequence. Existing signed licenses are never edited.

License state in the Control Center distinguishes:

- `draft`: not approved or signed;
- `issued`: signed and ready for delivery;
- `delivered`: operator recorded delivery;
- `acknowledged`: customer installation proved import;
- `superseded`: a higher sequence was issued;
- `expired`: expiry and grace have passed;
- `revocation_pending`: vendor intent exists but an offline node has not
  received a replacement authorization.

### Feature catalog

Feature definitions are controlled metadata, not arbitrary text typed during
license issue. Each feature has a stable key, Arabic/English label, description,
risk class, availability, and dependencies. Phase-one keys remain:

- `system.onboarding`
- `mobile.remote_access`

Disabling or renaming a feature key requires compatibility review because old
customer binaries may still consume it.

### Activations

Activation requests are separate from commercial licenses. A request contains
an installation identity, nonce, creation time, and optional privacy-minimized
device claim. Approval produces a signed, short-lived, single-use bootstrap
authorization. Replays and expired requests are rejected and audited.

### Support

There is no hidden vendor account. A support session requires:

1. a customer-visible request from the local system;
2. vendor approval and a reason/ticket reference;
3. a signed capability restricted by installation, actions, and time;
4. explicit local acceptance when interactive access is required;
5. append-only logs and automatic expiry/revocation.

### Releases

Tracks production artifact identity, version, commit, checksums, migrations,
minimum compatible license schema, release notes, and rollback/forward-fix
instructions. Customer packages exclude the Control Center, private keys, Git,
tests, development tools, and source maps.

### Audit and security

Audit filters cover actor, customer, installation, action, date, request ID,
and risk. Security screens manage vendor users, MFA status, sessions, allowed
networks where applicable, signing key metadata, rotation status, and security
alerts. Private keys and complete license payload secrets are never logged.

## 5. Roles and least privilege

- **Owner:** all Control Center decisions, user administration, key rotation
  approval, and emergency policy.
- **License administrator:** customer/install records and license drafts; cannot
  manage owner accounts or export private keys.
- **Support engineer:** support requests and diagnostics only; cannot change
  commercial entitlements.
- **Release manager:** signed artifact and rollout metadata; cannot issue
  licenses.
- **Auditor:** read-only access to registry and audit evidence.

High-risk actions require fresh MFA. Key rotation and emergency revocation
should support four-eyes approval when a second trusted operator exists.

## 6. Core data model

The Control Center owns these entity families:

- `vendor_users`, roles, permissions, MFA methods, and sessions;
- `customers` and approved contacts;
- `installations` and device claims;
- `activation_requests` and one-time bootstrap grants;
- `feature_definitions` and edition templates;
- `license_revisions` and delivery acknowledgements;
- `signing_keys` metadata only, never private key bytes;
- `support_requests`, support grants, and support audit events;
- `product_releases`, artifact checksums, and rollout records;
- append-only `control_audit_events`.

Customer ERP business tables are explicitly outside this database.

## 7. Offline feature-change workflow

1. The vendor selects the customer installation.
2. The UI displays its latest known sequence and acknowledgement.
3. The vendor changes the approved feature set and supplies a reason.
4. The backend validates feature keys, dependencies, dates, and sequence.
5. A license draft is approved and sent to the signing boundary.
6. The signed file is stored in private vendor storage and downloaded once for
   delivery.
7. The customer imports the file locally.
8. The local ERP verifies signature, installation binding, and sequence.
9. An acknowledgement file can be returned manually and uploaded to the Control
   Center. Until then the dashboard says `issued`, never `acknowledged`.

An offline customer can choose not to import a replacement file. Therefore
immediate remote disable is impossible. Commercial enforcement for offline
customers relies on bounded license validity, renewal policy, protected
production packaging, and contract terms.

## 8. Optional connected mode

Connected mode is an opt-in later phase. The customer node makes outbound HTTPS
requests; it never exposes a permanent inbound administration port. It can:

- register heartbeat and installed release metadata;
- pull a newer signed license;
- return import acknowledgement;
- download an approved signed update;
- request a support session.

The signed document remains authoritative, so loss of internet does not create
a second entitlement system. Network synchronization only transports signed
facts.

## 9. Security controls

- Vendor authentication uses strong passwords, MFA, throttling, secure sessions,
  and session revocation.
- Every non-public route is authenticated and authorized server-side.
- Private signing keys remain in the signing boundary, outside source and the
  Control Center database.
- License and activation issuance is idempotent and sequence protected.
- Signed files use private object storage and authorized expiring downloads.
- Material actions require reason, actor, request ID, and append-only audit.
- Customer identifiers and diagnostics are privacy-minimized.
- There is no universal customer password, hidden vendor account, or silent
  support tunnel.
- Backups cover the registry/audit database and signing-key recovery is separate.
- Emergency key compromise follows documented stop-issue, rotate, dual-trust,
  reissue, and retirement steps.

## 10. Source-delivery and packaging track

This is a separate workstream from the Control Center UI:

1. build the React frontend into production assets without source maps;
2. install backend production dependencies only;
3. exclude `control-plane/`, `.git`, tests, fixtures, keys, licenses, and
   development configuration;
4. encode selected PHP application modules using an approved commercial encoder;
5. sign the release manifest and record artifact checksums;
6. test installation, migration, upgrade, rollback/forward-fix, and data export;
7. ship the exact tested artifact.

Docker is a delivery boundary, not source-code secrecy. A customer with root
access can inspect an image unless code is encoded or delivered as a managed
appliance.

## 11. Recommended implementation stack

To minimize operating complexity and reuse team knowledge:

- separate Laravel 12 API application;
- separate React 19 TypeScript frontend;
- PostgreSQL or MySQL as a dedicated Control Center database;
- Redis for sessions, rate limits, and queued non-authoritative work;
- private object storage for signed licenses and release artifacts;
- KMS/HSM-backed Ed25519 signing service when production hosting is selected;
- one production domain with HTTPS, strict origin policy, backups, monitoring,
  and audited deployments.

The customer ERP and Control Center must not share a database, user table,
session cookie, or deployment artifact.

## 12. Implementation phases

### Phase 0 — accepted design and threat model

- confirm naming and navigation;
- approve the trust boundaries and offline limitations;
- define license/support/activation state machines;
- produce ADR and threat model.

Exit: owner accepts this plan and no unresolved security boundary remains.

### Phase 1 — secure Control Center foundation

- scaffold separate backend/frontend and database;
- owner authentication, MFA, sessions, RBAC, and audit;
- customer and installation registry;
- backup, recovery, configuration, and CI foundations.

Exit: authenticated owner can register customers/installations and every write
is authorized and audited.

### Phase 2 — licensing UI

- feature catalog and edition templates;
- draft, approve, sign, download, deliver, and acknowledge workflows;
- integrate the existing license envelope and verifier;
- expiry and sequence warning dashboard.

Exit: one real offline activation and feature change passes end-to-end.

### Phase 3 — one-time setup authorization

- activation request and nonce file;
- short-lived single-use bootstrap grant;
- local replay protection and setup state machine;
- recovery/replacement workflow.

Exit: an unapproved installation cannot execute onboarding in enforcement mode.

### Phase 4 — support and releases

- customer-visible time-bound support capabilities;
- signed release manifests and artifact registry;
- packaging pipeline and optional PHP encoding;
- upgrade and recovery drills.

Exit: support leaves no permanent access and the exact tested artifact can be
traced to each installation.

### Phase 5 — optional connected transport

- outbound-only customer agent;
- heartbeat, acknowledgement, license pull, and signed update download;
- retry, idempotency, certificate rotation, observability, and offline fallback.

Exit: connected customers receive approved signed changes automatically without
making the network channel the source of entitlement truth.

## 13. Explicit non-goals

- storing customer ERP transactions in the Control Center;
- adding multi-tenancy to the local Pro One ERP;
- a hidden developer role or permanent master password;
- silently locking customers out of reading/exporting their own data;
- claiming instant control over a machine with no network connection;
- treating Docker alone as source protection;
- building billing, marketplace, or analytics before licensing security works.

## 14. First implementation slice

The next code slice should create the Phase 1 skeleton only:

1. separate `control-plane/backend` and `control-plane/frontend` applications;
2. owner login with mandatory MFA enrollment;
3. customer/install registry migrations and APIs;
4. append-only Control Center audit;
5. first RTL dashboard shell using the approved navigation;
6. automated auth, authorization, validation, and audit tests.

The existing `control-plane/licensing` signer remains unchanged and is called
through an explicit adapter only after the approval workflow is implemented.
