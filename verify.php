<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

$token = $_GET['token'] ?? '';
$msg = ''; $type = 'error';

if ($token) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE verification_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $db->prepare("UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE id = ?")
           ->execute([$user['id']]);
        $msg = 'Email verified! You can now log in.';
        $type = 'success';
    } else {
        $msg = 'Invalid or expired verification link.';
    }
} else {
    $msg = 'No token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Email Verification – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
  body { background:#0f0f1a; font-family:'DM Sans',sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .box { background:rgba(255,255,255,.04); border:1px solid rgba(226,192,125,.2); border-radius:20px; padding:40px; text-align:center; max-width:380px; }
  .icon { margin-bottom:16px; }
  .icon .ui-icon { width:48px; height:48px; }
  h2 { color:<?= $type==='success' ? '#4ecb8d' : '#e74c6a' ?>; font-size:20px; margin-bottom:10px; }
  p { color:#8888aa; font-size:14px; margin-bottom:24px; }
  a { display:inline-block; padding:12px 28px; background:linear-gradient(135deg,#e2c07d,#c9a227); color:#0f0f1a; border-radius:10px; text-decoration:none; font-weight:700; }
</style>
</head>
<body>
<div class="box">
  <div class="icon"><?= uiIcon($type==='success' ? 'check-circle' : 'x-circle') ?></div>
  <h2><?= $type==='success' ? 'Verified!' : 'Verification Failed' ?></h2>
  <p><?= htmlspecialchars($msg) ?></p>
  <a href="<?= APP_URL ?>/index.php">Go to Login</a>
</div>
</body>
</html>
