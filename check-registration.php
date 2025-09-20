<?php
header('Content-Type: application/json');
session_start();

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// Validate captcha (simple math stored in session)
$captcha = isset($_POST['captcha']) ? trim((string)$_POST['captcha']) : '';
if ($captcha === '' || !isset($_SESSION['captcha_check_answer']) || (int)$captcha !== (int)$_SESSION['captcha_check_answer']) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid captcha']);
  exit;
}

// Normalize mobile: keep last 10 digits and prefix +91
$input = $_POST['mobile'] ?? '';
$digits = preg_replace('/\D+/', '', (string)$input);
if (strlen($digits) >= 10) {
  $last10 = substr($digits, -10);
} else {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid mobile number']);
  exit;
}
$mobileFull = '+91' . $last10;

// Connect DB
try {
  require __DIR__ . '/db.php';
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Database not configured']);
  exit;
}

try {
  // Ensure table exists (idempotent)
  $pdo->exec("CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $stmt = $pdo->prepare('SELECT full_name, mobile, created_at FROM registrations WHERE mobile = :m LIMIT 1');
  $stmt->execute([':m' => $mobileFull]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    echo json_encode([
      'ok' => true,
      'registered' => true,
      'data' => [
        'full_name' => $row['full_name'],
        'mobile' => $row['mobile'],
        'created_at' => $row['created_at'],
      ]
    ]);
  } else {
    echo json_encode(['ok' => true, 'registered' => false, 'mobile' => $mobileFull]);
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Query failed']);
}
