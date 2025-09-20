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

    // Bulk CSV upload (exported from Excel)
    elseif ($type === 'bulk_vocab' || $type === 'bulk_idiom') {
        $forIdioms = ($type === 'bulk_idiom');
        $keyName = $forIdioms ? 'idiom' : 'word';
        $table = $forIdioms ? 'idioms' : 'vocabulary';

        // Ensure destination table/columns exist
        if ($forIdioms) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS idioms (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    idiom VARCHAR(255) NOT NULL,
                    marathi_translation VARCHAR(255) NULL,
                    example TEXT NULL,
                    entry_date DATE NOT NULL UNIQUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            } catch (Throwable $e) { }
        } else {
            try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'marathi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN marathi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) { }
            try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'example'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN example TEXT NULL"); } } catch (Throwable $e) { }
        }

        $summaryVar = $forIdioms ? 'bulk_message_idiom' : 'bulk_message_vocab';
        $$summaryVar = '';

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $$summaryVar = '❌ Upload failed. Please select a CSV file.';
        } else {
            $tmp = $_FILES['csv_file']['tmp_name'];
            $fp = @fopen($tmp, 'r');
            if (!$fp) {
                $$summaryVar = '❌ Could not read the uploaded file.';
            } else {
                $header = fgetcsv($fp, 0, ',', '"', '\\');
                if (!$header) {
                    $$summaryVar = '❌ CSV appears empty.';
                } else {
                    // Map headers (case-insensitive), handle UTF-8 BOM and common aliases
                    $map = [];
                    foreach ($header as $i => $h) {
                        if ($i === 0) { $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h); } // strip BOM
                        $norm = strtolower(trim((string)$h));
                        $norm = preg_replace('/[^a-z0-9]+/', '_', $norm);
                        $norm = trim($norm, '_');
                        if ($norm === 'entrydate' || $norm === 'date') { $norm = 'entry_date'; }
                        if ($norm === 'marathi') { $norm = 'marathi_translation'; }
                        $map[$norm] = $i;
                    }
                    $required = ['entry_date', $keyName];
                    foreach ($required as $req) {
                        if (!array_key_exists($req, $map)) {
                            $$summaryVar = '❌ Missing required column: ' . $req;
                            fclose($fp);
                            $fp = null;
                            break;
                        }
                    }
                    if ($fp) {
                        $count = 0; $skipped = 0; $updated = 0;
                        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
                            if (count($row) === 1 && trim($row[0]) === '') { continue; }
                            $get = function($name) use ($map, $row) {
                                $k = strtolower($name);
                                if (isset($map[$k])) return trim($row[$map[$k]]);
                                // allow alias 'marathi' for marathi_translation
                                if ($k === 'marathi_translation' && isset($map['marathi'])) return trim($row[$map['marathi']]);
                                return '';
                            };
                            $dateRaw = $get('entry_date');
                            $val = $get($keyName);
                            $mar = $get('marathi_translation');
                            $ex = $get('example');
                            if ($val === '' || $dateRaw === '') { $skipped++; continue; }

                            // Normalize date to Y-m-d (supports common Excel exports)
                            $date = false;
                            if (preg_match('~^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$~', $dateRaw, $m)) {
                                $date = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                            } elseif (preg_match('~^(\d{1,2})[-/](\d{1,2})[-/](\d{2,4})$~', $dateRaw, $m)) {
                                $y = (int)$m[3]; if ($y < 100) $y += 2000; $date = sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
                            } else {
                                $t = strtotime($dateRaw); if ($t) { $date = date('Y-m-d', $t); }
                            }
                            if (!$date) { $skipped++; continue; }

                            if ($forIdioms) {
                                $stmt = $pdo->prepare("INSERT INTO idioms (idiom, marathi_translation, example, entry_date)
                                                        VALUES (:v, :m, :e, :d)
                                                        ON DUPLICATE KEY UPDATE idiom = :v, marathi_translation = :m, example = :e");
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO vocabulary (word, marathi_translation, example, entry_date)
                                                        VALUES (:v, :m, :e, :d)
                                                        ON DUPLICATE KEY UPDATE word = :v, marathi_translation = :m, example = :e");
                            }
                            try {
                                $stmt->execute([':v' => $val, ':m' => $mar, ':e' => $ex, ':d' => $date]);
                                $count++;
                            } catch (PDOException $e) {
                                // Duplicate means updated
                                if (strpos($e->getMessage(), 'Duplicate') !== false || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                                    $updated++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        fclose($fp);
                        $$summaryVar = '✅ Processed: ' . $count . ' rows; Updated: ' . $updated . '; Skipped: ' . $skipped . '.';
                    }
                }
            }
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
    .stack { display: flex; flex-direction: column; gap: 16px; width: 100%; max-width: 480px; }
    .card input, .card textarea {
      padding: 10px; 
      font-size: 16px; 
      width: 100%; 
      margin-bottom: 10px; 
      border: 1px solid #ccc; 
      border-radius: 8px;
    }
    .note { font-size: 0.9rem; color: #4b5563; text-align: left; }
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

    <div class="card">
      <h2>Bulk Upload (CSV from Excel)</h2>
      <p class="note">Export from Excel as CSV (UTF‑8). Required columns:</p>
      <p class="note"><strong>Vocabulary:</strong> entry_date, word, marathi (or marathi_translation), example</p>
      <p class="note"><strong>Idioms:</strong> entry_date, idiom, marathi (or marathi_translation), example</p>
      <p class="note">Need a starting point? Download templates:
        <a href="template-vocabulary.php">Vocabulary CSV template</a> ·
        <a href="template-idioms.php">Idioms CSV template</a>
      </p>
      <?php if (!empty($bulk_message_vocab)) echo "<div class='msg'>$bulk_message_vocab</div>"; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="type" value="bulk_vocab">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Upload Vocabulary CSV</button>
      </form>
      <?php if (!empty($bulk_message_idiom)) echo "<div class='msg'>$bulk_message_idiom</div>"; ?>
      <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
        <input type="hidden" name="type" value="bulk_idiom">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Upload Idioms CSV</button>
      </form>
    </div>
  </div>
</body>
</html>
