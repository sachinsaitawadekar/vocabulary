<?php
/**
 * Restores a user session from a 90-day persistent cookie.
 * Include this AFTER session_start() and BEFORE any auth check.
 */
if (!empty($_SESSION['user_id'])) return; // already logged in

$_cookie_token = $_COOKIE['remember_token'] ?? '';
if (strlen($_cookie_token) !== 64 || !ctype_xdigit($_cookie_token)) {
    if ($_cookie_token !== '') {
        // Malformed cookie — clear it
        setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
    }
    return;
}

if (!isset($pdo)) require_once __DIR__ . '/../db.php';

try {
    $stmt = $pdo->prepare(
        'SELECT rt.user_id, rt.token, u.role, u.full_name
         FROM remember_tokens rt
         JOIN users u ON u.id = rt.user_id
         WHERE rt.token = ? AND rt.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$_cookie_token]);
    $_rt_row = $stmt->fetch();

    if ($_rt_row) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$_rt_row['user_id'];
        $_SESSION['role']      = $_rt_row['role'];
        $_SESSION['full_name'] = $_rt_row['full_name'];

        // Rotate the token on each use to detect cookie theft
        $_new_token = bin2hex(random_bytes(32));
        $pdo->prepare(
            'UPDATE remember_tokens SET token=?, expires_at=DATE_ADD(NOW(), INTERVAL 90 DAY) WHERE token=?'
        )->execute([$_new_token, $_cookie_token]);

        setcookie('remember_token', $_new_token, [
            'expires'  => time() + 90 * 24 * 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
    } else {
        // Token expired or not found — wipe the cookie
        setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
    }
} catch (Throwable $e) {}

unset($_cookie_token, $_rt_row, $_new_token);
