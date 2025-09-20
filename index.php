<?php
// index.php - Landing Home Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vocabulary App - Home</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#007BFF">
  <link rel="apple-touch-icon" href="/picons/icon-192.png">
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0; padding: 0;
      display: flex; flex-direction: column; align-items: center;
      background: #f5f5f5;
    }
    .container {
      width: 100%; max-width: 720px; margin: 0 auto; padding: 16px;
      display: flex; flex-direction: column; align-items: center; gap: 16px;
    }
    .menu {
      display: flex; flex-direction: column;
      gap: 15px; width: 80%; max-width: 400px;
    }
    a, button {
      display: block; text-align: center;
      background: #007BFF; color: #fff; text-decoration: none;
      padding: 15px; border-radius: 10px;
      font-size: 18px; font-weight: bold;
      transition: 0.3s; border: none; cursor: pointer;
    }
    a:hover, button:hover {
      background: #0056b3;
    }
    #installBtn {
      display: none; /* Hidden until install available */
      background: #28a745;
    }
    #installBtn:hover {
      background: #1e7e34;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="container">
    <h1>📘 Vocabulary App</h1>
    <div class="menu">
      <a href="vocabulary.php">📖 Vocabulary</a>
      <a href="register.php">📝 Register</a>
      <a href="about.php">ℹ️ About Class</a>
      <button id="installBtn">📲 Install App</button>
    </div>
  </main>

  <script>
    // Register service worker
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("/service-worker.js")
        .then(() => console.log("Service Worker registered"));
    }

    // Handle install prompt
    let deferredPrompt;
    const installBtn = document.getElementById("installBtn");

    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      deferredPrompt = e;
      installBtn.style.display = "block"; // show button
    });

    installBtn.addEventListener("click", () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(() => {
          deferredPrompt = null;
          installBtn.style.display = "none"; // hide button after install
        });
      }
    });
  </script>

  <script>
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("/service-worker.js")
        .then(reg => {
          console.log("✅ Service Worker registered successfully:", reg.scope);
        })
        .catch(err => {
          console.error("❌ Service Worker registration failed:", err);
        });
    } else {
      console.warn("⚠️ Service Workers are not supported in this browser.");
    }
  </script>
</body>
</html>
