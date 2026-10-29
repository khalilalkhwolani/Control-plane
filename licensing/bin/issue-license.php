<?php

declare(strict_types=1);

use SuperPro\ControlPlane\Licensing\LicenseIssuer;

require dirname(__DIR__).'/src/Base64Url.php';
require dirname(__DIR__).'/src/LicenseIssuer.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$options = getopt('', [
    'private-key:',
    'key-id:',
    'installation-id:',
    'license-id::',
    'sequence:',
    'customer-reference:',
    'customer-name:',
    'edition:',
    'features:',
    'issued-at::',
    'expires-at::',
    'grace-until::',
    'output:',
]);

$required = ['private-key', 'key-id', 'installation-id', 'sequence', 'customer-reference', 'customer-name', 'edition', 'features', 'output'];
foreach ($required as $key) {
    if (! isset($options[$key]) || ! is_string($options[$key]) || $options[$key] === '') {
        fwrite(STDERR, "Missing required option --{$key}.\n");
        exit(1);
    }
}

try {
    $encodedSecret = trim((string) file_get_contents($options['private-key']));
    $secret = base64_decode($encodedSecret, true);
    if ($secret === false) {
        throw new RuntimeException('Private key file is not valid base64.');
    }

    $issuedAt = new DateTimeImmutable(is_string($options['issued-at'] ?? null) ? $options['issued-at'] : 'now');
    $expiresAt = isset($options['expires-at']) && is_string($options['expires-at']) && $options['expires-at'] !== ''
        ? new DateTimeImmutable($options['expires-at'])
        : null;
    $graceUntil = isset($options['grace-until']) && is_string($options['grace-until']) && $options['grace-until'] !== ''
        ? new DateTimeImmutable($options['grace-until'])
        : null;

    $issuer = new LicenseIssuer($options['key-id'], $secret);
    $document = $issuer->issue(
        installationId: $options['installation-id'],
        sequence: filter_var($options['sequence'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0,
        customerReference: $options['customer-reference'],
        customerName: $options['customer-name'],
        edition: $options['edition'],
        features: array_values(array_filter(array_map('trim', explode(',', $options['features'])))),
        issuedAt: $issuedAt,
        expiresAt: $expiresAt,
        graceUntil: $graceUntil,
        licenseId: isset($options['license-id']) && is_string($options['license-id']) && $options['license-id'] !== ''
            ? $options['license-id']
            : null,
    );

    $output = $options['output'];
    $outputDirectory = dirname($output);
    if (! is_dir($outputDirectory)) {
        throw new RuntimeException('Output directory does not exist.');
    }
    if (file_exists($output)) {
        throw new RuntimeException('Refusing to overwrite an existing license file.');
    }
    if (file_put_contents($output, $document, LOCK_EX) === false) {
        throw new RuntimeException('Could not write license file.');
    }

    fwrite(STDOUT, "License issued: {$output}\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
