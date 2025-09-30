<?php
session_start();

$DEFAULT_DESTINATION = 'admin.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strncmp((string)$haystack, $needle, strlen($needle)) === 0;
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$sanitizeRedirect = function (?string $target) use ($DEFAULT_DESTINATION) {
    if (!$target) {
        return $DEFAULT_DESTINATION;
    }
    $target = trim($target);
    if ($target === '' || stripos($target, '://') !== false || str_starts_with($target, '//')) {
        return $DEFAULT_DESTINATION;
    }
    if (!preg_match('~^[A-Za-z0-9/_\-.?=&]+$~', $target)) {
        return $DEFAULT_DESTINATION;
    }
    return $target;
};

$pdo = null;
require __DIR__ . '/db.php';

$defaultUser = 'sachinsaitawadekar';
$defaultPass = 'Sachin@123456..';
$loginAvailable = true;

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u');
    $stmt->execute([':u' => $defaultUser]);
    if ((int)$stmt->fetchColumn() === 0) {
        $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :p)');
        $insert->execute([':u' => $defaultUser, ':p' => $hash]);
    }
} catch (Throwable $e) {
    $loginAvailable = false;
}

$redirectTarget = $sanitizeRedirect($_GET['redirect'] ?? $DEFAULT_DESTINATION);

if (!empty($_SESSION['is_admin'])) {
    header('Location: ' . $redirectTarget);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');
    $redirectTarget = $sanitizeRedirect($_POST['redirect'] ?? $DEFAULT_DESTINATION);
    if (!$loginAvailable) {
        $error = 'Login temporarily unavailable. Please try again later.';
    } elseif ($captcha === '' || (int)$captcha !== (int)($_SESSION['captcha_login_answer'] ?? -1)) {
        $error = 'Incorrect captcha answer.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && password_verify($password, $row['password_hash'])) {
                $_SESSION['is_admin'] = true;
                $_SESSION['login_time'] = time();
                header('Location: ' . $redirectTarget);
                exit;
            }
            $error = 'Invalid username or password.';
        } catch (Throwable $e) {
            $error = 'Login temporarily unavailable. Please try again later.';
        }
    }
}

$_SESSION['captcha_login_a'] = random_int(1, 9);
$_SESSION['captcha_login_b'] = random_int(1, 9);
$_SESSION['captcha_login_answer'] = $_SESSION['captcha_login_a'] + $_SESSION['captcha_login_b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0QT95F62SG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-0QT95F62SG');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Admin Login - 3S English Academy</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh; min-height: 100dvh;
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      display: flex;
      flex-direction: column;
    }
    main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .card {
      background: #fff;
      padding: 24px;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(15,23,42,0.12);
      width: 100%;
      max-width: 400px;
    }
    h1 {
      margin: 0 0 16px;
      text-align: center;
      color: #1d4ed8;
    }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #1f2937; }
    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 1rem;
      margin-bottom: 14px;
    }
    .captcha-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
    }
    .captcha-wrap span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 120px;
      padding: 10px 12px;
      border-radius: 10px;
      background: #e0ecff;
      border: 1px solid #93c5fd;
      font-weight: 600;
      color: #1e3a8a;
    }
    input[type="number"], .captcha-input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #d1d5db;
      font-size: 1rem;
    }
    button {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 10px;
      background: #1d4ed8;
      color: #fff;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    button:hover { background: #153ea5; }
    .error {
      background: #fee2e2;
      color: #b91c1c;
      border: 1px solid #fecaca;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 12px;
      text-align: center;
    }
    .hint {
      margin-top: 12px;
      font-size: 0.9rem;
      color: #475569;
      text-align: center;
    }
    .hint a { color: #1d4ed8; text-decoration: none; }
    .hint a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main>
    <div class="card" role="form">
      <h1>Admin Login</h1>
      <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <label for="captcha">Captcha</label>
        <div class="captcha-wrap">
          <span><?= $_SESSION['captcha_login_a'] ?> + <?= $_SESSION['captcha_login_b'] ?> = ?</span>
          <input id="captcha" name="captcha" type="text" inputmode="numeric" pattern="\\d+" class="captcha-input" placeholder="Enter result" required>
        </div>

        <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">

        <button type="submit">Sign In</button>
      </form>
      <p class="hint">Use the credentials provided to access the admin tools.</p>
    </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
