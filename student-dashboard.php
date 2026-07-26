<?php
session_start();
require __DIR__ . '/partials/auth.php';
require_role(['student']);
require __DIR__ . '/db.php';

$student_id    = (int)$_SESSION['user_id'];
$respond_success = '';
$respond_error   = '';

// Ensure allocation tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allocated_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        type ENUM('kahoot','paragraph','verb','other') NOT NULL DEFAULT 'other',
        instructions TEXT NOT NULL,
        allocated_by INT NOT NULL,
        allocated_to INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_alloc_to (allocated_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allocated_assignment_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        allocation_id INT NOT NULL,
        student_id INT NOT NULL,
        response TEXT NULL,
        file_path VARCHAR(500) NULL,
        original_filename VARCHAR(255) NULL,
        status ENUM('pending','needs_revision','reviewed') NOT NULL DEFAULT 'pending',
        responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_response (allocation_id, student_id),
        INDEX idx_alloc (allocation_id),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {}

// Idempotent column additions for existing installations
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'file_path'");       if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN file_path VARCHAR(500) NULL AFTER response"); } catch (Throwable $e) {}
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'original_filename'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN original_filename VARCHAR(255) NULL AFTER file_path"); } catch (Throwable $e) {}
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'status'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN status ENUM('pending','needs_revision','reviewed') NOT NULL DEFAULT 'pending' AFTER original_filename"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE allocated_assignment_responses MODIFY COLUMN response TEXT NULL"); } catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        student_id INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_member (group_id, student_id),
        INDEX idx_group (group_id),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {}

try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignments LIKE 'allocated_group_id'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignments ADD COLUMN allocated_group_id INT NULL AFTER allocated_to"); } catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allocated_response_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        response_id INT NOT NULL,
        checker_id INT NOT NULL,
        comment TEXT NOT NULL,
        commented_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_resp (response_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ── POST: respond to allocated task ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'respond') {
    csrf_verify();
    $alloc_id = (int)($_POST['allocation_id'] ?? 0);
    $response = trim($_POST['response'] ?? '');

    $resp_file_path = null; $resp_filename = null;
    $resp_file = $_FILES['response_file'] ?? null;
    if ($resp_file && $resp_file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($resp_file['error'] !== UPLOAD_ERR_OK) {
            $respond_error = 'File upload failed (error ' . $resp_file['error'] . ').';
        } elseif ($resp_file['size'] > 10 * 1024 * 1024) {
            $respond_error = 'File too large. Maximum is 10 MB.';
        } else {
            $ext = strtolower(pathinfo($resp_file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','txt','jpg','jpeg','png'], true)) {
                $respond_error = 'Invalid file type. Allowed: PDF, TXT, JPG, PNG.';
            } else {
                $upload_dir = __DIR__ . '/uploads/assignments/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $unique = bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($resp_file['tmp_name'], $upload_dir . $unique)) {
                    $respond_error = 'Failed to save file. Please try again.';
                } else {
                    $resp_file_path = $unique;
                    $resp_filename  = basename($resp_file['name']);
                }
            }
        }
    }

    if (!$respond_error) {
        if (!$alloc_id) {
            $respond_error = 'Invalid request.';
        } elseif ($response === '' && !$resp_file_path) {
            $respond_error = 'Please write a response or attach a file.';
        } else {
            // Verify this allocation is actually assigned to the student's group
            $auth_check = $pdo->prepare(
                "SELECT 1 FROM allocated_assignments aa
                 JOIN student_group_members sgm ON sgm.group_id = aa.allocated_group_id
                 WHERE aa.id = ? AND sgm.student_id = ?
                 UNION
                 SELECT 1 FROM allocated_assignments WHERE id = ? AND allocated_to = ?
                 LIMIT 1"
            );
            $auth_check->execute([$alloc_id, $student_id, $alloc_id, $student_id]);
            if (!$auth_check->fetch()) {
                $respond_error = 'You are not authorised to respond to this assignment.';
            }
        }
        if (!$respond_error && $alloc_id) {
            $pdo->prepare(
                "INSERT INTO allocated_assignment_responses
                    (allocation_id, student_id, response, file_path, original_filename, status)
                 VALUES (?,?,?,?,?,'pending')
                 ON DUPLICATE KEY UPDATE
                    response=VALUES(response), file_path=VALUES(file_path),
                    original_filename=VALUES(original_filename),
                    status='pending', responded_at=NOW()"
            )->execute([$alloc_id, $student_id, $response ?: null, $resp_file_path, $resp_filename]);
            $respond_success = 'Response submitted! Your checker will review it shortly.';
        }
    }
}

