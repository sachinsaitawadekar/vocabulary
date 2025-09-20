<!-- header.php -->
<header>
  <nav class="top-nav">
    <a href="/index.php">🏠 Home</a>
    <a href="/vocabulary.php">📖 Vocabulary</a>
    <a href="/register.php">📝 Register</a>
    <a href="/about.php">ℹ️ About</a>
    <button id="installBtn">📲 Install App</button>
  </nav>
</header>

<style>
  body { margin: 0; font-family: Arial, sans-serif; }
  .top-nav {
    position: sticky;
    top: 0;
    z-index: 999;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    padding: 10px;
    background: #007BFF;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  }
  .top-nav a, .top-nav button {
    color: white;
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 8px;
    font-weight: bold;
    border: none;
    background: rgba(255,255,255,0.2);
    transition: 0.3s;
    cursor: pointer;
  }
  .top-nav a:hover, .top-nav button:hover {
    background: rgba(255,255,255,0.4);
  }
  #installBtn { display: none; }

  @media (max-width: 480px) {
    .top-nav a, .top-nav button {
      font-size: 4vw;
      padding: 8px 10px;
    }
  }
</style>

<script>
  // Register Service Worker
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/service-worker.js")
      .then(reg => console.log("✅ SW registered:", reg.scope))
      .catch(err => console.error("❌ SW failed:", err));
  }

  // Handle Install App button
  let deferredPrompt;
  const installBtn = document.getElementById("installBtn");
  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installBtn.style.display = "block";
  });
  installBtn.addEventListener("click", () => {
    if (deferredPrompt) {
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(() => {
        deferredPrompt = null;
        installBtn.style.display = "none";
      });
    }
  });
</script>