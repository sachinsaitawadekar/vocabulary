<?php
session_start();
$data = $_SESSION['registration_success'] ?? null;
if ($data) {
  // one-time flash
  unset($_SESSION['registration_success']);
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
  <title>Registration Success - Vocabulary App</title>
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
    .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.08); width: 100%; max-width: 480px; text-align: center; }
    h2 { color: #007BFF; margin-top: 0; }
    p { color: #374151; }
    .meta { color: #6b7280; font-size: 0.95rem; margin-top: 10px; }
    .btn { display: inline-block; margin-top: 16px; background: #007BFF; color: #fff; text-decoration: none; padding: 10px 14px; border-radius: 10px; }
    .btn:hover { background: #0056b3; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main>
  <div class="card">
    <?php if ($data): ?>
      <h2>Registration Successful 🎉</h2>
      <p>Thank you<?= $data['name'] ? ', ' . htmlspecialchars($data['name']) : '' ?>!<br>
        Your registration with mobile <strong><?= htmlspecialchars($data['mobile']) ?></strong> is successful.</p>
      <?php
        $ts = strtotime($data['created_at']);
        $formatted = $ts ? date('d M Y, h:i A', $ts) : htmlspecialchars($data['created_at']);
      ?>
      <div class="meta">Completed on: <?= $formatted ?></div>
      <a class="btn" href="index.php">Go to Home</a>
    <?php else: ?>
      <h2>No Recent Registration</h2>
      <p>Please complete the registration form.</p>
      <a class="btn" href="register.php">Go to Register</a>
    <?php endif; ?>
  </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
