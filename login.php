<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    $r = $_SESSION['role'] ?? '';
    if ($r === 'admin')   { header('Location: admin.php'); exit; }
    if ($r === 'checker') { header('Location: checker-dashboard.php'); exit; }
    header('Location: student-dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username      = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';
    $captcha_input = trim($_POST['captcha'] ?? '');
    $captcha_ans   = (int)($_SESSION['captcha_login_answer'] ?? -1);

    // Invalidate used captcha so it must reload
    unset($_SESSION['captcha_login_answer']);

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } elseif ((int)$captcha_input !== $captcha_ans) {
        $error = 'Incorrect answer to the security check. Please try again.';
    } else {
        require __DIR__ . '/db.php';
        try {
            $stmt = $pdo->prepare('SELECT id, password_hash, role, full_name FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            $user = false;
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Set 90-day persistent login token
            try {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO remember_tokens (user_id, token, expires_at)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 90 DAY))
                     ON DUPLICATE KEY UPDATE token=VALUES(token), expires_at=VALUES(expires_at)'
                )->execute([$user['id'], $token]);
                setcookie('remember_token', $token, [
                    'expires'  => time() + 90 * 24 * 3600,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } catch (Throwable $e) {}

            if ($user['role'] === 'admin')   { header('Location: admin.php'); exit; }
            if ($user['role'] === 'checker') { header('Location: checker-dashboard.php'); exit; }
            header('Location: student-dashboard.php'); exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$err_msg = match($_GET['e'] ?? '') {
    'access' => 'You do not have permission to access that page.',
    'login'  => 'Please log in to continue.',
    default  => '',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0QT95F62SG"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-0QT95F62SG');</script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Login - 3S English Academy</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:Arial,sans-serif; background:#f5f5f5; display:flex; flex-direction:column; }
    .page-main { flex:1; display:flex; justify-content:center; align-items:center; padding:20px; }
    @media(max-width:480px){ .page-main{ padding:16px 10px; align-items:flex-start; padding-top:24px; } }
    .card { background:#fff; padding:28px 24px; border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,0.1); width:100%; max-width:380px; }
    .card h2 { margin:0 0 20px; color:#111827; font-size:1.5rem; }
    .field { margin-bottom:14px; }
    .field label { display:block; font-weight:600; margin-bottom:6px; font-size:0.95rem; color:#374151; }
    .field input { width:100%; padding:10px 12px; font-size:1rem; border:1px solid #d1d5db; border-radius:8px; transition:border-color 0.2s; }
    .field input:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .btn { width:100%; padding:12px; font-size:1rem; font-weight:600; background:#007BFF; color:#fff; border:none; border-radius:10px; cursor:pointer; transition:background 0.2s; margin-top:6px; }
    .btn:hover { background:#0056b3; }
    .alert-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:10px 12px; border-radius:8px; margin-bottom:14px; font-size:0.9rem; }
    .alert-info  { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:10px 12px; border-radius:8px; margin-bottom:14px; font-size:0.9rem; }
    @media(max-width:480px){ .card{ padding:20px 16px; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="card">
      <h2>🔐 Login</h2>
      <?php if ($err_msg): ?>
        <div class="alert-info"><?= htmlspecialchars($err_msg) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" autocomplete="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <div class="field">
          <label>Security Check</label>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <img id="captcha-img" src="captcha.php?for=login&v=<?= time() ?>" alt="Math captcha"
                 style="border:1px solid #d1d5db;border-radius:8px;height:48px;cursor:pointer;" title="Click to refresh">
            <button type="button" onclick="refreshCaptcha()" style="background:none;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;cursor:pointer;font-size:1rem;color:#6b7280;min-width:44px;min-height:44px;" title="Get new question">&#8635;</button>
          </div>
          <input id="captcha" name="captcha" type="number" placeholder="Enter the answer" required autocomplete="off">
        </div>
        <button class="btn" type="submit">Login</button>
      </form>
    </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
  <script>
    function refreshCaptcha() {
      var img = document.getElementById('captcha-img');
      img.src = 'captcha.php?for=login&v=' + Date.now();
      document.getElementById('captcha').value = '';
      document.getElementById('captcha').focus();
    }
    document.getElementById('captcha-img').addEventListener('click', refreshCaptcha);
  </script>
</body>
</html>
