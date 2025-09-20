<?php
// Shared responsive top navigation bar
?>
<style>
  .topnav {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 16px;
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
    background: #f3f4f6; padding: 8px 12px; border-radius: 8px;
    font-size: 0.95rem; transition: background 0.2s;
  }
  .topnav .links a:hover { background: #e5e7eb; }
  .topnav .hamburger {
    display: none; background: transparent; border: 0; font-size: 24px;
    line-height: 1; cursor: pointer; padding: 8px; border-radius: 8px;
  }
  /* Prevent content from sitting under the fixed nav */
  body { padding-top: 72px; }
  @media (max-width: 768px) {
    .topnav { height: 56px; }
    body { padding-top: 64px; }
    .topnav .hamburger { display: block; }
    .topnav .links {
      display: none; /* hidden by default on mobile */
      position: fixed; top: 56px; left: 0; right: 0;
      background: #ffffff; border-bottom: 1px solid #e5e7eb;
      flex-direction: column; gap: 6px; padding: 10px 12px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .topnav .links.open { display: flex; }
    .topnav .links a { font-size: 1rem; }
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
