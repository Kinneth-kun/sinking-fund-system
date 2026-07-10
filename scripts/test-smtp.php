<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\SMTP;

$env = [];
foreach (file(__DIR__ . '/../.env.production.local', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim(trim($value), "\"'");
}
foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD'] as $key) {
    $runtimeValue = getenv($key);
    if ($runtimeValue !== false && $runtimeValue !== '') {
        $env[$key] = $runtimeValue;
    }
}
if (empty($env['SMTP_USERNAME']) || empty($env['SMTP_PASSWORD'])) {
    fwrite(STDERR, "SMTP credentials are missing.\n");
    exit(1);
}

$smtp = new SMTP();
try {
    if (!$smtp->connect($env['SMTP_HOST'], (int) $env['SMTP_PORT'], 10)) {
        throw new RuntimeException('SMTP connection failed.');
    }
    if (!$smtp->hello(gethostname())) {
        throw new RuntimeException('SMTP greeting failed.');
    }
    if (!$smtp->startTLS() || !$smtp->hello(gethostname())) {
        throw new RuntimeException('SMTP TLS negotiation failed.');
    }
    if (!$smtp->authenticate($env['SMTP_USERNAME'], $env['SMTP_PASSWORD'])) {
        throw new RuntimeException('SMTP authentication failed.');
    }
    echo "Gmail SMTP authentication successful.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    $smtp->quit();
}
