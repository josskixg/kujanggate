<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Self-contained Ed25519 Offline License Issuer & Verifier using PHP 8.2 ext-sodium.
 *
 * Token Format: KGATE-v1.<base64url(canonical_json_payload)>.<base64url(ed25519_detached_sig)>
 * Clients only need the 32-byte Public Key (64 hex chars) to verify licenses 100% offline.
 */
final class Ed25519LicenseService
{
    public const TOKEN_PREFIX = 'KGATE-v1';

    /**
     * Generate a fresh Ed25519 signing keypair (hex-encoded public & secret keys).
     *
     * @return array{public_key_hex: string, secret_key_hex: string, key_id: string}
     */
    public function generateKeypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicHex = sodium_bin2hex($publicKey);

        return [
            'public_key_hex' => $publicHex,
            'secret_key_hex' => sodium_bin2hex($secretKey),
            'key_id'         => 'kjk_' . substr(hash('sha256', $publicKey), 0, 12),
        ];
    }

    /**
     * Issue and sign an Ed25519 offline-verifiable license certificate.
     *
     * @param array<string, mixed> $claims
     * @return array{token: string, payload: array<string, mixed>, signature_hex: string, key_id: string}
     */
    public function issueLicense(array $claims, string $secretKeyHex): array
    {
        $secretKey = $this->decodeHexKey($secretKeyHex, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'secret');
        $publicKey = sodium_crypto_sign_publickey_from_secretkey($secretKey);
        $keyId = 'kjk_' . substr(hash('sha256', $publicKey), 0, 12);

        $now = (int) ($claims['issued_at'] ?? time());
        $expiresAt = isset($claims['expires_at']) ? (int) $claims['expires_at'] : ($now + 31536000);

        if ($expiresAt <= $now) {
            throw new InvalidArgumentException('License expires_at must be greater than issued_at.');
        }

        $maxSeats = (int) ($claims['max_seats'] ?? 1);
        if ($maxSeats < 1 || $maxSeats > 10000) {
            throw new InvalidArgumentException('max_seats must be between 1 and 10000.');
        }

        $payload = [
            'v'              => 1,
            'kid'            => $keyId,
            'lic_id'         => (string) ($claims['lic_id'] ?? ('LIC-' . strtoupper(bin2hex(random_bytes(4))))),
            'product_slug'   => (string) ($claims['product_slug'] ?? 'kujang-pro'),
            'customer_email' => strtolower(trim((string) ($claims['customer_email'] ?? ''))),
            'tier'           => (string) ($claims['tier'] ?? 'pro'),
            'max_seats'      => $maxSeats,
            'features'       => array_values(array_unique(array_map('strval', (array) ($claims['features'] ?? ['core', 'updates'])))),
            'hw_locks'       => array_values(array_unique(array_map('strval', (array) ($claims['hw_locks'] ?? [])))),
            'iat'            => $now,
            'exp'            => $expiresAt,
        ];

        if ($payload['customer_email'] === '' || !filter_var($payload['customer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer_email is required to mint a license.');
        }

        ksort($payload);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($jsonPayload, $secretKey);

        $token = sprintf(
            '%s.%s.%s',
            self::TOKEN_PREFIX,
            $this->base64UrlEncode($jsonPayload),
            $this->base64UrlEncode($signature)
        );

        return [
            'token'         => $token,
            'payload'       => $payload,
            'signature_hex' => sodium_bin2hex($signature),
            'key_id'        => $keyId,
        ];
    }

    /**
     * Verify a KGATE-v1 token offline using only the 32-byte Ed25519 Public Key.
     *
     * @return array{valid: bool, reason: string, payload: ?array<string, mixed>}
     */
    public function verifyOffline(
        string $token,
        string $publicKeyHex,
        ?string $requiredHardwareHash = null,
        ?int $currentTime = null
    ): array {
        $publicKey = $this->decodeHexKey($publicKeyHex, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, 'public');
        $parts = explode('.', trim($token));

        if (count($parts) !== 3 || $parts[0] !== self::TOKEN_PREFIX) {
            return ['valid' => false, 'reason' => 'ERR_MALFORMED_TOKEN_HEADER', 'payload' => null];
        }

        $rawPayload = $this->base64UrlDecode($parts[1]);
        $signature = $this->base64UrlDecode($parts[2]);

        if ($rawPayload === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return ['valid' => false, 'reason' => 'ERR_INVALID_ENCODING_OR_SIG_LENGTH', 'payload' => null];
        }

        if (!sodium_crypto_sign_verify_detached($signature, $rawPayload, $publicKey)) {
            return ['valid' => false, 'reason' => 'ERR_ED25519_SIGNATURE_MISMATCH', 'payload' => null];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($rawPayload, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['valid' => false, 'reason' => 'ERR_CORRUPT_PAYLOAD_JSON', 'payload' => null];
        }

        $now = $currentTime ?? time();
        $exp = (int) ($decoded['exp'] ?? 0);
        if ($exp <= $now) {
            return ['valid' => false, 'reason' => 'ERR_LICENSE_EXPIRED', 'payload' => $decoded];
        }

        if ($requiredHardwareHash !== null && $requiredHardwareHash !== '') {
            $locks = (array) ($decoded['hw_locks'] ?? []);
            if ($locks !== [] && !in_array($requiredHardwareHash, $locks, true)) {
                return ['valid' => false, 'reason' => 'ERR_HARDWARE_FINGERPRINT_MISMATCH', 'payload' => $decoded];
            }
        }

        return ['valid' => true, 'reason' => 'ED25519_VALID', 'payload' => $decoded];
    }

    private function decodeHexKey(string $hex, int $expectedBytes, string $label): string
    {
        $clean = strtolower(trim($hex));
        if (strlen($clean) !== $expectedBytes * 2 || !ctype_xdigit($clean)) {
            throw new InvalidArgumentException(sprintf('Invalid Ed25519 %s key hex length.', $label));
        }

        return sodium_hex2bin($clean);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): ?string
    {
        $remainder = strlen($encoded) % 4;
        if ($remainder > 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
