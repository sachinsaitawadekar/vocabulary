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
    .card input {
      padding: 10px; 
      font-size: 16px; 
      width: 100%; 
      margin-bottom: 10px; 
      border: 1px solid #ccc; 
      border-radius: 8px;
    }
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
  <div class="card">
    <h2>Admin - Set Today's Word</h2>
    <?php if (!empty($message)) echo "<div class='msg'>$message</div>"; ?>
    <form method="POST">
      <input type="text" name="word" placeholder="Enter today's word" required>
      <button type="submit">Save</button>
    </form>
  </div>
</body>
</html>
