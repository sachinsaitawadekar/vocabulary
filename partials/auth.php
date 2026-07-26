<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/remember_me.php';
require_once __DIR__ . '/csrf.php';

if (!empty($_SESSION['user_id'])) {
    if (!isset($pdo)) require_once __DIR__ . '/../db.php';
    try {
        $act = $pdo->prepare('SELECT is_active FROM users WHERE id = ? LIMIT 1');
        $act->execute([$_SESSION['user_id']]);
        if (!(int)$act->fetchColumn()) {
            session_destroy();
            header('Location: login.php?e=deactivated');
            exit;
        }
    } catch (Throwable $_ex) {}
}

function require_role(array $roles) {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php?e=login');
        exit;
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        header('Location: login.php?e=access');
        exit;
    }
}
