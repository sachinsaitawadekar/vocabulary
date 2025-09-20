<?php
require 'db.php';

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
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="word"><?= htmlspecialchars($word) ?></div>
  <?php if ($marathi): ?>
    <div class="marathi">मराठी: <?= htmlspecialchars($marathi) ?></div>
  <?php endif; ?>
  <?php if ($example): ?>
    <div class="example">“<?= htmlspecialchars($example) ?>”</div>
  <?php endif; ?>
  <div class="date">📅 <?= htmlspecialchars($date) ?></div>
  <div class="nav">
    <?php if ($prevDate): ?>
      <a href="?date=<?= $prevDate ?>">⬅ Previous</a>
    <?php endif; ?>
    <?php if ($nextDate): ?>
      <a href="?date=<?= $nextDate ?>">Next ➡</a>
    <?php endif; ?>
  </div>
</body>
</html>
