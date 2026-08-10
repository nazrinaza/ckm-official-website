<?php
/**
 * CKM Admin — Invoice List
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

$stmtC = $pdo->prepare("SELECT COUNT(*) FROM invoices {$clause}");
$stmtC->execute($params);
$total = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM invoices {$clause} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$statuses = ['unpaid'=>'Tidak Bayar','partial'=>'Sebahagian','paid'=>'Dibayar','overdue'=>'Tertunggak','cancelled'=>'Batal'];

$pageTitle = 'Invoice';
include __DIR__ . '/header.php';
?>
<div class="filter-bar">
  <select name="status" onchange="window.location.href='?status='+this.value">
    <option value="all">Semua Status</option>
    <?php foreach ($statuses as $k => $v): ?>
      <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
    <?php endforeach; ?>
  </select>
  <a class="btn btn-gold btn-sm" href="invoice-create.php">+ Invoice Baru</a>
</div>

<div class="card">
  <?php if (empty($invoices)): ?>
    <div class="empty-state">Tiada invoice. Klik "Invoice Baru" untuk mula.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>No. Invoice</th><th>Klien</th><th>Jumlah</th><th>Dibayar</th><th>Baki</th><th>Status</th><th>Dikeluarkan</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
      <tr>
        <td><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></td>
        <td><?= htmlspecialchars($inv['client_name']) ?></td>
        <td>RM <?= number_format((float)$inv['total'], 2) ?></td>
        <td>RM <?= number_format((float)$inv['amount_paid'], 2) ?></td>
        <td><strong>RM <?= number_format((float)$inv['balance'], 2) ?></strong></td>
        <td><span class="badge badge-<?= $inv['status'] ?>"><?= $statuses[$inv['status']] ?? $inv['status'] ?></span></td>
        <td><?= date('d/m/Y', strtotime($inv['issue_date'])) ?></td>
        <td><a class="btn btn-sm btn-outline" href="invoice-view.php?id=<?= (int)$inv['id'] ?>">Lihat</a></td>
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
