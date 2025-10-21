<?php
// index.php - Landing Home Page
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
  <title>Vocabulary App - Home</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#007BFF">
  <link rel="apple-touch-icon" href="/picons/icon-192.png">
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f5f5f5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .page-main {
      flex: 1;
      display: flex;
      justify-content: center;
    }
    .container {
      width: 100%; max-width: 720px; margin: 0 auto; padding: 16px;
      display: flex; flex-direction: column; align-items: center; gap: 16px;
    }
    .menu {
      display: flex; flex-direction: column;
      gap: 15px; width: 80%; max-width: 400px;
    }
    .menu a, .menu button {
      display: block; text-align: center;
      background: #007BFF; color: #fff; text-decoration: none;
      padding: 15px; border-radius: 10px;
      font-size: 18px; font-weight: bold;
      transition: 0.3s; border: none; cursor: pointer;
    }
    .menu a:hover, .menu button:hover {
      background: #0056b3;
    }
    .menu a.vocab-link {
      background: linear-gradient(135deg, #f97316, #ef4444);
      box-shadow: 0 10px 20px rgba(239, 68, 68, 0.35);
    }
    .menu a.vocab-link:hover {
      transform: translateY(-2px);
      background: linear-gradient(135deg, #fb923c, #f87171);
      box-shadow: 0 14px 24px rgba(249, 115, 22, 0.35);
    }
    .menu a .icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 8px;
    }
    .menu a .icon svg { width: 18px; height: 18px; fill: currentColor; }
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
  <main class="page-main">
    <div class="container">
    <h1>📘 Vocabulary App</h1>
    <div class="menu">
      <a class="vocab-link" href="vocabulary.php">📖 Today's Vocabulary</a>
      <a href="register.php">📝 Register</a>
      <a href="contest-register.php">🏆 Contest Registration</a>
      <a href="about.php">ℹ️ About Class</a>
      <a href="tel:+918591388500"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l1.99-1.99a1 1 0 011.01-.24c1.12.37 2.33.57 3.59.57a1 1 0 011 1V21a1 1 0 01-1 1C12.95 22 2 11.05 2 4a1 1 0 011-1h3.37a1 1 0 011 1c0 1.26.2 2.47.57 3.59a1 1 0 01-.24 1.01l-1.99 1.99z"/></svg></span>85913 88500</a>
      <button id="installBtn">📲 Install App</button>
    </div>
    </div>
  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

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
