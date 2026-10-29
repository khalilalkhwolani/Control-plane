# SuperPro vendor licensing engine

Status: phase-1 issuer for offline/on-prem installations.

This directory belongs to the vendor control plane and MUST NOT be included in
customer deployment artifacts. It creates Ed25519 signing keys and signed
license documents. The private signing key stays on a vendor-controlled machine;
only the matching public key is configured in customer installations.

The command-line interface remains available for recovery. Pro One Control
Center now calls this same issuer from its single Laravel application, so the
web flow and CLI share one signature format and one validation contract.

## Generate a signing key

Store keys outside the repository:

```powershell
php control-plane/licensing/bin/generate-signing-key.php `
  --key-id=vendor-2026-01 `
  --output-dir=C:\secure\superpro-keys
```

Configure the customer installation with the content of the public key file:

```env
PRODUCT_LICENSING_MODE=enforce
PRODUCT_LICENSE_PUBLIC_KEYS_JSON={"vendor-2026-01":"BASE64_PUBLIC_KEY"}
```

## Issue an offline license

First obtain the installation UUID on the customer server:

```powershell
php backend/artisan superpro:installation:show
```

Then issue a license on the vendor machine:

```powershell
php control-plane/licensing/bin/issue-license.php `
  --private-key=C:\secure\superpro-keys\vendor-2026-01.private.key `
  --key-id=vendor-2026-01 `
  --installation-id=00000000-0000-4000-8000-000000000000 `
  --sequence=1 `
  --customer-reference=CUST-001 `
  --customer-name="Customer name" `
  --edition=standard `
  --features=system.onboarding,mobile.remote_access `
  --expires-at=2027-09-01T00:00:00Z `
  --grace-until=2027-10-01T00:00:00Z `
  --output=C:\secure\licenses\CUST-001.superpro-license.json
```

Transfer the resulting license document to the customer by approved offline
media and import it:

```powershell
php backend/artisan superpro:license:import C:\path\CUST-001.superpro-license.json
```

Every later license for the same installation must use a strictly increasing
sequence number. The local application rejects tampering, another installation's
license, and sequence rollback.
