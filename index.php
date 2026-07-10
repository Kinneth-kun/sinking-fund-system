<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$step    = $_SESSION['login_step'] ?? 'credentials';

// ── STEP 1: Email + Password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'credentials') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@gmail.com')) {
            $error = 'Please use a valid Gmail address.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && verifyPassword($password, $user['password'])) {
                // Generate 6-digit OTP
                $code      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

                // Invalidate old codes
                $db->prepare("UPDATE otp_codes SET used = TRUE WHERE user_id = ? AND used = FALSE")->execute([$user['id']]);

                // Store new OTP
                $db->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)")
                   ->execute([$user['id'], $code, $expiresAt]);

                // Send OTP to the admin Gmail
                $sent = sendOtpEmail($user['username'], $user['name'], $code);

                if ($sent) {
                    $_SESSION['login_step']       = 'otp';
                    $_SESSION['pending_user_id']  = $user['id'];
                    $_SESSION['pending_remember'] = $remember;
                    $step = 'otp';
                    $success = 'A 6-digit code has been sent to your Gmail. Enter it below.';
                } elseif (APP_ENV === 'local' && APP_DEBUG) {
                    $_SESSION['login_step']       = 'otp';
                    $_SESSION['pending_user_id']  = $user['id'];
                    $_SESSION['pending_remember'] = $remember;
                    $step = 'otp';
                    $success = "Email unavailable. Dev code: <strong style='color:#e2c07d;font-size:22px;letter-spacing:6px;font-family:monospace'>{$code}</strong>";
                } else {
                    // Do not show the code-entry screen when no code was delivered.
                    $db->prepare("UPDATE otp_codes SET used = TRUE WHERE user_id = ? AND used = FALSE")->execute([$user['id']]);
                    unset($_SESSION['login_step'], $_SESSION['pending_user_id'], $_SESSION['pending_remember']);
                    $step = 'credentials';
                    $error = 'We could not send your login code. No code was delivered. Please try again later or contact the administrator.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

// ── STEP 2: OTP Verification ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'otp') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        // Normalize: strip spaces, pad leading zeros back to 6 digits
        $enteredCode = str_pad(trim(preg_replace('/\D/', '', $_POST['otp_code'] ?? '')), 6, '0', STR_PAD_LEFT);
        $pendingId   = $_SESSION['pending_user_id'] ?? null;

        if (!$pendingId) {
            $error = 'Session expired. Please log in again.';
            $step  = 'credentials';
            unset($_SESSION['login_step'], $_SESSION['pending_user_id']);
        } else {
            $db = getDB();

            // Use PHP time for a consistent expiry comparison.
            $nowTs = date('Y-m-d H:i:s');

            // Fetch the most recent unused code for this user
            $stmt = $db->prepare("
                SELECT * FROM otp_codes
                WHERE user_id = ? AND used = FALSE
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$pendingId]);
            $otp = $stmt->fetch();

            // Compare code and expiry in PHP.
            $codeMatches  = $otp && hash_equals($otp['code'], $enteredCode);
            $notExpired   = $otp && ($otp['expires_at'] > $nowTs);

            if ($codeMatches && $notExpired) {
                // Mark OTP as used
                $db->prepare("UPDATE otp_codes SET used = TRUE WHERE id = ?")->execute([$otp['id']]);

                // Fetch user
                $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $userStmt->execute([$pendingId]);
                $user = $userStmt->fetch();

                // Auto-verify email if not yet verified
                if (!$user['email_verified_at']) {
                    $db->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")->execute([$user['id']]);
                }

                $remember = $_SESSION['pending_remember'] ?? false;
                unset($_SESSION['login_step'], $_SESSION['pending_user_id'], $_SESSION['pending_remember']);

                loginUser($user, $remember);
                header('Location: ' . APP_URL . '/pages/dashboard.php');
                exit;
            } else {
                if (!$otp) {
                    $error = 'No active code found. Please request a new one.';
                } elseif (!$notExpired) {
                    $error = 'This code has expired. Please request a new one.';
                } else {
                    $error = 'Incorrect code. Please check your Gmail and try again.';
                }
                $step = 'otp';
            }
        }
    }
}

