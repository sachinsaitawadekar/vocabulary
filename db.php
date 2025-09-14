<?php
// Database connection
$host = "localhost";
$db   = "vocab";      // change to your DB name
$user = "vocab";      // DB username
$pass = "}K642-JYsDCMW";          // DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>