// ── Fetch allocated tasks with response status ────────────────────
$tasks = [];
try {
    $ts = $pdo->prepare(
        "SELECT aa.id, aa.title, aa.type, aa.instructions, aa.created_at,
                ub.full_name AS by_name, sg.name AS group_name,
                r.id AS resp_id, r.response AS my_response, r.file_path AS my_file,
                r.original_filename AS my_filename, r.responded_at, r.status AS my_status
         FROM allocated_assignments aa
         JOIN users ub ON ub.id = aa.allocated_by
         JOIN users me ON me.id = ?
         LEFT JOIN student_groups sg ON sg.id = aa.allocated_group_id
         LEFT JOIN allocated_assignment_responses r
               ON r.allocation_id = aa.id AND r.student_id = ?
         WHERE aa.created_at >= me.created_at
           AND (
               aa.allocated_group_id IN (
                   SELECT group_id FROM student_group_members WHERE student_id = ?
               )
               OR aa.allocated_to = ?
           )
         ORDER BY aa.created_at DESC"
    );
    $ts->execute([$student_id, $student_id, $student_id, $student_id]);
    $tasks = $ts->fetchAll();
} catch (Throwable $e) {}

// ── Fetch checker comments on my responses ────────────────────────
$resp_comments = [];
try {
    $cc = $pdo->query(
        "SELECT rc.comment, rc.commented_at, u.full_name AS checker_name, r.allocation_id
         FROM allocated_response_comments rc
         JOIN allocated_assignment_responses r ON r.id = rc.response_id
         JOIN users u ON u.id = rc.checker_id
         WHERE r.student_id = $student_id
         ORDER BY rc.commented_at ASC"
    )->fetchAll();
    foreach ($cc as $c) { $resp_comments[$c['allocation_id']][] = $c; }
} catch (Throwable $e) {}

