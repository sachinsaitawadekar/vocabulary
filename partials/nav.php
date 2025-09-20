<?php
// Shared responsive top navigation bar
?>
<style>
  /* Base mobile-friendly defaults */
  html { -webkit-text-size-adjust: 100%; }
  *, *::before, *::after { box-sizing: border-box; }
  :root { --nav-h: 64px; }
  @media (max-width: 768px) { :root { --nav-h: 56px; } }

  .topnav {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--nav-h);
    display: flex; align-items: center; justify-content: space-between;
    padding: calc(env(safe-area-inset-top, 0))
             max(calc(16px + env(safe-area-inset-right, 0)), 16px)
             0
             max(calc(16px + env(safe-area-inset-left, 0)), 16px);
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    z-index: 1000;
  }
  .topnav .brand a {
    color: #007BFF; text-decoration: none; font-weight: 700; font-size: 1.1rem;
  }
  .topnav .links {
    display: flex; gap: 8px; align-items: center;
  }
  .topnav .links a {
    text-decoration: none; color: #111827;
    background: #f3f4f6; padding: 10px 14px; border-radius: 10px;
    font-size: 1rem; line-height: 1.2; transition: background 0.2s;
    min-height: 44px; display: inline-flex; align-items: center;
  }
  .topnav .links a:hover { background: #e5e7eb; }
  .topnav .hamburger {
    display: none; background: transparent; border: 0; font-size: 28px;
    line-height: 1; cursor: pointer; padding: 8px; border-radius: 8px;
  }
  /* Prevent content from sitting under the fixed nav */
  body { padding-top: calc(var(--nav-h) + env(safe-area-inset-top, 0)); }
  @media (max-width: 768px) {
    body { padding-top: calc(var(--nav-h) + env(safe-area-inset-top, 0)); }
    .topnav .hamburger { display: block; }
    .topnav .links {
      display: none; /* hidden by default on mobile */
      position: fixed; top: var(--nav-h); left: 0; right: 0;
      background: #ffffff; border-bottom: 1px solid #e5e7eb;
      flex-direction: column; gap: 8px; padding: 12px max(12px, calc(12px + env(safe-area-inset-right, 0))) 16px max(12px, calc(12px + env(safe-area-inset-left, 0)));
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .topnav .links.open { display: flex; }
    .topnav .links a {
      font-size: 1.05rem; background: #007BFF; color: #ffffff;
    }
  }
</style>

<nav class="topnav" role="navigation" aria-label="Primary">
  <div class="brand"><a href="index.php">📘 Vocabulary</a></div>
  <button class="hamburger" aria-label="Toggle menu" aria-expanded="false">☰</button>
  <div class="links" id="navLinks">
    <a href="index.php">Home</a>
    <a href="vocabulary.php">Vocabulary</a>
    <a href="register.php">Register</a>
    <a href="about.php">About</a>
    <a href="admin.php">Admin</a>
  </div>
  <script>
    (function(){
      const btn = document.currentScript.previousElementSibling.previousElementSibling; // hamburger
      const links = document.getElementById('navLinks');
      if (btn && links) {
        btn.addEventListener('click', function(){
          const open = links.classList.toggle('open');
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
          links.classList.remove('open');
          btn.setAttribute('aria-expanded', 'false');
        }));
      }
    })();
  </script>
</nav>
