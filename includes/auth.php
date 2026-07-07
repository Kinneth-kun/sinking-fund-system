<?php
require_once __DIR__ . '/../config/config.php';

$sessionPath = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);

session_start();

function isLoggedIn(): bool {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    // Check remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT us.user_id, u.name, u.username, u.role 
            FROM user_sessions us
            JOIN users u ON u.id = us.user_id
            WHERE us.session_token = ? AND us.expires_at > NOW()
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email']= $user['username'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }
    }
    return false;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? '',
    ];
}

function loginUser(array $user, bool $remember = false): void {
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['username'];
    $_SESSION['user_role']  = $user['role'];

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $db = getDB();
        $db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)")
           ->execute([$user['id'], $token, $expires]);
        setcookie('remember_token', $token, time() + SESSION_LIFETIME, '/', '', false, true);
    }
}

function logoutUser(): void {
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $db->prepare("DELETE FROM user_sessions WHERE session_token = ?")
           ->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    session_destroy();
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// CSRF protection
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
