<?php
session_start();
$filename = "vocabulary.txt";
$adminPassword = "admin123";

if (isset($_SESSION["admin"]) && $_SESSION["admin"] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $newWord = trim($_POST["vocabulary"]);
        if (!empty($newWord)) {
            file_put_contents($filename, $newWord . PHP_EOL, FILE_APPEND);
            $message = "Word added successfully!";
        }
    }
    $words = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
} else {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["password"])) {
        if ($_POST["password"] === $adminPassword) {
            $_SESSION["admin"] = true;
            header("Location: admin.php");
            exit;
        } else {
            $error = "Incorrect password!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Vocabulary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f4f8;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }
        h2 { margin-bottom: 20px; color: #333; }
        input[type="text"], input[type="password"] {
            width: 90%;
            padding: 10px;
            margin: 12px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            background: #0073e6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover { background: #005bb5; }
        a {
            display: inline-block;
            margin-top: 15px;
            color: #0073e6;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
        .success { color: green; margin-bottom: 10px; }
        .error { color: red; margin-bottom: 10px; }
        ul {
            text-align: left;
            margin-top: 20px;
            padding-left: 20px;
        }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="box">
    <?php if (isset($_SESSION["admin"]) && $_SESSION["admin"] === true): ?>
        <h2>Add Vocabulary</h2>
        <?php if (!empty($message)) echo "<p class='success'>$message</p>"; ?>
        <form method="post" action="">
            <input type="text" name="vocabulary" placeholder="Enter new word" required>
            <button type="submit">Add</button>
        </form>
        <a href="index.php">Back to Home</a> | <a href="logout.php">Logout</a>
        <h3>All Words</h3>
        <ul>
            <?php foreach ($words as $i => $w): ?>
                <li><?php echo ($i+1) . ". " . htmlspecialchars($w); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <h2>Admin Login</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post" action="">
            <input type="password" name="password" placeholder="Enter password" required>
            <button type="submit">Login</button>
        </form>
        <a href="index.php">Back to Home</a>
    <?php endif; ?>
    </div>
</body>
</html>
