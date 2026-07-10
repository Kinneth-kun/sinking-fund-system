<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

// Block if admin already exists
$db = getDB();
$adminExists = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($adminExists > 0) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!str_ends_with($email, '@gmail.com')) {
        $error = 'Only Gmail addresses are allowed.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $token = generateToken();
        $hash  = hashPassword($password);
        $db->prepare("INSERT INTO users (name, username, password, verification_token) VALUES (?, ?, ?, ?)")
           ->execute([$name, $email, $hash, $token]);

        if (sendVerificationEmail($email, $name, $token)) {
            $success = "Account created! A verification email has been sent to {$email}. Please verify before logging in.";
        } elseif (APP_ENV === 'local' && APP_DEBUG) {
            $db->prepare("UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE username = ?")->execute([$email]);
            $success = "Account created! (Email sending failed – auto-verified for local use.) You can now log in.";
        } else {
            $error = 'Account created, but the verification email could not be sent. Check the SMTP environment variables before continuing.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Admin – <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root { --gold:#e2c07d; --gold2:#c9a227; --dark:#0f0f1a; --dark2:#1a1a2e; --card:rgba(255,255,255,0.04); --border:rgba(226,192,125,0.2); --text:#e8e8f0; --muted:#8888aa; --error:#e74c6a; --success:#4ecb8d; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { min-height:100vh; background:var(--dark); font-family:'DM Sans',sans-serif; display:flex; align-items:center; justify-content:center; }
  body::before { content:''; position:fixed; width:500px; height:500px; background:var(--gold); border-radius:50%; filter:blur(100px); opacity:.1; top:-150px; right:-100px; }
  .wrap { width:100%; max-width:440px; padding:20px; animation:fadeUp .5s ease both; }
  @keyframes fadeUp { from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)} }
  .logo { text-align:center; margin-bottom:28px; }
  .logo h1 { font-family:'Playfair Display',serif; font-size:26px; color:var(--gold); }
  .logo p  { color:var(--muted); font-size:13px; margin-top:4px; }
  .card { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:32px; backdrop-filter:blur(20px); }
  h2 { font-family:'Playfair Display',serif; color:var(--gold); font-size:20px; margin-bottom:24px; }
  .alert { padding:12px 16px; border-radius:10px; font-size:13.5px; margin-bottom:18px; }
  .alert-error   { background:rgba(231,76,106,.12); color:var(--error);   border:1px solid rgba(231,76,106,.3); }
  .alert-success { background:rgba(78,203,141,.12); color:var(--success); border:1px solid rgba(78,203,141,.3); }
  label { display:block; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
  .field { margin-bottom:18px; }
  input[type="text"],input[type="email"],input[type="password"] {
    width:100%; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1);
    border-radius:10px; color:var(--text); font-family:'DM Sans',sans-serif; font-size:14px;
    padding:12px 14px; outline:none; transition:border-color .2s;
  }
  input:focus { border-color:var(--gold); background:rgba(226,192,125,.05); }
  .btn { width:100%; padding:13px; border:none; border-radius:10px; background:linear-gradient(135deg,var(--gold),var(--gold2)); color:var(--dark); font-family:'DM Sans',sans-serif; font-size:15px; font-weight:700; cursor:pointer; }
  .btn:hover { opacity:.9; }
  .back { text-align:center; margin-top:16px; font-size:13px; color:var(--muted); }
  .back a { color:var(--gold); text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div style="font-size:48px;margin-bottom:8px">💰</div>
    <h1><?= APP_NAME ?></h1>
    <p>Initial Admin Setup</p>
  </div>
  <div class="card">
    <h2>Create Admin Account</h2>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?> <br><a href="index.php" style="color:var(--gold)">Go to Login →</a></div><?php else: ?>
    <form method="POST">
      <div class="field"><label>Full Name</label><input type="text" name="name" required placeholder="Your Name"></div>
      <div class="field"><label>Gmail Address</label><input type="email" name="email" required placeholder="you@gmail.com"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required placeholder="Min 8 characters"></div>
      <div class="field"><label>Confirm Password</label><input type="password" name="confirm" required placeholder="Repeat password"></div>
      <button class="btn" type="submit">🚀 Create Account & Send Verification</button>
    </form>
    <?php endif; ?>
  </div>
  <p class="back"><a href="index.php">← Back to Login</a></p>
</div>
</body>
</html>
