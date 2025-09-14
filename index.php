<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vocabulary App - Home</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#007BFF">
  <link rel="icon" href="/icons/icon-192.png">
  <style>
    body {
      margin: 0; 
      font-family: Arial, sans-serif; 
      display: flex; 
      flex-direction: column; 
      min-height: 100vh; 
      background: #f5f5f5;
    }
    header {
      background: #007BFF; 
      color: white; 
      padding: 20px; 
      text-align: center;
    }
    main {
      flex: 1; 
      display: flex; 
      flex-wrap: wrap; 
      justify-content: center; 
      align-items: center; 
      padding: 20px; 
      gap: 20px;
    }
    .menu {
      background: white; 
      padding: 30px; 
      border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      text-align: center; 
      width: 220px; 
      transition: transform 0.2s, background 0.3s;
    }
    .menu:hover {
      transform: scale(1.05); 
      background: #f0f8ff;
    }
    .menu a {
      text-decoration: none; 
      color: #007BFF; 
      font-size: 1.2em; 
      font-weight: bold;
    }
    footer {
      text-align: center; 
      padding: 10px; 
      background: #ddd;
    }
    @media (max-width: 600px) {
      .menu { width: 100%; padding: 20px; }
    }
  </style>
  <script>
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("/service-worker.js")
        .then(() => console.log("Service Worker Registered"));
    }
  </script>
</head>
<body>
  <header>
    <h1>📘 Vocabulary App</h1>
    <p>Learn new words every day</p>
  </header>

  <main>
    <div class="menu"><a href="vocabulary.php">Vocabulary</a></div>
    <div class="menu"><a href="register.php">Register</a></div>
    <div class="menu"><a href="about.php">About Class</a></div>
  </main>

  <footer>
    &copy; <?= date("Y") ?> Vocabulary App
  </footer>
</body>
</html>
