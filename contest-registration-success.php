<?php
session_start();
$tz = 'Asia/Kolkata';
if (function_exists('date_default_timezone_set')) { date_default_timezone_set($tz); }
$data = $_SESSION['contest_registration_success'] ?? null;
if ($data) {
  unset($_SESSION['contest_registration_success']);
}
function e($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
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
  <title>Contest Entry Submitted - 3S English Academy</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh; min-height: 100dvh;
      font-family: Arial, sans-serif;
      background: #f5f5f5;
      display: flex;
      flex-direction: column;
    }
    .page-main {
      flex: 1;
      display: flex;
      justify-content: center;
    }
    .container {
      width: 100%;
      max-width: 720px;
      margin: 0 auto;
      padding: 16px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 16px;
    }
    .card {
      background: white;
      padding: 32px 28px;
      border-radius: 18px;
      box-shadow: 0 18px 45px rgba(15,23,42,0.12);
      width: 100%;
      max-width: 500px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .card::after {
      content: "";
      position: absolute;
      inset: -60px -80px auto auto;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, rgba(59,130,246,0) 65%);
      transform: rotate(25deg);
    }
    h2 {
      color: #1d4ed8;
      margin-top: 0;
      font-size: 1.9rem;
      letter-spacing: -0.5px;
    }
    .subheading {
      margin: 4px 0 10px;
      font-size: 1.05rem;
      color: #2563eb;
      font-weight: 600;
    }
    .marathi-line {
      margin: 2px 0 12px;
      font-size: 1.05rem;
      color: #1d4ed8;
      font-weight: 600;
    }
    p { color: #374151; }
    .meta {
      color: #64748b;
      margin-top: 10px;
      font-size: 0.95rem;
    }
    .badge {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: linear-gradient(135deg, #f97316, #ef4444);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 2rem;
      margin: 0 auto 18px;
      box-shadow: 0 12px 22px rgba(239,68,68,0.25);
      position: relative;
      z-index: 1;
    }
    .highlight {
      background: #fef3c7;
      color: #92400e;
      border-radius: 12px;
      padding: 12px 16px;
      margin: 18px 0;
      font-weight: 600;
      box-shadow: inset 0 0 0 1px rgba(250,204,21,0.4);
    }
    .highlight-marathi {
      background: #e0f2fe;
      color: #0f172a;
      box-shadow: inset 0 0 0 1px rgba(59,130,246,0.25);
    }
    .date-box {
      margin: 12px 0;
      padding: 12px 16px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(14,165,233,0.18));
      color: #1e3a8a;
      font-weight: 600;
      border: 1px solid rgba(14,165,233,0.25);
    }
    .date-box span {
      color: #0f172a;
    }
    .next-steps {
      list-style: none;
      padding: 0;
      margin: 24px 0 0;
      display: grid;
      gap: 16px;
      text-align: left;
    }
    .next-steps li {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px 18px;
      border-radius: 16px;
      background: linear-gradient(135deg, rgba(226,232,240,0.65), #e0f2fe);
      border: 1px solid rgba(37,99,235,0.18);
      box-shadow: 0 12px 24px rgba(15,23,42,0.08);
      color: #0f172a;
      font-size: 0.95rem;
      font-weight: 600;
      position: relative;
      overflow: hidden;
    }
    .next-steps li::after {
      content: "";
      position: absolute;
      inset: auto -70px -70px auto;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, rgba(59,130,246,0) 70%);
    }
    .step-icon {
      min-width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, #22c55e, #16a34a);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 1.35rem;
      box-shadow: 0 10px 18px rgba(34,197,94,0.28);
      position: relative;
      z-index: 1;
    }
    .step-body {
      position: relative;
      z-index: 1;
    }
    .step-body strong {
      display: block;
      margin-bottom: 6px;
    }
    .step-body small {
      display: block;
      margin-top: 4px;
      color: #475569;
      font-size: 0.9rem;
      font-weight: 500;
    }
    .cta-group {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 12px;
      margin-top: 24px;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #1d4ed8;
      color: #fff;
      text-decoration: none;
      padding: 10px 18px;
      border-radius: 999px;
      font-weight: 600;
      box-shadow: 0 12px 20px rgba(37,99,235,0.25);
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .btn:hover { background: #153ea5; transform: translateY(-1px); box-shadow: 0 16px 24px rgba(21,62,165,0.28); }
    .btn-secondary {
      background: #475569;
      box-shadow: 0 12px 20px rgba(71,85,105,0.25);
    }
    .btn-secondary:hover { background: #1e293b; box-shadow: 0 16px 24px rgba(30,41,59,0.3); }
    .contact-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: #22c55e;
      color: #fff;
      font-weight: 600;
      text-decoration: none;
      margin: 6px 0;
      box-shadow: 0 6px 12px rgba(34,197,94,0.25);
    }
    .contact-link:hover { background: #16a34a; }
    @media (max-width: 480px) {
      .card { padding: 28px 20px; }
      .next-steps li { font-size: 0.9rem; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="container">
    <div class="card">
      <?php if ($data): ?>
        <?php
          $contestDateLabel = '';
          if (!empty($data['contest_date'])) {
            $dateTs = strtotime($data['contest_date']);
            $contestDateLabel = $dateTs ? date('d F Y', $dateTs) : e($data['contest_date']);
          }
        ?>
        <h2>Entry Received 🎉</h2>
        <div class="badge">🥳</div>
        <p class="subheading">Contest entry confirmed!</p>
        <p class="marathi-line">स्पर्धेसाठी तुमची नोंदणी यशस्वी झाली आहे!</p>
        <p>Thank you<?= $data['name'] ? ' <strong>' . e($data['name']) . '</strong>' : '' ?>! Registered successfully. Wait for WhatsApp confirmation.</p>
        <p class="marathi-line">धन्यवाद<?= $data['name'] ? ' <strong>' . e($data['name']) . '</strong>' : '' ?>! नोंदणी यशस्वीरीत्या पूर्ण झाली आहे. WhatsApp द्वारे पुष्टीची प्रतीक्षा करा..</p>
        <div class="highlight">
          Watch for our WhatsApp update on <strong><?= e($data['mobile']) ?></strong> with the exact contest date &amp; time. Stay tuned!
        </div>
        <div class="highlight highlight-marathi">
          स्पर्धेची नेमकी तारीख व वेळ WhatsApp वर <strong><?= e($data['mobile']) ?></strong> या क्रमांकावर लवकरच कळवण्यात येईल. कृपया लक्ष ठेवा!
        </div>
        <?php if (!empty($data['location'])): ?>
          <div class="highlight" style="background:#f8fafc; color:#0f172a; border:1px solid #cbd5f5;">
            Registered from: <strong><?= e($data['location']) ?></strong><br>
            <span style="color:#1d4ed8;">📍 तुमचे ठिकाण: <strong><?= e($data['location']) ?></strong></span>
          </div>
        <?php endif; ?>
        <?php if ($contestDateLabel): ?>
          <div class="date-box">
            Your preferred contest date: <span><?= e($contestDateLabel) ?></span><br>
            तुमची निवडलेली स्पर्धेची तारीख: <span><?= e($contestDateLabel) ?></span>
          </div>
        <?php endif; ?>
        <p class="marathi-line">🎯 स्पर्धेसाठी नोंदणीबद्दल मनःपूर्वक आभार!</p>
        <?php
          $ts = strtotime($data['created_at']);
          $formatted = $ts ? date('d M Y, h:i A', $ts) : e($data['created_at']);
        ?>
        <div class="meta">Submitted on: <?= $formatted ?></div>
        <div class="meta" style="margin-top:4px;">सबमिट केलेली तारीख: <?= $formatted ?></div>
        <ul class="next-steps">
          <li>
            <div class="step-icon">✅</div>
            <div class="step-body">
              <strong>Save our contact so the WhatsApp message lands safely in your inbox.</strong>
              <div>
                <a class="contact-link" href="data:text/vcard;charset=utf-8,BEGIN:VCARD%0AVERSION:3.0%0AFN:3S%20English%20Classes%20Chiplun%0ATEL;TYPE=CELL:%2B918591388500%0AEND:VCARD%0A" download="3S-English-Classes-Chiplun.vcf">📇 Save 3S English Classes Chiplun</a>
              </div>
              <small>आमचा संपर्क क्रमांक सेव्ह करा (📲 <strong>+91 85913 88500</strong>) जेणेकरून WhatsApp संदेश नक्की मिळेल.</small>
            </div>
          </li>
          <li>
            <div class="step-icon">📅</div>
            <div class="step-body">
              <strong>Keep the slot free and polish your skills – the stage is set for you!</strong>
              <small>वेळ मोकळी ठेवा, तयारीला लागा – मंच तुमच्यासाठी सज्ज आहे!</small>
            </div>
          </li>
          <li>
            <div class="step-icon">🤝</div>
            <div class="step-body">
              <strong>Share the contest link with friends and cheer each other on.</strong>
              <small>स्पर्धेची लिंक मित्रांसोबत शेअर करा आणि एकमेकांना प्रोत्साहन द्या.</small>
            </div>
          </li>
        </ul>
        <div class="cta-group">
          <a class="btn btn-secondary" href="vocabulary.php">Explore Vocabulary</a>
          <a class="btn btn-secondary" href="vocabulary.php">नवीन शब्द पहा</a>
        </div>
      <?php else: ?>
        <h2>No Recent Entry</h2>
        <p>Please fill the contest form first.</p>
        <p class="marathi-line">कृपया प्रथम स्पर्धेसाठी नोंदणी फॉर्म भरा.</p>
        <div class="cta-group">
          <a class="btn" href="contest-register.php">Go to Contest Form</a>
          <a class="btn btn-secondary" href="contest-register.php">स्पर्धा फॉर्म भरा</a>
        </div>
      <?php endif; ?>
    </div>
    </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
