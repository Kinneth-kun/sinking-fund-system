<?php
// =============================================
// DATABASE CONFIGURATION
// Edit these values to match your MySQL setup
// =============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sinking_funds_db');
define('DB_USER', 'root');         // Your MySQL username
define('DB_PASS', '06162004');             // Your MySQL password
define('DB_PORT', '3306');

// =============================================
// SMTP / MAILER CONFIGURATION (Gmail)
// =============================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'kinnethdaluag.pro@gmail.com');     // Your Gmail address
define('SMTP_PASSWORD', 'joeqpqbxpumccmjj');   // Gmail App Password (not your real password)
define('SMTP_FROM_EMAIL', 'kinnethdaluag.pro@gmail.com');
define('SMTP_FROM_NAME', 'Staycation Savings Fund');

// =============================================
// APP CONFIGURATION
// =============================================
define('APP_NAME', 'Staycation Savings');
define('APP_URL', 'http://localhost:8000');  // Change to your local URL
define('DAILY_RATE', 5);         // 5 pesos per day
define('START_DATE', '2026-01-01');

// =============================================
// SESSION CONFIG
// =============================================
define('SESSION_LIFETIME', 86400 * 7); // 7 days for remember me

// =============================================
// DATABASE CONNECTION (PDO)
// =============================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}