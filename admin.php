<?php
session_start();

$requestUri = $_SERVER['REQUEST_URI'] ?? 'admin.php';
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strncmp((string)$haystack, $needle, strlen($needle)) === 0;
    }
}
if (empty($_SESSION['is_admin'])) {
    if (stripos($requestUri, '://') !== false || str_starts_with($requestUri, '//')) {
        $requestUri = 'admin.php';
    }
    header('Location: login.php?redirect=' . rawurlencode($requestUri));
    exit;
}

require 'db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS everyday_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100) NOT NULL,
        name VARCHAR(150) NOT NULL,
        marathi_translation VARCHAR(150) NULL,
        image_url VARCHAR(255) NULL,
        entry_date DATE NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) { /* ignore */ }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        show_vocabulary TINYINT(1) NOT NULL DEFAULT 1,
        show_idiom TINYINT(1) NOT NULL DEFAULT 1,
        show_everyday TINYINT(1) NOT NULL DEFAULT 1,
        show_contest_cta TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("INSERT IGNORE INTO content_settings (id) VALUES (1)");
} catch (Throwable $e) { /* ignore */ }

try {
    $col = $pdo->query("SHOW COLUMNS FROM content_settings LIKE 'show_contest_cta'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE content_settings ADD COLUMN show_contest_cta TINYINT(1) NOT NULL DEFAULT 1");
    }
} catch (Throwable $e) { /* ignore */ }

// Align legacy columns with simplified structure
try {
    $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'marathi_translation'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE everyday_items ADD COLUMN marathi_translation VARCHAR(150) NULL");
    }
} catch (Throwable $e) { /* ignore */ }
try {
    $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'marathi_name'");
    if ($col->rowCount() > 0) {
        $pdo->exec("ALTER TABLE everyday_items CHANGE marathi_name marathi_translation VARCHAR(150) NULL");
    }
} catch (Throwable $e) { /* ignore */ }
try {
    $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'description'");
    if ($col->rowCount() > 0) {
        $pdo->exec("ALTER TABLE everyday_items DROP COLUMN description");
    }
} catch (Throwable $e) { /* ignore */ }
try {
    $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'created_at'");
    if ($col->rowCount() > 0) {
        $pdo->exec("ALTER TABLE everyday_items DROP COLUMN created_at");
    }
} catch (Throwable $e) { /* ignore */ }

