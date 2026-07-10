<?php

/**
 * Application configuration.
 *
 * Production credentials must be configured as environment variables. The
 * local defaults contain no secrets and are suitable for a standard PostgreSQL
 * development installation.
 */
function env(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('DATABASE_URL', env('DATABASE_URL', ''));
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'sinking_funds'));
define('DB_USER', env('DB_USER', 'postgres'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_PORT', env('DB_PORT', '5432'));

define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', '587'));
define('SMTP_SECURE', env('SMTP_SECURE', 'tls'));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', SMTP_USERNAME));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Staycation Savings Fund'));

define('APP_NAME', env('APP_NAME', 'Staycation Savings'));
define('APP_ENV', env('APP_ENV', env('VERCEL') ? 'production' : 'local'));

$forwardedProto = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0];
$appScheme = $forwardedProto ?: ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$appHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('APP_URL', rtrim(env('APP_URL', $appScheme . '://' . $appHost), '/'));
define('DAILY_RATE', (int) env('DAILY_RATE', '5'));
define('START_DATE', env('START_DATE', '2026-01-01'));
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', (string) (86400 * 7)));
define('SESSION_DRIVER', env('SESSION_DRIVER', env('VERCEL') ? 'database' : 'files'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $user = DB_USER;
    $password = DB_PASS;
    if (DATABASE_URL !== '') {
        $parts = parse_url(DATABASE_URL);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL is not a valid PostgreSQL connection URL.');
        }
        $host = $parts['host'];
        $port = $parts['port'] ?? 5432;
        $database = ltrim($parts['path'], '/');
        $user = urldecode($parts['user'] ?? '');
        $password = urldecode($parts['pass'] ?? '');
        parse_str($parts['query'] ?? '', $query);
        $sslmode = $query['sslmode'] ?? 'require';
    } else {
        $host = DB_HOST;
        $port = DB_PORT;
        $database = DB_NAME;
        $sslmode = env('DB_SSLMODE', 'prefer');
    }
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, $port, $database, $sslmode);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];

    try {
        $pdo = new PDO($dsn, $user, $password, $options);
        return $pdo;
    } catch (PDOException $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('The database is temporarily unavailable. Check the deployment configuration.');
    }
}
