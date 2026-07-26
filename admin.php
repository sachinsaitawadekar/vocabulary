<?php
session_start();

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $needle = (string)$needle;
        if ($needle === '') return true;
        return strncmp((string)$haystack, $needle, strlen($needle)) === 0;
    }
}

require 'db.php';

// ── Table creation (idempotent) ────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS everyday_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100) NOT NULL,
        name VARCHAR(150) NOT NULL,
        marathi_translation VARCHAR(150) NULL,
        image_url VARCHAR(255) NULL,
        entry_date DATE NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        show_vocabulary TINYINT(1) NOT NULL DEFAULT 1,
        show_idiom TINYINT(1) NOT NULL DEFAULT 1,
        show_everyday TINYINT(1) NOT NULL DEFAULT 1,
        show_contest_cta TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("INSERT IGNORE INTO content_settings (id) VALUES (1)");
} catch (Throwable $e) {}

try {
    $col = $pdo->query("SHOW COLUMNS FROM content_settings LIKE 'show_contest_cta'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE content_settings ADD COLUMN show_contest_cta TINYINT(1) NOT NULL DEFAULT 1");
    }
} catch (Throwable $e) {}

// Align legacy everyday_items columns
try { $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'marathi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE everyday_items ADD COLUMN marathi_translation VARCHAR(150) NULL"); } } catch (Throwable $e) {}
try { $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'marathi_name'"); if ($col->rowCount() > 0) { $pdo->exec("ALTER TABLE everyday_items CHANGE marathi_name marathi_translation VARCHAR(150) NULL"); } } catch (Throwable $e) {}
try { $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'description'"); if ($col->rowCount() > 0) { $pdo->exec("ALTER TABLE everyday_items DROP COLUMN description"); } } catch (Throwable $e) {}
try { $col = $pdo->query("SHOW COLUMNS FROM everyday_items LIKE 'created_at'"); if ($col->rowCount() > 0) { $pdo->exec("ALTER TABLE everyday_items DROP COLUMN created_at"); } } catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin','checker','student') NOT NULL DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        file_path VARCHAR(500) NULL,
        original_filename VARCHAR(255) NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending','reviewed','needs_revision') NOT NULL DEFAULT 'pending',
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS assignment_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        checker_id INT NOT NULL,
        comment TEXT NOT NULL,
        commented_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (checker_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {}

// Add needs_revision to existing installations (safe no-op if already present)
try { $pdo->exec("ALTER TABLE assignments MODIFY COLUMN status ENUM('pending','reviewed','needs_revision') NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}

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
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'file_path'");       if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN file_path VARCHAR(500) NULL AFTER response"); } catch (Throwable $e) {}
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'original_filename'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN original_filename VARCHAR(255) NULL AFTER file_path"); } catch (Throwable $e) {}
try { $c=$pdo->query("SHOW COLUMNS FROM allocated_assignment_responses LIKE 'status'"); if($c->rowCount()===0) $pdo->exec("ALTER TABLE allocated_assignment_responses ADD COLUMN status ENUM('pending','needs_revision','reviewed') NOT NULL DEFAULT 'pending' AFTER original_filename"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE allocated_assignment_responses MODIFY COLUMN response TEXT NULL"); } catch (Throwable $e) {}

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
    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Throwable $e) {}

// ── Load visibility settings ───────────────────────────────────────
$sanitize = static fn($value) => trim((string)$value);
$message_everyday = '';
$bulk_message_vocab = '';
$bulk_message_idiom = '';
$bulk_message_everyday = '';
$message_settings = '';
$visibilitySettings = [
    'show_vocabulary'  => 1,
    'show_idiom'       => 1,
    'show_everyday'    => 1,
    'show_contest_cta' => 1,
];
try {
    $stmt = $pdo->query("SELECT show_vocabulary, show_idiom, show_everyday, show_contest_cta FROM content_settings WHERE id = 1 LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $visibilitySettings = array_merge($visibilitySettings, array_intersect_key($row, $visibilitySettings));
} catch (Throwable $e) {}

// ── First-time setup check ─────────────────────────────────────────
$adminCount = 0;
try { $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(); } catch (Throwable $e) {}
$isSetup = ($adminCount === 0);
$setup_error = '';

if ($isSetup) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup') {
        $sn = trim($_POST['full_name'] ?? '');
        $su = trim($_POST['username']  ?? '');
        $sp = $_POST['password'] ?? '';
        if (!$sn || !$su || strlen($sp) < 6) {
            $setup_error = 'All fields required. Password must be at least 6 characters.';
        } else {
            try {
                $hash = password_hash($sp, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,'admin')");
                $ins->execute([$su, $hash, $sn]);
                session_regenerate_id(true);
                $_SESSION['user_id']   = $pdo->lastInsertId();
                $_SESSION['role']      = 'admin';
                $_SESSION['full_name'] = $sn;
                header('Location: admin.php'); exit;
            } catch (PDOException $e) {
                $setup_error = 'Username already taken. Choose another.';
            }
        }
    }
    goto show_setup;
}

// ── Auth guard ─────────────────────────────────────────────────────
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit;
}

// ── Active tab ────────────────────────────────────────────────────
$tab = in_array($_GET['tab'] ?? '', ['vocab', 'users', 'tasks', 'dashboard']) ? $_GET['tab'] : 'vocab';

// ── User management POST ──────────────────────────────────────────
$user_msg = ''; $user_err = '';
if ($tab === 'users' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $fn       = trim($_POST['full_name'] ?? '');
        $un       = trim($_POST['username']  ?? '');
        $pw       = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin','checker','student']) ? $_POST['role'] : 'student';
        $group_id = ($_POST['group_id'] ?? '') === '' ? null : (int)$_POST['group_id'];
        if (!$fn || !$un || strlen($pw) < 6) {
            $user_err = 'All fields required. Password must be at least 6 characters.';
        } else {
            try {
                $hash   = password_hash($pw, PASSWORD_DEFAULT);
                $ins    = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)');
                $ins->execute([$un, $hash, $fn, $role]);
                $new_id = (int)$pdo->lastInsertId();
                if ($group_id && $role === 'student') {
                    $pdo->prepare('INSERT IGNORE INTO student_group_members (group_id, student_id) VALUES (?,?)')->execute([$group_id, $new_id]);
                }
                $user_msg = '✅ User "' . htmlspecialchars($un) . '" created successfully.';
            } catch (PDOException $e) {
                $user_err = 'Username already exists. Choose a different one.';
            }
        }
    }

    if ($action === 'delete_user') {
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id && $del_id !== (int)$_SESSION['user_id']) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$del_id]);
            $user_msg = '✅ User deleted.';
        } else {
            $user_err = 'Cannot delete your own account.';
        }
    }

    if ($action === 'reset_password') {
        $rp_id = (int)($_POST['rp_id'] ?? 0);
        $rp_pw = $_POST['rp_password'] ?? '';
        if ($rp_id && strlen($rp_pw) >= 6) {
            $hash = password_hash($rp_pw, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $rp_id]);
            $user_msg = '✅ Password updated.';
        } else {
            $user_err = 'Password must be at least 6 characters.';
        }
    }

    if ($action === 'create_group') {
        $gname = trim($_POST['group_name'] ?? '');
        if ($gname === '') {
            $user_err = 'Group name cannot be empty.';
        } else {
            try {
                $pdo->prepare('INSERT INTO student_groups (name) VALUES (?)')->execute([$gname]);
                $user_msg = '✅ Group "' . htmlspecialchars($gname) . '" created.';
            } catch (PDOException $e) {
                $user_err = 'A group with that name already exists.';
            }
        }
    }

    if ($action === 'delete_group') {
        $gid = (int)($_POST['group_id'] ?? 0);
        if ($gid) {
            $pdo->prepare('DELETE FROM student_groups WHERE id=?')->execute([$gid]);
            $user_msg = '✅ Group deleted.';
        }
    }

    if ($action === 'add_to_group') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($gid && $sid) {
            try {
                $pdo->prepare('INSERT IGNORE INTO student_group_members (group_id, student_id) VALUES (?,?)')->execute([$gid, $sid]);
                $user_msg = '✅ Student added to group.';
            } catch (PDOException $e) { $user_err = 'Could not add student to group.'; }
        }
    }

    if ($action === 'remove_from_group') {
        $gid = (int)($_POST['group_id'] ?? 0);
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($gid && $sid) {
            $pdo->prepare('DELETE FROM student_group_members WHERE group_id=? AND student_id=?')->execute([$gid, $sid]);
            $user_msg = '✅ Student removed from group.';
        }
    }

    if ($action === 'update_user') {
        $upd_id   = (int)($_POST['upd_id'] ?? 0);
        $fn       = trim($_POST['full_name'] ?? '');
        $un       = trim($_POST['username']  ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin','checker','student']) ? $_POST['role'] : 'student';
        $pw       = $_POST['new_password'] ?? '';
        $group_id = ($_POST['group_id'] ?? '') === '' ? null : (int)$_POST['group_id'];
        if (!$upd_id || !$fn || !$un) {
            $user_err = 'Name and username are required.';
        } else {
            try {
                if ($pw !== '' && strlen($pw) >= 6) {
                    $hash = password_hash($pw, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, password_hash=? WHERE id=?')
                        ->execute([$fn, $un, $role, $hash, $upd_id]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name=?, username=?, role=? WHERE id=?')
                        ->execute([$fn, $un, $role, $upd_id]);
                }
                $pdo->prepare('DELETE FROM student_group_members WHERE student_id=?')->execute([$upd_id]);
                if ($group_id && $role === 'student') {
                    $pdo->prepare('INSERT IGNORE INTO student_group_members (group_id, student_id) VALUES (?,?)')->execute([$group_id, $upd_id]);
                }
                if ($upd_id === (int)$_SESSION['user_id']) {
                    $_SESSION['full_name'] = $fn;
                }
                $user_msg = '✅ User updated.';
            } catch (PDOException $e) {
                $user_err = 'Username already taken. Choose a different one.';
            }
        }
    }
}

// ── Tasks POST ────────────────────────────────────────────────────
$task_msg = ''; $task_err = '';
if ($tab === 'tasks' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'allocate') {
        $title              = trim($_POST['title']        ?? '');
        $type               = in_array($_POST['type'] ?? '', ['kahoot','paragraph','verb','other']) ? $_POST['type'] : 'other';
        $instructions       = trim($_POST['instructions'] ?? '');
        $allocated_group_id = ($_POST['allocated_group_id'] ?? '') === '' ? null : (int)$_POST['allocated_group_id'];
        if (!$title || !$instructions) {
            $task_err = 'Title and instructions are required.';
        } elseif (!$allocated_group_id) {
            $task_err = 'Please select a group to assign to.';
        } else {
            $pdo->prepare("INSERT INTO allocated_assignments (title, type, instructions, allocated_by, allocated_group_id) VALUES (?,?,?,?,?)")
                ->execute([$title, $type, $instructions, $_SESSION['user_id'], $allocated_group_id]);
            $task_msg = '✅ Assignment allocated successfully.';
        }
    }

    if ($action === 'delete_allocation') {
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id) {
            $pdo->prepare('DELETE FROM allocated_assignments WHERE id = ?')->execute([$del_id]);
            $task_msg = '✅ Assignment deleted.';
        }
    }
}

