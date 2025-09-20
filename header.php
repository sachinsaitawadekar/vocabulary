<?php // header.php ?>
<header>
  <nav>
    <ul class="menu">
      <li><a href="/index.php">🏠 Home</a></li>
      <li><a href="/vocabulary.php">📖 Vocabulary</a></li>
      <li><a href="/register.php">📝 Register</a></li>
      <li><a href="/about.php">ℹ️ About</a></li>
      <li><button id="installBtn">📲 Install App</button></li>
    </ul>
  </nav>
</header>

<style>
  header {
    background: #007BFF;
    padding: 10px;
  }
  nav ul {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-wrap: wrap;
    justify-content: center; gap: 10px;
  }
  nav a, nav button {
    background: #fff; color: #007BFF;
    padding: 10px 15px; border-radius: 8px;
    text-decoration: none; font-weight: bold;
    border: none; cursor: pointer;
    transition: 0.3s;
  }
  nav a:hover, nav button:hover {
    background: #0056b3; color: #fff;
  }
  #installBtn { display: none; } /* hidden until available */
</style>

<script>
  // Register Service Worker
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/service-worker.js")
      .then(() => console.log("Service Worker registered"));
  }

  // Handle Install App button
  let deferredPrompt;
  const installBtn = document.getElementById("installBtn");

  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installBtn.style.display = "block"; // show install button
  });

  installBtn.addEventListener("click", () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(() => {
        deferredPrompt = null;
        installBtn.style.display = "none"; // hide after install
      });
    }
  });
</script>
