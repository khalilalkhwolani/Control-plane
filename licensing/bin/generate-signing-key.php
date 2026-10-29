<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}
if (! extension_loaded('sodium')) {
    fwrite(STDERR, "The PHP sodium extension is required.\n");
    exit(1);
}

$options = getopt('', ['key-id:', 'output-dir:']);
$keyId = $options['key-id'] ?? null;
$outputDir = $options['output-dir'] ?? null;

if (! is_string($keyId) || preg_match('/^[A-Za-z0-9._-]{1,100}$/', $keyId) !== 1 || ! is_string($outputDir) || $outputDir === '') {
    fwrite(STDERR, "Usage: php generate-signing-key.php --key-id=vendor-2026-01 --output-dir=C:\\secure\\superpro-keys\n");
    exit(1);
}

if (! is_dir($outputDir) && ! mkdir($outputDir, 0700, true) && ! is_dir($outputDir)) {
    fwrite(STDERR, "Cannot create output directory.\n");
    exit(1);
}

$privatePath = rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$keyId.'.private.key';
$publicPath = rtrim($outputDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$keyId.'.public.key';

if (file_exists($privatePath) || file_exists($publicPath)) {
    fwrite(STDERR, "Refusing to overwrite an existing signing key.\n");
    exit(1);
}

$keyPair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keyPair);
$public = sodium_crypto_sign_publickey($keyPair);

if (file_put_contents($privatePath, base64_encode($secret).PHP_EOL, LOCK_EX) === false
    || file_put_contents($publicPath, base64_encode($public).PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Failed to write signing key files.\n");
    exit(1);
}

@chmod($privatePath, 0600);
@chmod($publicPath, 0644);

fwrite(STDOUT, "Signing key generated.\nKey ID: {$keyId}\nPublic key file: {$publicPath}\n");
fwrite(STDOUT, "Keep the private key outside the repository and customer devices.\n");
