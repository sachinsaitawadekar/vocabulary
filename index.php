<?php
// File to store vocabulary
$filename = "vocabulary.txt";

// If file doesn't exist, create with default word
if (!file_exists($filename)) {
    file_put_contents($filename, "Hello");
}

// Read the word
$word = trim(file_get_contents($filename));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vocabulary</title>
</head>
<body>
    <h2>Today's Vocabulary:</h2>
    <p><strong><?php echo htmlspecialchars($word); ?></strong></p>
    <a href="admin.php">Admin Login</a>
</body>
</html>
