<?php
require __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$allowedSort = [
    'full_name' => 'full_name',
    'mobile' => 'mobile',
    'created_at' => 'created_at'
];
$sortLabels = [
    'full_name' => 'Full Name',
    'mobile' => 'Mobile',
    'created_at' => 'Registered On'
];
$sortParam = isset($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'created_at';
$sortColumn = $allowedSort[$sortParam] ?? 'created_at';

$dirParam = isset($_GET['dir']) ? strtolower(trim($_GET['dir'])) : 'desc';
$direction = $dirParam === 'asc' ? 'ASC' : 'DESC';
$dirParam = $direction === 'ASC' ? 'asc' : 'desc';

$perPageOptions = [10, 25, 50];
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }

$totalRows = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$totalPages = $totalRows > 0 ? (int)ceil($totalRows / $perPage) : 1;
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT full_name, mobile, created_at FROM registrations ORDER BY {$sortColumn} {$direction} LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
$pageCount = count($rows);
$rangeStart = $totalRows ? $offset + 1 : 0;
$rangeEnd = $totalRows ? $offset + $pageCount : 0;

$baseParams = [
    'sort' => $sortParam,
    'dir' => $dirParam,
    'per_page' => $perPage
];

$toggleDir = function(string $column) use ($sortParam, $dirParam) {
    if ($sortParam === $column) {
        return $dirParam === 'asc' ? 'desc' : 'asc';
    }
    return 'asc';
};

$buildQuery = function(array $overrides = []) use ($baseParams) {
    return http_build_query(array_merge($baseParams, $overrides));
};

$sortLabel = $sortLabels[$sortParam] ?? $sortLabels['created_at'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0QT95F62SG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-0QT95F62SG');
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Registrations - 3S English Academy</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f5f7fb;
      color: #0f172a;
    }
    .container {
      max-width: 960px;
      margin: 0 auto;
      padding: 24px 16px 48px;
    }
    h1 {
      margin: 0 0 12px;
      font-size: 2rem;
      color: #1d4ed8;
      text-align: center;
    }
    .card {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
      padding: 24px;
      overflow-x: auto;
    }
    .controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 16px;
    }
    .per-page {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.95rem;
      color: #475569;
    }
    .per-page select {
      padding: 6px 10px;
      border-radius: 8px;
      border: 1px solid #cbd5f5;
      font-size: 0.95rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 600px;
    }
    thead {
      background: #1d4ed8;
      color: #fff;
    }
    th, td {
      padding: 14px 16px;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    th a {
      color: inherit;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    th a:hover { text-decoration: underline; }
    .sort-indicator {
      font-size: 0.85rem;
      opacity: 0.85;
    }
    tbody tr:nth-child(even) { background: #f9fafb; }
    .empty-state {
      text-align: center;
      padding: 40px 0;
      color: #6b7280;
      font-size: 1.05rem;
    }
    .meta {
      text-align: center;
      margin-bottom: 16px;
      color: #475569;
    }
    .pagination {
      display: flex;
      justify-content: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    .pagination a, .pagination span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 36px;
      padding: 8px 12px;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      background: #fff;
      color: #1f2937;
      text-decoration: none;
      font-size: 0.95rem;
    }
    .pagination a:hover { background: #eef2ff; border-color: #c7d2fe; }
    .pagination .active {
      background: #1d4ed8;
      border-color: #1d4ed8;
      color: #fff;
      cursor: default;
      pointer-events: none;
    }
    .pagination .disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }
    @media (max-width: 640px) {
      .card { padding: 16px; }
      table { min-width: 100%; }
      th, td { padding: 10px; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <main class="container">
    <h1>Student Registrations</h1>
    <p class="meta">
      Showing <?= e($pageCount) ?> of <?= e($totalRows) ?> registration<?= $totalRows === 1 ? '' : 's' ?>
      <?= $totalRows ? '· ' . e($rangeStart) . '–' . e($rangeEnd) : '' ?>
      · Sorted by <?= e($sortLabel) ?>
    </p>
    <div class="card">
      <div class="controls">
        <div>Page <?= e($page) ?> of <?= e($totalPages) ?> · Total <?= e($totalRows) ?></div>
        <form method="get" class="per-page">
          <label for="per_page">Rows per page:</label>
          <input type="hidden" name="sort" value="<?= e($sortParam) ?>">
          <input type="hidden" name="dir" value="<?= e($dirParam) ?>">
          <select id="per_page" name="per_page" onchange="this.form.submit()">
            <?php foreach ($perPageOptions as $option): ?>
              <option value="<?= e($option) ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="page" value="1">
        </form>
      </div>
      <?php if (!$rows): ?>
        <div class="empty-state">No registrations yet.</div>
      <?php else: ?>
        <table aria-label="Registrations">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">
                <a href="?<?= e($buildQuery(['sort' => 'full_name', 'dir' => $toggleDir('full_name'), 'page' => 1])) ?>">
                  Full Name
                  <?php if ($sortParam === 'full_name'): ?>
                    <span class="sort-indicator"><?= $dirParam === 'asc' ? '▲' : '▼' ?></span>
                  <?php endif; ?>
                </a>
              </th>
              <th scope="col">
                <a href="?<?= e($buildQuery(['sort' => 'mobile', 'dir' => $toggleDir('mobile'), 'page' => 1])) ?>">
                  Mobile
                  <?php if ($sortParam === 'mobile'): ?>
                    <span class="sort-indicator"><?= $dirParam === 'asc' ? '▲' : '▼' ?></span>
                  <?php endif; ?>
                </a>
              </th>
              <th scope="col">
                <a href="?<?= e($buildQuery(['sort' => 'created_at', 'dir' => $toggleDir('created_at'), 'page' => 1])) ?>">
                  Registered On
                  <?php if ($sortParam === 'created_at'): ?>
                    <span class="sort-indicator"><?= $dirParam === 'asc' ? '▲' : '▼' ?></span>
                  <?php endif; ?>
                </a>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $index => $row): ?>
              <tr>
                <td><?= e($index + 1) ?></td>
                <td><?= e($row['full_name']) ?></td>
                <td><?= e($row['mobile']) ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($row['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <nav class="pagination" aria-label="Pagination">
          <?php $prevPage = $page - 1; $nextPage = $page + 1; ?>
          <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= e($buildQuery(['page' => $prevPage < 1 ? 1 : $prevPage])) ?>">Prev</a>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($totalPages > 10 && $i > 2 && $i < $totalPages - 1 && abs($i - $page) > 2) {
                if ($i === 3 || $i === $totalPages - 2) echo '<span class="disabled">…</span>';
                continue;
              }
            ?>
            <?php if ($i === $page): ?>
              <span class="active"><?= e($i) ?></span>
            <?php else: ?>
              <a href="?<?= e($buildQuery(['page' => $i])) ?>"><?= e($i) ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="?<?= e($buildQuery(['page' => $nextPage > $totalPages ? $totalPages : $nextPage])) ?>">Next</a>
        </nav>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
