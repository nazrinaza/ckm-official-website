<?php
/**
 * CKM Admin — Enquiry List
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$status = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($status !== '' && $status !== 'all') {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(name LIKE ? OR premise LIKE ? OR location LIKE ? OR phone LIKE ? OR ref_no LIKE ?)';
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}
$clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM enquiries {$clause}");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Fetch
$stmt = $pdo->prepare("SELECT * FROM enquiries {$clause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

$statuses = ['new'=>'Baru','contacted'=>'Dihubungi','quoted'=>'Dipetik','won'=>'Menang','lost'=>'Hilang','archived'=>'Arkib'];

$pageTitle = 'Enquiry';
include __DIR__ . '/header.php';
?>
<div class="filter-bar">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
    <select name="status" onchange="this.form.submit()">
      <option value="all">Semua Status</option>
      <?php foreach ($statuses as $k => $v): ?>
        <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, premis, lokasi, phone..." style="width:250px">
    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
    <?php if ($search || $status): ?>
      <a class="btn btn-outline btn-sm" href="enquiries.php">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (empty($enquiries)): ?>
    <div class="empty-state">Tiada enquiry dijumpai.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>Rujukan</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Premis</th><th>Lokasi</th><th>Status</th><th>Tarikh</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($enquiries as $e): ?>
      <tr>
        <td><strong><?= htmlspecialchars($e['ref_no']) ?></strong></td>
        <td><?= htmlspecialchars($e['name']) ?></td>
        <td><?= $e['email'] ? htmlspecialchars($e['email']) : '<span style="color:#adb5bd">-</span>' ?></td>
        <td><?= htmlspecialchars($e['phone']) ?></td>
        <td><?= htmlspecialchars($e['premise']) ?></td>
        <td><?= htmlspecialchars($e['location']) ?></td>
        <td><span class="badge badge-<?= $e['status'] ?>"><?= $statuses[$e['status']] ?? $e['status'] ?></span></td>
        <td><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></td>
        <td><a class="btn btn-sm btn-outline" href="enquiry-view.php?id=<?= (int)$e['id'] ?>">Lihat</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a class="<?= $i === $page ? 'current' : '' ?>" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
