<?php
session_start();
require __DIR__ . '/partials/auth.php';
require_role(['checker', 'admin']);
require __DIR__ . '/db.php';

$checker_id = (int)$_SESSION['user_id'];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function status_label(string $s): string {
    return match($s) {
        'needs_revision' => 'Needs Revision',
        'reviewed'       => 'Reviewed',
        'pending'        => 'Pending',
        default          => ucfirst(str_replace('_', ' ', $s)),
    };
}

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
} catch (Throwable $ex) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allocated_assignment_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        allocation_id INT NOT NULL,
        student_id INT NOT NULL,
        response TEXT NULL,
        file_path VARCHAR(500) NULL,
        original_filename VARCHAR(255) NULL,
        responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_response (allocation_id, student_id),
        INDEX idx_alloc (allocation_id),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $ex) {}

// Add columns to existing installations
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'file_path'");       if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN file_path VARCHAR(500) NULL AFTER response"); } catch (Throwable $ex) {}
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'original_filename'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN original_filename VARCHAR(255) NULL AFTER file_path"); } catch (Throwable $ex) {}
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'status'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN status ENUM('pending','needs_revision','reviewed') NOT NULL DEFAULT 'pending' AFTER original_filename"); } catch (Throwable $ex) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS allocated_response_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        response_id INT NOT NULL,
        checker_id INT NOT NULL,
        comment TEXT NOT NULL,
        commented_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_resp (response_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $ex) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $ex) {}

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
} catch (Throwable $ex) {}

try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignments LIKE 'allocated_group_id'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignments ADD COLUMN allocated_group_id INT NULL AFTER allocated_to"); } catch (Throwable $ex) {}

// Active panel
$panel = in_array($_GET['panel'] ?? '', ['review', 'allocate']) ? $_GET['panel'] : 'review';

// ── Allocation POST ───────────────────────────────────────────────
$alloc_msg = ''; $alloc_err = '';
if ($panel === 'allocate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'allocate') {
        $title              = trim($_POST['title']        ?? '');
        $type               = in_array($_POST['type'] ?? '', ['kahoot','paragraph','verb','other']) ? $_POST['type'] : 'other';
        $instructions       = trim($_POST['instructions'] ?? '');
        $allocated_group_id = ($_POST['allocated_group_id'] ?? '') === '' ? null : (int)$_POST['allocated_group_id'];
        if (!$title || !$instructions) {
            $alloc_err = 'Title and instructions are required.';
        } elseif (!$allocated_group_id) {
            $alloc_err = 'Please select a group to assign to.';
        } else {
            $pdo->prepare("INSERT INTO allocated_assignments (title, type, instructions, allocated_by, allocated_group_id) VALUES (?,?,?,?,?)")
                ->execute([$title, $type, $instructions, $checker_id, $allocated_group_id]);
            header('Location: checker-dashboard.php?panel=allocate&saved=alloc'); exit;
        }
    }

    if ($action === 'delete_allocation') {
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id) {
            $pdo->prepare('DELETE FROM allocated_assignments WHERE id = ?')->execute([$del_id]);
        }
        header('Location: checker-dashboard.php?panel=allocate&saved=del'); exit;
    }
}

// ── Review POST ───────────────────────────────────────────────────
if ($panel === 'review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $resp_id = (int)($_POST['response_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($resp_id && $comment !== '') {
        $pdo->prepare('INSERT INTO allocated_response_comments (response_id, checker_id, comment) VALUES (?, ?, ?)')
            ->execute([$resp_id, $checker_id, $comment]);
        $new_status = ($_POST['review_action'] ?? '') === 'needs_revision' ? 'needs_revision' : 'reviewed';
        $pdo->prepare("UPDATE allocated_assignment_responses SET status=? WHERE id=?")->execute([$new_status, $resp_id]);
        header('Location: checker-dashboard.php?panel=review&view=' . $resp_id . '&saved=1&status=' . $new_status);
        exit;
    }
}

// ── Review data ───────────────────────────────────────────────────
$filter  = in_array($_GET['filter'] ?? '', ['pending', 'reviewed', 'needs_revision']) ? $_GET['filter'] : 'all';
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

