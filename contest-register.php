<?php
session_start();
$errors = [];

$pdo = null;
if (file_exists(__DIR__ . '/db.php')) {
  try { require __DIR__ . '/db.php'; } catch (Throwable $e) { /* ignore for now */ }
}

if ($pdo instanceof PDO) {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contest_registrations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      full_name VARCHAR(100) NOT NULL,
      gender ENUM('Male','Female','Other') NOT NULL,
      participant_type VARCHAR(100) NOT NULL,
      mobile VARCHAR(20) NOT NULL UNIQUE,
      age TINYINT UNSIGNED NOT NULL,
      location VARCHAR(150) NOT NULL,
      contest_date DATE NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_contest_mobile (mobile),
      INDEX idx_contest_date (contest_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  } catch (Throwable $e) { /* ignore */ }
  try {
    $col = $pdo->query("SHOW COLUMNS FROM contest_registrations LIKE 'contest_date'");
    if ($col->rowCount() === 0) {
      $pdo->exec("ALTER TABLE contest_registrations ADD COLUMN contest_date DATE NOT NULL DEFAULT '2025-11-01', ADD INDEX idx_contest_date (contest_date)");
    }
  } catch (Throwable $e) { /* ignore */ }
  try {
    $col = $pdo->query("SHOW COLUMNS FROM contest_registrations LIKE 'location'");
    if ($col->rowCount() === 0) {
      $pdo->exec("ALTER TABLE contest_registrations ADD COLUMN location VARCHAR(150) NOT NULL DEFAULT 'Chiplun'");
    }
  } catch (Throwable $e) { /* ignore */ }
}

$genderOptions = ['Male', 'Female', 'Other'];
$participantOptions = ['Student', 'Parent', 'Professional', 'Individual', 'Other'];
$contestDateOptions = [
  '2025-11-01' => '01 November 2025',
  '2025-11-02' => '02 November 2025'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['full_name'] ?? '');
  $gender = trim($_POST['gender'] ?? '');
  $participantType = trim($_POST['participant_type'] ?? '');
  $mobileRaw = preg_replace('/\D+/', '', $_POST['mobile'] ?? '');
  $age = (int)($_POST['age'] ?? 0);
  $contestDate = $_POST['contest_date'] ?? '';
  $location = trim($_POST['location'] ?? '');
  $captcha = trim($_POST['captcha'] ?? '');

  if ($name === '') { $errors[] = 'Full name is required.'; }
  if (!in_array($gender, $genderOptions, true)) { $errors[] = 'Please select a valid gender.'; }
  if (!in_array($participantType, $participantOptions, true)) { $errors[] = 'Please choose a valid participant type.'; }
  if (!preg_match('/^\d{10}$/', $mobileRaw)) { $errors[] = 'Mobile number must be 10 digits.'; }
  if ($age < 5 || $age > 120) { $errors[] = 'Please enter a valid age (5-120).'; }
  if (!array_key_exists($contestDate, $contestDateOptions)) { $errors[] = 'Please select a contest date.'; }
  if ($location === '') { $errors[] = 'Please let us know your location.'; }
  if ($captcha === '' || (int)$captcha !== (int)($_SESSION['captcha_contest_answer'] ?? -1)) {
    $errors[] = 'Incorrect captcha answer.';
  }

  if (!$errors) {
    $mobileFull = '+91' . $mobileRaw;
    if ($pdo instanceof PDO) {
      try {
        $stmt = $pdo->prepare('INSERT INTO contest_registrations (full_name, gender, participant_type, mobile, age, location, contest_date) VALUES (:name, :gender, :type, :mobile, :age, :location, :contest_date)');
        $stmt->execute([
          ':name' => $name,
          ':gender' => $gender,
          ':type' => $participantType,
          ':mobile' => $mobileFull,
          ':age' => $age,
          ':location' => $location,
          ':contest_date' => $contestDate
        ]);
        $createdAt = null;
        try {
          $fetch = $pdo->prepare('SELECT created_at FROM contest_registrations WHERE mobile = :mobile');
          $fetch->execute([':mobile' => $mobileFull]);
          $createdAt = $fetch->fetchColumn();
        } catch (Throwable $inner) { /* ignore */ }
        $_SESSION['contest_registration_success'] = [
          'name' => $name,
          'gender' => $gender,
          'participant_type' => $participantType,
          'mobile' => $mobileFull,
          'age' => $age,
          'location' => $location,
          'contest_date' => $contestDate,
          'created_at' => $createdAt ?: date('Y-m-d H:i:s')
        ];
        header('Location: contest-registration-success.php');
        exit;
      } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || ($e->errorInfo[1] ?? 0) == 1062) {
          $errors[] = 'This mobile number has already been registered for the contest.';
        } else {
          $errors[] = 'Could not submit your entry. Please try again later.';
        }
      }
    } else {
      $_SESSION['contest_registration_success'] = [
        'name' => $name,
        'gender' => $gender,
        'participant_type' => $participantType,
        'mobile' => $mobileFull,
        'age' => $age,
        'location' => $location,
        'contest_date' => $contestDate,
        'created_at' => date('Y-m-d H:i:s')
      ];
      header('Location: contest-registration-success.php');
      exit;
    }
  }
}

