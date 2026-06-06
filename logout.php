<?php
session_start();

// Clear persistent login token
if (!empty($_COOKIE['remember_token'])) {
    require __DIR__ . '/db.php';
    try {
        $pdo->prepare('DELETE FROM remember_tokens WHERE token=?')
            ->execute([$_COOKIE['remember_token']]);
    } catch (Throwable $e) {}
    setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
}

session_destroy();
header("Location: login.php");
exit;
