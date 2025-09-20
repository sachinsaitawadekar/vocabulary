<?php
// Database connection
$host = "localhost";
$db   = "vocab";      // change to your DB name
$user = "vocab";      // DB username
$pass = "}K642-JYsDCMW";          // DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
?>
