<?php
session_start();
$filename = "vocabulary.txt";

// Simple admin password (you can change it)
$adminPassword = "admin123";

// If already logged in
if (isset($_SESSION["admin"]) && $_SESSION["admin"] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $newWord = trim($_POST["vocabulary"]);
        if (!empty($newWord)) {
            file_put_contents($filename, $newWord);
            $message = "Word updated successfully!";
        }
    }
    $currentWord = file_exists($filename) ? trim(file_get_contents($filename)) : "";
} else {
    // Handle login
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
    <title>Admin - Update Vocabulary</title>
</head>
<body>
<?php if (isset($_SESSION["admin"]) && $_SESSION["admin"] === true): ?>
    <h2>Update Vocabulary</h2>
    <?php if (!empty($message)) echo "<p style='color:green;'>$message</p>"; ?>
    <form method="post" action="">
        <label for="vocabulary">Word:</label>
        <input type="text" id="vocabulary" name="vocabulary" 
               value="<?php echo htmlspecialchars($currentWord); ?>" required>
        <button type="submit">Save</button>
    </form>
    <p><a href="index.php">Back to Home</a></p>
    <p><a href="logout.php">Logout</a></p>
<?php else: ?>
    <h2>Admin Login</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="post" action="">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Login</button>
    </form>
    <p><a href="index.php">Back to Home</a></p>
<?php endif; ?>
</body>
</html>