function e($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
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
  <title>Contest Registration - 3S English Academy</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh; min-height: 100dvh;
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      display: flex;
      flex-direction: column;
    }
    .page-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
      padding: 20px;
    }
    .card {
      background: white; padding: 20px; border-radius: 12px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      width: 100%; max-width: 460px;
    }
    h2 {
      margin-top: 0;
      color: #1d4ed8;
      text-align: center;
    }
    .field { margin: 10px 0; }
    .label { display: block; font-weight: 600; margin-bottom: 6px; color: #1f2937; }
    .card input[type="text"],
    .card input[type="number"],
    .card select {
      width: 100%;
      padding: 10px;
      font-size: 1em;
      border-radius: 8px;
      border: 1px solid #ccc;
      background: #fff;
    }
    .card button {
      width: 100%; padding: 12px; margin-top: 8px; font-size: 1rem; border-radius: 10px; border: none; cursor: pointer;
      background: #007BFF; color: #fff; transition: background 0.2s;
    }
    .card button:hover { background: #0056b3; }
    .errors { background: #fdecea; color: #b91c1c; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
    .errors div { margin-bottom: 4px; }
    .notice-card {
      display: grid;
      gap: 10px;
      border-radius: 12px;
      width: 100%;
      max-width: 460px;
    }
    .notice-box {
      border-radius: 12px;
      padding: 14px 16px;
      box-shadow: 0 4px 8px rgba(14, 116, 144, 0.1);
    }
    .notice-box--en {
      background: #e0f2fe;
      color: #0f172a;
      border: 1px solid #bfdbfe;
    }
    .notice-box--mr {
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #facc15;
    }
    .prefix-row {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .prefix {
      background: #f3f4f6; padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; color: #111827;
      min-width: 64px; text-align: center; font-weight: 600;
    }
    .captcha-img { display: block; margin: 6px 0; border: 1px solid #e5e7eb; border-radius: 8px; }
    .captcha-wrap { display: inline-flex; flex-direction: column; align-items: flex-start; margin-bottom: 10px; }
    .refresh-link { display: inline-block; margin-top: 6px; color: #007BFF; text-decoration: none; font-size: 0.95rem; }
    .refresh-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="notice-card">
      <div class="notice-box notice-box--en">
        <strong style="font-size:1.1rem; display:block; margin-bottom:6px;">🎉 Contest Registration</strong>
        Fill out the form below to participate. We will contact shortlisted participants on WhatsApp at the number you provide.
      </div>
      <div class="notice-box notice-box--mr">
        <strong style="font-size:1.1rem; display:block; margin-bottom:6px;">📲 स्पर्धा नोंदणी</strong>
        स्पर्धेत सहभागी होण्यासाठी खालील फॉर्म भरा. निवडलेल्या सहभागीना आपण दिलेल्या मोबाइल क्रमांकावर WhatsApp द्वारे संपर्क केला जाईल.
      </div>
    </div>
    <div class="card">
      <h2>Enter Contest</h2>
      <?php if ($errors): ?>
        <div class="errors">
          <?php foreach ($errors as $err): ?>
            <div>• <?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <div class="field">
          <label class="label" for="full_name">Full Name</label>
          <input id="full_name" name="full_name" type="text" required placeholder="Full Name" value="<?= e($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="label" for="gender">Gender</label>
          <select id="gender" name="gender" required>
            <option value="">Select gender</option>
            <?php foreach ($genderOptions as $option): ?>
              <option value="<?= e($option) ?>" <?= ($option === ($_POST['gender'] ?? '')) ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="label" for="participant_type">Are you?</label>
          <select id="participant_type" name="participant_type" required>
            <option value="">Select option</option>
            <?php foreach ($participantOptions as $option): ?>
              <option value="<?= e($option) ?>" <?= ($option === ($_POST['participant_type'] ?? '')) ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="label" for="location">You are from?</label>
          <input id="location" name="location" type="text" required placeholder="City / Town" value="<?= e($_POST['location'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="label" for="contest_date">Date you want to give contest</label>
          <select id="contest_date" name="contest_date" required>
            <option value="">Select date</option>
            <?php foreach ($contestDateOptions as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= ($value === ($_POST['contest_date'] ?? '')) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label class="label" for="mobile">Mobile Number</label>
          <div class="prefix-row">
            <div class="prefix">+91</div>
            <input id="mobile" name="mobile" type="text" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number" required value="<?= e(preg_replace('/\D+/', '', $_POST['mobile'] ?? '')) ?>">
          </div>
        </div>
        <div class="field">
          <label class="label" for="age">Age</label>
          <input id="age" name="age" type="number" min="5" max="120" required placeholder="Enter age" value="<?= e($_POST['age'] ?? '') ?>">
        </div>
        <div class="field">
          <label class="label" for="captcha">Captcha</label>
          <div class="captcha-wrap">
            <img id="captcha_img_contest" class="captcha-img" src="captcha.php?for=contest&ts=<?= time() ?>" width="220" height="80" alt="Captcha image">
            <a id="captcha_refresh_contest" href="#" class="refresh-link" aria-label="Refresh captcha">↻ Refresh</a>
          </div>
          <input id="captcha" name="captcha" type="text" inputmode="numeric" pattern="\d+" placeholder="Enter result" required>
        </div>
        <button type="submit">Submit Entry</button>
      </form>
    </div>
  </main>
  <script>
    (function(){
      const mobile = document.getElementById('mobile');
      if (mobile) {
        mobile.addEventListener('input', () => {
          mobile.value = mobile.value.replace(/\D+/g, '').slice(0, 10);
        });
      }
      const age = document.getElementById('age');
      if (age) {
        age.addEventListener('input', () => {
          age.value = age.value.replace(/\D+/g, '').slice(0, 3);
        });
        age.addEventListener('blur', () => {
          if (age.value === '') return;
          let val = parseInt(age.value, 10);
          if (Number.isNaN(val)) { age.value = ''; return; }
          val = Math.max(5, Math.min(120, val));
          age.value = String(val);
        });
      }
      const img = document.getElementById('captcha_img_contest');
      const btn = document.getElementById('captcha_refresh_contest');
      function refreshCaptcha() {
        if (img) {
          img.src = 'captcha.php?for=contest&ts=' + Date.now();
        }
        const capInput = document.getElementById('captcha');
        if (capInput) { capInput.value = ''; capInput.focus(); }
      }
      btn?.addEventListener('click', (e) => { e.preventDefault(); refreshCaptcha(); });
      img?.addEventListener('click', refreshCaptcha);
      img?.addEventListener('error', () => setTimeout(refreshCaptcha, 200));
    })();
  </script>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
