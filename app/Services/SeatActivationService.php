<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Hardware Fingerprint Seat-Binding & Anti-Clone Activation Engine.
 *
 * Computes deterministic SHA-256 hardware fingerprints, enforces max_seats quota,
 * handles idempotent seat heartbeats, and validates HMAC-SHA256 payment webhooks.
 */
final class SeatActivationService
{
    /**
     * Compute a deterministic SHA-256 hardware/domain fingerprint.
     *
     * @param array{domain?: string, machine_id?: string, cpu_arch?: string, os_family?: string} $telemetry
     */
    public function computeHardwareFingerprint(array $telemetry): string
    {
        $domain = strtolower(trim((string) ($telemetry['domain'] ?? 'localhost')));
        $machineId = strtolower(trim((string) ($telemetry['machine_id'] ?? '')));
        $arch = strtolower(trim((string) ($telemetry['cpu_arch'] ?? 'x86_64')));
        $os = strtolower(trim((string) ($telemetry['os_family'] ?? 'linux')));

        if ($machineId === '') {
            throw new InvalidArgumentException('machine_id is required to compute a hardware fingerprint.');
        }

        $canonical = implode('|', ['v1', $domain, $machineId, $arch, $os]);

        return 'hw_' . substr(hash('sha256', $canonical), 0, 32);
    }

    /**
     * Activate or heartbeat a hardware seat against an existing license state.
     *
     * @param array<string, array{fingerprint: string, label: string, activated_at: int, last_seen_at: int}> $activeSeats
     * @return array{
     *   allowed: bool,
     *   status: string,
     *   seats_used: int,
     *   max_seats: int,
     *   active_seats: array<string, array{fingerprint: string, label: string, activated_at: int, last_seen_at: int}>
     * }
     */
    public function activateSeat(
        string $licenseId,
        int $maxSeats,
        array $activeSeats,
        string $fingerprint,
        string $deviceLabel = 'prod-node-01',
        ?int $now = null
    ): array {
        $timestamp = $now ?? time();

        if (isset($activeSeats[$fingerprint])) {
            $activeSeats[$fingerprint]['last_seen_at'] = $timestamp;
            return [
                'allowed'      => true,
                'status'       => 'SEAT_HEARTBEAT_RENEWED',
                'seats_used'   => count($activeSeats),
                'max_seats'    => $maxSeats,
                'active_seats' => $activeSeats,
            ];
        }

        if (count($activeSeats) >= $maxSeats) {
            return [
                'allowed'      => false,
                'status'       => 'ERR_SEAT_LIMIT_EXHAUSTED',
                'seats_used'   => count($activeSeats),
                'max_seats'    => $maxSeats,
                'active_seats' => $activeSeats,
            ];
        }

        $activeSeats[$fingerprint] = [
            'fingerprint'  => $fingerprint,
            'label'        => $deviceLabel,
            'activated_at' => $timestamp,
            'last_seen_at' => $timestamp,
        ];

        return [
            'allowed'      => true,
            'status'       => 'SEAT_BOUND_NEW',
            'seats_used'   => count($activeSeats),
            'max_seats'    => $maxSeats,
            'active_seats' => $activeSeats,
        ];
    }

    /**
     * Deactivate / revoke a bound hardware seat so the customer can migrate machines.
     *
     * @param array<string, array{fingerprint: string, label: string, activated_at: int, last_seen_at: int}> $activeSeats
     * @return array{revoked: bool, status: string, seats_used: int, active_seats: array<string, mixed>}
     */
    public function revokeSeat(array $activeSeats, string $fingerprint): array
    {
        if (!isset($activeSeats[$fingerprint])) {
            return [
                'revoked'      => false,
                'status'       => 'ERR_SEAT_NOT_FOUND',
                'seats_used'   => count($activeSeats),
                'active_seats' => $activeSeats,
            ];
        }

        unset($activeSeats[$fingerprint]);

        return [
            'revoked'      => true,
            'status'       => 'SEAT_REVOKED_OK',
            'seats_used'   => count($activeSeats),
            'active_seats' => $activeSeats,
        ];
    }

    /**
     * Sign and verify incoming order webhooks (Lynk.id / Midtrans / Gumroad / LemonSqueezy bridge)
     * with timestamp replay window protection (default 300s).
     */
    public function signWebhookPayload(string $rawBody, int $timestamp, string $webhookSecret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);
    }

    public function verifyWebhookSignature(
        string $rawBody,
        int $timestamp,
        string $providedSignature,
        string $webhookSecret,
        int $toleranceSeconds = 300,
        ?int $currentTime = null
    ): bool {
        $now = $currentTime ?? time();
        if (abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = $this->signWebhookPayload($rawBody, $timestamp, $webhookSecret);

        return hash_equals($expected, strtolower(trim($providedSignature)));
    }
}
