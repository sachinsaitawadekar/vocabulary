<?php
$filename = "vocabulary.txt";

// If file does not exist, create with default word
if (!file_exists($filename)) {
    file_put_contents($filename, "Hello\nWorld\nLearning");
}

// Load words into array
$words = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Total words
$total = count($words);

// Current word index (default 0)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 0) $id = 0;
if ($id >= $total) $id = $total - 1;

$currentWord = $words[$id];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vocabulary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .container {
            background: white;
            padding: 40px 60px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h2 { margin-bottom: 20px; color: #333; }
        .word {
            font-size: 2em;
            font-weight: bold;
            color: #0073e6;
            margin-bottom: 30px;
        }
        .nav {
            margin: 20px 0;
        }
        .nav a {
            display: inline-block;
            padding: 10px 20px;
            background: #0073e6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 0 10px;
        }
        .nav a:hover { background: #005bb5; }
        .disabled {
            background: #ccc !important;
            pointer-events: none;
        }
        .admin-link {
            margin-top: 20px;
            display: inline-block;
            color: #0073e6;
            text-decoration: none;
        }
        .admin-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Vocabulary</h2>
        <div class="word"><?php echo htmlspecialchars($currentWord); ?></div>
        <div class="nav">
            <a href="?id=<?php echo $id-1; ?>" class="<?php echo $id <= 0 ? 'disabled' : ''; ?>">Previous</a>
            <a href="?id=<?php echo $id+1; ?>" class="<?php echo $id >= $total-1 ? 'disabled' : ''; ?>">Next</a>
        </div>
        <a class="admin-link" href="admin.php">Admin Login</a>
    </div>
</body>
</html>
