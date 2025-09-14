<?php
require 'db.php';

$date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

// Fetch today/selected date's word
$stmt = $pdo->prepare("SELECT word FROM vocabulary WHERE entry_date = :date");
$stmt->execute(['date' => $date]);
$row = $stmt->fetch();
$word = $row ? $row['word'] : "No word set for this date!";

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
<html>
<head>
  <title>Vocabulary</title>
  <style>
    body { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; font-family: Arial, sans-serif; }
    .word { font-size: 3em; margin: 20px; }
    .nav { margin-top: 20px; }
    a { text-decoration: none; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; margin: 0 5px; background: #f8f8f8; }
    a:hover { background: #ddd; }
    .date { font-size: 1.2em; color: #555; }
  </style>
</head>
<body>
  <div class="word"><?= htmlspecialchars($word) ?></div>
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