// ── RESEND OTP ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'resend') {
    $pendingId = $_SESSION['pending_user_id'] ?? null;
    if ($pendingId) {
        $db = getDB();
        $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$pendingId]);
        $user = $userStmt->fetch();

        $code      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600);
        $db->prepare("UPDATE otp_codes SET used = TRUE WHERE user_id = ? AND used = FALSE")->execute([$pendingId]);
        $db->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)")->execute([$pendingId, $code, $expiresAt]);

        $sent = sendOtpEmail($user['username'], $user['name'], $code);
        $step = 'otp';
        $_SESSION['login_step'] = 'otp';
        if ($sent) {
            $success = 'A new code has been sent to your Gmail.';
        } elseif (APP_ENV === 'local' && APP_DEBUG) {
            $success = "Email unavailable. Dev code: <strong style='color:#e2c07d;font-size:22px;letter-spacing:6px;font-family:monospace'>{$code}</strong>";
        } else {
            $error = 'The login email could not be sent. Please try again later.';
        }
    }
}

// ── CANCEL back to step 1 ──
if (isset($_GET['cancel'])) {
    unset($_SESSION['login_step'], $_SESSION['pending_user_id'], $_SESSION['pending_remember']);
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$step = $_SESSION['login_step'] ?? 'credentials';
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#e2c07d">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Staycation Savings">
<title><?= APP_NAME ?> – Login</title>
<link rel="manifest" href="<?= APP_URL ?>/manifest.webmanifest">
<link rel="icon" href="<?= APP_URL ?>/assets/icons/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/icons/icon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --gold:#e2c07d; --gold2:#c9a227; --dark:#0f0f1a; --dark2:#1a1a2e;
    --card:rgba(255,255,255,0.04); --border:rgba(226,192,125,0.2);
    --text:#e8e8f0; --muted:#8888aa; --error:#e74c6a; --success:#4ecb8d;
  }
  * { box-sizing:border-box; margin:0; padding:0; }
  body {
    min-height:100vh; min-height:100dvh; background:var(--dark);
    font-family:'DM Sans',sans-serif;
    display:flex; align-items:flex-start; justify-content:center;
    overflow-x:hidden; overflow-y:auto; position:relative;
    padding:clamp(14px, 3vh, 32px) 0;
  }
  body::before {
    content:''; position:fixed; width:500px; height:500px;
    background:var(--gold); border-radius:50%; filter:blur(100px);
    opacity:.1; top:-150px; left:-100px; pointer-events:none;
  }
  body::after {
    content:''; position:fixed; width:400px; height:400px;
    background:#4a90d9; border-radius:50%; filter:blur(90px);
    opacity:.08; bottom:-80px; right:-80px; pointer-events:none;
  }
  .wrap { width:100%; max-width:420px; padding:20px; margin:auto 0; position:relative; z-index:1; }

  .steps {
    display:flex; align-items:center; justify-content:center;
    gap:8px; margin-bottom:28px;
  }
  .step-dot {
    width:34px; height:34px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:13px; font-weight:700;
    border:2px solid var(--border); color:var(--muted);
    background:transparent; transition:all .3s;
  }
  .step-dot.active { border-color:var(--gold); color:var(--gold); background:rgba(226,192,125,.1); }
  .step-dot.done   { border-color:var(--success); color:var(--success); background:rgba(78,203,141,.1); }
  .step-line { flex:1; max-width:50px; height:2px; background:var(--border); border-radius:2px; transition:background .4s; }
  .step-line.done { background:var(--success); }
  .step-label { font-size:10px; color:var(--muted); text-align:center; margin-top:3px; }

  .logo { text-align:center; margin-bottom:28px; animation:fadeUp .5s ease both; }
  .logo-icon { font-size:48px; display:block; margin-bottom:10px; animation:bounce 2.5s ease-in-out infinite; }
  @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
  .logo h1 { font-family:'Playfair Display',serif; font-size:26px; color:var(--gold); }
  .logo p  { color:var(--muted); font-size:12px; margin-top:4px; }

  .card {
    background:var(--card); border:1px solid var(--border);
    border-radius:20px; padding:32px;
    backdrop-filter:blur(20px);
    box-shadow:0 20px 60px rgba(0,0,0,.4);
    animation:fadeUp .5s ease .1s both;
  }
  @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }

  .card-title { font-family:'Playfair Display',serif; font-size:19px; color:var(--gold); margin-bottom:5px; }
  .card-sub   { font-size:12.5px; color:var(--muted); margin-bottom:22px; line-height:1.6; }

  .alert {
    padding:11px 14px; border-radius:10px; font-size:13px;
    margin-bottom:18px; display:flex; align-items:flex-start; gap:8px; line-height:1.5;
  }
  .alert-error   { background:rgba(231,76,106,.1); color:var(--error);   border:1px solid rgba(231,76,106,.25); }
  .alert-success { background:rgba(78,203,141,.1); color:var(--success); border:1px solid rgba(78,203,141,.25); }

  label { display:block; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:7px; }
  .field { margin-bottom:18px; }
  .input-wrap { position:relative; }
  .input-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:15px; pointer-events:none; }
  input[type="email"],input[type="password"] {
    width:100%; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1);
    border-radius:10px; color:var(--text); font-family:'DM Sans',sans-serif;
    font-size:14px; padding:12px 14px 12px 40px; outline:none;
    transition:border-color .2s, background .2s;
  }
  input:focus { border-color:var(--gold); background:rgba(226,192,125,.05); }

  .otp-input {
    width:100%; text-align:center; letter-spacing:16px; font-size:30px;
    font-weight:700; font-family:monospace; padding:18px 14px;
    background:rgba(226,192,125,.06); border:2px solid var(--gold);
    border-radius:14px; color:var(--gold); outline:none;
    transition:box-shadow .2s, background .2s;
  }
  .otp-input:focus { background:rgba(226,192,125,.1); box-shadow:0 0 0 3px rgba(226,192,125,.2); }
  .otp-input::placeholder { color:rgba(226,192,125,.3); letter-spacing:8px; font-size:22px; }

  .countdown { text-align:center; font-size:12px; color:var(--muted); margin:10px 0 18px; }
  .countdown span { color:var(--gold); font-weight:700; font-variant-numeric:tabular-nums; }

  .remember-row { display:flex; align-items:center; gap:8px; margin-bottom:20px; }
  .remember-row label { text-transform:none; letter-spacing:0; font-size:13px; margin:0; cursor:pointer; }
  input[type="checkbox"] { accent-color:var(--gold); width:15px; height:15px; }

  .btn-primary {
    width:100%; padding:13px; border:none; border-radius:10px;
    background:linear-gradient(135deg,var(--gold),var(--gold2));
    color:var(--dark); font-family:'DM Sans',sans-serif;
    font-size:15px; font-weight:700; cursor:pointer;
    transition:opacity .2s, transform .1s; letter-spacing:.3px;
  }
  .btn-primary:hover  { opacity:.9; transform:translateY(-1px); }
  .btn-primary:active { transform:translateY(0); }

  .btn-ghost {
    background:none; border:none; color:var(--muted); font-size:13px;
    cursor:pointer; padding:4px 0; font-family:'DM Sans',sans-serif;
    transition:color .2s; text-decoration:none; display:inline-block;
  }
  .btn-ghost:hover { color:var(--gold); }
  .btn-ghost:disabled { opacity:.4; cursor:not-allowed; }

  .action-row { display:flex; align-items:center; justify-content:space-between; margin-top:16px; }
  .hint { font-size:11px; color:var(--muted); margin-top:-12px; margin-bottom:16px; }

  .clock { text-align:center; margin-top:22px; color:var(--muted); font-size:11.5px; letter-spacing:.5px; animation:fadeUp .5s ease .2s both; }
  .clock span { color:var(--gold); }
  .pwa-install-btn,.offline-badge{position:fixed;left:max(16px,env(safe-area-inset-left));bottom:max(16px,env(safe-area-inset-bottom));z-index:3000;border-radius:12px;box-shadow:0 14px 34px rgba(0,0,0,.45)}
  .pwa-install-btn{border:0;background:linear-gradient(135deg,var(--gold),var(--gold2));color:var(--dark);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:800;padding:11px 15px;cursor:pointer}
  .pwa-install-btn[hidden],.offline-badge[hidden]{display:none!important}
  .offline-badge{background:rgba(231,76,106,.95);color:#fff;border:1px solid rgba(255,255,255,.18);font-size:12px;font-weight:700;padding:9px 12px}

  /* Keep the full form visible on common laptop screens while preserving
     vertical scrolling for smaller displays and browser UI overlays. */
  @media (min-width:600px) and (max-height:760px) {
    body { padding:8px 0; }
    .wrap { padding:10px 20px; }
    .logo { margin-bottom:14px; }
    .logo-icon { font-size:38px; margin-bottom:4px; }
    .logo h1 { font-size:24px; }
    .steps { margin-bottom:16px; }
    .step-dot { width:32px; height:32px; }
    .card { padding:24px 32px; }
    .card-sub { margin-bottom:16px; }
    .field { margin-bottom:14px; }
    .remember-row { margin-bottom:16px; }
    .clock { margin-top:12px; }
  }

  @media (max-width:480px) {
    body { padding:0; }
    .wrap { padding:20px 16px 28px; }
    .card { padding:24px 20px; border-radius:16px; }
    .logo { margin-bottom:20px; }
    .steps { margin-bottom:20px; }
  }
</style>
</head>
<body>
<div class="wrap">

  <div class="logo">
    <span class="logo-icon">💰</span>
    <h1><?= APP_NAME ?></h1>
    <p>Savings 2k26 · Secure Login</p>
  </div>

  <!-- Step indicator -->
  <div class="steps">
    <div>
      <div class="step-dot <?= $step==='credentials' ? 'active' : 'done' ?>"><?= $step==='credentials' ? '1' : '✓' ?></div>
      <div class="step-label">Password</div>
    </div>
    <div class="step-line <?= $step==='otp' ? 'done' : '' ?>"></div>
    <div>
      <div class="step-dot <?= $step==='otp' ? 'active' : '' ?>">2</div>
      <div class="step-label">Gmail Code</div>
    </div>
  </div>

  <div class="card">

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>

    <?php if ($step === 'credentials'): ?>
    <!-- ══ STEP 1 ══ -->
    <div class="card-title">Welcome back</div>
    <div class="card-sub">Enter your Gmail and password. A 6-digit code will be sent to your inbox.</div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="form" value="credentials">

      <div class="field">
        <label>Gmail Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉</span>
          <input type="email" name="email" placeholder="yourname@gmail.com" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
        </div>
      </div>

      <div class="field">
        <label>Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" name="password" placeholder="Your password" required autocomplete="current-password">
        </div>
      </div>

      <div class="remember-row">
        <input type="checkbox" name="remember" id="remember">
        <label for="remember">Keep me logged in for 7 days</label>
      </div>

      <button type="submit" class="btn-primary">📨 Send Login Code to Gmail</button>
    </form>

    <div style="text-align:center;margin-top:18px">
      <a href="setup.php" class="btn-ghost">First time? Create admin account →</a>
    </div>

    <?php else: ?>
    <!-- ══ STEP 2 ══ -->
    <div class="card-title">Enter your Gmail code</div>
    <div class="card-sub">A 6-digit code was sent to your Gmail inbox. It expires in 10 minutes. The code auto-submits when all 6 digits are entered.</div>

    <form method="POST" id="otp-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="form" value="otp">

      <div class="field">
        <label>6-Digit Code</label>
        <input type="text" name="otp_code" class="otp-input"
               placeholder="······" maxlength="6"
               inputmode="numeric" pattern="[0-9]{6}"
               autocomplete="one-time-code" required autofocus
               oninput="this.value=this.value.replace(/\D/g,'');
                        if(this.value.length===6) document.getElementById('otp-form').submit();">
      </div>

      <div class="countdown">Expires in <span id="timer">10:00</span></div>

      <button type="submit" class="btn-primary">🔓 Verify & Sign In</button>
    </form>

    <div class="action-row">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="form" value="resend">
        <button type="submit" class="btn-ghost" id="resend-btn" disabled>🔄 Resend code</button>
      </form>
      <a href="?cancel=1" class="btn-ghost">← Different account</a>
    </div>

    <script>
    let secs = 600;
    const timerEl   = document.getElementById('timer');
    const resendBtn = document.getElementById('resend-btn');
    const interval  = setInterval(() => {
      secs--;
      const m = String(Math.floor(secs / 60)).padStart(2,'0');
      const s = String(secs % 60).padStart(2,'0');
      timerEl.textContent = m + ':' + s;
      if (secs <= 0) {
        clearInterval(interval);
        timerEl.textContent = 'Expired';
        timerEl.style.color = '#e74c6a';
        resendBtn.disabled = false;
      }
      if (secs === 540) resendBtn.disabled = false; // allow resend after 1 min
    }, 1000);
    </script>
    <?php endif; ?>

  </div>

  <div class="clock">
    <span id="live-time"></span> &nbsp;·&nbsp; <span id="live-date"></span>
  </div>
</div>

<script>
function tick() {
  const now = new Date();
  document.getElementById('live-time').textContent =
    now.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
  document.getElementById('live-date').textContent =
    now.toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
}
tick(); setInterval(tick, 1000);
</script>
<script src="<?= APP_URL ?>/assets/js/pwa.js"></script>
</body>
</html>
