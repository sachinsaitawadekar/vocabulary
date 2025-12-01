<?php
require 'db.php';
header('Content-Type: text/html; charset=UTF-8');

// HTML escape helper with explicit UTF-8
if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$visibilitySettings = [
  'show_vocabulary' => 1,
  'show_idiom' => 1,
  'show_everyday' => 1,
  'show_contest_cta' => 1
];
try {
  $stmtSettings = $pdo->query("SELECT show_vocabulary, show_idiom, show_everyday, show_contest_cta FROM content_settings WHERE id = 1 LIMIT 1");
  $rowSettings = $stmtSettings->fetch();
  if ($rowSettings) {
    foreach ($visibilitySettings as $key => $default) {
      if (isset($rowSettings[$key])) {
        $visibilitySettings[$key] = (int)$rowSettings[$key] ? 1 : 0;
      }
    }
  }
} catch (Throwable $e) { /* table may not exist yet */ }

$date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");
$today = date('Y-m-d');
// Normalize incoming date and prevent browsing to future dates
if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) {
  $ts = strtotime((string)$date);
  $date = $ts ? date('Y-m-d', $ts) : $today;
}
if ($date > $today) { $date = $today; }

// Fetch today/selected date's word with optional marathi + example
$word = "No word set for this date!";
$marathi = null;
$hindi = null;
$example = null;
$prevDate = null;
$nextDate = null;
if (!empty($visibilitySettings['show_vocabulary'])) {
  try {
    $stmt = $pdo->prepare("SELECT word, marathi_translation, hindi_translation, example FROM vocabulary WHERE entry_date = :date");
    $stmt->execute(['date' => $date]);
    $row = $stmt->fetch();
    if ($row) {
      $word = $row['word'];
      $marathi = $row['marathi_translation'] ?? null;
      $hindi = $row['hindi_translation'] ?? null;
      $example = $row['example'] ?? null;
    }
  } catch (Throwable $e) {
    // Fallback if columns not present
    try {
      $stmt = $pdo->prepare("SELECT word FROM vocabulary WHERE entry_date = :date");
      $stmt->execute(['date' => $date]);
      $row = $stmt->fetch();
      if ($row) { $word = $row['word']; }
    } catch (Throwable $ignored) { /* ignore */ }
  }

  try {
    $prevStmt = $pdo->prepare("SELECT entry_date FROM vocabulary WHERE entry_date < :date ORDER BY entry_date DESC LIMIT 1");
    $prevStmt->execute(['date' => $date]);
    $prevDate = $prevStmt->fetchColumn();
  } catch (Throwable $e) { $prevDate = null; }

  try {
    $nextStmt = $pdo->prepare("SELECT entry_date FROM vocabulary WHERE entry_date > :date AND entry_date <= :today ORDER BY entry_date ASC LIMIT 1");
    $nextStmt->execute(['date' => $date, 'today' => $today]);
    $nextDate = $nextStmt->fetchColumn();
  } catch (Throwable $e) { $nextDate = null; }
}

$isToday = ($date === date('Y-m-d'));
$cardTitle = $isToday ? "Today's Word" : "Older Words";
$idiomTitle = $isToday ? "Today's Idiom" : "Older Idiom";
$dailyTitle = $isToday ? "Today's Everyday Essentials" : "Older Everyday Essentials";

// Fetch idiom for the date if table exists
$idiom = null; $idiomMarathi = null; $idiomHindi = null; $idiomExample = null;
if (!empty($visibilitySettings['show_idiom'])) {
  try {
    $stmtI = $pdo->prepare("SELECT idiom, marathi_translation, hindi_translation, example FROM idioms WHERE entry_date = :date");
    $stmtI->execute(['date' => $date]);
    $rowI = $stmtI->fetch();
    if ($rowI) {
      $idiom = $rowI['idiom'];
      $idiomMarathi = $rowI['marathi_translation'] ?? null;
      $idiomHindi = $rowI['hindi_translation'] ?? null;
      $idiomExample = $rowI['example'] ?? null;
    }
  } catch (Throwable $e) { /* table may not exist yet */ }
}

