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
$panel = in_array($_GET['panel'] ?? '', ['review', 'allocate', 'students', 'dashboard']) ? $_GET['panel'] : 'review';

// ── Allocation POST ───────────────────────────────────────────────
$alloc_msg = ''; $alloc_err = '';
if ($panel === 'allocate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
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
            $pdo->prepare('DELETE FROM allocated_assignments WHERE id = ? AND allocated_by = ?')->execute([$del_id, $checker_id]);
        }
        header('Location: checker-dashboard.php?panel=allocate&saved=del'); exit;
    }
}

// ── Review POST ───────────────────────────────────────────────────
if ($panel === 'review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
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

// ── Students list data ────────────────────────────────────────────
$st_search   = trim($_GET['sq'] ?? '');
$st_per_page = 10;
$st_page     = max(1, (int)($_GET['spage'] ?? 1));
$st_total    = 0;
$st_pages    = 1;
$students_list = [];
if ($panel === 'students') {
    try {
        $like = '%' . $st_search . '%';
        $cnt  = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE u.role='student' AND (u.full_name LIKE ? OR u.username LIKE ?)");
        $cnt->execute([$like, $like]);
        $st_total  = (int)$cnt->fetchColumn();
        $st_pages  = max(1, (int)ceil($st_total / $st_per_page));
        $st_page   = min($st_page, $st_pages);
        $st_offset = ($st_page - 1) * $st_per_page;
        $dst = $pdo->prepare(
            "SELECT u.id, u.full_name, u.username, u.created_at,
                    (SELECT sg.name FROM student_group_members sgm
                     JOIN student_groups sg ON sg.id = sgm.group_id
                     WHERE sgm.student_id = u.id LIMIT 1) AS group_name
             FROM users u WHERE u.role='student'
             AND (u.full_name LIKE ? OR u.username LIKE ?)
             ORDER BY u.full_name LIMIT ? OFFSET ?"
        );
        $dst->bindValue(1, $like, PDO::PARAM_STR);
        $dst->bindValue(2, $like, PDO::PARAM_STR);
        $dst->bindValue(3, $st_per_page, PDO::PARAM_INT);
        $dst->bindValue(4, $st_offset,   PDO::PARAM_INT);
        $dst->execute();
        $students_list = $dst->fetchAll();
    } catch (Throwable $ex) {}
}

// ── Dashboard report data ─────────────────────────────────────────
$dash_summary    = ['total_assignments' => 0, 'total_students' => 0, 'pending' => 0, 'needs_revision' => 0, 'reviewed' => 0];
$dash_by_student = [];
if ($panel === 'dashboard') {
    try {
        $srow = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM allocated_assignments)                                        AS total_assignments,
                (SELECT COUNT(*) FROM users WHERE role='student')                                   AS total_students,
                (SELECT COUNT(*) FROM allocated_assignment_responses WHERE status='pending')        AS pending,
                (SELECT COUNT(*) FROM allocated_assignment_responses WHERE status='needs_revision')  AS needs_revision,
                (SELECT COUNT(*) FROM allocated_assignment_responses WHERE status='reviewed')        AS reviewed"
        )->fetch();
        if ($srow) $dash_summary = $srow;
    } catch (Throwable $ex) {}
    try {
        $dash_by_student = $pdo->query(
            "SELECT u.id, u.full_name, u.username,
                    (SELECT sg.name FROM student_group_members sgm2
                     JOIN student_groups sg ON sg.id = sgm2.group_id
                     WHERE sgm2.student_id = u.id LIMIT 1) AS group_name,
                    SUM(CASE WHEN aa.id IS NOT NULL AND r.id IS NULL THEN 1 ELSE 0 END)          AS not_started,
                    SUM(CASE WHEN r.status = 'pending'        THEN 1 ELSE 0 END)                AS pending,
                    SUM(CASE WHEN r.status = 'needs_revision' THEN 1 ELSE 0 END)                AS needs_revision,
                    SUM(CASE WHEN r.status = 'reviewed'       THEN 1 ELSE 0 END)                AS reviewed
             FROM users u
             LEFT JOIN student_group_members sgm ON sgm.student_id = u.id
             LEFT JOIN allocated_assignments aa  ON aa.allocated_group_id = sgm.group_id AND aa.created_at >= u.created_at
             LEFT JOIN allocated_assignment_responses r ON r.allocation_id = aa.id AND r.student_id = u.id
             WHERE u.role = 'student'
             GROUP BY u.id
             ORDER BY u.full_name"
        )->fetchAll();
    } catch (Throwable $ex) {}
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

    /* Dashboard report */
    .rpt-table-wrap { overflow-x:auto; border-radius:8px; border:1px solid #e5e7eb; }
    .rpt-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
    .rpt-table th { text-align:left; padding:9px 12px; background:#f9fafb; border-bottom:2px solid #e5e7eb; color:#374151; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.04em; white-space:nowrap; }
    .rpt-table th.num, .rpt-table td.num { text-align:center; }
    .rpt-table td { padding:10px 12px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    .rpt-table tr:last-child td { border-bottom:none; }
    .rpt-table tr.total-row td { background:#f9fafb; font-weight:700; border-top:2px solid #e5e7eb; }
    @media(max-width:600px){ .rpt-table th, .rpt-table td { padding:8px 9px; font-size:0.82rem; } }
    .rpt-table th.sortable { cursor:pointer; user-select:none; white-space:nowrap; }
    .rpt-table th.sortable:hover { background:#eef2ff; color:#1d4ed8; }
    .rpt-table th.sort-asc  { background:#eff6ff; color:#1d4ed8; }
    .rpt-table th.sort-desc { background:#eff6ff; color:#1d4ed8; }
    .sort-icon { font-size:0.75rem; opacity:0.5; margin-left:3px; }
    .sort-asc  .sort-icon::after { content:'▲'; opacity:1; }
    .sort-desc .sort-icon::after { content:'▼'; opacity:1; }
    .sort-asc  .sort-icon, .sort-desc .sort-icon { font-size:0; }
    .num-pill { display:inline-block; min-width:28px; text-align:center; padding:2px 8px; border-radius:999px; font-size:0.78rem; font-weight:700; }
    .np-pending  { background:#fef3c7; color:#92400e; }
    .np-revision { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
    .np-reviewed { background:#d1fae5; color:#065f46; }
    .np-neutral  { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }

    /* Summary row above report */
    .dash-summary { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:18px; }
    @media(max-width:800px){ .dash-summary{ grid-template-columns:repeat(3,1fr); } }
    @media(max-width:480px){ .dash-summary{ grid-template-columns:repeat(2,1fr); gap:8px; } }
    .ds-cell { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; }
    .ds-cell .ds-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; margin-bottom:4px; }
    .ds-cell .ds-value { font-size:1.5rem; font-weight:800; color:#111827; line-height:1; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">

    <div class="panel-tabs">
      <a href="?panel=review"    class="ptab <?= $panel === 'review'    ? 'active' : '' ?>">📋 Review Submissions</a>
      <a href="?panel=allocate"  class="ptab <?= $panel === 'allocate'  ? 'active' : '' ?>">📝 Allocate Assignment</a>
      <a href="?panel=students"  class="ptab <?= $panel === 'students'  ? 'active' : '' ?>">👨‍🎓 Students</a>
      <a href="?panel=dashboard" class="ptab <?= $panel === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
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
              <?= csrf_input() ?>
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

    <?php elseif ($panel === 'allocate'): ?>
    <!-- ══ ALLOCATE PANEL ══ -->
    <div class="alloc-wrap">

      <!-- Create form -->
      <div class="card">
        <h2>📝 Create Assignment</h2>
        <?php if ($alloc_saved_msg) echo "<div class='alert-ok'>$alloc_saved_msg</div>"; ?>
        <?php if ($alloc_err) echo "<div class='alert-err'>" . e($alloc_err) . "</div>"; ?>
        <form method="POST">
          <?= csrf_input() ?>
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
                    <?= csrf_input() ?>
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

    <?php elseif ($panel === 'students'): ?>
    <!-- ══ STUDENTS PANEL ══ -->
    <div style="width:100%;max-width:1080px;">
      <div class="card">
        <h2>👨‍🎓 Students</h2>

        <!-- Search bar -->
        <form method="GET" id="st-search-form" style="margin-bottom:14px;">
          <input type="hidden" name="panel" value="students">
          <input type="hidden" name="spage" value="1">
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="st-search-input" name="sq"
                   value="<?= e($st_search) ?>"
                   placeholder="Search by name or username…"
                   autocomplete="off"
                   style="flex:1;padding:9px 12px;font-size:0.92rem;border:1px solid #d1d5db;border-radius:8px;transition:border-color 0.2s;min-width:0;">
            <?php if ($st_search): ?>
              <a href="?panel=students" style="padding:9px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:0.87rem;color:#374151;text-decoration:none;white-space:nowrap;background:#f9fafb;">✕ Clear</a>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($students_list): ?>
        <div style="overflow-x:auto;border-radius:8px;border:1px solid #e5e7eb;">
          <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
            <thead>
              <tr>
                <th style="text-align:left;padding:9px 12px;background:#f9fafb;border-bottom:2px solid #e5e7eb;color:#374151;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;">#</th>
                <th style="text-align:left;padding:9px 12px;background:#f9fafb;border-bottom:2px solid #e5e7eb;color:#374151;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">Name</th>
                <th style="text-align:left;padding:9px 12px;background:#f9fafb;border-bottom:2px solid #e5e7eb;color:#374151;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">Username</th>
                <th style="text-align:left;padding:9px 12px;background:#f9fafb;border-bottom:2px solid #e5e7eb;color:#374151;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">Group</th>
                <th style="text-align:left;padding:9px 12px;background:#f9fafb;border-bottom:2px solid #e5e7eb;color:#374151;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;">Joined</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students_list as $i => $st): ?>
              <tr>
                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;color:#9ca3af;font-size:0.82rem;"><?= $st_offset + $i + 1 ?></td>
                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;font-weight:600;color:#111827;"><?= e($st['full_name']) ?></td>
                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;color:#374151;font-size:0.85rem;"><?= e($st['username']) ?></td>
                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;">
                  <?php if ($st['group_name']): ?>
                    <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0369a1;"><?= e($st['group_name']) ?></span>
                  <?php else: ?>
                    <span style="color:#9ca3af;font-style:italic;font-size:0.82rem;">—</span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #f3f4f6;color:#6b7280;font-size:0.82rem;white-space:nowrap;"><?= e(date('d M Y', strtotime($st['created_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Footer: count + pagination -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:12px;">
          <div style="font-size:0.82rem;color:#9ca3af;">
            <?= $st_total ?> student<?= $st_total !== 1 ? 's' : '' ?> total
            <?php if ($st_search): ?>&nbsp;· filtered<?php endif; ?>
          </div>
          <?php if ($st_pages > 1):
            $st_q = $st_search ? '&sq=' . urlencode($st_search) : '';
            $st_win_start = max(1, $st_page - 2);
            $st_win_end   = min($st_pages, $st_page + 2);
          ?>
          <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
            <?php if ($st_page > 1): ?>
              <a href="?panel=students&spage=<?= $st_page-1 ?><?= $st_q ?>" style="padding:5px 11px;border:1px solid #e5e7eb;border-radius:6px;font-size:0.82rem;text-decoration:none;color:#374151;background:#fff;">‹</a>
            <?php endif; ?>
            <?php if ($st_win_start > 1): ?>
              <a href="?panel=students&spage=1<?= $st_q ?>" style="padding:5px 11px;border:1px solid #e5e7eb;border-radius:6px;font-size:0.82rem;text-decoration:none;color:#374151;background:#fff;">1</a>
              <?php if ($st_win_start > 2): ?><span style="padding:5px 4px;font-size:0.82rem;color:#9ca3af;">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $st_win_start; $p <= $st_win_end; $p++): ?>
              <a href="?panel=students&spage=<?= $p ?><?= $st_q ?>"
                 style="padding:5px 11px;border-radius:6px;font-size:0.82rem;text-decoration:none;<?= $p===$st_page ? 'background:#007BFF;color:#fff;border:1px solid #007BFF;' : 'border:1px solid #e5e7eb;color:#374151;background:#fff;' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($st_win_end < $st_pages): ?>
              <?php if ($st_win_end < $st_pages - 1): ?><span style="padding:5px 4px;font-size:0.82rem;color:#9ca3af;">…</span><?php endif; ?>
              <a href="?panel=students&spage=<?= $st_pages ?><?= $st_q ?>" style="padding:5px 11px;border:1px solid #e5e7eb;border-radius:6px;font-size:0.82rem;text-decoration:none;color:#374151;background:#fff;"><?= $st_pages ?></a>
            <?php endif; ?>
            <?php if ($st_page < $st_pages): ?>
              <a href="?panel=students&spage=<?= $st_page+1 ?><?= $st_q ?>" style="padding:5px 11px;border:1px solid #e5e7eb;border-radius:6px;font-size:0.82rem;text-decoration:none;color:#374151;background:#fff;">›</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <?php else: ?>
          <div class="empty-state"><?= $st_search ? 'No students match your search.' : 'No students found.' ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php elseif ($panel === 'dashboard'): ?>
    <!-- ══ DASHBOARD PANEL ══ -->
    <div style="width:100%;max-width:1080px;">
      <div class="card">
        <h2>📊 Dashboard</h2>

        <!-- Summary row -->
        <div class="dash-summary">
          <div class="ds-cell">
            <div class="ds-label">Assignments</div>
            <div class="ds-value"><?= (int)$dash_summary['total_assignments'] ?></div>
          </div>
          <div class="ds-cell">
            <div class="ds-label">Students</div>
            <div class="ds-value"><?= (int)$dash_summary['total_students'] ?></div>
          </div>
          <div class="ds-cell">
            <div class="ds-label">Pending</div>
            <div class="ds-value" style="color:#92400e;"><?= (int)$dash_summary['pending'] ?></div>
          </div>
          <div class="ds-cell">
            <div class="ds-label">In Revision</div>
            <div class="ds-value" style="color:#c2410c;"><?= (int)$dash_summary['needs_revision'] ?></div>
          </div>
          <div class="ds-cell">
            <div class="ds-label">Reviewed</div>
            <div class="ds-value" style="color:#065f46;"><?= (int)$dash_summary['reviewed'] ?></div>
          </div>
        </div>

        <!-- Per-student breakdown -->
        <div style="font-size:0.82rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Breakdown by Student</div>
        <?php if ($dash_by_student): ?>
        <div class="rpt-table-wrap">
          <table class="rpt-table" id="dash-student-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Student</th>
                <th>Username</th>
                <th>Group</th>
                <th class="num sortable" data-col="4">Not Started <span class="sort-icon">⇅</span></th>
                <th class="num sortable" data-col="5">Pending <span class="sort-icon">⇅</span></th>
                <th class="num sortable" data-col="6">In Revision <span class="sort-icon">⇅</span></th>
                <th class="num sortable" data-col="7">Reviewed <span class="sort-icon">⇅</span></th>
              </tr>
            </thead>
            <tbody>
              <?php
                $tot_not_started = $tot_pend = $tot_rev_need = $tot_rev = 0;
                foreach ($dash_by_student as $ri => $row):
                  $tot_not_started += (int)$row['not_started'];
                  $tot_pend     += (int)$row['pending'];
                  $tot_rev_need += (int)$row['needs_revision'];
                  $tot_rev      += (int)$row['reviewed'];
              ?>
              <tr>
                <td style="color:#9ca3af;font-size:0.8rem;"><?= $ri + 1 ?></td>
                <td style="font-weight:600;color:#111827;"><?= e($row['full_name']) ?></td>
                <td style="color:#374151;font-size:0.85rem;"><?= e($row['username']) ?></td>
                <td>
                  <?php if ($row['group_name']): ?>
                    <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:0.75rem;font-weight:600;background:#e0f2fe;color:#0369a1;"><?= e($row['group_name']) ?></span>
                  <?php else: ?>
                    <span style="color:#9ca3af;font-style:italic;font-size:0.82rem;">—</span>
                  <?php endif; ?>
                </td>
                <td class="num"><span class="num-pill np-neutral"><?= (int)$row['not_started'] ?></span></td>
                <td class="num"><span class="num-pill np-pending"><?= (int)$row['pending'] ?></span></td>
                <td class="num"><span class="num-pill np-revision"><?= (int)$row['needs_revision'] ?></span></td>
                <td class="num"><span class="num-pill np-reviewed"><?= (int)$row['reviewed'] ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="total-row">
                <td colspan="4" style="text-align:right;font-size:0.82rem;color:#6b7280;padding-right:16px;">Total</td>
                <td class="num"><span class="num-pill np-neutral"><?= $tot_not_started ?></span></td>
                <td class="num"><span class="num-pill np-pending"><?= $tot_pend ?></span></td>
                <td class="num"><span class="num-pill np-revision"><?= $tot_rev_need ?></span></td>
                <td class="num"><span class="num-pill np-reviewed"><?= $tot_rev ?></span></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php else: ?>
          <div class="empty-state">No students found.</div>
        <?php endif; ?>
      </div>
    </div>

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
    // Dashboard table column sort
    (function () {
      var table = document.getElementById('dash-student-table');
      if (!table) return;
      var lastCol = -1, asc = false;
      table.querySelectorAll('th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
          var col = parseInt(th.dataset.col);
          asc = (lastCol === col) ? !asc : false; // first click = high→low
          lastCol = col;
          // update header styles
          table.querySelectorAll('th.sortable').forEach(function (h) {
            h.classList.remove('sort-asc', 'sort-desc');
            h.querySelector('.sort-icon').textContent = '⇅';
          });
          th.classList.add(asc ? 'sort-asc' : 'sort-desc');
          th.querySelector('.sort-icon').textContent = '';
          // sort tbody rows (skip tfoot)
          var tbody = table.querySelector('tbody');
          var rows  = Array.from(tbody.querySelectorAll('tr'));
          rows.sort(function (a, b) {
            var av = parseInt(a.cells[col].textContent.trim()) || 0;
            var bv = parseInt(b.cells[col].textContent.trim()) || 0;
            return asc ? av - bv : bv - av;
          });
          rows.forEach(function (r, i) {
            r.cells[0].textContent = i + 1; // re-number
            tbody.appendChild(r);
          });
        });
      });
    })();
    // Live search for students panel
    (function () {
      var inp = document.getElementById('st-search-input');
      if (!inp) return;
      var timer;
      inp.addEventListener('input', function () {
        clearTimeout(timer);
        var len = inp.value.trim().length;
        if (len === 0 || len >= 3) {
          timer = setTimeout(function () { inp.form.submit(); }, 400);
        }
      });
    })();
  </script>
</body>
</html>
