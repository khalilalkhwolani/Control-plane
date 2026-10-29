<?php

declare(strict_types=1);

namespace SuperPro\ControlPlane\Licensing;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

final class LicenseIssuer
{
    public const ENVELOPE_FORMAT = 'superpro-license-envelope/v1';
    public const PAYLOAD_SCHEMA = 'superpro-license/v1';

    public function __construct(
        private readonly string $keyId,
        private readonly string $secretKey,
    ) {
        if (! extension_loaded('sodium')) {
            throw new InvalidArgumentException('The PHP sodium extension is required.');
        }
        if ($keyId === '' || preg_match('/^[A-Za-z0-9._-]{1,100}$/', $keyId) !== 1) {
            throw new InvalidArgumentException('Invalid signing key id.');
        }
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Invalid Ed25519 secret key length.');
        }
    }

    /**
     * @param list<string> $features
     * @throws JsonException
     */
    public function issue(
        string $installationId,
        int $sequence,
        string $customerReference,
        string $customerName,
        string $edition,
        array $features,
        DateTimeImmutable $issuedAt,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $graceUntil,
        ?string $licenseId = null,
    ): string {
        $licenseId ??= self::uuidV4();
        $this->assertUuid($licenseId, 'license id');
        $this->assertUuid($installationId, 'installation id');

        if ($sequence < 1) {
            throw new InvalidArgumentException('Sequence must be at least 1.');
        }
        if ($customerReference === '' || strlen($customerReference) > 100) {
            throw new InvalidArgumentException('Customer reference is required and must be at most 100 bytes.');
        }
        if ($customerName === '' || mb_strlen($customerName) > 200) {
            throw new InvalidArgumentException('Customer name is required and must be at most 200 characters.');
        }
        if (preg_match('/^[a-z][a-z0-9._-]*$/', $edition) !== 1) {
            throw new InvalidArgumentException('Invalid edition identifier.');
        }
        if ($expiresAt !== null && $expiresAt < $issuedAt) {
            throw new InvalidArgumentException('Expiry cannot be earlier than issue time.');
        }
        if ($graceUntil !== null && ($expiresAt === null || $graceUntil < $expiresAt)) {
            throw new InvalidArgumentException('Grace time requires an expiry and cannot be earlier than it.');
        }

        $features = array_values(array_unique($features));
        sort($features, SORT_STRING);
        foreach ($features as $feature) {
            if (preg_match('/^[a-z][a-z0-9._-]{1,99}$/', $feature) !== 1) {
                throw new InvalidArgumentException("Invalid feature identifier [{$feature}].");
            }
        }

        $payload = [
            'schema' => self::PAYLOAD_SCHEMA,
            'license_id' => strtolower($licenseId),
            'installation_id' => strtolower($installationId),
            'sequence' => $sequence,
            'customer' => [
                'reference' => trim($customerReference),
                'name' => trim($customerName),
            ],
            'edition' => $edition,
            'features' => $features,
            'issued_at' => self::utc($issuedAt),
            'expires_at' => $expiresAt === null ? null : self::utc($expiresAt),
            'grace_until' => $graceUntil === null ? null : self::utc($graceUntil),
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($payloadJson, $this->secretKey);

        return json_encode([
            'format' => self::ENVELOPE_FORMAT,
            'key_id' => $this->keyId,
            'payload' => Base64Url::encode($payloadJson),
            'signature' => Base64Url::encode($signature),
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private static function utc(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }

    private function assertUuid(string $value, string $label): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }
    }
}
