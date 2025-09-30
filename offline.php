<?php /* Offline fallback page with shared navbar */ ?>
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
  <title>Offline - Vocabulary App</title>
  <style>
    html { -webkit-text-size-adjust: 100%; }
    *, *::before, *::after { box-sizing: border-box; }
    :root { --nav-h: 64px; }
    @media (max-width: 768px) { :root { --nav-h: 56px; } }

    body {
      display: flex; 
      justify-content: center; 
      align-items: center; 
      min-height: 100vh; min-height: 100dvh;
      margin: 0;
      font-family: Arial, sans-serif; 
      background: #f5f5f5; 
      text-align: center; 
      padding: 20px;
    }
    .card {
      background: #fff; 
      padding: 20px; 
      border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      max-width: 400px;
    }
    h2 { color: #007BFF; }
    p { color: #555; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="card">
    <h2>📴 You’re Offline</h2>
    <p>Don’t worry! Last viewed word is still available if cached.<br>
    Please reconnect to fetch new vocabulary.</p>
  </div>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
