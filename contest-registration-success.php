<?php
session_start();
$data = $_SESSION['contest_registration_success'] ?? null;
if ($data) {
  unset($_SESSION['contest_registration_success']);
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
  <title>Contest Entry Submitted - 3S English Academy</title>
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
      background: white;
      padding: 24px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(15,23,42,0.12);
      width: 100%;
      max-width: 480px;
      text-align: center;
    }
    h2 {
      color: #1d4ed8;
      margin-top: 0;
    }
    p { color: #374151; }
    .meta {
      color: #64748b;
      margin-top: 10px;
      font-size: 0.95rem;
    }
    .btn {
      display: inline-block;
      margin-top: 16px;
      background: #1d4ed8;
      color: #fff;
      text-decoration: none;
      padding: 10px 16px;
      border-radius: 10px;
    }
    .btn:hover { background: #153ea5; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main>
    <div class="card">
      <?php if ($data): ?>
        <h2>Entry Received 🎉</h2>
        <p>Thank you <?= $data['name'] ? ', ' . e($data['name']) : '' ?> for registering for the contest.</p>
        <p>We will reach out on <strong><?= e($data['mobile']) ?></strong> with further details.</p>
        <?php
          $ts = strtotime($data['created_at']);
          $formatted = $ts ? date('d M Y, h:i A', $ts) : e($data['created_at']);
        ?>
        <div class="meta">Submitted on: <?= $formatted ?></div>
        <a class="btn" href="contest-register.php">Submit another entry</a>
      <?php else: ?>
        <h2>No Recent Entry</h2>
        <p>Please fill the contest form first.</p>
        <a class="btn" href="contest-register.php">Go to Contest Form</a>
      <?php endif; ?>
    </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
