<?php
require 'db.php';
header('Content-Type: text/html; charset=UTF-8');

// HTML escape helper with explicit UTF-8
if (!function_exists('e')) {
  function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

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

// Get next date
$nextStmt = $pdo->prepare("SELECT entry_date FROM vocabulary WHERE entry_date > :date ORDER BY entry_date ASC LIMIT 1");
$nextStmt->execute(['date' => $date]);
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Vocabulary</title>
  <style>
    body {
      display: flex; 
      flex-direction: column; 
      align-items: center; 
      justify-content: center; 
      min-height: 100vh; 
      min-height: 100dvh; /* Better fit on mobile browsers */
      font-family: Arial, sans-serif; 
      margin: 0; 
      padding: 20px;
      text-align: center;
    }
    .word {
      font-size: 3em; 
      margin: 20px; 
      word-wrap: break-word;
    }
    .date {
      font-size: 1.2em; 
      color: #555;
    }
    .marathi { color: #1f2937; font-size: 1.25em; margin-top: 6px; }
    .example { color: #374151; font-style: italic; margin-top: 10px; max-width: 720px; }
    .nav {
      margin-top: 20px; 
      display: flex; 
      gap: 10px; 
      flex-wrap: wrap;
    }
    .nav a {
      text-decoration: none; 
      padding: 10px 15px; 
      border: 1px solid #ddd; 
      border-radius: 8px; 
      background: #f8f8f8; 
      transition: background 0.3s;
      font-size: 1em;
    }
    .nav a:hover {
      background: #ddd;
    }
    @media (max-width: 768px) {
      .word { font-size: 2em; }
      .date { font-size: 1em; }
      .nav a { font-size: 0.9em; padding: 8px 12px; }
    }
    @media (max-width: 480px) {
      .word { font-size: 1.5em; }
      .date { font-size: 0.9em; }
      .nav { flex-direction: column; align-items: center; }
      .nav a { width: 100%; text-align: center; }
    }
  </style>
  <style>
    .container { width: 100%; max-width: 760px; margin: 0 auto; padding: 8px; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); padding: 24px; text-align: left; }
    .card-title { margin: 0 0 8px; font-weight: 700; color: #007BFF; font-size: 1.1rem; }
    .header { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
    .word { font-size: 2.5rem; line-height: 1.1; margin: 0; word-wrap: break-word; color: #111827; }
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
  <main class="container">
    <section class="card">
      <div class="card-title"><?= htmlspecialchars($cardTitle) ?></div>
      <div class="header">
        <h1 class="word"><?= e($word) ?></h1>
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
          <h2 class="word" style="font-size: 1.6rem; margin: 0;"><?= e($idiom) ?></h2>
          <div class="date-chip">📅 <?= e($date) ?></div>
        </div>
        <?php if ($idiomMarathi): ?>
          <div class="marathi"><strong>अर्थ:</strong> <span class="value-box"><?= e($idiomMarathi) ?></span></div>
        <?php endif; ?>
        <?php if ($idiomExample): ?>
          <div class="example"><strong>Sample Sentense:</strong> <span class="value-box">“<?= e($idiomExample) ?>”</span></div>
        <?php endif; ?>
      <?php else: ?>
        <div style="color:#6b7280;">No idiom set for this date.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