$sanitize = static fn($value) => trim((string)$value);
$message_everyday = '';
$bulk_message_vocab = '';
$bulk_message_idiom = '';
$bulk_message_everyday = '';
$message_settings = '';
$visibilitySettings = [
    'show_vocabulary' => 1,
    'show_idiom' => 1,
    'show_everyday' => 1,
    'show_contest_cta' => 1
];
try {
    $stmt = $pdo->query("SELECT show_vocabulary, show_idiom, show_everyday, show_contest_cta FROM content_settings WHERE id = 1 LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $visibilitySettings = array_merge($visibilitySettings, array_intersect_key($row, $visibilitySettings));
    }
} catch (Throwable $e) { /* ignore */ }


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date("Y-m-d");
    $type = $_POST['type'] ?? 'vocab';

    if ($type === 'vocab') {
        $word = trim($_POST['word'] ?? '');
        $marathi = trim($_POST['marathi'] ?? '');
        $hindi = trim($_POST['hindi'] ?? '');
        $example = trim($_POST['example'] ?? '');

        // Ensure columns exist for Marathi, Hindi and example (idempotent)
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'marathi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN marathi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) { }
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'hindi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN hindi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) { }
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'example'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN example TEXT NULL"); } } catch (Throwable $e) { }

        if ($word) {
            $stmt = $pdo->prepare("INSERT INTO vocabulary (word, marathi_translation, hindi_translation, example, entry_date)
                                   VALUES (:word, :marathi, :hindi, :example, :entry_date)
                                   ON DUPLICATE KEY UPDATE word = :word, marathi_translation = :marathi, hindi_translation = :hindi, example = :example");
            $stmt->execute(['word' => $word, 'marathi' => $marathi, 'hindi' => $hindi, 'example' => $example, 'entry_date' => $today]);
            $message = "✅ Today's vocabulary saved!";
        }
    } elseif ($type === 'idiom') {
        // Ensure idioms table exists
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS idioms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                idiom VARCHAR(255) NOT NULL,
                marathi_translation VARCHAR(255) NULL,
                hindi_translation VARCHAR(255) NULL,
                example TEXT NULL,
                entry_date DATE NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) { }

        $idiom = trim($_POST['idiom'] ?? '');
        $imarathi = trim($_POST['idiom_marathi'] ?? '');
        $ihindi = trim($_POST['idiom_hindi'] ?? '');
        $iexample = trim($_POST['idiom_example'] ?? '');
        if ($idiom) {
            try { $col = $pdo->query("SHOW COLUMNS FROM idioms LIKE 'hindi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE idioms ADD COLUMN hindi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) { }
            $stmt = $pdo->prepare("INSERT INTO idioms (idiom, marathi_translation, hindi_translation, example, entry_date)
                                   VALUES (:idiom, :marathi, :hindi, :example, :entry_date)
                                   ON DUPLICATE KEY UPDATE idiom = :idiom, marathi_translation = :marathi, hindi_translation = :hindi, example = :example");
            $stmt->execute(['idiom' => $idiom, 'marathi' => $imarathi, 'hindi' => $ihindi, 'example' => $iexample, 'entry_date' => $today]);
            $message_idiom = "✅ Today's idiom saved!";
        }
    } elseif ($type === 'everyday') {
        $allowedCategories = ['Vegetables', 'Fruits', 'Kitchen Utensils', 'Living Room Decor'];
        $category = $sanitize($_POST['category'] ?? 'Vegetables');
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'Vegetables';
        }
        $itemName = $sanitize($_POST['item_name'] ?? '');
        $itemMarathi = $sanitize($_POST['item_marathi'] ?? '');
        $imageUrl = $sanitize($_POST['image_url'] ?? '');

        if ($itemName !== '') {
            $stmt = $pdo->prepare("INSERT INTO everyday_items (category, name, marathi_translation, image_url, entry_date)
                                   VALUES (:category, :name, :marathi, :image_url, :entry_date)
                                   ON DUPLICATE KEY UPDATE
                                     category = VALUES(category),
                                     name = VALUES(name),
                                     marathi_translation = VALUES(marathi_translation),
                                     image_url = VALUES(image_url)");
            $stmt->execute([
                ':category' => $category,
                ':name' => $itemName,
                ':marathi' => $itemMarathi !== '' ? $itemMarathi : null,
                ':image_url' => $imageUrl !== '' ? $imageUrl : null,
                ':entry_date' => $today
            ]);
            $message_everyday = "✅ Daily things vocabulary saved!";
        } else {
            $message_everyday = "⚠️ Please enter an item name.";
        }
    }

    // Toggle visibility of sections
    elseif ($type === 'visibility') {
        $showVocab = isset($_POST['show_vocabulary']) ? 1 : 0;
        $showIdiom = isset($_POST['show_idiom']) ? 1 : 0;
        $showEveryday = isset($_POST['show_everyday']) ? 1 : 0;
        $showContest = isset($_POST['show_contest_cta']) ? 1 : 0;
        try {
            $stmt = $pdo->prepare("UPDATE content_settings SET show_vocabulary = :sv, show_idiom = :si, show_everyday = :se, show_contest_cta = :sc WHERE id = 1");
            $stmt->execute([
                ':sv' => $showVocab,
                ':si' => $showIdiom,
                ':se' => $showEveryday,
                ':sc' => $showContest
            ]);
            $visibilitySettings['show_vocabulary'] = $showVocab;
            $visibilitySettings['show_idiom'] = $showIdiom;
            $visibilitySettings['show_everyday'] = $showEveryday;
            $visibilitySettings['show_contest_cta'] = $showContest;
            $message_settings = "✅ Display settings updated.";
        } catch (Throwable $e) {
            $message_settings = "❌ Unable to update display settings.";
        }
    }

    // Bulk CSV upload (exported from Excel)
    elseif ($type === 'bulk_vocab' || $type === 'bulk_idiom' || $type === 'bulk_everyday') {
        $forIdioms = $type === 'bulk_idiom';
        $forEveryday = $type === 'bulk_everyday';
        $keyName = $forIdioms ? 'idiom' : ($forEveryday ? 'name' : 'word');
        $table = $forIdioms ? 'idioms' : ($forEveryday ? 'everyday_items' : 'vocabulary');

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
        } elseif ($forEveryday) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS everyday_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category VARCHAR(100) NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    marathi_translation VARCHAR(150) NULL,
                    image_url VARCHAR(255) NULL,
                    entry_date DATE NOT NULL UNIQUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            } catch (Throwable $e) { }
        } else {
            try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'marathi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN marathi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) { }
            try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'example'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN example TEXT NULL"); } } catch (Throwable $e) { }
        }

        $summaryVar = $forIdioms ? 'bulk_message_idiom' : ($forEveryday ? 'bulk_message_everyday' : 'bulk_message_vocab');
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
                        if ($norm === 'hindi') { $norm = 'hindi_translation'; }
                        $map[$norm] = $i;
                    }
                    $required = ['entry_date', $keyName];
                    if ($forEveryday) {
                        $required[] = 'category';
                    }
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
                            $hin = $get('hindi_translation');
                            $ex = $get('example');
                            $cat = $get('category');
                            $img = $get('image_url');
                            if ($val === '' || $dateRaw === '') { $skipped++; continue; }
                            if ($forEveryday) {
                                if ($cat === '') { $skipped++; continue; }
                                $catKey = preg_replace('/[^a-z]+/', '', strtolower($cat));
                                $allowedCatMap = [
                                    'vegetables' => 'Vegetables',
                                    'fruits' => 'Fruits',
                                    'kitchenutensils' => 'Kitchen Utensils',
                                    'livingroomdecor' => 'Living Room Decor'
                                ];
                                if (!isset($allowedCatMap[$catKey])) {
                                    $skipped++;
                                    continue;
                                }
                                $cat = $allowedCatMap[$catKey];
                            }

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
                                $stmt = $pdo->prepare("INSERT INTO idioms (idiom, marathi_translation, hindi_translation, example, entry_date)
                                                        VALUES (:v, :m, :h, :e, :d)
                                                        ON DUPLICATE KEY UPDATE idiom = :v, marathi_translation = :m, hindi_translation = :h, example = :e");
                            } elseif ($forEveryday) {
                                $stmt = $pdo->prepare("INSERT INTO everyday_items (category, name, marathi_translation, image_url, entry_date)
                                                        VALUES (:c, :v, :m, :i, :d)
                                                        ON DUPLICATE KEY UPDATE
                                                            category = VALUES(category),
                                                            name = VALUES(name),
                                                            marathi_translation = VALUES(marathi_translation),
                                                            image_url = VALUES(image_url)");
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO vocabulary (word, marathi_translation, hindi_translation, example, entry_date)
                                                        VALUES (:v, :m, :h, :e, :d)
                                                        ON DUPLICATE KEY UPDATE word = :v, marathi_translation = :m, hindi_translation = :h, example = :e");
                            }
                            try {
                                if ($forEveryday) {
                                    $stmt->execute([
                                        ':c' => $cat,
                                        ':v' => $val,
                                        ':m' => $mar !== '' ? $mar : null,
                                        ':i' => $img !== '' ? $img : null,
                                        ':d' => $date
                                    ]);
                                } else {
                                    $stmt->execute([
                                        ':v' => $val,
                                        ':m' => $mar !== '' ? $mar : null,
                                        ':h' => $hin !== '' ? $hin : null,
                                        ':e' => $ex !== '' ? $ex : null,
                                        ':d' => $date
                                    ]);
                                }
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
  <title>Admin - Vocabulary</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      min-height: 100vh; min-height: 100dvh;
      background: #f5f5f5;
      display: flex;
      flex-direction: column;
    }
    .page-main {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
      max-width: 480px;
      margin-bottom: 12px;
      padding: 0 4px;
    }
    .logout-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid #1d4ed8;
      color: #1d4ed8;
      text-decoration: none;
      font-size: 0.95rem;
      transition: background 0.2s, color 0.2s;
    }
    .logout-link:hover { background: #1d4ed8; color: #fff; }
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
    .card select {
      padding: 10px;
      font-size: 16px;
      width: 100%;
      margin-bottom: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      background: #fff;
    }
    .note { font-size: 0.9rem; color: #4b5563; text-align: left; }
    .toggle-list {
      text-align: left;
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 14px;
    }
    .toggle-list label {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
      color: #1f2937;
    }
    .toggle-list input[type="checkbox"] {
      width: 18px;
      height: 18px;
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
  <main class="page-main">
  <div class="stack">
    <div class="header-bar" aria-label="Admin actions">
      <h1 style="margin:0; font-size:1.25rem; color:#1d4ed8;">Admin Portal</h1>
      <a class="logout-link" href="logout.php" rel="nofollow">⎋ Logout</a>
    </div>
    <div class="card">
      <h2>Admin - Set Today's Word</h2>
      <?php if (!empty($message)) echo "<div class='msg'>$message</div>"; ?>
      <form method="POST">
        <input type="hidden" name="type" value="vocab">
        <input type="text" name="word" placeholder="Enter today's word (English)" required>
        <input type="text" name="marathi" placeholder="Marathi translation (मराठी अर्थ)">
        <input type="text" name="hindi" placeholder="Hindi meaning (हिंदी अर्थ)">
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
        <input type="text" name="idiom_hindi" placeholder="Hindi meaning (हिंदी अर्थ)">
        <textarea name="idiom_example" placeholder="Example sentence (optional)"></textarea>
        <button type="submit">Save Idiom</button>
      </form>
    </div>

    <div class="card">
      <h2>Daily Things Vocabulary</h2>
      <?php if (!empty($message_everyday)) echo "<div class='msg'>$message_everyday</div>"; ?>
      <form method="POST">
        <input type="hidden" name="type" value="everyday">
        <select name="category" required>
          <option value="Vegetables">Vegetables</option>
          <option value="Fruits">Fruits</option>
          <option value="Kitchen Utensils">Kitchen Utensils</option>
          <option value="Living Room Decor">Living Room Decor</option>
        </select>
        <input type="text" name="item_name" placeholder="Item name (e.g., Spinach)" required>
        <input type="text" name="item_marathi" placeholder="Marathi translation (optional)">
        <input type="url" name="image_url" placeholder="Image URL (optional)">
        <button type="submit">Add Daily Item</button>
      </form>
    </div>

    <div class="card">
      <h2>Display Settings</h2>
      <?php if (!empty($message_settings)) echo "<div class='msg'>$message_settings</div>"; ?>
      <form method="POST">
        <input type="hidden" name="type" value="visibility">
        <div class="toggle-list">
          <label>
            <input type="checkbox" name="show_vocabulary" value="1" <?= !empty($visibilitySettings['show_vocabulary']) ? 'checked' : '' ?>>
            <span>Show Today's Word</span>
          </label>
          <label>
            <input type="checkbox" name="show_idiom" value="1" <?= !empty($visibilitySettings['show_idiom']) ? 'checked' : '' ?>>
            <span>Show Today's Idiom</span>
          </label>
          <label>
            <input type="checkbox" name="show_everyday" value="1" <?= !empty($visibilitySettings['show_everyday']) ? 'checked' : '' ?>>
            <span>Show Everyday Essentials</span>
          </label>
          <label>
            <input type="checkbox" name="show_contest_cta" value="1" <?= !empty($visibilitySettings['show_contest_cta']) ? 'checked' : '' ?>>
            <span>Show Contest Button</span>
          </label>
        </div>
        <button type="submit">Save Display Settings</button>
      </form>
    </div>

    <div class="card">
      <h2>Bulk Upload (CSV from Excel)</h2>
      <p class="note">Export from Excel as CSV (UTF‑8). Required columns:</p>
      <p class="note"><strong>Vocabulary:</strong> entry_date, word, marathi (or marathi_translation), hindi (or hindi_translation), example</p>
      <p class="note"><strong>Idioms:</strong> entry_date, idiom, marathi (or marathi_translation), hindi (or hindi_translation), example</p>
      <p class="note"><strong>Everyday Essentials:</strong> entry_date, category, name, marathi (or marathi_translation), image_url</p>
      <p class="note">Need a starting point? Download templates:
        <a href="template-vocabulary.php">Vocabulary CSV template</a> ·
        <a href="template-idioms.php">Idioms CSV template</a> ·
        <a href="template-everyday.php">Everyday essentials CSV template</a>
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
      <?php if (!empty($bulk_message_everyday)) echo "<div class='msg'>$bulk_message_everyday</div>"; ?>
      <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
        <input type="hidden" name="type" value="bulk_everyday">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Upload Everyday Essentials CSV</button>
      </form>
    </div>
  </div>
  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
