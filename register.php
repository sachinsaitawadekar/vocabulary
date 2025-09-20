<?php
session_start();
$errors = [];
$success = '';

// Image captcha handled by captcha.php

// Try DB connection only if available
$pdo = null;
if (file_exists(__DIR__ . '/db.php')) {
  try { require __DIR__ . '/db.php'; } catch (Throwable $e) { /* ignore here */ }
  if (isset($pdo) && $pdo instanceof PDO) {
    // Ensure registrations table exists (name + mobile only)
    $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(100) NOT NULL,
      mobile VARCHAR(20) NOT NULL UNIQUE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['full_name'] ?? '');
  // No email/password per requirements
  $mobile_raw = preg_replace('/\D+/', '', $_POST['mobile'] ?? ''); // digits only
  $captcha = trim($_POST['captcha'] ?? '');

  if ($name === '') { $errors[] = 'Full name is required.'; }
  if (!preg_match('/^\d{10}$/', $mobile_raw)) { $errors[] = 'Mobile number must be 10 digits.'; }

  if ($captcha === '' || (int)$captcha !== (int)($_SESSION['captcha_register_answer'] ?? -1)) {
    $errors[] = 'Incorrect captcha answer.';
  }

  if (!$errors) {
    $mobile_full = '+91' . $mobile_raw;
    if ($pdo instanceof PDO) {
      try {
        $stmt = $pdo->prepare('INSERT INTO registrations (full_name, mobile) VALUES (:n, :m)');
        $stmt->execute([':n' => $name, ':m' => $mobile_full]);
        $lastId = $pdo->lastInsertId();
        $createdAt = null;
        try {
          $tsStmt = $pdo->prepare('SELECT created_at FROM registrations WHERE id = :id');
          $tsStmt->execute([':id' => $lastId]);
          $createdAt = $tsStmt->fetchColumn();
        } catch (Throwable $e2) { /* ignore */ }
        $_SESSION['registration_success'] = [
          'name' => $name,
          'mobile' => $mobile_full,
          'created_at' => $createdAt ?: date('Y-m-d H:i:s')
        ];
        header('Location: registration-success.php');
        exit;
      } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || $e->errorInfo[1] == 1062) {
          $errors[] = 'This mobile number is already registered.';
        } else {
          $errors[] = 'Registration failed. Please try again later.';
        }
      }
    } else {
      // No DB available – still redirect to success with current timestamp
      $_SESSION['registration_success'] = [
        'name' => $name,
        'mobile' => $mobile_full,
        'created_at' => date('Y-m-d H:i:s')
      ];
      header('Location: registration-success.php');
      exit;
    }
  }

  // No explicit refresh here; captcha image will reload on next GET or manual refresh
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Register - Vocabulary App</title>
  <style>
    body {
      display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 12px;
      min-height: 100vh; min-height: 100dvh; margin: 0; font-family: Arial, sans-serif; 
      background: #f5f5f5; padding: 20px;
    }
    .card {
      background: white; padding: 20px; border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      width: 100%; max-width: 420px;
    }
    .field { margin: 10px 0; }
    .label { display: block; font-weight: 600; margin-bottom: 6px; }
    .row { display: flex; gap: 8px; align-items: center; }
    .prefix {
      background: #f3f4f6; padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; color: #111827;
      min-width: 64px; text-align: center; font-weight: 600;
    }
    .card input[type="text"], .card input[type="email"], .card input[type="password"], .card input[type="number"] {
      width: 100%; padding: 10px; font-size: 1em; border-radius: 8px; border: 1px solid #ccc;
    }
    .card button {
      width: 100%; padding: 12px; margin-top: 8px; font-size: 1rem; border-radius: 10px; border: none; cursor: pointer;
      background: #007BFF; color: #fff; transition: background 0.2s;
    }
    .card button:hover { background: #0056b3; }
    .errors { background: #fdecea; color: #b91c1c; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
    .success { background: #e7f7ef; color: #065f46; border: 1px solid #a7f3d0; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
    .notice-card { margin-bottom: 12px; }
    .notice-card a { color: #007BFF; text-decoration: none; }
    .notice-card a:hover { text-decoration: underline; }
    .captcha-img { display: block; margin: 6px 0; border: 1px solid #e5e7eb; border-radius: 8px; }
    .captcha-wrap { display: inline-flex; flex-direction: column; align-items: flex-start; margin-bottom: 10px; }
    .refresh-link { display: inline-block; margin-top: 6px; color: #007BFF; text-decoration: none; font-size: 0.95rem; }
    .refresh-link:hover { text-decoration: underline; }
    @media (max-width: 480px) { .row { flex-direction: row; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="card notice-card">Already registered? <a href="check-registration-page.php">Check your registration status.</a></div>
  <div class="card" id="formCard">
    <h2>Register</h2>
    <?php if ($errors): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="POST" novalidate>
      <div class="field">
        <label class="label" for="full_name">Full Name</label>
        <input id="full_name" name="full_name" type="text" placeholder="Full Name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
      </div>
      <div class="field">
        <label class="label" for="mobile">Mobile Number</label>
        <div class="row">
          <div class="prefix">+91</div>
          <input id="mobile" name="mobile" type="text" inputmode="numeric" pattern="\\d{10}" maxlength="10" placeholder="10-digit mobile number" required value="<?= htmlspecialchars(preg_replace('/\\D+/', '', $_POST['mobile'] ?? '')) ?>">
        </div>
      </div>
      <div class="field">
        <label class="label" for="captcha">Captcha</label>
        <div class="captcha-wrap">
          <img id="captcha_img_reg" class="captcha-img" src="captcha.php?for=register&ts=<?= time() ?>" width="180" height="60" alt="Captcha image">
          <a id="captcha_refresh_reg" href="#" class="refresh-link" aria-label="Refresh captcha">↻ Refresh</a>
        </div>
        <input id="captcha" name="captcha" type="text" inputmode="numeric" pattern="\\d+" placeholder="Enter result" required>
      </div>
      <button type="submit">Register</button>
    </form>
  </div>

  <script>
    // Keep mobile input digits-only UX friendly
    const mobile = document.getElementById('mobile');
    if (mobile) {
      mobile.addEventListener('input', () => {
        mobile.value = mobile.value.replace(/\D+/g, '').slice(0, 10);
      });
    }

    // Refresh register captcha on demand
    const capImgReg = document.getElementById('captcha_img_reg');
    const capBtnReg = document.getElementById('captcha_refresh_reg');
    function refreshRegCaptcha(){
      if (capImgReg) {
        capImgReg.src = 'captcha.php?for=register&ts=' + Date.now();
      }
      const capInput = document.getElementById('captcha');
      if (capInput) { capInput.value = ''; capInput.focus(); }
    }
    capBtnReg?.addEventListener('click', (e) => { e.preventDefault(); refreshRegCaptcha(); });
    capImgReg?.addEventListener('click', refreshRegCaptcha);

  </script>
</body>
</html>
