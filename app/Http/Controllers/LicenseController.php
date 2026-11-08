<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Ed25519LicenseService;
use App\Services\SeatActivationService;

/**
 * Laravel 11 API Controller for Issuing Ed25519 Licenses, Binding Hardware Seats,
 * and Auto-Provisioning Licenses via Signed Order Webhooks.
 */
final class LicenseController
{
    public function __construct(
        private readonly Ed25519LicenseService $licenseService,
        private readonly SeatActivationService $seatService,
    ) {
    }

    /**
     * POST /api/v1/licenses/issue
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function issue(array $request, string $secretKeyHex): array
    {
        $issued = $this->licenseService->issueLicense($request, $secretKeyHex);

        return [
            'status' => 'ok',
            'data'   => $issued,
        ];
    }

    /**
     * POST /api/v1/licenses/verify-offline
     *
     * @param array{token: string, public_key_hex: string, hardware_hash?: string} $request
     * @return array<string, mixed>
     */
    public function verifyOffline(array $request): array
    {
        $result = $this->licenseService->verifyOffline(
            (string) ($request['token'] ?? ''),
            (string) ($request['public_key_hex'] ?? ''),
            isset($request['hardware_hash']) ? (string) $request['hardware_hash'] : null
        );

        return [
            'status' => $result['valid'] ? 'ok' : 'rejected',
            'data'   => $result,
        ];
    }

    /**
     * POST /api/v1/webhooks/order-paid
     * Verifies HMAC-SHA256 signature and mints an Ed25519 offline license automatically.
     *
     * @return array<string, mixed>
     */
    public function handleOrderWebhook(
        string $rawBody,
        int $timestamp,
        string $signatureHeader,
        string $webhookSecret,
        string $ed25519SecretKeyHex,
        ?int $currentTime = null
    ): array {
        if (!$this->seatService->verifyWebhookSignature($rawBody, $timestamp, $signatureHeader, $webhookSecret, 300, $currentTime)) {
            return [
                'status' => 'error',
                'code'   => 401,
                'reason' => 'ERR_WEBHOOK_HMAC_OR_REPLAY_REJECTED',
            ];
        }

        /** @var array<string, mixed> $order */
        $order = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        $now = $currentTime ?? time();
        $minted = $this->licenseService->issueLicense([
            'lic_id'         => 'LIC-' . strtoupper((string) ($order['order_id'] ?? bin2hex(random_bytes(3)))),
            'product_slug'   => (string) ($order['product_slug'] ?? 'kujanggate-enterprise'),
            'customer_email' => (string) ($order['buyer_email'] ?? ''),
            'tier'           => (string) ($order['tier'] ?? 'agency'),
            'max_seats'      => (int) ($order['max_seats'] ?? 5),
            'features'       => (array) ($order['features'] ?? ['core', 'white_label', 'source_code']),
            'issued_at'      => $now,
            'expires_at'     => $now + 86400 * 365,
        ], $ed25519SecretKeyHex);

        return [
            'status' => 'ok',
            'code'   => 201,
            'data'   => $minted,
        ];
    }
}
