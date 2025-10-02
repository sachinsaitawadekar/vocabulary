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
  <title>About Class - Vocabulary App</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      min-height: 100vh;
      background: #f5f5f5;
      display: flex;
      flex-direction: column;
      line-height: 1.6;
    }
    main {
      flex: 1;
      display: flex;
      justify-content: center;
      padding: 20px;
    }
    .content {
      background: white; padding: 20px; border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      max-width: 800px; width: 100%;
    }
    h2 { color: #007BFF; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main>
  <div class="content">
    <h2>About the Class</h2>
    <p>This class helps students improve their English vocabulary by learning one new word every day.</p>
    <p style="background:#eef2ff; border-left:4px solid #3b82f6; padding:12px 16px; border-radius:10px; font-weight:600; color:#1e3a8a;">
      <span style="display:block; font-size:1.05rem; margin-bottom:4px;">📍 Address</span>
      Ground Floor, Mahavir Market,<br>
      Bhogale Rd,<br>
      Chiplun - 415605
    </p>
    <p>Features:</p>
    <ul>
      <li>Daily vocabulary updates</li>
      <li>Simple registration process</li>
      <li>Mobile-friendly interface</li>
      <li>Installable as an app (PWA)</li>
    </ul>
  </div>
  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
