<?php
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied. Please log in.');
}

require __DIR__ . '/db.php';

$type = $_GET['type'] ?? 'assignment';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); exit('Invalid request.'); }

if ($type === 'task_response') {
    // File attached to an allocated assignment response
    $stmt = $pdo->prepare('SELECT r.student_id, r.file_path, r.original_filename
                           FROM allocated_assignment_responses r WHERE r.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row || !$row['file_path']) { http_response_code(404); exit('File not found.'); }

    // Students may only download their own response file; checkers and admins may download all
    if ($_SESSION['role'] === 'student' && (int)$row['student_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403); exit('Access denied.');
    }

    $file_path = __DIR__ . '/uploads/assignments/' . basename($row['file_path']);
    $filename  = $row['original_filename'] ?: basename($row['file_path']);
} else {
    // Standard assignment submission file
    $stmt = $pdo->prepare('SELECT id, student_id, file_path, original_filename FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) { http_response_code(404); exit('File not found.'); }

    if ($_SESSION['role'] === 'student' && (int)$row['student_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403); exit('Access denied.');
    }

    $file_path = __DIR__ . '/uploads/assignments/' . basename($row['file_path']);
    $filename  = $row['original_filename'] ?: basename($row['file_path']);
}

if (!file_exists($file_path)) { http_response_code(404); exit('File not found on server.'); }

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_map = [
    'pdf'  => 'application/pdf',
    'txt'  => 'text/plain',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, no-cache');
readfile($file_path);
exit;
