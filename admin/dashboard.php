<?php
/**
 * CKM Admin — Dashboard
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

// Stats
$stats = [
    'enquiries_new'       => 0,
    'enquiries_contacted' => 0,
    'enquiries_won'       => 0,
    'enquiries_lost'      => 0,
    'enquiries_total'     => 0,
    'quotes_draft'        => 0,
    'quotes_sent'         => 0,
    'quotes_accepted'     => 0,
    'invoices_unpaid'     => 0,
    'invoices_paid'       => 0,
    'invoices_total_amt'  => 0,
    'invoices_paid_amt'   => 0,
];

try {
    $stats['enquiries_new']       = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn();
    $stats['enquiries_contacted'] = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='contacted'")->fetchColumn();
    $stats['enquiries_won']       = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='won'")->fetchColumn();
    $stats['enquiries_lost']      = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='lost'")->fetchColumn();
    $stats['enquiries_total']     = (int)$pdo->query("SELECT COUNT(*) FROM enquiries")->fetchColumn();
    $stats['quotes_draft']        = (int)$pdo->query("SELECT COUNT(*) FROM quotations WHERE status='draft'")->fetchColumn();
    $stats['quotes_sent']         = (int)$pdo->query("SELECT COUNT(*) FROM quotations WHERE status='sent'")->fetchColumn();
    $stats['quotes_accepted']     = (int)$pdo->query("SELECT COUNT(*) FROM quotations WHERE status='accepted'")->fetchColumn();
    $stats['invoices_unpaid']     = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN('unpaid','partial','overdue')")->fetchColumn();
    $stats['invoices_paid']       = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn();
    $stats['invoices_total_amt'] = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status!='cancelled'")->fetchColumn();
    $stats['invoices_paid_amt']  = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE status!='cancelled'")->fetchColumn();
} catch (Exception $e) {
    // tables may not exist yet
}

// Recent enquiries
$recent = [];
try {
    $recent = $pdo->query("SELECT * FROM enquiries ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'Dashboard';
include __DIR__ . '/header.php';
?>
<div class="stats-grid">
  <div class="stat-card new">
    <div class="num"><?= $stats['enquiries_new'] ?></div>
    <div class="label">Enquiry Baru</div>
  </div>
  <div class="stat-card contact">
    <div class="num"><?= $stats['enquiries_contacted'] ?></div>
    <div class="label">Dihubungi</div>
  </div>
  <div class="stat-card won">
    <div class="num"><?= $stats['enquiries_won'] ?></div>
    <div class="label">Menang</div>
  </div>
  <div class="stat-card lost">
    <div class="num"><?= $stats['enquiries_lost'] ?></div>
    <div class="label">Hilang</div>
  </div>
  <div class="stat-card invoice">
    <div class="num"><?= $stats['invoices_unpaid'] ?></div>
    <div class="label">Invoice Tertunggak</div>
  </div>
  <div class="stat-card">
    <div class="num">RM <?= number_format($stats['invoices_total_amt'], 2) ?></div>
    <div class="label">Jumlah Invoice</div>
  </div>
  <div class="stat-card">
    <div class="num">RM <?= number_format($stats['invoices_paid_amt'], 2) ?></div>
    <div class="label">Telah Dibayar</div>
  </div>
</div>

<div class="card">
  <div class="card-title">Enquiry Terkini</div>
  <?php if (empty($recent)): ?>
    <div class="empty-state">Belum ada enquiry. Form dari website akan muncul di sini.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr><th>Rujukan</th><th>Nama</th><th>Premis</th><th>Lokasi</th><th>Status</th><th>Tarikh</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $e): ?>
      <tr>
        <td><strong><?= htmlspecialchars($e['ref_no']) ?></strong></td>
        <td><?= htmlspecialchars($e['name']) ?></td>
        <td><?= htmlspecialchars($e['premise']) ?></td>
        <td><?= htmlspecialchars($e['location']) ?></td>
        <td><span class="badge badge-<?= $e['status'] ?>"><?= $e['status'] ?></span></td>
        <td><?= date('d/m/Y', strtotime($e['created_at'])) ?></td>
        <td><a class="btn btn-sm btn-outline" href="enquiry-view.php?id=<?= (int)$e['id'] ?>">Lihat</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <div class="mt-20">
    <a class="btn btn-primary" href="enquiries.php">Semua Enquiry &rarr;</a>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
