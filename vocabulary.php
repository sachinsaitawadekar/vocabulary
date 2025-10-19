<?php
require 'db.php';
header('Content-Type: text/html; charset=UTF-8');

// HTML escape helper with explicit UTF-8
if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

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
$example = null;
try {
  $stmt = $pdo->prepare("SELECT word, marathi_translation, example FROM vocabulary WHERE entry_date = :date");
  $stmt->execute(['date' => $date]);
  $row = $stmt->fetch();
  if ($row) {
    $word = $row['word'];
    $marathi = $row['marathi_translation'] ?? null;
    $example = $row['example'] ?? null;
  }
} catch (Throwable $e) {
  // Fallback if columns not present
  $stmt = $pdo->prepare("SELECT word FROM vocabulary WHERE entry_date = :date");
  $stmt->execute(['date' => $date]);
  $row = $stmt->fetch();
  if ($row) { $word = $row['word']; }
}

// Get previous date
$prevStmt = $pdo->prepare("SELECT entry_date FROM vocabulary WHERE entry_date < :date ORDER BY entry_date DESC LIMIT 1");
$prevStmt->execute(['date' => $date]);
$prevDate = $prevStmt->fetchColumn();

// Get next date (never beyond today)
$nextStmt = $pdo->prepare("SELECT entry_date FROM vocabulary WHERE entry_date > :date AND entry_date <= :today ORDER BY entry_date ASC LIMIT 1");
$nextStmt->execute(['date' => $date, 'today' => $today]);
$nextDate = $nextStmt->fetchColumn();

$isToday = ($date === date('Y-m-d'));
$cardTitle = $isToday ? "Today's Word" : "Older Words";
$idiomTitle = $isToday ? "Today's Idiom" : "Older Idiom";

// Fetch idiom for the date if table exists
$idiom = null; $idiomMarathi = null; $idiomExample = null;
try {
  $stmtI = $pdo->prepare("SELECT idiom, marathi_translation, example FROM idioms WHERE entry_date = :date");
  $stmtI->execute(['date' => $date]);
  $rowI = $stmtI->fetch();
  if ($rowI) {
    $idiom = $rowI['idiom'];
    $idiomMarathi = $rowI['marathi_translation'] ?? null;
    $idiomExample = $rowI['example'] ?? null;
  }
} catch (Throwable $e) { /* table may not exist yet */ }
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
    .marathi { color: #1f2937; font-size: 1.15rem; margin-top: 12px; }
    .example { color: #374151; font-style: italic; margin-top: 14px; line-height: 1.6; }
    .value-box { display: inline-block; padding: 6px 10px; margin-left: 8px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; color: #111827; }
    .divider { height: 1px; background: #f3f4f6; margin: 16px 0; border: 0; }
    .pager { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
    .pager a { text-decoration: none; padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; color: #111827; transition: background 0.2s, border-color 0.2s; font-size: 1rem; }
    .pager a:hover { background: #eef2ff; border-color: #c7d2fe; }
    @media (max-width: 768px) {
      .word { font-size: 2rem; }
      .marathi { font-size: 1.05rem; }
      .pager a { font-size: 0.95rem; padding: 8px 12px; }
    }
    
    @media (max-width: 480px) { .word { font-size: 1.6rem; } .container { padding: 4px; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="container">
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
        <div class="marathi"><strong>अर्थ:</strong> <span class="value-box"><?= e($marathi) ?></span></div>
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
          <div class="marathi"><strong>अर्थ:</strong> <span class="value-box"><?= e($idiomMarathi) ?></span></div>
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
