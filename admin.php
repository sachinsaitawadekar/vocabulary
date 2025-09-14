<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $word = trim($_POST['word']);
    $today = date("Y-m-d");

    if ($word) {
        $stmt = $pdo->prepare("INSERT INTO vocabulary (word, entry_date) 
                               VALUES (:word, :entry_date) 
                               ON DUPLICATE KEY UPDATE word = :word");
        $stmt->execute(['word' => $word, 'entry_date' => $today]);
        $message = "✅ Today's vocabulary saved!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin - Vocabulary</title>
  <style>
    body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; }
    .card { padding: 20px; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; }
    input { padding: 10px; font-size: 16px; width: 250px; margin-bottom: 10px; }
    button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
    .msg { color: green; margin-bottom: 10px; }
  </style>
</head>
<body>
  <div class="card">
    <h2>Admin - Set Today's Word</h2>
    <?php if (!empty($message)) echo "<div class='msg'>$message</div>"; ?>
    <form method="POST">
      <input type="text" name="word" placeholder="Enter today's word" required>
      <br>
      <button type="submit">Save</button>
    </form>
  </div>
</body>
</html>