$where = $filter !== 'all' ? "WHERE r.status = " . $pdo->quote($filter) : '';
$submissions = $pdo->query(
    "SELECT r.id, r.status, r.responded_at, r.response,
            u.full_name AS student_name,
            aa.title, aa.type,
            COUNT(rc.id) AS comment_count
     FROM allocated_assignment_responses r
     JOIN users u ON u.id = r.student_id
     JOIN allocated_assignments aa ON aa.id = r.allocation_id
     LEFT JOIN allocated_response_comments rc ON rc.response_id = r.id
     $where
     GROUP BY r.id
     ORDER BY FIELD(r.status,'needs_revision','pending','reviewed'), r.responded_at DESC"
)->fetchAll();

$view_resp = null; $view_comments = [];
if ($view_id) {
    $vs = $pdo->prepare(
        "SELECT r.*, u.full_name AS student_name, aa.title AS task_title,
                aa.type AS task_type, aa.instructions AS task_instructions
         FROM allocated_assignment_responses r
         JOIN users u ON u.id = r.student_id
         JOIN allocated_assignments aa ON aa.id = r.allocation_id
         WHERE r.id = ?"
    );
    $vs->execute([$view_id]);
    $view_resp = $vs->fetch();
    if ($view_resp) {
        $cs = $pdo->prepare(
            'SELECT rc.comment, rc.commented_at, u.full_name AS checker_name
             FROM allocated_response_comments rc
             JOIN users u ON u.id = rc.checker_id
             WHERE rc.response_id = ? ORDER BY rc.commented_at ASC'
        );
        $cs->execute([$view_id]);
        $view_comments = $cs->fetchAll();
    }
}
$saved        = !empty($_GET['saved']) && $_GET['saved'] === '1';
$saved_status = $_GET['status'] ?? 'reviewed';

// ── Allocation data (ALL allocations, not just mine) ─────────────
$groups = [];
try { $groups = $pdo->query("SELECT id, name FROM student_groups ORDER BY id")->fetchAll(); } catch (Throwable $ex) {}

$all_allocations = [];
try {
    $all_allocations = $pdo->query(
        "SELECT aa.id, aa.title, aa.type, aa.allocated_group_id, aa.created_at,
                ub.full_name AS by_name, sg.name AS group_name,
                COUNT(DISTINCT r.id) AS response_count
         FROM allocated_assignments aa
         JOIN users ub ON ub.id = aa.allocated_by
         LEFT JOIN student_groups sg ON sg.id = aa.allocated_group_id
         LEFT JOIN allocated_assignment_responses r ON r.allocation_id = aa.id
         GROUP BY aa.id ORDER BY aa.created_at DESC"
    )->fetchAll();
} catch (Throwable $ex) {}

// Fetch all responses grouped by allocation_id
$responses_by_alloc = [];
try {
    $resp_rows = $pdo->query(
        "SELECT r.id AS resp_id, r.allocation_id, r.response, r.file_path, r.original_filename, r.responded_at,
                u.full_name AS student_name
         FROM allocated_assignment_responses r
         JOIN users u ON u.id = r.student_id
         ORDER BY r.responded_at ASC"
    )->fetchAll();
    foreach ($resp_rows as $rr) {
        $responses_by_alloc[$rr['allocation_id']][] = $rr;
    }
} catch (Throwable $ex) {}

