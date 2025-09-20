<?php /* UI page to check registration status by mobile */ 
session_start();
// Initialize math captcha values for this page (separate from registration page)
if (!isset($_SESSION['captcha_chk_a'], $_SESSION['captcha_chk_b'])) {
  $_SESSION['captcha_chk_a'] = random_int(1, 9);
  $_SESSION['captcha_chk_b'] = random_int(1, 9);
}
$_SESSION['captcha_chk_answer'] = $_SESSION['captcha_chk_a'] + $_SESSION['captcha_chk_b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Check Registration - Vocabulary App</title>
  <style>
    body { display: flex; justify-content: center; align-items: center; min-height: 100vh; min-height: 100dvh; margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
    .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 100%; max-width: 420px; }
    .field { margin: 10px 0; }
    .label { display: block; font-weight: 600; margin-bottom: 6px; }
    .row { display: flex; gap: 8px; align-items: center; }
    .prefix { background: #f3f4f6; padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; color: #111827; min-width: 64px; text-align: center; font-weight: 600; }
    input { width: 100%; padding: 10px; font-size: 1em; border-radius: 8px; border: 1px solid #ccc; }
    button { width: 100%; padding: 12px; margin-top: 8px; font-size: 1rem; border-radius: 10px; border: none; cursor: pointer; background: #007BFF; color: #fff; transition: background 0.2s; }
    button:hover { background: #0056b3; }
    .errors { background: #fdecea; color: #b91c1c; border: 1px solid #fecaca; padding: 10px; border-radius: 8px; margin-top: 10px; }
    .success { background: #e7f7ef; color: #065f46; border: 1px solid #a7f3d0; padding: 10px; border-radius: 8px; margin-top: 10px; }
    .info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 10px; border-radius: 8px; margin-top: 10px; }
    .blink { animation: blink 1s steps(2, start) infinite; }
    @keyframes blink { to { visibility: hidden; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="card">
    <h2>Check Registration</h2>
    <div class="field">
      <label class="label" for="check_mobile">Enter your mobile number</label>
      <div class="row">
        <div class="prefix">+91</div>
        <input id="check_mobile" type="text" inputmode="numeric" maxlength="10" placeholder="10-digit mobile number">
      </div>
    </div>
    <div class="field">
      <label class="label" id="captchaLabel">Captcha: <?php echo (int)($_SESSION['captcha_chk_a'] ?? 0); ?> + <?php echo (int)($_SESSION['captcha_chk_b'] ?? 0); ?> = ?</label>
      <input id="captcha_input" type="text" inputmode="numeric" pattern="\\d+" placeholder="Your answer">
    </div>
    <button id="check_btn" type="button">Check</button>
    <div id="result" class="info" style="display:none"></div>
  </div>

  <script>
    const mobile = document.getElementById('check_mobile');
    const btn = document.getElementById('check_btn');
    const result = document.getElementById('result');
    const captchaInput = document.getElementById('captcha_input');
    const captchaLabel = document.getElementById('captchaLabel');
    if (mobile) {
      mobile.addEventListener('input', () => {
        mobile.value = mobile.value.replace(/\D+/g, '').slice(0, 10);
      });
    }
    if (captchaInput) {
      captchaInput.addEventListener('input', () => {
        captchaInput.value = captchaInput.value.replace(/\D+/g, '');
      });
    }
    // Captcha refresh removed per request; numbers set on page load by server
    async function doCheck() {
      const m = (mobile?.value || '').replace(/\D+/g, '');
      if (m.length !== 10) {
        result.className = 'info';
        result.textContent = 'Please enter a valid 10-digit mobile number.';
        result.style.display = '';
        return;
      }
      const cap = (captchaInput?.value || '').replace(/\D+/g, '');
      if (cap.length === 0) {
        result.className = 'info';
        result.textContent = 'Please solve the captcha.';
        result.style.display = '';
        return;
      }
      btn.disabled = true;
      result.className = 'info';
      result.textContent = 'Checking...';
      result.style.display = '';
      try {
        const resp = await fetch('check-registration.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'mobile=' + encodeURIComponent(m) + '&captcha=' + encodeURIComponent(cap)
        });
        const data = await resp.json();
        if (!data.ok) throw new Error(data.error || 'Unknown error');
        if (data.registered) {
          const ts = data.data?.created_at ? new Date(data.data.created_at.replace(' ', 'T')) : null;
          const formatted = ts ? ts.toLocaleString() : (data.data?.created_at || '');
          result.className = 'success';
          result.innerHTML = `Mobile <strong>${data.data.mobile}</strong> is already registered${formatted ? ' on ' + formatted : ''}.`;
        } else {
          result.className = 'info';
          result.innerHTML = 'Not registered yet. <a href="register.php" class="blink">You can proceed to register.</a>';
        }
      } catch (e) {
        result.className = 'errors';
        result.textContent = 'Could not check right now. Please try again.';
      } finally {
        btn.disabled = false;
      }
    }
    btn?.addEventListener('click', doCheck);
  </script>
</body>
</html>