// ── Vocabulary / idiom / everyday POST ───────────────────────────
$message = ''; $message_idiom = '';

if ($tab === 'vocab' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date("Y-m-d");
    $type  = $_POST['type'] ?? 'vocab';

    if ($type === 'vocab') {
        $word    = trim($_POST['word']    ?? '');
        $marathi = trim($_POST['marathi'] ?? '');
        $hindi   = trim($_POST['hindi']   ?? '');
        $example = trim($_POST['example'] ?? '');

        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'marathi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN marathi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) {}
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'hindi_translation'");   if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN hindi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) {}
        try { $col = $pdo->query("SHOW COLUMNS FROM vocabulary LIKE 'example'");             if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE vocabulary ADD COLUMN example TEXT NULL"); } } catch (Throwable $e) {}

        if ($word) {
            $stmt = $pdo->prepare("INSERT INTO vocabulary (word, marathi_translation, hindi_translation, example, entry_date)
                                   VALUES (:word, :marathi, :hindi, :example, :entry_date)
                                   ON DUPLICATE KEY UPDATE word=:word, marathi_translation=:marathi, hindi_translation=:hindi, example=:example");
            $stmt->execute(['word'=>$word,'marathi'=>$marathi,'hindi'=>$hindi,'example'=>$example,'entry_date'=>$today]);
            $message = "✅ Today's vocabulary saved!";
        }

    } elseif ($type === 'idiom') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS idioms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                idiom VARCHAR(255) NOT NULL,
                marathi_translation VARCHAR(255) NULL,
                hindi_translation VARCHAR(255) NULL,
                example TEXT NULL,
                entry_date DATE NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (Throwable $e) {}

        $idiom    = trim($_POST['idiom']        ?? '');
        $imarathi = trim($_POST['idiom_marathi'] ?? '');
        $ihindi   = trim($_POST['idiom_hindi']   ?? '');
        $iexample = trim($_POST['idiom_example'] ?? '');

        if ($idiom) {
            try { $col = $pdo->query("SHOW COLUMNS FROM idioms LIKE 'hindi_translation'"); if ($col->rowCount() === 0) { $pdo->exec("ALTER TABLE idioms ADD COLUMN hindi_translation VARCHAR(255) NULL"); } } catch (Throwable $e) {}
            $stmt = $pdo->prepare("INSERT INTO idioms (idiom, marathi_translation, hindi_translation, example, entry_date)
                                   VALUES (:idiom, :marathi, :hindi, :example, :entry_date)
                                   ON DUPLICATE KEY UPDATE idiom=:idiom, marathi_translation=:marathi, hindi_translation=:hindi, example=:example");
            $stmt->execute(['idiom'=>$idiom,'marathi'=>$imarathi,'hindi'=>$ihindi,'example'=>$iexample,'entry_date'=>$today]);
            $message_idiom = "✅ Today's idiom saved!";
        }

    } elseif ($type === 'everyday') {
        $allowedCategories = ['Vegetables', 'Fruits', 'Kitchen Utensils', 'Living Room Decor'];
        $category    = $sanitize($_POST['category']    ?? 'Vegetables');
        if (!in_array($category, $allowedCategories, true)) $category = 'Vegetables';
        $itemName    = $sanitize($_POST['item_name']   ?? '');
        $itemMarathi = $sanitize($_POST['item_marathi'] ?? '');
        $imageUrl    = $sanitize($_POST['image_url']   ?? '');

        if ($itemName !== '') {
            $stmt = $pdo->prepare("INSERT INTO everyday_items (category, name, marathi_translation, image_url, entry_date)
                                   VALUES (:category, :name, :marathi, :image_url, :entry_date)
                                   ON DUPLICATE KEY UPDATE
                                     category=VALUES(category), name=VALUES(name),
                                     marathi_translation=VALUES(marathi_translation), image_url=VALUES(image_url)");
            $stmt->execute([
                ':category'  => $category,
                ':name'      => $itemName,
                ':marathi'   => $itemMarathi !== '' ? $itemMarathi : null,
                ':image_url' => $imageUrl    !== '' ? $imageUrl    : null,
                ':entry_date'=> $today,
            ]);
            $message_everyday = "✅ Daily things vocabulary saved!";
        } else {
            $message_everyday = "⚠️ Please enter an item name.";
        }

    } elseif ($type === 'visibility') {
        $showVocab   = isset($_POST['show_vocabulary'])  ? 1 : 0;
        $showIdiom   = isset($_POST['show_idiom'])       ? 1 : 0;
        $showEveryday= isset($_POST['show_everyday'])    ? 1 : 0;
        $showContest = isset($_POST['show_contest_cta']) ? 1 : 0;
        try {
            $pdo->prepare("UPDATE content_settings SET show_vocabulary=:sv, show_idiom=:si, show_everyday=:se, show_contest_cta=:sc WHERE id=1")
                ->execute([':sv'=>$showVocab,':si'=>$showIdiom,':se'=>$showEveryday,':sc'=>$showContest]);
            $visibilitySettings['show_vocabulary']  = $showVocab;
            $visibilitySettings['show_idiom']       = $showIdiom;
            $visibilitySettings['show_everyday']    = $showEveryday;
            $visibilitySettings['show_contest_cta'] = $showContest;
            $message_settings = "✅ Display settings updated.";
        } catch (Throwable $e) {
            $message_settings = "❌ Unable to update display settings.";
        }

    } elseif ($type === 'bulk_vocab' || $type === 'bulk_idiom' || $type === 'bulk_everyday') {
        $forIdioms   = ($type === 'bulk_idiom');
        $forEveryday = ($type === 'bulk_everyday');
        $keyName     = $forIdioms ? 'idiom' : ($forEveryday ? 'name' : 'word');
        $table       = $forIdioms ? 'idioms' : ($forEveryday ? 'everyday_items' : 'vocabulary');
        $summaryVar  = $forIdioms ? 'bulk_message_idiom' : ($forEveryday ? 'bulk_message_everyday' : 'bulk_message_vocab');

        if ($forIdioms) {
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS idioms (id INT AUTO_INCREMENT PRIMARY KEY, idiom VARCHAR(255) NOT NULL, marathi_translation VARCHAR(255) NULL, hindi_translation VARCHAR(255) NULL, example TEXT NULL, entry_date DATE NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); } catch (Throwable $e) {}
        } elseif ($forEveryday) {
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS everyday_items (id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(100) NOT NULL, name VARCHAR(150) NOT NULL, marathi_translation VARCHAR(150) NULL, image_url VARCHAR(255) NULL, entry_date DATE NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); } catch (Throwable $e) {}
        }

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $$summaryVar = '❌ Upload failed. Please select a CSV file.';
        } else {
            $tmp = $_FILES['csv_file']['tmp_name'];
            $fp  = @fopen($tmp, 'r');
            if (!$fp) {
                $$summaryVar = '❌ Could not read file.';
            } else {
                $header = fgetcsv($fp, 0, ',', '"', '\\');
                if (!$header) {
                    $$summaryVar = '❌ CSV appears empty.';
                } else {
                    $map = [];
                    foreach ($header as $i => $h) {
                        if ($i === 0) { $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h); }
                        $norm = strtolower(trim((string)$h));
                        $norm = preg_replace('/[^a-z0-9]+/', '_', $norm);
                        $norm = trim($norm, '_');
                        if ($norm === 'entrydate' || $norm === 'date') { $norm = 'entry_date'; }
                        if ($norm === 'marathi') { $norm = 'marathi_translation'; }
                        if ($norm === 'hindi')   { $norm = 'hindi_translation'; }
                        $map[$norm] = $i;
                    }
                    $required = ['entry_date', $keyName];
                    if ($forEveryday) $required[] = 'category';
                    $missing = [];
                    foreach ($required as $req) {
                        if (!array_key_exists($req, $map)) $missing[] = $req;
                    }
                    if ($missing) {
                        $$summaryVar = '❌ Missing required column(s): ' . implode(', ', $missing);
                        fclose($fp);
                    } else {
                        $count = 0; $skipped = 0; $updated = 0;
                        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
                            if (count($row) === 1 && trim($row[0]) === '') continue;
                            $get = function($name) use ($map, $row) {
                                $k = strtolower($name);
                                if (isset($map[$k])) return trim($row[$map[$k]]);
                                if ($k === 'marathi_translation' && isset($map['marathi'])) return trim($row[$map['marathi']]);
                                return '';
                            };
                            $dateRaw = $get('entry_date');
                            $val     = $get($keyName);
                            $mar     = $get('marathi_translation');
                            $hin     = $get('hindi_translation');
                            $ex      = $get('example');
                            $cat     = $get('category');
                            $img     = $get('image_url');
                            if ($val === '' || $dateRaw === '') { $skipped++; continue; }
                            if ($forEveryday) {
                                if ($cat === '') { $skipped++; continue; }
                                $catKey = preg_replace('/[^a-z]+/', '', strtolower($cat));
                                $allowedCatMap = [
                                    'vegetables'      => 'Vegetables',
                                    'fruits'          => 'Fruits',
                                    'kitchenutensils' => 'Kitchen Utensils',
                                    'livingroomdecor' => 'Living Room Decor',
                                ];
                                if (!isset($allowedCatMap[$catKey])) { $skipped++; continue; }
                                $cat = $allowedCatMap[$catKey];
                            }
                            $date = false;
                            if (preg_match('~^(\d{4})[-/](\d{1,2})[-/](\d{1,2})$~', $dateRaw, $m)) {
                                $date = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                            } elseif (preg_match('~^(\d{1,2})[-/](\d{1,2})[-/](\d{2,4})$~', $dateRaw, $m)) {
                                $y = (int)$m[3]; if ($y < 100) $y += 2000;
                                $date = sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
                            } else {
                                $t = strtotime($dateRaw); if ($t) $date = date('Y-m-d', $t);
                            }
                            if (!$date) { $skipped++; continue; }

                            try {
                                if ($forEveryday) {
                                    $stmt = $pdo->prepare("INSERT INTO everyday_items (category, name, marathi_translation, image_url, entry_date)
                                                           VALUES (:c, :v, :m, :i, :d)
                                                           ON DUPLICATE KEY UPDATE category=VALUES(category), name=VALUES(name), marathi_translation=VALUES(marathi_translation), image_url=VALUES(image_url)");
                                    $stmt->execute([':c'=>$cat,':v'=>$val,':m'=>$mar!==''?$mar:null,':i'=>$img!==''?$img:null,':d'=>$date]);
                                } elseif ($forIdioms) {
                                    $stmt = $pdo->prepare("INSERT INTO idioms (idiom, marathi_translation, hindi_translation, example, entry_date)
                                                           VALUES (:v, :m, :h, :e, :d)
                                                           ON DUPLICATE KEY UPDATE idiom=:v, marathi_translation=:m, hindi_translation=:h, example=:e");
                                    $stmt->execute([':v'=>$val,':m'=>$mar!==''?$mar:null,':h'=>$hin!==''?$hin:null,':e'=>$ex!==''?$ex:null,':d'=>$date]);
                                } else {
                                    $stmt = $pdo->prepare("INSERT INTO vocabulary (word, marathi_translation, hindi_translation, example, entry_date)
                                                           VALUES (:v, :m, :h, :e, :d)
                                                           ON DUPLICATE KEY UPDATE word=:v, marathi_translation=:m, hindi_translation=:h, example=:e");
                                    $stmt->execute([':v'=>$val,':m'=>$mar!==''?$mar:null,':h'=>$hin!==''?$hin:null,':e'=>$ex!==''?$ex:null,':d'=>$date]);
                                }
                                $count++;
                            } catch (PDOException $e) {
                                if (strpos($e->getMessage(), 'Duplicate') !== false || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
                                    $updated++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                        fclose($fp);
                        $$summaryVar = "✅ Inserted: $count rows" . ($updated ? ", Updated: $updated" : '') . ($skipped ? ", Skipped: $skipped" : '') . ".";
                    }
                }
            }
        }
    }
}

// ── User list with search + pagination ───────────────────────────
$u_search   = trim($_GET['q'] ?? '');
$u_per_page = 10;
$u_page     = max(1, (int)($_GET['upage'] ?? 1));
$u_total    = 0;
$u_pages    = 1;
$users      = [];

try {
    $like = '%' . $u_search . '%';

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE full_name LIKE ? OR username LIKE ? OR role LIKE ?");
    $count_stmt->execute([$like, $like, $like]);
    $u_total  = (int)$count_stmt->fetchColumn();
    $u_pages  = max(1, (int)ceil($u_total / $u_per_page));
    $u_page   = min($u_page, $u_pages);
    $u_offset = ($u_page - 1) * $u_per_page;

    $data_stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.full_name, u.role, u.created_at,
                (SELECT sgm.group_id FROM student_group_members sgm WHERE sgm.student_id = u.id LIMIT 1) AS current_group_id,
                (SELECT sg.name FROM student_group_members sgm JOIN student_groups sg ON sg.id = sgm.group_id WHERE sgm.student_id = u.id LIMIT 1) AS current_group_name
         FROM users u
         WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.role LIKE ?
         ORDER BY u.role, u.full_name
         LIMIT ? OFFSET ?"
    );
    $data_stmt->bindValue(1, $like, PDO::PARAM_STR);
    $data_stmt->bindValue(2, $like, PDO::PARAM_STR);
    $data_stmt->bindValue(3, $like, PDO::PARAM_STR);
    $data_stmt->bindValue(4, (int)$u_per_page, PDO::PARAM_INT);
    $data_stmt->bindValue(5, (int)$u_offset,   PDO::PARAM_INT);
    $data_stmt->execute();
    $users = $data_stmt->fetchAll();
} catch (Throwable $e) {}

// ── Groups ────────────────────────────────────────────────────────
$groups = [];
try { $groups = $pdo->query("SELECT id, name FROM student_groups ORDER BY id")->fetchAll(); } catch (Throwable $e) {}

// ── All allocations (admin sees everything) ───────────────────────
$all_allocations = [];
try {
    $all_allocations = $pdo->query(
        "SELECT aa.id, aa.title, aa.type, aa.allocated_group_id, aa.created_at,
                ub.full_name AS by_name, sg.name AS group_name,
                COUNT(r.id) AS response_count
         FROM allocated_assignments aa
         JOIN users ub ON ub.id = aa.allocated_by
         LEFT JOIN student_groups sg ON sg.id = aa.allocated_group_id
         LEFT JOIN allocated_assignment_responses r ON r.allocation_id = aa.id
         GROUP BY aa.id ORDER BY aa.created_at DESC"
    )->fetchAll();
} catch (Throwable $e) {}

// ── Responses grouped by allocation_id ────────────────────────────
$resp_by_alloc = [];
try {
    $resp_rows = $pdo->query(
        "SELECT r.id AS resp_id, r.allocation_id, r.response, r.file_path, r.original_filename, r.responded_at,
                r.status, u.full_name AS student_name
         FROM allocated_assignment_responses r
         JOIN users u ON u.id = r.student_id
         ORDER BY r.responded_at ASC"
    )->fetchAll();
    foreach ($resp_rows as $rr) { $resp_by_alloc[$rr['allocation_id']][] = $rr; }
} catch (Throwable $e) {}

// ── Response stats (from allocated assignment responses) ──────────
$stats = ['total' => 0, 'pending' => 0, 'needs_revision' => 0, 'reviewed' => 0];
try {
    $rows = $pdo->query("SELECT status, COUNT(*) AS cnt FROM allocated_assignment_responses GROUP BY status")->fetchAll();
    foreach ($rows as $r) {
        if (array_key_exists($r['status'], $stats)) $stats[$r['status']] = (int)$r['cnt'];
        $stats['total'] += (int)$r['cnt'];
    }
} catch (Throwable $e) {}
$totalStudents = 0;
try { $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(); } catch (Throwable $e) {}

// ── Dashboard report data ─────────────────────────────────────────
$dash_summary    = ['total_assignments' => 0, 'total_students' => 0, 'pending' => 0, 'needs_revision' => 0, 'reviewed' => 0];
$dash_by_student = [];
if ($tab === 'dashboard') {
    try {
        $srow = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM allocated_assignments)                                       AS total_assignments,
                (SELECT COUNT(*) FROM users WHERE role='student')                                  AS total_students,
                (SELECT COUNT(*) FROM allocated_assignment_responses WHERE status='pending')        AS pending,
                (SELECT COUNT(*) FROM allocated_assignment_responses WHERE status='needs_revision') AS needs_revision,
                (SELECT COUNT(*) FROM allocated_assignment_responses WHERE status='reviewed')       AS reviewed"
        )->fetch();
        if ($srow) $dash_summary = $srow;
    } catch (Throwable $ex) {}
    try {
        $dash_by_student = $pdo->query(
            "SELECT u.id, u.full_name, u.username,
                    (SELECT sg.name FROM student_group_members sgm2
                     JOIN student_groups sg ON sg.id = sgm2.group_id
                     WHERE sgm2.student_id = u.id LIMIT 1) AS group_name,
                    SUM(CASE WHEN aa.id IS NOT NULL AND r.id IS NULL THEN 1 ELSE 0 END) AS not_started,
                    SUM(CASE WHEN r.status = 'pending'        THEN 1 ELSE 0 END)        AS pending,
                    SUM(CASE WHEN r.status = 'needs_revision' THEN 1 ELSE 0 END)        AS needs_revision,
                    SUM(CASE WHEN r.status = 'reviewed'       THEN 1 ELSE 0 END)        AS reviewed
             FROM users u
             LEFT JOIN student_group_members sgm ON sgm.student_id = u.id
             LEFT JOIN allocated_assignments aa  ON aa.allocated_group_id = sgm.group_id
             LEFT JOIN allocated_assignment_responses r ON r.allocation_id = aa.id AND r.student_id = u.id
             WHERE u.role = 'student'
             GROUP BY u.id
             ORDER BY u.full_name"
        )->fetchAll();
    } catch (Throwable $ex) {}
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

goto show_page;

// ══════════════════════════════════════════════════════════════════
show_setup:
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>First-Time Setup - 3S English Academy</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:Arial,sans-serif; background:#f5f5f5; display:flex; flex-direction:column; }
    .page-main { flex:1; display:flex; justify-content:center; align-items:center; padding:20px; }
    .card { background:#fff; padding:28px 24px; border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,0.1); width:100%; max-width:400px; }
    .card h2 { margin:0 0 6px; color:#111827; }
    .sub { color:#6b7280; font-size:0.88rem; margin-bottom:20px; }
    .field { margin-bottom:14px; }
    .field label { display:block; font-weight:600; margin-bottom:6px; font-size:0.9rem; color:#374151; }
    .field input { width:100%; padding:10px 12px; font-size:0.95rem; border:1px solid #d1d5db; border-radius:8px; }
    .field input:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .btn { width:100%; padding:12px; font-size:1rem; font-weight:600; background:#007BFF; color:#fff; border:none; border-radius:10px; cursor:pointer; transition:background 0.2s; }
    .btn:hover { background:#0056b3; }
    .alert-err { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; padding:10px 12px; border-radius:8px; margin-bottom:12px; font-size:0.9rem; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">
    <div class="card">
      <h2>🚀 First-Time Setup</h2>
      <p class="sub">No admin account found. Create your admin account to get started.</p>
      <?php if ($setup_error): ?><div class="alert-err"><?= e($setup_error) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="setup">
        <div class="field"><label>Full Name</label><input name="full_name" type="text" required autofocus></div>
        <div class="field"><label>Username</label><input name="username" type="text" required autocomplete="username"></div>
        <div class="field"><label>Password (min 6 chars)</label><input name="password" type="password" required autocomplete="new-password"></div>
        <button class="btn" type="submit">Create Admin Account</button>
      </form>
    </div>
  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
<?php
exit;

// ══════════════════════════════════════════════════════════════════
show_page:
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0QT95F62SG"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-0QT95F62SG');</script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Admin - 3S English Academy</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:Arial,sans-serif; background:#f5f5f5; display:flex; flex-direction:column; }
    .page-main { flex:1; padding:20px; display:flex; flex-direction:column; align-items:center; }
    @media(max-width:600px){ .page-main{ padding:12px 10px; } }

    /* Tabs */
    .tabs-bar { display:flex; gap:8px; margin-bottom:18px; width:100%; max-width:860px; flex-wrap:wrap; }
    @media(max-width:400px){ .tabs-bar{ gap:6px; } }
    .tab-link { padding:9px 22px; border-radius:10px; text-decoration:none; font-weight:600; font-size:0.95rem;
                border:1px solid #e5e7eb; color:#374151; background:#fff; transition:all 0.15s; }
    .tab-link:hover { background:#eef2ff; border-color:#c7d2fe; }
    .tab-link.active { background:#007BFF; color:#fff; border-color:#007BFF; }

    /* Layout */
    .stack { display:flex; flex-direction:column; gap:18px; width:100%; max-width:860px; }
    .card { background:#fff; padding:22px; border:1px solid #ddd; border-radius:12px; box-shadow:0 4px 8px rgba(0,0,0,0.06); }
    .card h2 { margin:0 0 16px; color:#111827; font-size:1.1rem; }
    .card input[type=text],.card input[type=password],.card input[type=url],.card textarea,.card select {
      width:100%; padding:9px 12px; font-size:0.95rem; border:1px solid #ccc; border-radius:8px; margin-bottom:10px; }
    .card textarea { min-height:90px; resize:vertical; }
    .card input:focus,.card textarea:focus,.card select:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .card input[type=file] { margin-bottom:8px; font-size:0.9rem; }

    /* Buttons */
    .btn { padding:10px 18px; font-size:0.9rem; font-weight:600; background:#007BFF; color:#fff;
           border:none; border-radius:8px; cursor:pointer; transition:background 0.2s; }
    .btn:hover { background:#0056b3; }
    .btn-full { width:100%; padding:12px; }
    .btn-danger { background:#ef4444; } .btn-danger:hover { background:#dc2626; }
    .btn-sm { padding:8px 14px; font-size:0.85rem; }

    /* Messages */
    .msg { color:green; margin-bottom:10px; font-size:0.92rem; }
    .err { color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; padding:9px 12px; border-radius:8px; margin-bottom:10px; font-size:0.9rem; }
    .note { font-size:0.85rem; color:#6b7280; margin-bottom:8px; }

    /* Visibility toggle checkboxes */
    .toggle-list { text-align:left; display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
    .toggle-list label { display:inline-flex; align-items:center; gap:10px; font-size:0.95rem; color:#1f2937; }
    .toggle-list input[type=checkbox] { width:18px; height:18px; flex-shrink:0; }

    /* Status badges */
    .badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; }
    .badge-pending        { background:#fef3c7; color:#92400e; }
    .badge-reviewed       { background:#d1fae5; color:#065f46; }
    .badge-needs_revision { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }

    /* Task type badges */
    .type-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; }
    .type-kahoot    { background:#e0f2fe; color:#0369a1; }
    .type-paragraph { background:#f3e8ff; color:#7c3aed; }
    .type-verb      { background:#d1fae5; color:#065f46; }
    .type-other     { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }
    .alloc-to-all   { color:#6b7280; font-style:italic; font-size:0.85rem; }
    .users-table .expand-row td { background:#f0f7ff; padding:0; border-bottom:1px solid #e5e7eb; }
    .resp-expand { padding:14px 16px; }
    .resp-bubble { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:10px 12px; margin-bottom:8px; }
    .resp-bubble:last-child { margin-bottom:0; }
    .resp-bubble .resp-who  { font-size:0.8rem; font-weight:700; color:#1d4ed8; }
    .resp-bubble .resp-when { font-size:0.76rem; color:#9ca3af; margin-left:6px; }
    .resp-bubble .resp-text { color:#1f2937; font-size:0.88rem; line-height:1.55; margin-top:5px; white-space:pre-wrap; }
    .resp-bubble .resp-file { margin-top:6px; }
    .no-resp { color:#9ca3af; font-style:italic; font-size:0.85rem; }
    .btn-ghost { background:none; border:1px solid #d1d5db; border-radius:6px; padding:7px 12px; font-size:0.82rem; cursor:pointer; color:#374151; }
    .btn-ghost:hover { background:#f3f4f6; }

    /* Assignment stats dashboard */
    .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; width:100%; max-width:860px; margin-bottom:20px; }
    @media(max-width:700px){ .stat-grid{ grid-template-columns:repeat(2,1fr); gap:10px; } }
    .stat-card { border-radius:12px; padding:16px 14px; text-align:center; border:1px solid transparent; }
    .stat-num { font-size:2rem; font-weight:800; line-height:1.1; }
    .stat-lbl { font-size:0.78rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; margin-top:4px; opacity:0.75; }
    .stat-total    { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
    .stat-pending  { background:#fefce8; border-color:#fde047; color:#854d0e; }
    .stat-revision { background:#fff7ed; border-color:#fdba74; color:#9a3412; }
    .stat-reviewed { background:#f0fdf4; border-color:#86efac; color:#166534; }
    .stat-students { background:#faf5ff; border-color:#d8b4fe; color:#6b21a8; }
    @media(max-width:700px){ .stat-num{ font-size:1.6rem; } .stat-card{ padding:14px 10px; } }
    @media(max-width:480px){ .stat-num{ font-size:1.4rem; } .stat-card{ padding:12px 8px; } .stat-lbl{ font-size:0.72rem; } }

    /* Role badges */
    .role-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; }
    .role-admin   { background:#fee2e2; color:#b91c1c; }
    .role-checker { background:#dbeafe; color:#1d4ed8; }
    .role-student { background:#d1fae5; color:#065f46; }

    /* Users table */
    .users-table-wrap { overflow-x:auto; margin-top:4px; border-radius:8px; border:1px solid #e5e7eb; }
    .users-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
    .users-table th { text-align:left; padding:10px 14px; background:#f9fafb; border-bottom:2px solid #e5e7eb;
                      color:#374151; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.04em; white-space:nowrap; }
    .users-table td { padding:10px 14px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    @media(max-width:600px){ .users-table th, .users-table td { padding:8px 10px; font-size:0.82rem; } }
    .users-table .user-row:last-child td { border-bottom:none; }
    .users-table .user-row:hover td { background:#f9fafb; }
    .users-table .edit-row td { background:#eff6ff; padding:14px; white-space:normal; }
    .edit-row:last-child td { border-bottom:none; }
    .edit-fields { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .edit-fields input,.edit-fields select { padding:7px 10px; font-size:0.88rem; border:1px solid #d1d5db;
                                             border-radius:7px; flex:1; min-width:130px; margin:0; }
    .edit-fields input:focus,.edit-fields select:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 2px rgba(0,123,255,0.1); }
    .edit-actions { display:flex; gap:6px; flex-shrink:0; }
    .btn-edit   { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }
    .btn-edit:hover { background:#e5e7eb; }
    .btn-cancel { background:#6b7280; } .btn-cancel:hover { background:#4b5563; }
    .inline-form { display:contents; }
    @media(max-width:600px){ .card{ padding:16px; } }
    @media(max-width:480px){ .card{ padding:14px 12px; } }

    /* User search bar */
    .user-search-bar { display:flex; gap:8px; margin-bottom:14px; }
    .user-search-bar input { flex:1; padding:9px 12px; font-size:0.92rem; border:1px solid #d1d5db; border-radius:8px; min-width:0; }
    .user-search-bar input:focus { outline:none; border-color:#007BFF; box-shadow:0 0 0 3px rgba(0,123,255,0.1); }
    .user-search-bar button { padding:9px 16px; font-size:0.88rem; font-weight:600; background:#007BFF; color:#fff; border:none; border-radius:8px; cursor:pointer; white-space:nowrap; }
    .user-search-bar button:hover { background:#0056b3; }
    .search-clear { padding:9px 14px; font-size:0.88rem; background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; white-space:nowrap; text-decoration:none; display:inline-flex; align-items:center; }
    .search-clear:hover { background:#e5e7eb; }
    .search-meta { font-size:0.82rem; color:#6b7280; margin-bottom:10px; }

    /* Pagination */
    .pagination { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:14px; flex-wrap:wrap; }
    .pagination-info { font-size:0.82rem; color:#6b7280; }
    .pagination-links { display:flex; gap:4px; flex-wrap:wrap; }
    .pg-btn { display:inline-flex; align-items:center; justify-content:center; min-width:36px; height:36px; padding:0 10px; border:1px solid #e5e7eb; border-radius:7px; font-size:0.85rem; color:#374151; text-decoration:none; background:#fff; transition:all 0.15s; }
    .pg-btn:hover { background:#eef2ff; border-color:#c7d2fe; }
    .pg-btn.active { background:#007BFF; color:#fff; border-color:#007BFF; font-weight:700; }
    .pg-btn.disabled { opacity:0.4; pointer-events:none; }
    @media(max-width:500px){ .pagination{ justify-content:center; } .pagination-info{ width:100%; text-align:center; } }

    /* Dashboard tab */
    .dash-summary { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:18px; }
    @media(max-width:800px){ .dash-summary{ grid-template-columns:repeat(3,1fr); } }
    @media(max-width:480px){ .dash-summary{ grid-template-columns:repeat(2,1fr); gap:8px; } }
    .ds-cell { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; }
    .ds-cell .ds-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280; margin-bottom:4px; }
    .ds-cell .ds-value { font-size:1.5rem; font-weight:800; color:#111827; line-height:1; }
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
    .rpt-table th.sort-asc, .rpt-table th.sort-desc { background:#eff6ff; color:#1d4ed8; }
    .sort-icon { font-size:0.75rem; opacity:0.5; margin-left:3px; }
    .sort-asc .sort-icon::after { content:'▲'; opacity:1; }
    .sort-desc .sort-icon::after { content:'▼'; opacity:1; }
    .sort-asc .sort-icon, .sort-desc .sort-icon { font-size:0; }
    .num-pill { display:inline-block; min-width:28px; text-align:center; padding:2px 8px; border-radius:999px; font-size:0.78rem; font-weight:700; }
    .np-neutral  { background:#f3f4f6; color:#374151; border:1px solid #e5e7eb; }
    .np-pending  { background:#fef3c7; color:#92400e; }
    .np-revision { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
    .np-reviewed { background:#d1fae5; color:#065f46; }
    .type-badge { display:inline-block; padding:2px 9px; border-radius:999px; font-size:0.75rem; font-weight:600; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="page-main">

    <div class="stat-grid">
      <div class="stat-card stat-total">
        <div class="stat-num"><?= $stats['total'] ?></div>
        <div class="stat-lbl">Total</div>
      </div>
      <div class="stat-card stat-pending">
        <div class="stat-num"><?= $stats['pending'] ?></div>
        <div class="stat-lbl">Pending</div>
      </div>
      <div class="stat-card stat-revision">
        <div class="stat-num"><?= $stats['needs_revision'] ?></div>
        <div class="stat-lbl">Needs Revision</div>
      </div>
      <div class="stat-card stat-reviewed">
        <div class="stat-num"><?= $stats['reviewed'] ?></div>
        <div class="stat-lbl">Reviewed</div>
      </div>
      <div class="stat-card stat-students" style="grid-column:1/-1;">
        <div class="stat-num"><?= $totalStudents ?></div>
        <div class="stat-lbl">Students Enrolled</div>
      </div>
    </div>

    <div class="tabs-bar">
      <a href="?tab=vocab"      class="tab-link <?= $tab === 'vocab'      ? 'active' : '' ?>">📚 Vocabulary</a>
      <a href="?tab=users"      class="tab-link <?= $tab === 'users'      ? 'active' : '' ?>">👥 Users</a>
      <a href="?tab=tasks"      class="tab-link <?= $tab === 'tasks'      ? 'active' : '' ?>">📝 Tasks</a>
      <a href="?tab=dashboard"  class="tab-link <?= $tab === 'dashboard'  ? 'active' : '' ?>">📊 Dashboard</a>
    </div>

    <?php if ($tab === 'vocab'): ?>
    <!-- ── VOCABULARY TAB ── -->
    <div class="stack">

      <div class="card">
        <h2>Set Today's Word</h2>
        <?php if ($message) echo "<div class='msg'>$message</div>"; ?>
        <form method="POST">
          <input type="hidden" name="type" value="vocab">
          <input type="text" name="word" placeholder="English word" required>
          <input type="text" name="marathi" placeholder="Marathi translation (मराठी अर्थ)">
          <input type="text" name="hindi" placeholder="Hindi meaning (हिंदी अर्थ)">
          <textarea name="example" placeholder="Example sentence (optional)"></textarea>
          <button class="btn btn-full" type="submit">Save Word</button>
        </form>
      </div>

      <div class="card">
        <h2>Set Today's Idiom</h2>
        <?php if ($message_idiom) echo "<div class='msg'>$message_idiom</div>"; ?>
        <form method="POST">
          <input type="hidden" name="type" value="idiom">
          <input type="text" name="idiom" placeholder="Idiom (English)" required>
          <input type="text" name="idiom_marathi" placeholder="Marathi translation (मराठी अर्थ)">
          <input type="text" name="idiom_hindi" placeholder="Hindi meaning (हिंदी अर्थ)">
          <textarea name="idiom_example" placeholder="Example sentence (optional)"></textarea>
          <button class="btn btn-full" type="submit">Save Idiom</button>
        </form>
      </div>

      <div class="card">
        <h2>Daily Things Vocabulary</h2>
        <?php if ($message_everyday) echo "<div class='msg'>$message_everyday</div>"; ?>
        <form method="POST">
          <input type="hidden" name="type" value="everyday">
          <select name="category" required>
            <option value="Vegetables">Vegetables</option>
            <option value="Fruits">Fruits</option>
            <option value="Kitchen Utensils">Kitchen Utensils</option>
            <option value="Living Room Decor">Living Room Decor</option>
          </select>
          <input type="text" name="item_name" placeholder="Item name (e.g., Spinach)" required>
          <input type="text" name="item_marathi" placeholder="Marathi translation (optional)">
          <input type="url" name="image_url" placeholder="Image URL (optional)">
          <button class="btn btn-full" type="submit">Add Daily Item</button>
        </form>
      </div>

      <div class="card">
        <h2>Display Settings</h2>
        <?php if ($message_settings) echo "<div class='msg'>$message_settings</div>"; ?>
        <form method="POST">
          <input type="hidden" name="type" value="visibility">
          <div class="toggle-list">
            <label><input type="checkbox" name="show_vocabulary" value="1" <?= !empty($visibilitySettings['show_vocabulary']) ? 'checked' : '' ?>><span>Show Today's Word</span></label>
            <label><input type="checkbox" name="show_idiom" value="1" <?= !empty($visibilitySettings['show_idiom']) ? 'checked' : '' ?>><span>Show Today's Idiom</span></label>
            <label><input type="checkbox" name="show_everyday" value="1" <?= !empty($visibilitySettings['show_everyday']) ? 'checked' : '' ?>><span>Show Everyday Essentials</span></label>
            <label><input type="checkbox" name="show_contest_cta" value="1" <?= !empty($visibilitySettings['show_contest_cta']) ? 'checked' : '' ?>><span>Show Contest Button</span></label>
          </div>
          <button class="btn btn-full" type="submit">Save Display Settings</button>
        </form>
      </div>

      <div class="card">
        <h2>Bulk Upload (CSV from Excel)</h2>
        <p class="note">Export from Excel as CSV (UTF-8). Required columns:</p>
        <p class="note"><strong>Vocabulary:</strong> entry_date, word, marathi, hindi, example</p>
        <p class="note"><strong>Idioms:</strong> entry_date, idiom, marathi, hindi, example</p>
        <p class="note"><strong>Everyday Essentials:</strong> entry_date, category, name, marathi, image_url</p>
        <p class="note">Templates: <a href="template-vocabulary.php">Vocabulary CSV</a> · <a href="template-idioms.php">Idioms CSV</a> · <a href="template-everyday.php">Everyday CSV</a></p>

        <?php if ($bulk_message_vocab) echo "<div class='msg'>$bulk_message_vocab</div>"; ?>
        <form method="POST" enctype="multipart/form-data" style="margin-bottom:12px;">
          <input type="hidden" name="type" value="bulk_vocab">
          <input type="file" name="csv_file" accept=".csv" required>
          <button class="btn" type="submit">Upload Vocabulary CSV</button>
        </form>

        <?php if ($bulk_message_idiom) echo "<div class='msg'>$bulk_message_idiom</div>"; ?>
        <form method="POST" enctype="multipart/form-data" style="margin-bottom:12px;">
          <input type="hidden" name="type" value="bulk_idiom">
          <input type="file" name="csv_file" accept=".csv" required>
          <button class="btn" type="submit">Upload Idioms CSV</button>
        </form>

        <?php if ($bulk_message_everyday) echo "<div class='msg'>$bulk_message_everyday</div>"; ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="type" value="bulk_everyday">
          <input type="file" name="csv_file" accept=".csv" required>
          <button class="btn" type="submit">Upload Everyday Essentials CSV</button>
        </form>
      </div>

    </div><!-- /stack vocab -->

    <?php elseif ($tab === 'users'): ?>
    <!-- ── USERS TAB ── -->
    <div class="stack">

      <div class="card">
        <h2>Create New User</h2>
        <?php if ($user_msg) echo "<div class='msg'>$user_msg</div>"; ?>
        <?php if ($user_err) echo "<div class='err'>$user_err</div>"; ?>
        <form method="POST" action="?tab=users<?= $u_search !== '' ? '&q=' . urlencode($u_search) : '' ?>&upage=<?= $u_page ?>">
          <input type="hidden" name="action" value="create_user">
          <input type="text" name="full_name" placeholder="Full Name" required>
          <input type="text" name="username" placeholder="Username (used to log in)" required autocomplete="off">
          <input type="password" name="password" placeholder="Password (min 6 characters)" required autocomplete="new-password">
          <select name="role">
            <option value="student">Student</option>
            <option value="checker">Checker</option>
            <option value="admin">Admin</option>
          </select>
          <select name="group_id">
            <option value="">Group (optional, students only)</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-full" type="submit">Create User</button>
        </form>
      </div>

      <div class="card">
        <h2>All Users <span style="font-size:0.8rem;font-weight:400;color:#6b7280;">(<?= $u_total ?>)</span></h2>

        <!-- Search bar -->
        <form method="GET" action="" class="user-search-bar">
          <input type="hidden" name="tab" value="users">
          <input type="text" id="user-search-input" name="q" value="<?= e($u_search) ?>" placeholder="Search by name, username or role…" autocomplete="off">
          <button type="submit">Search</button>
          <?php if ($u_search !== ''): ?>
            <a href="?tab=users" class="search-clear">✕ Clear</a>
          <?php endif; ?>
        </form>

        <?php if ($u_search !== ''): ?>
          <p class="search-meta">Showing <?= count($users) ?> of <?= $u_total ?> result<?= $u_total !== 1 ? 's' : '' ?> for "<strong><?= e($u_search) ?></strong>"</p>
        <?php endif; ?>

        <?php if ($users): ?>
        <div class="users-table-wrap">
          <table class="users-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Group</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $i => $u): $uid = (int)$u['id']; $u_offset_val = ($u_page - 1) * $u_per_page; ?>
              <tr class="user-row">
                <td style="color:#9ca3af;font-size:0.82rem;"><?= $u_offset_val + $i + 1 ?></td>
                <td style="font-weight:600;color:#111827;"><?= e($u['full_name']) ?></td>
                <td style="color:#6b7280;">@<?= e($u['username']) ?></td>
                <td><span class="role-badge role-<?= e($u['role']) ?>"><?= ucfirst(e($u['role'])) ?></span></td>
                <td>
                  <?php if ($u['current_group_name']): ?>
                    <span class="type-badge" style="background:#e0f2fe;color:#0369a1;"><?= e($u['current_group_name']) ?></span>
                  <?php elseif ($u['role'] === 'student'): ?>
                    <span style="color:#9ca3af;font-size:0.82rem;">—</span>
                  <?php endif; ?>
                </td>
                <td style="color:#6b7280;font-size:0.85rem;"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                <td>
                  <button class="btn btn-sm btn-edit" type="button"
                          onclick="toggleEdit(<?= $uid ?>)">Edit</button>
                  <?php if ($uid !== (int)$_SESSION['user_id']): ?>
                  <form method="POST" action="?tab=users<?= $u_search !== '' ? '&q=' . urlencode($u_search) : '' ?>&upage=<?= $u_page ?>" class="inline-form"
                        onsubmit="return confirm('Delete <?= e(addslashes($u['full_name'])) ?>? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="del_id" value="<?= $uid ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <tr class="edit-row" id="edit-<?= $uid ?>" style="display:none;">
                <td colspan="7">
                  <form method="POST" action="?tab=users<?= $u_search !== '' ? '&q=' . urlencode($u_search) : '' ?>&upage=<?= $u_page ?>">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="upd_id" value="<?= $uid ?>">
                    <div class="edit-fields">
                      <input type="text" name="full_name" value="<?= e($u['full_name']) ?>" placeholder="Full Name" required>
                      <input type="text" name="username" value="<?= e($u['username']) ?>" placeholder="Username" required autocomplete="off">
                      <select name="role">
                        <option value="student" <?= $u['role']==='student'?'selected':'' ?>>Student</option>
                        <option value="checker" <?= $u['role']==='checker'?'selected':'' ?>>Checker</option>
                        <option value="admin"   <?= $u['role']==='admin'  ?'selected':'' ?>>Admin</option>
                      </select>
                      <select name="group_id">
                        <option value="">No Group</option>
                        <?php foreach ($groups as $g): ?>
                          <option value="<?= (int)$g['id'] ?>" <?= (int)($u['current_group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="password" name="new_password" placeholder="New password (leave blank to keep)" autocomplete="new-password">
                      <div class="edit-actions">
                        <button class="btn btn-sm" type="submit">Save</button>
                        <button class="btn btn-sm btn-cancel" type="button" onclick="toggleEdit(<?= $uid ?>)">Cancel</button>
                      </div>
                    </div>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($u_pages > 1): ?>
        <div class="pagination">
          <span class="pagination-info">
            Page <?= $u_page ?> of <?= $u_pages ?> &nbsp;·&nbsp; <?= $u_total ?> users
          </span>
          <div class="pagination-links">
            <?php
              $pg_base = '?tab=users' . ($u_search !== '' ? '&q=' . urlencode($u_search) : '') . '&upage=';
              $show_prev = $u_page > 1;
              $show_next = $u_page < $u_pages;
              // Determine page window (show max 5 page links)
              $pg_start = max(1, $u_page - 2);
              $pg_end   = min($u_pages, $pg_start + 4);
              $pg_start = max(1, $pg_end - 4);
            ?>
            <a href="<?= $pg_base . ($u_page - 1) ?>" class="pg-btn <?= !$show_prev ? 'disabled' : '' ?>">&#8249;</a>
            <?php if ($pg_start > 1): ?>
              <a href="<?= $pg_base . 1 ?>" class="pg-btn">1</a>
              <?php if ($pg_start > 2): ?><span class="pg-btn disabled">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $pg_start; $p <= $pg_end; $p++): ?>
              <a href="<?= $pg_base . $p ?>" class="pg-btn <?= $p === $u_page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($pg_end < $u_pages): ?>
              <?php if ($pg_end < $u_pages - 1): ?><span class="pg-btn disabled">…</span><?php endif; ?>
              <a href="<?= $pg_base . $u_pages ?>" class="pg-btn"><?= $u_pages ?></a>
            <?php endif; ?>
            <a href="<?= $pg_base . ($u_page + 1) ?>" class="pg-btn <?= !$show_next ? 'disabled' : '' ?>">&#8250;</a>
          </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
          <p class="note"><?= $u_search !== '' ? 'No users match your search.' : 'No users yet.' ?></p>
        <?php endif; ?>
      </div>

    </div><!-- /stack users -->

    <?php elseif ($tab === 'tasks'): ?>
    <!-- ── TASKS TAB ── -->
    <div class="stack">

      <div class="card">
        <h2>📝 Allocate Assignment</h2>
        <?php if ($task_msg) echo "<div class='msg'>$task_msg</div>"; ?>
        <?php if ($task_err) echo "<div class='err'>$task_err</div>"; ?>
        <form method="POST">
          <input type="hidden" name="action" value="allocate">
          <input type="text" name="title" placeholder="Assignment title" required>
          <select name="type">
            <option value="kahoot">Kahoot</option>
            <option value="paragraph">Paragraph</option>
            <option value="verb">Verb</option>
            <option value="other">Other</option>
          </select>
          <textarea name="instructions" placeholder="Write the instructions or content for students here..." required></textarea>
          <select name="allocated_group_id" required>
            <option value="">Select Group *</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-full" type="submit">Allocate Assignment</button>
        </form>
      </div>

      <div class="card">
        <h2>All Allocated Assignments</h2>
        <?php if ($all_allocations): ?>
        <div class="users-table-wrap">
          <table class="users-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Assigned To</th>
                <th>Created By</th>
                <th>Date</th>
                <th>Responses</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_allocations as $al):
                $aid   = (int)$al['id'];
                $resps = $resp_by_alloc[$aid] ?? [];
                $rc    = count($resps);
              ?>
              <tr>
                <td style="font-weight:600;color:#111827;"><?= e($al['title']) ?></td>
                <td><span class="type-badge type-<?= e($al['type']) ?>"><?= ucfirst(e($al['type'])) ?></span></td>
                <td><?= $al['group_name'] ? '<span class="type-badge" style="background:#e0f2fe;color:#0369a1;">'.e($al['group_name']).'</span>' : '<span class="alloc-to-all">—</span>' ?></td>
                <td style="color:#374151;"><?= e($al['by_name']) ?></td>
                <td style="color:#6b7280;font-size:0.85rem;"><?= e(date('d M Y', strtotime($al['created_at']))) ?></td>
                <td style="text-align:center;">
                  <button class="btn-ghost" onclick="toggleResp('aresp-<?= $aid ?>')">
                    <?= $rc ?> <?= $rc === 1 ? 'resp' : 'resps' ?>
                  </button>
                </td>
                <td>
                  <form method="POST" class="inline-form" onsubmit="return confirm('Delete this assignment? Student responses will also be removed.')">
                    <input type="hidden" name="action" value="delete_allocation">
                    <input type="hidden" name="del_id" value="<?= $aid ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
              <tr class="expand-row" id="aresp-<?= $aid ?>" style="display:none;">
                <td colspan="7">
                  <div class="resp-expand">
                    <?php if ($resps): ?>
                      <?php foreach ($resps as $rr): ?>
                      <div class="resp-bubble">
                        <span class="resp-who"><?= e($rr['student_name']) ?></span>
                        <span class="resp-when"><?= e(date('d M Y, g:i A', strtotime($rr['responded_at']))) ?></span>
                        <span class="badge badge-<?= e($rr['status'] ?? 'pending') ?>" style="margin-left:6px;font-size:0.72rem;"><?= $rr['status'] === 'reviewed' ? 'Reviewed' : ($rr['status'] === 'needs_revision' ? 'Needs Revision' : 'Pending') ?></span>
                        <?php if ($rr['response']): ?>
                          <div class="resp-text"><?= e($rr['response']) ?></div>
                        <?php endif; ?>
                        <?php if ($rr['file_path']): ?>
                          <div class="resp-file">
                            <a href="download.php?type=task_response&id=<?= (int)$rr['resp_id'] ?>" style="color:#1d4ed8;font-size:0.82rem;">
                              📎 <?= e($rr['original_filename'] ?: 'Download file') ?>
                            </a>
                          </div>
                        <?php endif; ?>
                      </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p class="no-resp">No responses yet.</p>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="note">No assignments allocated yet.</p>
        <?php endif; ?>
      </div>

    </div><!-- /stack tasks -->

    <?php else: ?>
    <!-- ── DASHBOARD TAB ── -->
    <div class="stack">
      <div class="card">
        <h2 style="margin:0 0 16px;font-size:1.05rem;color:#111827;">📊 Dashboard</h2>

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
          <table class="rpt-table" id="admin-dash-table">
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
                $tot_ns = $tot_pend = $tot_rev_need = $tot_rev = 0;
                foreach ($dash_by_student as $ri => $row):
                  $tot_ns       += (int)$row['not_started'];
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
                <td class="num"><span class="num-pill np-neutral"><?= $tot_ns ?></span></td>
                <td class="num"><span class="num-pill np-pending"><?= $tot_pend ?></span></td>
                <td class="num"><span class="num-pill np-revision"><?= $tot_rev_need ?></span></td>
                <td class="num"><span class="num-pill np-reviewed"><?= $tot_rev ?></span></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php else: ?>
          <p class="note">No students found.</p>
        <?php endif; ?>
      </div>
    </div><!-- /stack dashboard -->

    <?php endif; ?>

  </main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
  <script>
    // Live user search: auto-submit after 3+ chars with 400ms debounce
    (function () {
      var input = document.getElementById('user-search-input');
      if (!input) return;
      var timer;
      input.addEventListener('input', function () {
        clearTimeout(timer);
        var len = input.value.trim().length;
        if (len === 0 || len >= 3) {
          timer = setTimeout(function () { input.form.submit(); }, 400);
        }
      });
    })();

    function toggleEdit(id) {
      var row = document.getElementById('edit-' + id);
      if (!row) return;
      var open = row.style.display === 'none' || row.style.display === '';
      row.style.display = open ? 'table-row' : 'none';
      if (open) row.querySelector('input[name=full_name]').focus();
    }
    function toggleResp(id) {
      var row = document.getElementById(id);
      if (!row) return;
      row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }
    // Dashboard table column sort
    (function () {
      var table = document.getElementById('admin-dash-table');
      if (!table) return;
      var lastCol = -1, asc = false;
      table.querySelectorAll('th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
          var col = parseInt(th.dataset.col);
          asc = (lastCol === col) ? !asc : false;
          lastCol = col;
          table.querySelectorAll('th.sortable').forEach(function (h) {
            h.classList.remove('sort-asc', 'sort-desc');
            h.querySelector('.sort-icon').textContent = '⇅';
          });
          th.classList.add(asc ? 'sort-asc' : 'sort-desc');
          th.querySelector('.sort-icon').textContent = '';
          var tbody = table.querySelector('tbody');
          var rows  = Array.from(tbody.querySelectorAll('tr'));
          rows.sort(function (a, b) {
            var av = parseInt(a.cells[col].textContent.trim()) || 0;
            var bv = parseInt(b.cells[col].textContent.trim()) || 0;
            return asc ? av - bv : bv - av;
          });
          rows.forEach(function (r, i) {
            r.cells[0].textContent = i + 1;
            tbody.appendChild(r);
          });
        });
      });
    })();
  </script>
</body>
</html>
