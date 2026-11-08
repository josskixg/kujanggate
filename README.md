# KujangGate — Self-Hosted Ed25519 Offline License & Hardware Seat Authority

<p align="center">
  <img src="logo.png" width="128" height="128" alt="KujangGate Logo">
</p>

**KujangGate** is a self-hosted cryptographic software licensing server, hardware fingerprint seat-binding authority, and HMAC-SHA256 order webhook engine built for **Laravel 11 / PHP 8.2+** (`ext-sodium`).

- **Live Operator Console:** [https://josskixg.github.io/kujanggate/](https://josskixg.github.io/kujanggate/)
- **Design System:** Supabase Dark (`#0f0f0f` / `#171717` / `#3ecf8e` / `Inter` + `JetBrains Mono` tabular-nums) — Formal spec in [`DESIGN.md`](DESIGN.md)

---

## Core Architecture

1. **Zero-DB Offline License Verification (`App\Services\Ed25519LicenseService`)**
   - Uses native PHP 8.2 `ext-sodium` (`sodium_crypto_sign_keypair`, `sodium_crypto_sign_detached`, `sodium_crypto_sign_verify_detached`).
   - Mints compact `KGATE-v1.<base64url(canonical_payload)>.<base64url(ed25519_sig)>` certificates.
   - Client applications (Laravel, WordPress plugins, Electron, Flutter, POS PWAs) embed only the 32-byte **Public Key** (`64 hex chars`) to verify license authenticity, expiration, feature flags, and hardware locks **100% offline** without calling home on every request.
2. **Hardware Fingerprint Seat-Binding (`App\Services\SeatActivationService`)**
   - Normalizes `domain + machine_id + cpu_arch + os_family` into a deterministic `hw_<32hex>` SHA-256 fingerprint.
   - Enforces `max_seats` quotas, handles idempotent device heartbeats, and supports self-service seat revocation/migration.
3. **HMAC-SHA256 Order Webhook Auto-Provisioning (`App\Http\Controllers\LicenseController`)**
   - Verifies incoming payment webhooks from Lynk.id, Midtrans, Tripay, or Gumroad with a 300-second timestamp replay window guard before minting and returning a signed `KGATE-v1` license.

---

## Quick Verification (Zero Dependencies)

```bash
php tests/Unit/KujangGateTest.php
```

Expected output:
```text
ALL 16/16 KUJANGGATE CRYPTO & SEAT ASSERTIONS PASSED.
```
