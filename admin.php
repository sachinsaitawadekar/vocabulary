<?php
require 'db.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date("Y-m-d");
    $type = $_POST['type'] ?? 'vocab';

    if ($type === 'vocab') {
        $word = trim($_POST['word'] ?? '');
        $marathi = trim($_POST['marathi'] ?? '');
        $example = trim($_POST['example'] ?? '');

        // Ensure columns exist for Marathi and example (idempotent)
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'marathi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN marathi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) { }
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'example'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN example TEXT NULL"); } } catch (Throwable $e) { }

        if ($word) {
            $stmt = $pdo->prepare("INSERT INTO vocabulary (word, marathi_translation, example, entry_date)
                                   VALUES (:word, :marathi, :example, :entry_date)
                                   ON DUPLICATE KEY UPDATE word = :word, marathi_translation = :marathi, example = :example");
            $stmt->execute(['word' => $word, 'marathi' => $marathi, 'example' => $example, 'entry_date' => $today]);
            $message = "✅ Today's vocabulary saved!";
        }
    } elseif ($type === 'idiom') {
        // Ensure idioms table exists
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS idioms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                idiom VARCHAR(255) NOT NULL,
                marathi_translation VARCHAR(255) NULL,
                example TEXT NULL,
                entry_date DATE NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) { }

        $idiom = trim($_POST['idiom'] ?? '');
        $imarathi = trim($_POST['idiom_marathi'] ?? '');
        $iexample = trim($_POST['idiom_example'] ?? '');
        if ($idiom) {
            $stmt = $pdo->prepare("INSERT INTO idioms (idiom, marathi_translation, example, entry_date)
                                   VALUES (:idiom, :marathi, :example, :entry_date)
                                   ON DUPLICATE KEY UPDATE idiom = :idiom, marathi_translation = :marathi, example = :example");
            $stmt->execute(['idiom' => $idiom, 'marathi' => $imarathi, 'example' => $iexample, 'entry_date' => $today]);
            $message_idiom = "✅ Today's idiom saved!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Admin - Vocabulary</title>
  <style>
    body {
      font-family: Arial, sans-serif; 
      display: flex; 
      justify-content: center; 
      align-items: center; 
      min-height: 100vh; 
      min-height: 100dvh; /* Better mobile vh */
      margin: 0;
      padding: 20px;
      background: #f5f5f5;
    }
    .card {
      background: #fff; 
      padding: 20px; 
      border: 1px solid #ddd; 
      border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      width: 100%; 
      max-width: 400px; 
      text-align: center;
    }
    .stack { display: flex; flex-direction: column; gap: 16px; width: 100%; max-width: 440px; }
    .card input, .card textarea {
      padding: 10px; 
      font-size: 16px; 
      width: 100%; 
      margin-bottom: 10px; 
      border: 1px solid #ccc; 
      border-radius: 8px;
    }
    .card textarea { min-height: 100px; resize: vertical; }
    .card button {
      padding: 10px; 
      font-size: 16px; 
      cursor: pointer; 
      border: none; 
      border-radius: 8px; 
      background: #007BFF; 
      color: white; 
      width: 100%;
      transition: background 0.3s;
    }
    .card button:hover {
      background: #0056b3;
    }
    .msg {
      color: green; 
      margin-bottom: 10px; 
      font-size: 0.95em;
    }
    @media (max-width: 480px) {
      .card { padding: 15px; }
      .card input, .card button { font-size: 14px; padding: 8px; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="stack">
    <div class="card">
      <h2>Admin - Set Today's Word</h2>
      <?php if (!empty($message)) echo "<div class='msg'>$message</div>"; ?>
      <form method="POST">
        <input type="hidden" name="type" value="vocab">
        <input type="text" name="word" placeholder="Enter today's word (English)" required>
        <input type="text" name="marathi" placeholder="Marathi translation (मराठी अर्थ)">
        <textarea name="example" placeholder="Example sentence (optional)"></textarea>
        <button type="submit">Save Word</button>
      </form>
    </div>

    <div class="card">
      <h2>Admin - Set Today's Idiom</h2>
      <?php if (!empty($message_idiom)) echo "<div class='msg'>$message_idiom</div>"; ?>
      <form method="POST">
        <input type="hidden" name="type" value="idiom">
        <input type="text" name="idiom" placeholder="Enter idiom (English)" required>
        <input type="text" name="idiom_marathi" placeholder="Marathi translation (मराठी अर्थ)">
        <textarea name="idiom_example" placeholder="Example sentence (optional)"></textarea>
        <button type="submit">Save Idiom</button>
      </form>
    </div>
  </div>
</body>
</html>
