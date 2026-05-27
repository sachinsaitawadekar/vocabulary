<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_role(array $roles) {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    if (!in_array($_SESSION['role'], $roles, true)) {
        header('Location: login.php?e=access');
        exit;
    }
}
