<?php
/**
 * CKM Admin — Quotation List
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$status  = trim((string)($_GET['status'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($status && $status !== 'all') {
    $where[] = 'status = ?';
    $params[] = $status;
}
$clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM quotations {$clause}")->execute($params) ?: 0;
$stmtC = $pdo->prepare("SELECT COUNT(*) FROM quotations {$clause}");
$stmtC->execute($params);
$total = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM quotations {$clause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$quotes = $stmt->fetchAll();

$statuses = ['draft'=>'Draf','sent'=>'Dihantar','accepted'=>'Diterima','rejected'=>'Ditolak','expired'=>'Tamat Tempoh'];

$pageTitle = 'Quotation';
include __DIR__ . '/header.php';
?>
<div class="filter-bar">
  <select name="status" onchange="window.location.href='?status='+this.value">
    <option value="all">Semua Status</option>
    <?php foreach ($statuses as $k => $v): ?>
      <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <a class="btn btn-gold btn-sm" href="quotation-create.php">+ Quotation Baru</a>
</div>

<div class="card">
  <?php if (empty($quotes)): ?>
    <div class="empty-state">Tiada quotation. Klik "Quotation Baru" untuk mula.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>No. Quote</th><th>Klien</th><th>Premis</th><th>Jumlah</th><th>Status</th><th>Dicipta</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($quotes as $q): ?>
      <tr>
        <td><strong><?= htmlspecialchars($q['quote_no']) ?></strong></td>
        <td><?= htmlspecialchars($q['client_name']) ?></td>
        <td><?= htmlspecialchars($q['premise'] ?: '-') ?></td>
        <td><strong>RM <?= number_format((float)$q['total'], 2) ?></strong></td>
        <td><span class="badge badge-<?= $q['status'] ?>"><?= $statuses[$q['status']] ?? $q['status'] ?></span></td>
        <td><?= date('d/m/Y', strtotime($q['created_at'])) ?></td>
        <td>
          <a class="btn btn-sm btn-outline" href="quotation-view.php?id=<?= (int)$q['id'] ?>">Lihat</a>
          <?php if ($q['status'] === 'accepted'): ?>
            <a class="btn btn-sm btn-gold" href="invoice-create.php?quote_id=<?= (int)$q['id'] ?>">Buat Invoice</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a class="<?= $i === $page ? 'current' : '' ?>" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