$dailyItem = null;
$dailyFallbackSvg = null;
if (!empty($visibilitySettings['show_everyday'])) {
  try {
    $stmtDaily = $pdo->prepare("SELECT category, name, marathi_translation, image_url FROM everyday_items WHERE entry_date = :date LIMIT 1");
    $stmtDaily->execute(['date' => $date]);
    $dailyItem = $stmtDaily->fetch();
  } catch (Throwable $e) { /* ignore if table missing */ }

  if ($dailyItem) {
    $categoryLabel = $dailyItem['category'] ?? '';
    $categorySvgMap = [
      'Vegetables' => '<svg viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="vegGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#bbf7d0"/><stop offset="100%" stop-color="#34d399"/></linearGradient></defs><rect width="140" height="140" rx="28" fill="url(#vegGrad)"/><path fill="#166534" d="M52 88c0-22 16-36 36-36s36 14 36 36-16 36-36 36S52 110 52 88z" opacity=".85"/><path fill="#22c55e" d="M78 90c-20 0-30-16-30-36 10-6 18-4 26 2 4-10 10-16 26-14 4 20-2 48-22 48z"/><circle cx="56" cy="52" r="18" fill="#16a34a" opacity=".8"/></svg>',
      'Fruits' => '<svg viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="fruitGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#fde68a"/><stop offset="100%" stop-color="#f97316"/></linearGradient></defs><rect width="140" height="140" rx="28" fill="url(#fruitGrad)"/><path fill="#f97316" d="M70 110c-18 0-34-14-34-32s14-32 34-32 34 14 34 32-16 32-34 32z" opacity=".9"/><path fill="#fb923c" d="M52 70c8-8 16-12 26-12s18 4 26 12c-6 10-16 20-30 20s-24-10-22-20z"/></svg>',
      'Kitchen Utensils' => '<svg viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="utGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#c7d2fe"/><stop offset="100%" stop-color="#6366f1"/></linearGradient></defs><rect width="140" height="140" rx="28" fill="url(#utGrad)"/><path fill="#312e81" d="M42 36h24v68H42z" opacity=".85"/><path fill="#4338ca" d="M74 44h24v60H74z"/><rect x="50" y="104" width="12" height="24" rx="5" fill="#312e81"/><rect x="90" y="104" width="12" height="24" rx="5" fill="#312e81"/></svg>',
      'Living Room Decor' => '<svg viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="lrGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#e0f2fe"/><stop offset="100%" stop-color="#60a5fa"/></linearGradient></defs><rect width="140" height="140" rx="28" fill="url(#lrGrad)"/><path fill="#2563eb" d="M50 98h40c10 0 18 8 18 18v8H32v-8c0-10 8-18 18-18z" opacity=".85"/><rect x="42" y="50" width="56" height="40" rx="10" fill="#1e40af" opacity=".9"/><rect x="52" y="60" width="36" height="20" rx="6" fill="#bfdbfe"/></svg>'
    ];
    $dailyFallbackSvg = $categorySvgMap[$categoryLabel] ?? $categorySvgMap['Vegetables'];
  }
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
  <title>Vocabulary</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh; min-height: 100dvh;
      font-family: Arial, sans-serif;
      background: #f5f7fb;
      display: flex;
      flex-direction: column;
    }
    .page-main {
      flex: 1;
      display: flex;
      justify-content: center;
      padding: 20px;
      text-align: center;
    }
    .container { width: 100%; max-width: 760px; margin: 0 auto; padding: 8px; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); padding: 24px; text-align: left; }
    .card-title { margin: 0 0 8px; font-weight: 700; color: #007BFF; font-size: 1.1rem; }
    .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .word-group { display: inline-flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .word { font-size: 2.5rem; line-height: 1.1; margin: 0; word-wrap: break-word; color: #111827; }
    .sound-btn {
      border: none;
      background: #eef2ff;
      color: #1d4ed8;
      border-radius: 50%;
      width: 42px;
      height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: transform 0.2s, background 0.2s;
    }
    .sound-btn:hover { background: #dbeafe; transform: translateY(-1px); }
    .sound-btn svg { width: 20px; height: 20px; fill: currentColor; }
    .date-chip { font-size: 0.95rem; color: #1f2937; background: #eef2ff; border: 1px solid #c7d2fe; padding: 6px 10px; border-radius: 999px; }
    .marathi { color: #1f2937; font-size: 1.05rem; margin-top: 8px; }
    .hindi { color: #1f2937; font-size: 1.05rem; margin-top: 8px; }
    .example { color: #374151; font-style: italic; margin-top: 14px; line-height: 1.6; }
    .value-box { display: inline-block; padding: 6px 10px; margin-left: 8px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; color: #111827; }
    .divider { height: 1px; background: #f3f4f6; margin: 16px 0; border: 0; }
    .pager { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
    .pager a { text-decoration: none; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; color: #111827; transition: background 0.2s, border-color 0.2s; font-size: 1rem; }
    .pager a:hover { background: #eef2ff; border-color: #c7d2fe; }
    .cta-bar {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 12px;
    }
    .cta-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: linear-gradient(135deg, #f97316, #ef4444);
      color: #fff;
      text-decoration: none;
      padding: 10px 16px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 1rem;
      box-shadow: 0 10px 20px rgba(239, 68, 68, 0.25);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .cta-btn.blink {
      animation: pulseBlink 1.2s ease-in-out infinite;
    }
    .cta-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 24px rgba(249, 115, 22, 0.3);
    }
    .cta-btn svg {
      width: 18px;
      height: 18px;
      fill: currentColor;
    }
    @keyframes pulseBlink {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.6; transform: scale(1.05); }
    }
    .daily-wrap {
      display: grid;
      grid-template-columns: minmax(200px, 280px) minmax(0, 1fr);
      gap: 28px;
      align-items: start;
      margin-top: 20px;
    }
    .daily-media {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 16px;
      border: 1px solid #dbeafe;
      border-radius: 16px;
      background: #fff;
      box-shadow: inset 0 0 0 1px rgba(59,130,246,0.08);
    }
    .daily-image {
      width: 100%;
      max-width: 280px;
      border-radius: 12px;
      box-shadow: 0 8px 18px rgba(15,23,42,0.12);
      object-fit: cover;
    }
    .daily-fallback {
      width: 100%;
      max-width: 280px;
      border-radius: 12px;
      overflow: hidden;
    }
    .daily-fallback svg { width: 100%; height: auto; display: block; }
    .daily-info { text-align: left; }
    .daily-info p { margin: 0 0 12px; font-size: 1.05rem; }
    .daily-meta { margin-top: 12px; font-size: 0.85rem; color: #64748b; }
    @media (max-width: 1024px) {
      .daily-wrap { grid-template-columns: minmax(180px, 240px) 1fr; gap: 20px; }
      .daily-image,
      .daily-fallback { max-width: 240px; }
    }
    @media (max-width: 768px) {
      .word { font-size: 2rem; }
      .marathi, .hindi { font-size: 1.05rem; }
      .pager a { font-size: 0.95rem; padding: 8px 12px; }
      .daily-wrap {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
        gap: 12px;
      }
      .daily-media { margin-top: 10px; }
      .daily-fallback, .daily-image { max-width: 260px; }
      .daily-info { text-align: center; }
      .daily-info p { margin-bottom: 10px; }
      .header .date-chip { margin-top: 8px; }
    }

    @media (max-width: 480px) { .word { font-size: 1.6rem; } .container { padding: 4px; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="container">
    <?php if (!empty($visibilitySettings['show_contest_cta'])): ?>
    <div class="cta-bar">
      <a class="cta-btn blink" href="contest-register.php">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l2.39 4.84 5.34.78-3.86 3.76.91 5.31L12 14.77l-4.78 2.52.91-5.31-3.86-3.76 5.34-.78z"/></svg>
        स्पर्धेत सहभागी व्हा
      </a>
    </div>
    <?php endif; ?>
    <?php if (!empty($visibilitySettings['show_vocabulary'])): ?>
    <section class="card">
      <div class="card-title"><?= htmlspecialchars($cardTitle) ?></div>
      <div class="header">
        <div class="word-group">
          <h1 class="word" aria-live="polite"><?= e($word) ?></h1>
          <button type="button" class="sound-btn" data-text="<?= htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Play vocabulary pronunciation">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5 9v6h3l4 4V5l-4 4H5zm11.5 3a2.5 2.5 0 00-1.5-2.292v4.584A2.5 2.5 0 0016.5 12zm-1.5-6.708v1.684a4.5 4.5 0 010 8.048v1.684A6.5 6.5 0 0018.5 12a6.5 6.5 0 00-3.5-6.708z"/></svg>
          </button>
        </div>
        <div class="date-chip">📅 <?= e($date) ?></div>
      </div>
      <?php if ($marathi): ?>
        <div class="marathi"><strong>मराठी अर्थ:</strong> <span class="value-box"><?= e($marathi) ?></span></div>
      <?php endif; ?>
      <?php if ($hindi): ?>
        <div class="hindi"><strong>हिंदी अर्थ:</strong> <span class="value-box"><?= e($hindi) ?></span></div>
      <?php endif; ?>
      <?php if ($example): ?>
        <div class="example"><strong>Sample Sentense:</strong> <span class="value-box">“<?= e($example) ?>”</span></div>
      <?php endif; ?>
      <hr class="divider" />
      <nav class="pager" aria-label="Word navigation">
        <?php if ($prevDate): ?>
          <a href="?date=<?= e($prevDate) ?>">⬅ Previous</a>
        <?php endif; ?>
        <?php if ($nextDate): ?>
          <a href="?date=<?= e($nextDate) ?>">Next ➡</a>
        <?php endif; ?>
      </nav>
    </section>
    <?php endif; ?>

    <?php if (!empty($visibilitySettings['show_idiom'])): ?>
    <section class="card" style="margin-top: 16px;">
      <div class="card-title"><?= htmlspecialchars($idiomTitle) ?></div>
      <?php if ($idiom): ?>
        <div class="header" style="justify-content: space-between; align-items: center;">
          <div class="word-group">
            <h2 class="word"><?= e($idiom) ?></h2>
            <button type="button" class="sound-btn" data-text="<?= htmlspecialchars($idiom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Play idiom pronunciation">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5 9v6h3l4 4V5l-4 4H5zm11.5 3a2.5 2.5 0 00-1.5-2.292v4.584A2.5 2.5 0 0016.5 12zm-1.5-6.708v1.684a4.5 4.5 0 010 8.048v1.684A6.5 6.5 0 0018.5 12a6.5 6.5 0 00-3.5-6.708z"/></svg>
            </button>
          </div>
          <div class="date-chip">📅 <?= e($date) ?></div>
        </div>
        <?php if ($idiomMarathi): ?>
          <div class="marathi"><strong>मराठी अर्थ:</strong> <span class="value-box"><?= e($idiomMarathi) ?></span></div>
        <?php endif; ?>
        <?php if ($idiomHindi): ?>
          <div class="hindi"><strong>हिंदी अर्थ:</strong> <span class="value-box"><?= e($idiomHindi) ?></span></div>
        <?php endif; ?>
        <?php if ($idiomExample): ?>
          <div class="example"><strong>Sample Sentense:</strong> <span class="value-box">“<?= e($idiomExample) ?>”</span></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="header" style="justify-content: space-between; align-items: center;">
          <h2 class="word" style="margin:0;">No idiom set for this date!</h2>
          <div class="date-chip">📅 <?= e($date) ?></div>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($visibilitySettings['show_everyday'])): ?>
    <section class="card" style="margin-top: 16px;">
      <div class="card-title"><?= htmlspecialchars($dailyTitle) ?></div>
      <?php if ($dailyItem): ?>
        <div class="header">
          <div class="word-group">
            <h1 class="word" aria-live="polite"><?= e($dailyItem['name']) ?></h1>
            <button type="button" class="sound-btn" data-text="<?= htmlspecialchars($dailyItem['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Play daily item pronunciation">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5 9v6h3l4 4V5l-4 4H5zm11.5 3a2.5 2.5 0 00-1.5-2.292v4.584A2.5 2.5 0 0016.5 12zm-1.5-6.708v1.684a4.5 4.5 0 010 8.048v1.684A6.5 6.5 0 0018.5 12a6.5 6.5 0 00-3.5-6.708z"/></svg>
            </button>
          </div>
          <div class="date-chip">📅 <?= e($date) ?></div>
        </div>
        <div class="daily-wrap">
          <div class="daily-media" aria-hidden="true">
            <?php if (!empty($dailyItem['image_url'])): ?>
              <img class="daily-image" src="<?= e($dailyItem['image_url']) ?>" alt="<?= e($dailyItem['name']) ?> illustration">
            <?php else: ?>
              <div class="daily-fallback"><?= $dailyFallbackSvg ?></div>
            <?php endif; ?>
          </div>
          <div class="daily-info">
            <?php if (!empty($dailyItem['marathi_translation'])): ?>
              <p><strong>मराठी अर्थ:</strong> <?= e($dailyItem['marathi_translation']) ?></p>
            <?php endif; ?>
            <?php if (!empty($dailyItem['category'])): ?>
              <p><strong>Category:</strong> <?= e($dailyItem['category']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="header" style="justify-content: space-between; align-items: center;">
          <h2 class="word" style="margin:0;">No daily item set for this date!</h2>
          <div class="date-chip">📅 <?= e($date) ?></div>
        </div>
        <p style="margin:0; color:#475569;">Daily item for this date is not available yet. Check back soon!</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    </div>
  </main>

  <script>
    (function(){
      if (!('speechSynthesis' in window)) {
        document.querySelectorAll('.sound-btn').forEach(btn => btn.style.display = 'none');
        return;
      }
      const synth = window.speechSynthesis;
      let speaking = false;
      document.querySelectorAll('.sound-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const text = btn.getAttribute('data-text') || '';
          if (!text.trim()) return;
          if (speaking && synth.cancel) {
            synth.cancel();
            speaking = false;
          }
          const utter = new SpeechSynthesisUtterance(text);
          utter.lang = 'en-US';
          utter.rate = 0.95;
          utter.onstart = () => speaking = true;
          utter.onend = utter.onerror = () => speaking = false;
          synth.speak(utter);
        });
      });
    })();
  </script>

  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
