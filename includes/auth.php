<?php
require_once __DIR__ . '/../config/config.php';

if (SESSION_DRIVER === 'database') {
    require_once __DIR__ . '/DatabaseSessionHandler.php';
    session_set_save_handler(new DatabaseSessionHandler(getDB(), SESSION_LIFETIME), true);
} else {
    $sessionPath = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    session_save_path($sessionPath);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => str_starts_with(APP_URL, 'https://'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
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
        setcookie('remember_token', $token, [
            'expires' => time() + SESSION_LIFETIME,
            'path' => '/',
            'secure' => str_starts_with(APP_URL, 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function logoutUser(): void {
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $db->prepare("DELETE FROM user_sessions WHERE session_token = ?")
           ->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => str_starts_with(APP_URL, 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
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