$alloc_saved_msg = '';
if (isset($_GET['saved'])) {
    if ($_GET['saved'] === 'alloc') $alloc_saved_msg = '✅ Assignment allocated successfully.';
    if ($_GET['saved'] === 'del')   $alloc_saved_msg = '✅ Assignment deleted.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0QT95F62SG"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-0QT95F62SG');</script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Checker Dashboard - 3S English Academy</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:Arial,sans-serif; background:#f5f7fb; display:flex; flex-direction:column; }

    .page-main { flex:1; display:flex; flex-direction:column; align-items:center; padding:20px; }
    @media(max-width:600px){ .page-main{ padding:12px 10px; } }

    /* Panel switcher */
    .panel-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; width:100%; max-width:1080px; }
    .ptab { padding:9px 22px; border-radius:10px; text-decoration:none; font-weight:600; font-size:0.95rem; border:1px solid #e5e7eb; color:#374151; background:#fff; transition:all 0.15s; flex-shrink:0; }
    .ptab:hover  { background:#eef2ff; border-color:#c7d2fe; }
    .ptab.active { background:#007BFF; color:#fff; border-color:#007BFF; }

    /* Review layout */
    .layout { width:100%; max-width:1080px; display:grid; grid-template-columns:320px 1fr; gap:16px; align-items:start; }
    @media(max-width:900px){ .layout{ grid-template-columns:260px 1fr; gap:12px; } }
    @media(max-width:700px){ .layout{ grid-template-columns:1fr; } }
    @media(max-width:700px){
      .panel-detail { display:none; }
      .layout.has-detail .panel-list   { display:none; }
      .layout.has-detail .panel-detail { display:block; }
    }
    .mobile-back { display:none; align-items:center; gap:5px; color:#007BFF; text-decoration:none; font-size:0.9rem; font-weight:600; margin-bottom:14px; }
    @media(max-width:700px){ .mobile-back{ display:inline-flex; } }

    /* Cards */
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,0.05); padding:18px 20px; }
    .card h2 { margin:0 0 14px; color:#111827; font-size:1.05rem; }
    @media(max-width:480px){ .card{ padding:14px 16px; } }

    /* Filter tabs */
    .tabs { display:flex; gap:6px; margin-bottom:14px; overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:2px; }
    .tabs::-webkit-scrollbar { height:0; }
    .tab { flex-shrink:0; padding:5px 12px; border-radius:999px; font-size:0.82rem; font-weight:600; text-decoration:none; border:1px solid #e5e7eb; color:#374151; background:#f9fafb; white-space:nowrap; transition:all 0.15s; }
    .tab:hover  { background:#eef2ff; border-color:#c7d2fe; }
    .tab.active { background:#007BFF; color:#fff; border-color:#007BFF; }

    /* Assignment rows */
    .asgn-link { text-decoration:none; display:block; }
    .asgn-row  { display:flex; align-items:center; gap:8px; padding:10px 8px; border-radius:8px; transition:background 0.15s; }
    .asgn-row:not(:last-child) { border-bottom:1px solid #f3f4f6; border-radius:0; }
    .asgn-row:hover    { background:#f3f4f6; border-radius:8px !important; }
    .asgn-row.selected { background:#eff6ff; border-radius:8px !important; }
    .asgn-info  { flex:1; min-width:0; }
    .asgn-title { font-weight:600; color:#111827; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .asgn-meta  { font-size:0.82rem; color:#6b7280; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Badges */
    .badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; white-space:nowrap; flex-shrink:0; }
    .badge-pending        { background:#fef3c7; color:#92400e; }
    .badge-reviewed       { background:#d1fae5; color:#065f46; }
    .badge-needs_revision { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }

    /* Task type badges */
    .type-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; }
    .type-kahoot    { background:#e0f2fe; color:#0369a1; }
    .type-paragraph { background:#f3e8ff; color:#7c3aed; }
    .type-verb      { background:#d1fae5; color:#065f46; }
    .type-other     { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }

    /* Detail panel */
    .detail-empty  { display:flex; align-items:center; justify-content:center; min-height:200px; color:#9ca3af; font-size:0.95rem; text-align:center; padding:20px; }
    .detail-title  { margin:0 0 10px; font-size:1.15rem; color:#111827; font-weight:700; line-height:1.3; }
    .detail-meta   { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:14px; font-size:0.83rem; color:#6b7280; }
    .detail-desc   { color:#374151; line-height:1.65; margin-bottom:16px; font-size:0.92rem; }
    .dl-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb; color:#111827; text-decoration:none; font-size:0.87rem; font-weight:600; transition:background 0.2s; margin-bottom:18px; max-width:100%; }
    .dl-btn span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .dl-btn:hover { background:#e5e7eb; }
    .section-hd { margin:18px 0 10px; font-size:0.88rem; font-weight:700; color:#374151; padding-top:16px; border-top:1px solid #f3f4f6; }
    .section-hd:first-child { border-top:none; padding-top:0; margin-top:0; }
    .comment-bubble { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 14px; margin-bottom:8px; }
    .comment-bubble .who  { font-size:0.79rem; font-weight:700; color:#1d4ed8; }
    .comment-bubble .when { font-size:0.76rem; color:#9ca3af; margin-left:6px; }
    .comment-bubble .text { color:#1f2937; line-height:1.55; margin-top:4px; font-size:0.9rem; }
    .no-comments { color:#9ca3af; font-style:italic; font-size:0.87rem; margin-bottom:14px; }
    .add-comment textarea { width:100%; padding:10px 12px; font-size:0.92rem; border:1px solid #d1d5db; border-radius:8px; min-height:100px; resize:vertical; transition:border-color 0.2s; display:block; }
    .add-comment textarea:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .action-choice { display:flex; flex-direction:column; gap:8px; margin:12px 0; }
    .choice-opt { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border:2px solid #e5e7eb; border-radius:9px; cursor:pointer; transition:border-color 0.15s, background 0.15s; }
    .choice-opt:has(input:checked)        { border-color:#007BFF; background:#eff6ff; }
    .choice-opt.danger:has(input:checked) { border-color:#f97316; background:#fff7ed; }
    .choice-opt.is-checked        { border-color:#007BFF; background:#eff6ff; }
    .choice-opt.danger.is-checked { border-color:#f97316; background:#fff7ed; }
    .choice-opt input[type=radio]        { margin-top:3px; accent-color:#007BFF; flex-shrink:0; }
    .choice-opt.danger input[type=radio] { accent-color:#f97316; }
    .choice-opt .opt-label { font-weight:600; font-size:0.87rem; color:#111827; }
    .choice-opt .opt-desc  { font-size:0.79rem; color:#6b7280; margin-top:2px; line-height:1.4; }

    /* Allocate panel */
    .alloc-wrap { width:100%; max-width:1080px; display:grid; grid-template-columns:360px 1fr; gap:16px; align-items:start; }
    @media(max-width:860px){ .alloc-wrap{ grid-template-columns:1fr; } }

    .field { margin-bottom:14px; }
    .field label { display:block; font-weight:600; margin-bottom:6px; font-size:0.88rem; color:#374151; }
    .field input[type=text], .field textarea, .field select { width:100%; padding:9px 12px; font-size:0.92rem; border:1px solid #d1d5db; border-radius:8px; transition:border-color 0.2s; }
    .field input:focus, .field textarea:focus, .field select:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .field textarea { min-height:120px; resize:vertical; }
    .field .note { font-size:0.8rem; color:#6b7280; margin-top:4px; }

    /* Allocation table */
    .alloc-table-wrap { overflow-x:auto; border-radius:8px; border:1px solid #e5e7eb; }
    .alloc-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
    .alloc-table th { text-align:left; padding:9px 12px; background:#f9fafb; border-bottom:2px solid #e5e7eb; color:#374151; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.04em; white-space:nowrap; }
    .alloc-table td { padding:10px 12px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    @media(max-width:600px){ .alloc-table th, .alloc-table td { padding:8px; font-size:0.82rem; } }
    .alloc-table .expand-row td { background:#f0f7ff; padding:0; border-bottom:1px solid #e5e7eb; }
    .alloc-table tr:last-child td { border-bottom:none; }
    .alloc-to-all { color:#6b7280; font-style:italic; font-size:0.85rem; }

    /* Response bubbles inside expansion */
    .resp-expand { padding:14px 16px; }
    .resp-bubble { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; margin-bottom:8px; }
    .resp-bubble:last-child { margin-bottom:0; }
    .resp-bubble .resp-who  { font-size:0.8rem; font-weight:700; color:#1d4ed8; }
    .resp-bubble .resp-when { font-size:0.76rem; color:#9ca3af; margin-left:6px; }
    .resp-bubble .resp-text { color:#1f2937; font-size:0.88rem; line-height:1.55; margin-top:5px; white-space:pre-wrap; }
    .resp-bubble .resp-file { margin-top:6px; }
    .no-resp { color:#9ca3af; font-style:italic; font-size:0.85rem; }

    /* Buttons */
    .btn { display:inline-block; padding:10px 22px; font-size:0.9rem; font-weight:600; background:#007BFF; color:#fff; border:none; border-radius:8px; cursor:pointer; transition:background 0.2s; text-decoration:none; }
    .btn:hover { background:#0056b3; }
    .btn-full { width:100%; text-align:center; }
    .btn-sm   { padding:8px 14px; font-size:0.85rem; }
    .btn-ghost { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; font-size:0.82rem; padding:7px 12px; border-radius:6px; cursor:pointer; transition:background 0.15s; }
    .btn-ghost:hover { background:#e5e7eb; }
    .btn-danger { background:#ef4444; } .btn-danger:hover { background:#dc2626; }
    @media(max-width:480px){ .btn{ width:100%; text-align:center; } }

    .inline-form { display:contents; }
    .alert-ok   { background:#f0fdf4; color:#065f46; border:1px solid #a7f3d0; padding:9px 12px; border-radius:8px; margin-bottom:12px; font-size:0.9rem; }
    .alert-warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; padding:9px 12px; border-radius:8px; margin-bottom:12px; font-size:0.9rem; }
    .alert-err  { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:9px 12px; border-radius:8px; margin-bottom:12px; font-size:0.9rem; }
    .empty-state { text-align:center; color:#9ca3af; padding:28px 0; font-size:0.92rem; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">

    <div class="panel-tabs">
      <a href="?panel=review"   class="ptab <?= $panel === 'review'   ? 'active' : '' ?>">📋 Review Submissions</a>
      <a href="?panel=allocate" class="ptab <?= $panel === 'allocate' ? 'active' : '' ?>">📝 Allocate Assignment</a>
    </div>

    <?php if ($panel === 'review'): ?>
    <!-- ══ REVIEW PANEL ══ -->
    <div class="layout <?= $view_id ? 'has-detail' : '' ?>">

      <div class="card panel-list">
        <h2>📋 Submissions</h2>
        <div class="tabs">
          <a href="?panel=review&filter=all<?= $view_id ? '&view='.$view_id : '' ?>"            class="tab <?= $filter==='all'            ? 'active':'' ?>">All</a>
          <a href="?panel=review&filter=pending<?= $view_id ? '&view='.$view_id : '' ?>"        class="tab <?= $filter==='pending'        ? 'active':'' ?>">Pending</a>
          <a href="?panel=review&filter=needs_revision<?= $view_id ? '&view='.$view_id : '' ?>" class="tab <?= $filter==='needs_revision' ? 'active':'' ?>">⚠ Revision</a>
          <a href="?panel=review&filter=reviewed<?= $view_id ? '&view='.$view_id : '' ?>"       class="tab <?= $filter==='reviewed'       ? 'active':'' ?>">Reviewed</a>
        </div>
        <?php if ($submissions): ?>
          <?php foreach ($submissions as $s): ?>
            <a class="asgn-link" href="?panel=review&filter=<?= e($filter) ?>&view=<?= (int)$s['id'] ?>">
              <div class="asgn-row <?= $view_id === (int)$s['id'] ? 'selected' : '' ?>">
                <div class="asgn-info">
                  <div class="asgn-title"><?= e($s['title']) ?></div>
                  <div class="asgn-meta"><?= e($s['student_name']) ?> · <?= e(date('d M Y', strtotime($s['responded_at']))) ?> · 💬 <?= (int)$s['comment_count'] ?></div>
                </div>
                <span class="badge badge-<?= e($s['status']) ?>"><?= status_label($s['status']) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-state">No submissions found.</div>
        <?php endif; ?>
      </div>

      <div class="card panel-detail">
        <?php if ($view_resp): ?>
          <a class="mobile-back" href="?panel=review&filter=<?= e($filter) ?>">← Back to list</a>
          <?php if ($saved): ?>
            <?php if ($saved_status === 'needs_revision'): ?>
              <div class="alert-warn">⚠️ Feedback saved. Student has been asked to revise their response.</div>
            <?php else: ?>
              <div class="alert-ok">✅ Feedback saved and response marked as reviewed.</div>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Task context -->
          <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;margin-bottom:16px;">
            <div style="font-size:0.76rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px;">Task</div>
            <div style="font-weight:700;color:#111827;margin-bottom:6px;"><?= e($view_resp['task_title']) ?>
              <span class="type-badge type-<?= e($view_resp['task_type']) ?>" style="margin-left:6px;"><?= ucfirst(e($view_resp['task_type'])) ?></span>
            </div>
            <div style="color:#374151;font-size:0.88rem;line-height:1.55;"><?= nl2br(e($view_resp['task_instructions'])) ?></div>
          </div>

          <h2 class="detail-title">Response by <?= e($view_resp['student_name']) ?></h2>
          <div class="detail-meta">
            <span>📅 <?= e(date('d M Y, g:i A', strtotime($view_resp['responded_at']))) ?></span>
            <span class="badge badge-<?= e($view_resp['status']) ?>"><?= status_label($view_resp['status']) ?></span>
          </div>

          <?php if ($view_resp['response']): ?>
            <div class="detail-desc"><?= nl2br(e($view_resp['response'])) ?></div>
          <?php endif; ?>
          <?php if ($view_resp['file_path']): ?>
            <a href="download.php?type=task_response&id=<?= (int)$view_resp['id'] ?>" class="dl-btn">
              ⬇ <span><?= e($view_resp['original_filename'] ?? 'Download attachment') ?></span>
            </a>
          <?php endif; ?>

          <div class="section-hd">Feedback History</div>
          <?php if ($view_comments): ?>
            <?php foreach ($view_comments as $c): ?>
              <div class="comment-bubble">
                <span class="who"><?= e($c['checker_name']) ?></span>
                <span class="when"><?= e(date('d M Y, g:i A', strtotime($c['commented_at']))) ?></span>
                <div class="text"><?= nl2br(e($c['comment'])) ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="no-comments">No feedback yet.</div>
          <?php endif; ?>
          <div class="section-hd">Add Feedback</div>
          <div class="add-comment">
            <form method="POST">
              <input type="hidden" name="response_id" value="<?= (int)$view_resp['id'] ?>">
              <textarea name="comment" placeholder="Write your feedback here..." required></textarea>
              <div class="action-choice">
                <label class="choice-opt">
                  <input type="radio" name="review_action" value="reviewed" checked>
                  <div>
                    <div class="opt-label">✅ Mark as Reviewed</div>
                    <div class="opt-desc">Response is complete — student does not need to resubmit.</div>
                  </div>
                </label>
                <label class="choice-opt danger">
                  <input type="radio" name="review_action" value="needs_revision">
                  <div>
                    <div class="opt-label">⚠️ Needs Revision</div>
                    <div class="opt-desc">Student will be asked to address feedback and update their response.</div>
                  </div>
                </label>
              </div>
              <button class="btn" type="submit">Post Feedback</button>
            </form>
          </div>
        <?php else: ?>
          <div class="detail-empty">Select a submission from the list to review it.</div>
        <?php endif; ?>
      </div>

    </div><!-- /layout review -->

    <?php else: ?>
    <!-- ══ ALLOCATE PANEL ══ -->
    <div class="alloc-wrap">

      <!-- Create form -->
      <div class="card">
        <h2>📝 Create Assignment</h2>
        <?php if ($alloc_saved_msg) echo "<div class='alert-ok'>$alloc_saved_msg</div>"; ?>
        <?php if ($alloc_err) echo "<div class='alert-err'>" . e($alloc_err) . "</div>"; ?>
        <form method="POST">
          <input type="hidden" name="action" value="allocate">
          <div class="field">
            <label>Assignment Title *</label>
            <input type="text" name="title" placeholder="e.g. Paragraph writing on daily routine" required>
          </div>
          <div class="field">
            <label>Assignment Type *</label>
            <select name="type">
              <option value="kahoot">Kahoot</option>
              <option value="paragraph">Paragraph</option>
              <option value="verb">Verb</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="field">
            <label>Instructions / Content *</label>
            <textarea name="instructions" placeholder="Write the instructions, questions, or content for students..."></textarea>
          </div>
          <div class="field">
            <label>Assign To Group *</label>
            <select name="allocated_group_id" required>
              <option value="">Select Group *</option>
              <?php foreach ($groups as $g): ?>
                <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-full" type="submit">Allocate Assignment</button>
        </form>
      </div>

      <!-- All allocations + responses -->
      <div class="card">
        <h2>All Allocated Assignments</h2>
        <?php if ($all_allocations): ?>
        <div class="alloc-table-wrap">
          <table class="alloc-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Assigned To</th>
                <th>Created By</th>
                <th>Date</th>
                <th>Resp.</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_allocations as $al):
                $aid  = (int)$al['id'];
                $resps = $responses_by_alloc[$aid] ?? [];
                $rc   = count($resps);
              ?>
              <tr>
                <td style="font-weight:600;color:#111827;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($al['title']) ?></td>
                <td><span class="type-badge type-<?= e($al['type']) ?>"><?= ucfirst(e($al['type'])) ?></span></td>
                <td><?= $al['group_name'] ? '<span class="type-badge" style="background:#e0f2fe;color:#0369a1;">'.e($al['group_name']).'</span>' : '<span class="alloc-to-all">—</span>' ?></td>
                <td style="color:#374151;font-size:0.85rem;"><?= e($al['by_name']) ?></td>
                <td style="color:#6b7280;font-size:0.82rem;white-space:nowrap;"><?= e(date('d M Y', strtotime($al['created_at']))) ?></td>
                <td style="text-align:center;">
                  <button class="btn-ghost" onclick="toggleResp('resp-<?= $aid ?>')">
                    <?= $rc ?> <?= $rc === 1 ? 'resp' : 'resp' ?>
                  </button>
                </td>
                <td>
                  <form method="POST" class="inline-form" onsubmit="return confirm('Delete this assignment and all responses?')">
                    <input type="hidden" name="action" value="delete_allocation">
                    <input type="hidden" name="del_id" value="<?= $aid ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
              <tr class="expand-row" id="resp-<?= $aid ?>" style="display:none;">
                <td colspan="7">
                  <div class="resp-expand">
                    <?php if ($resps): ?>
                      <?php foreach ($resps as $rr): ?>
                      <div class="resp-bubble">
                        <span class="resp-who"><?= e($rr['student_name']) ?></span>
                        <span class="resp-when"><?= e(date('d M Y, g:i A', strtotime($rr['responded_at']))) ?></span>
                        <?php if ($rr['response']): ?>
                          <div class="resp-text"><?= nl2br(e($rr['response'])) ?></div>
                        <?php endif; ?>
                        <?php if ($rr['file_path']): ?>
                          <div class="resp-file">
                            <a href="download.php?type=task_response&id=<?= (int)$rr['resp_id'] ?>" class="dl-btn" style="margin-bottom:0;font-size:0.82rem;">
                              ⬇ <span><?= e($rr['original_filename'] ?? 'Download file') ?></span>
                            </a>
                          </div>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <div class="no-resp">No responses yet.</div>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="empty-state">No assignments allocated yet.</div>
        <?php endif; ?>
      </div>

    </div><!-- /alloc-wrap -->
    <?php endif; ?>

  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
  <script>
    function toggleResp(id) {
      var row = document.getElementById(id);
      if (!row) return;
      row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }
    // :has() fallback for radio choice opts
    function syncChoiceOpts() {
      document.querySelectorAll('.action-choice').forEach(function(g) {
        g.querySelectorAll('.choice-opt').forEach(function(o) {
          var r = o.querySelector('input[type=radio]');
          if (r) o.classList.toggle('is-checked', r.checked);
        });
      });
    }
    document.querySelectorAll('.choice-opt input[type=radio]').forEach(function(r) { r.addEventListener('change', syncChoiceOpts); });
    syncChoiceOpts();
  </script>
</body>
</html>
