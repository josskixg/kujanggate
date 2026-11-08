<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/Ed25519LicenseService.php';
require_once __DIR__ . '/../../app/Services/SeatActivationService.php';
require_once __DIR__ . '/../../app/Http/Controllers/LicenseController.php';

use App\Http\Controllers\LicenseController;
use App\Services\Ed25519LicenseService;
use App\Services\SeatActivationService;

$licenseService = new Ed25519LicenseService();
$seatService = new SeatActivationService();
$controller = new LicenseController($licenseService, $seatService);

$passed = 0;
$assert = static function (bool $condition, string $label) use (&$passed): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    $passed++;
    fwrite(STDOUT, "[PASS] {$label}\n");
};

// 1. Keypair generation
$keys = $licenseService->generateKeypair();
$assert(strlen($keys['public_key_hex']) === 64, 'Ed25519 public key is 32 bytes (64 hex chars)');
$assert(strlen($keys['secret_key_hex']) === 128, 'Ed25519 secret key is 64 bytes (128 hex chars)');
$assert(str_starts_with($keys['key_id'], 'kjk_'), 'Deterministic key_id prefix generated');

// 2. Deterministic hardware fingerprint
$hw1 = $seatService->computeHardwareFingerprint([
    'domain'     => 'pos.warungkopi.id',
    'machine_id' => 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6',
    'cpu_arch'   => 'aarch64',
    'os_family'  => 'linux',
]);
$assert(str_starts_with($hw1, 'hw_') && strlen($hw1) === 35, 'Hardware fingerprint normalized to hw_<32hex>');

// 3. Issue Ed25519 signed offline license locked to hardware
$now = 1794190000;
$minted = $licenseService->issueLicense([
    'lic_id'         => 'LIC-2026-9901',
    'product_slug'   => 'kasirku-pos-pro',
    'customer_email' => 'owner@warungkopi.id',
    'tier'           => 'agency',
    'max_seats'      => 2,
    'features'       => ['offline_pos', 'bluetooth_escpos', 'white_label'],
    'hw_locks'       => [$hw1],
    'issued_at'      => $now,
    'expires_at'     => $now + 86400 * 365,
], $keys['secret_key_hex']);

$assert(str_starts_with($minted['token'], 'KGATE-v1.'), 'License token has KGATE-v1 header');

// 4. Verify offline using ONLY the public key
$verdict = $licenseService->verifyOffline($minted['token'], $keys['public_key_hex'], $hw1, $now + 60);
$assert($verdict['valid'] === true && $verdict['reason'] === 'ED25519_VALID', 'Offline Ed25519 signature verification passes');

// 5. Reject hardware mismatch
$wrongHw = $seatService->computeHardwareFingerprint([
    'domain'     => 'cloned.pirate.net',
    'machine_id' => '00000000-1111-2222-3333-444444444444',
]);
$hwMismatch = $licenseService->verifyOffline($minted['token'], $keys['public_key_hex'], $wrongHw, $now + 60);
$assert($hwMismatch['valid'] === false && $hwMismatch['reason'] === 'ERR_HARDWARE_FINGERPRINT_MISMATCH', 'Hardware lock rejects unauthorized machine fingerprint');

// 6. Reject payload tampering
$parts = explode('.', $minted['token']);
$tamperedPayload = rtrim(strtr(base64_encode('{"v":1,"tier":"lifetime_pirated","exp":9999999999}'), '+/', '-_'), '=');
$tamperedToken = $parts[0] . '.' . $tamperedPayload . '.' . $parts[2];
$tamperVerdict = $licenseService->verifyOffline($tamperedToken, $keys['public_key_hex'], null, $now + 60);
$assert($tamperVerdict['valid'] === false && $tamperVerdict['reason'] === 'ERR_ED25519_SIGNATURE_MISMATCH', 'Tampered payload rejected by Ed25519 signature check');

// 7. Reject expired token
$expiredVerdict = $licenseService->verifyOffline($minted['token'], $keys['public_key_hex'], $hw1, $now + 86400 * 400);
$assert($expiredVerdict['valid'] === false && $expiredVerdict['reason'] === 'ERR_LICENSE_EXPIRED', 'Expired license rejected offline');

// 8. Seat activation quota & revocation flow
$seats = [];
$s1 = $seatService->activateSeat('LIC-2026-9901', 2, $seats, $hw1, 'Kasir Utama', $now);
$seats = $s1['active_seats'];
$assert($s1['allowed'] === true && $s1['seats_used'] === 1, 'Seat 1 activated');

$hw2 = $seatService->computeHardwareFingerprint(['domain' => 'cabang2.warungkopi.id', 'machine_id' => 'node-02']);
$s2 = $seatService->activateSeat('LIC-2026-9901', 2, $seats, $hw2, 'Kasir Cabang 2', $now);
$seats = $s2['active_seats'];
$assert($s2['allowed'] === true && $s2['seats_used'] === 2, 'Seat 2 activated');

$hw3 = $seatService->computeHardwareFingerprint(['domain' => 'cabang3.warungkopi.id', 'machine_id' => 'node-03']);
$s3 = $seatService->activateSeat('LIC-2026-9901', 2, $seats, $hw3, 'Kasir Ilegal 3', $now);
$assert($s3['allowed'] === false && $s3['status'] === 'ERR_SEAT_LIMIT_EXHAUSTED', 'Seat 3 blocked when max_seats=2');

$revoked = $seatService->revokeSeat($seats, $hw1);
$seats = $revoked['active_seats'];
$assert($revoked['revoked'] === true && $revoked['seats_used'] === 1, 'Seat 1 revoked cleanly');

$s3Retry = $seatService->activateSeat('LIC-2026-9901', 2, $seats, $hw3, 'Kasir Cabang 3 Resmi', $now);
$assert($s3Retry['allowed'] === true && $s3Retry['seats_used'] === 2, 'Seat 3 allowed after Seat 1 revocation');

// 9. Signed Order Webhook auto-provisioning & replay protection
$webhookSecret = 'whsec_kujanggate_prod_998877';
$orderJson = json_encode([
    'order_id'     => 'LYNK-88412',
    'product_slug' => 'kujangpay-gateway',
    'buyer_email'  => 'dev@agency.co.id',
    'tier'         => 'agency',
    'max_seats'    => 5,
], JSON_THROW_ON_ERROR);
$sig = $seatService->signWebhookPayload($orderJson, $now, $webhookSecret);

$whOk = $controller->handleOrderWebhook($orderJson, $now, $sig, $webhookSecret, $keys['secret_key_hex'], $now);
$assert($whOk['status'] === 'ok' && $whOk['code'] === 201 && str_starts_with($whOk['data']['token'], 'KGATE-v1.'), 'Valid HMAC-SHA256 order webhook auto-mints Ed25519 license');

$whReplay = $controller->handleOrderWebhook($orderJson, $now - 900, $sig, $webhookSecret, $keys['secret_key_hex'], $now);
$assert($whReplay['status'] === 'error' && $whReplay['code'] === 401, 'Stale webhook timestamp (>300s) rejected by replay guard');

fwrite(STDOUT, "\nALL {$passed}/{$passed} KUJANGGATE CRYPTO & SEAT ASSERTIONS PASSED.\n");
