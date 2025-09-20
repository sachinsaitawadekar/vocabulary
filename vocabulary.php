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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vocabulary</title>
  <style>
    body {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      font-family: Arial, sans-serif;
      text-align: center;
      padding: 10px;
      background: #f5f5f5;
    }

    .word {
      font-size: 6vw; /* adjusts based on viewport width */
      margin: 20px 10px;
      word-wrap: break-word;
    }

    .date {
      font-size: 4vw; /* adjusts with viewport */
      color: #555;
      margin-bottom: 15px;
    }

    .nav {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 20px;
    }

    a {
      text-decoration: none;
      padding: 12px 18px;
      border: 1px solid #ddd;
      border-radius: 8px;
      background: #f8f8f8;
      color: #007BFF;
      font-weight: bold;
      font-size: 4vw; /* adjusts with viewport */
      transition: background 0.3s;
    }

    a:hover {
      background: #ddd;
    }

    /* Media Queries for very small screens */
    @media (max-width: 480px) {
      .word { font-size: 8vw; }
      .date { font-size: 5vw; }
      a { font-size: 5vw; padding: 10px 12px; }
    }

    @media (min-width: 481px) and (max-width: 768px) {
      .word { font-size: 7vw; }
      .date { font-size: 4.5vw; }
      a { font-size: 4.5vw; padding: 10px 15px; }
    }
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