// ── Counts (all assigned tasks, not just responded) ───────────────
$counts = ['total' => 0, 'not_responded' => 0, 'pending' => 0, 'needs_revision' => 0, 'reviewed' => 0];
foreach ($tasks as $t) {
    $counts['total']++;
    if ($t['resp_id']) {
        $st = $t['my_status'] ?? 'pending';
        if (isset($counts[$st])) $counts[$st]++;
    } else {
        $counts['not_responded']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0QT95F62SG"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-0QT95F62SG');</script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>My Tasks - 3S English Academy</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:Arial,sans-serif; background:#f5f7fb; display:flex; flex-direction:column; }
    .page-main { flex:1; display:flex; justify-content:center; padding:20px; }
    @media(max-width:600px){ .page-main{ padding:12px 10px; } }
    .container { width:100%; max-width:760px; display:flex; flex-direction:column; gap:16px; }

    /* Cards */
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,0.05); padding:22px 24px; }
    .card h2 { margin:0 0 16px; color:#111827; font-size:1.2rem; }

    /* Summary strip */
    .summary-strip { display:flex; gap:10px; flex-wrap:wrap; }
    .stat-box { flex:1; min-width:90px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; text-align:center; }
    .stat-box .num { font-size:1.6rem; font-weight:700; line-height:1; }
    .stat-box .lbl { font-size:0.75rem; color:#6b7280; margin-top:3px; }
    .stat-box.revision .num { color:#c2410c; }
    .stat-box.revision { border-color:#fed7aa; background:#fff7ed; }

    /* Alerts */
    .alert-ok  { background:#f0fdf4; color:#065f46; border:1px solid #a7f3d0; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .alert-err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:10px 12px; border-radius:8px; margin-bottom:12px; }

    /* Badges */
    .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:0.78rem; font-weight:600; }
    .badge-pending        { background:#fef3c7; color:#92400e; }
    .badge-reviewed       { background:#d1fae5; color:#065f46; }
    .badge-needs_revision { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }

    /* Task type badges */
    .type-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; }
    .type-kahoot    { background:#e0f2fe; color:#0369a1; }
    .type-paragraph { background:#f3e8ff; color:#7c3aed; }
    .type-verb      { background:#d1fae5; color:#065f46; }
    .type-other     { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }

    /* Task rows */
    .task-list { display:flex; flex-direction:column; }
    .task-row { border-bottom:1px solid #f3f4f6; }
    .task-row:last-child { border-bottom:none; }
    .task-row-header { display:flex; align-items:center; gap:10px; padding:13px 4px; cursor:pointer; user-select:none; border-radius:8px; transition:background 0.15s; }
    .task-row-header:hover { background:#f9fafb; }
    .task-row.revision-row .task-row-header { background:#fff7ed; border-radius:8px; }
    .task-row.revision-row .task-row-header:hover { background:#ffedd5; }
    .task-row-title { font-weight:600; color:#111827; font-size:0.93rem; flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .task-row-meta  { font-size:0.78rem; color:#6b7280; white-space:nowrap; flex-shrink:0; }
    .task-chevron   { font-size:0.75rem; color:#9ca3af; transition:transform 0.2s; flex-shrink:0; }
    .task-row.open .task-chevron { transform:rotate(180deg); }
    .task-detail { display:none; padding:0 4px 14px; }
    .task-row.open .task-detail { display:block; }

    /* Detail sections */
    .detail-instructions { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:12px 14px; margin-bottom:12px; color:#374151; font-size:0.92rem; line-height:1.6; }
    .detail-instructions .instr-label { font-size:0.75rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; }

    /* Response section */
    .task-respond textarea { width:100%; padding:9px 12px; font-size:0.9rem; border:1px solid #d1d5db; border-radius:8px; min-height:80px; resize:vertical; transition:border-color 0.2s; box-sizing:border-box; }
    .task-respond textarea:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .task-respond .respond-actions { display:flex; gap:8px; align-items:center; margin-top:8px; flex-wrap:wrap; }
    .resp-tab { padding:8px 14px; border:1px solid #d1d5db; border-radius:6px; background:#f9fafb; font-size:0.85rem; cursor:pointer; transition:all 0.15s; min-height:36px; }
    .resp-tab.active { background:#007BFF; color:#fff; border-color:#007BFF; }

    /* Submitted response */
    .resp-done-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 14px; margin-bottom:10px; }
    .resp-done-box.revision { background:#fff7ed; border-color:#fed7aa; }
    .resp-status-bar { display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap; }
    .resp-label { font-size:0.78rem; font-weight:700; color:#374151; }
    .resp-date  { font-size:0.75rem; color:#9ca3af; }
    .resp-text  { color:#1f2937; font-size:0.9rem; line-height:1.55; white-space:pre-wrap; margin-bottom:6px; }

    /* Revision banner */
    .revision-banner { background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:10px 14px; margin-bottom:10px; }
    .revision-banner strong { color:#c2410c; display:block; margin-bottom:3px; font-size:0.88rem; }
    .revision-banner p { margin:0; color:#7c2d12; font-size:0.85rem; line-height:1.5; }

    /* Checker feedback */
    .feedback-section { margin-top:10px; }
    .feedback-section h4 { margin:0 0 7px; font-size:0.8rem; font-weight:700; color:#374151; }
    .comment-bubble { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:9px 12px; margin-bottom:6px; }
    .comment-bubble .who  { font-size:0.79rem; font-weight:700; color:#1d4ed8; }
    .comment-bubble .when { font-size:0.76rem; color:#9ca3af; margin-left:6px; }
    .comment-bubble .text { color:#1f2937; line-height:1.55; margin-top:4px; font-size:0.88rem; }

    /* Buttons */
    .btn { padding:11px 20px; font-size:0.92rem; font-weight:600; background:#007BFF; color:#fff; border:none; border-radius:8px; cursor:pointer; transition:background 0.2s; }
    .btn:hover { background:#0056b3; }
    .btn-sm { padding:8px 16px; font-size:0.85rem; }
    .btn-outline { background:#f3f4f6; color:#111827; border:1px solid #d1d5db; }
    .btn-outline:hover { background:#e5e7eb; }
    .btn-edit-resp { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; font-size:0.85rem; padding:8px 12px; border-radius:6px; cursor:pointer; min-height:36px; }
    .btn-edit-resp:hover { background:#e5e7eb; }

    .empty-state { text-align:center; color:#9ca3af; padding:32px 0; font-size:0.95rem; }
    @media(max-width:600px){ .card{ padding:16px 18px; } }
    @media(max-width:480px){ .card{ padding:14px 12px; } .summary-strip{ gap:8px; } .stat-box{ padding:10px 8px; } .task-row-meta{ display:none; } .btn{ width:100%; } }
    @media(max-width:360px){ .task-row-header{ gap:6px; padding:11px 2px; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="container">

      <?php if ($respond_success || $respond_error): ?>
      <div class="<?= $respond_success ? 'alert-ok' : 'alert-err' ?>" style="border-radius:14px;padding:14px 18px;">
        <?= e($respond_success ?: $respond_error) ?>
      </div>
      <?php endif; ?>

      <!-- Summary strip — always shown when tasks exist -->
      <?php if ($tasks): ?>
      <div class="card" style="padding:18px 22px;">
        <div class="summary-strip">
          <div class="stat-box">
            <div class="num"><?= $counts['total'] ?></div>
            <div class="lbl">Total Tasks</div>
          </div>
          <div class="stat-box">
            <div class="num" style="color:#374151;"><?= $counts['not_responded'] ?></div>
            <div class="lbl">Not Responded</div>
          </div>
          <div class="stat-box">
            <div class="num" style="color:#92400e;"><?= $counts['pending'] ?></div>
            <div class="lbl">Pending Review</div>
          </div>
          <?php if ($counts['needs_revision']): ?>
          <div class="stat-box revision">
            <div class="num"><?= $counts['needs_revision'] ?></div>
            <div class="lbl">Need Revision</div>
          </div>
          <?php endif; ?>
          <div class="stat-box">
            <div class="num" style="color:#065f46;"><?= $counts['reviewed'] ?></div>
            <div class="lbl">Reviewed</div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- My Tasks -->
      <div class="card">
        <h2>📝 My Tasks</h2>
        <?php if ($tasks): ?>
        <div class="task-list">
          <?php foreach ($tasks as $task): ?>
          <?php
            $tid           = (int)$task['id'];
            $hasResp       = $task['resp_id'] !== null;
            $status        = $task['my_status'] ?? 'pending';
            $comments      = $resp_comments[$tid] ?? [];
            $needsRevision = $hasResp && $status === 'needs_revision';
          ?>
          <div class="task-row <?= $needsRevision ? 'revision-row' : '' ?>" id="task-row-<?= $tid ?>">

            <!-- Clickable row header -->
            <div class="task-row-header" onclick="toggleTask(<?= $tid ?>)">
              <span class="type-badge type-<?= e($task['type']) ?>"><?= ucfirst(e($task['type'])) ?></span>
              <span class="task-row-title"><?= e($task['title']) ?></span>
              <?php if ($hasResp): ?>
                <span class="badge badge-<?= e($status) ?>">
                  <?= $status === 'needs_revision' ? '⚠️ Revision' : ($status === 'reviewed' ? '✅ Reviewed' : '🕐 Pending') ?>
                </span>
              <?php else: ?>
                <span class="badge" style="background:#e0f2fe;color:#0369a1;">Respond</span>
              <?php endif; ?>
              <span class="task-row-meta">by <?= e($task['by_name']) ?><?= $task['group_name'] ? ' · '.e($task['group_name']) : '' ?> · <?= e(date('d M Y', strtotime($task['created_at']))) ?></span>
              <span class="task-chevron">▼</span>
            </div>

            <!-- Collapsible detail panel -->
            <div class="task-detail">

              <!-- Instructions -->
              <div class="detail-instructions">
                <div class="instr-label">Instructions</div>
                <?= nl2br(e($task['instructions'])) ?>
              </div>

              <?php if ($hasResp): ?>
              <!-- Already responded -->
              <?php if ($needsRevision): ?>
              <div class="revision-banner">
                <strong>⚠️ Revision Requested</strong>
                <p>Your checker has reviewed this and requested changes. Read the feedback below and update your response.</p>
              </div>
              <?php endif; ?>

              <div class="resp-done-box <?= $needsRevision ? 'revision' : '' ?>">
                <div class="resp-status-bar">
                  <span class="resp-label">Your Response</span>
                  <span class="resp-date"><?= e(date('d M Y, g:i A', strtotime($task['responded_at']))) ?></span>
                </div>
                <?php if ($task['my_response']): ?>
                  <div class="resp-text"><?= nl2br(e($task['my_response'])) ?></div>
                <?php endif; ?>
                <?php if ($task['my_file']): ?>
                  <a href="download.php?type=task_response&id=<?= (int)$task['resp_id'] ?>" class="btn btn-sm btn-outline" style="text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:6px;">
                    ⬇ <?= e($task['my_filename'] ?? 'Download attachment') ?>
                  </a>
                <?php endif; ?>
              </div>

              <?php if ($comments): ?>
              <div class="feedback-section">
                <h4>Checker Feedback</h4>
                <?php foreach ($comments as $c): ?>
                <div class="comment-bubble">
                  <span class="who"><?= e($c['checker_name']) ?></span>
                  <span class="when"><?= e(date('d M Y, g:i A', strtotime($c['commented_at']))) ?></span>
                  <div class="text"><?= nl2br(e($c['comment'])) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

              <?php if ($status !== 'reviewed'): ?>
              <div style="margin-top:10px;">
                <button class="btn-edit-resp" onclick="toggleRespEdit(<?= $tid ?>)">
                  <?= $needsRevision ? '🔄 Update Response' : 'Edit Response' ?>
                </button>
                <form method="POST" enctype="multipart/form-data" id="resp-edit-<?= $tid ?>" style="display:none;margin-top:10px;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="respond">
                  <input type="hidden" name="allocation_id" value="<?= $tid ?>">
                  <label style="font-size:0.82rem;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Text Response</label>
                  <textarea name="response" placeholder="Write your response here..." style="width:100%;padding:9px 12px;font-size:0.9rem;border:1px solid #d1d5db;border-radius:8px;min-height:80px;resize:vertical;margin-bottom:8px;box-sizing:border-box;"><?= e($task['my_response'] ?? '') ?></textarea>
                  <label style="font-size:0.82rem;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Replace Attachment (optional)</label>
                  <input type="file" name="response_file" accept=".pdf,.txt,.jpg,.jpeg,.png" style="width:100%;font-size:0.85rem;margin-bottom:10px;">
                  <div style="display:flex;gap:8px;">
                    <button class="btn btn-sm" type="submit">Update Response</button>
                    <button class="btn-edit-resp" type="button" onclick="toggleRespEdit(<?= $tid ?>)">Cancel</button>
                  </div>
                </form>
              </div>
              <?php endif; ?>

              <?php else: ?>
              <!-- Not yet responded -->
              <div class="task-respond">
                <form method="POST" enctype="multipart/form-data">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="respond">
                  <input type="hidden" name="allocation_id" value="<?= $tid ?>">
                  <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
                    <button type="button" class="resp-tab active" onclick="switchRespTab(this,'text-<?= $tid ?>')">✏️ Type Response</button>
                    <button type="button" class="resp-tab" onclick="switchRespTab(this,'file-<?= $tid ?>')">📎 Upload File</button>
                    <button type="button" class="resp-tab" onclick="switchRespTab(this,'both-<?= $tid ?>')">Both</button>
                  </div>
                  <div id="text-<?= $tid ?>">
                    <textarea name="response" placeholder="Write your response here..." style="margin-bottom:8px;"></textarea>
                  </div>
                  <div id="file-<?= $tid ?>" style="display:none;">
                    <input type="file" name="response_file" accept=".pdf,.txt,.jpg,.jpeg,.png" disabled style="width:100%;font-size:0.88rem;margin-bottom:8px;">
                    <div style="font-size:0.78rem;color:#6b7280;">Allowed: PDF, TXT, JPG, PNG · Max 10 MB</div>
                  </div>
                  <div id="both-<?= $tid ?>" style="display:none;">
                    <textarea name="response" placeholder="Write your response here..." disabled style="margin-bottom:8px;"></textarea>
                    <input type="file" name="response_file" accept=".pdf,.txt,.jpg,.jpeg,.png" disabled style="width:100%;font-size:0.88rem;margin-bottom:4px;">
                    <div style="font-size:0.78rem;color:#6b7280;margin-bottom:8px;">Allowed: PDF, TXT, JPG, PNG · Max 10 MB</div>
                  </div>
                  <div class="respond-actions">
                    <button class="btn btn-sm" type="submit">Submit Response</button>
                  </div>
                </form>
              </div>
              <?php endif; ?>

            </div><!-- /task-detail -->
          </div><!-- /task-row -->
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <div class="empty-state">No tasks assigned to you yet. Check back soon!</div>
        <?php endif; ?>
      </div>

    </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
  <script>
    function toggleTask(id) {
      var row = document.getElementById('task-row-' + id);
      if (!row) return;
      var isOpen = row.classList.contains('open');
      document.querySelectorAll('.task-row.open').forEach(function(r) { r.classList.remove('open'); });
      if (!isOpen) row.classList.add('open');
    }
    function toggleRespEdit(id) {
      var form = document.getElementById('resp-edit-' + id);
      if (!form) return;
      form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    function switchRespTab(btn, showId) {
      var parent = btn.closest('form');
      parent.querySelectorAll('[id^="text-"],[id^="file-"],[id^="both-"]').forEach(function(d) {
        d.style.display = 'none';
        d.querySelectorAll('input,textarea').forEach(function(el) { el.disabled = true; });
      });
      parent.querySelectorAll('.resp-tab').forEach(function(b) { b.classList.remove('active'); });
      var target = document.getElementById(showId);
      target.style.display = 'block';
      target.querySelectorAll('input,textarea').forEach(function(el) { el.disabled = false; });
      btn.classList.add('active');
    }
  </script>
</body>
</html>
