<?php
/**
 * CKM Admin — Invoice View / Print + Payment Tracking
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: invoices.php'); exit; }

// Handle payment + status update
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $amount = (float)($_POST['amount_paid'] ?? 0);
        $method = trim((string)($_POST['payment_method'] ?? ''));
        $ref    = trim((string)($_POST['payment_ref'] ?? ''));

        $stmt = $pdo->prepare("SELECT total, amount_paid FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if ($inv) {
            $newPaid = (float)$inv['amount_paid'] + $amount;
            $balance = (float)$inv['total'] - $newPaid;

            if ($balance <= 0.01) {
                $status = 'paid';
                $balance = 0;
            } elseif ($newPaid > 0) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }

            $pdo->prepare("UPDATE invoices SET amount_paid=?, balance=?, status=?, payment_method=?, payment_ref=? WHERE id=?")
                ->execute([$newPaid, $balance, $status, $method, $ref, $id]);
            $alert = '<div class="alert alert-success">Pembayaran RM ' . number_format($amount, 2) . ' direkodkan.</div>';
        }
    } elseif ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        $valid = ['unpaid','partial','paid','overdue','cancelled'];
        if (in_array($newStatus, $valid, true)) {
            $pdo->prepare("UPDATE invoices SET status=? WHERE id=?")->execute([$newStatus, $id]);
            $alert = '<div class="alert alert-success">Status invoice dikemas kini.</div>';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { header('Location: invoices.php'); exit; }

$items = json_decode($inv['items'], true) ?: [];
$statuses = ['unpaid'=>'Tidak Bayar','partial'=>'Sebahagian','paid'=>'Dibayar','overdue'=>'Tertunggak','cancelled'=>'Batal'];

// Company info
$company = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($rows as $r) { $company[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $ex) {}
$company['name']    = $company['company_name']    ?? 'cucikarpetmasjid.com';
$company['email']   = $company['company_email']   ?? 'jom@cucikarpetmasjid.com';
$company['phone']   = $company['company_phone']    ?? '';
$company['address'] = $company['company_address'] ?? '';

$pageTitle = 'Invoice ' . htmlspecialchars($inv['invoice_no']);
include __DIR__ . '/header.php';
?>
<?= $alert ?>
<?php if (isset($_GET['new'])): ?>
<div class="alert alert-success">Invoice berjaya dicipta.</div>
<?php endif; ?>

<div class="card no-print" style="display:flex;gap:10px;justify-content:space-between;align-items:center">
  <div>
    <strong>Status:</strong> <span class="badge badge-<?= $inv['status'] ?>"><?= $statuses[$inv['status']] ?? $inv['status'] ?></span>
    <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
    <strong style="margin-left:15px">Baki:</strong> RM <?= number_format((float)$inv['balance'], 2) ?>
    <?php endif; ?>
  </div>
  <div>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak / PDF</button>
    <button onclick="sendDocument('invoice', <?= $id ?>, '<?= htmlspecialchars($inv['client_email'] ?? '', ENT_QUOTES) ?>')" class="btn btn-gold btn-sm">Hantar Email</button>
    <a class="btn btn-outline btn-sm" href="invoices.php">Kembali</a>
  </div>
</div>

<?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
<div class="card no-print">
  <div class="card-title">Rekod Pembayaran</div>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="action" value="record_payment">
    <div class="form-group" style="margin:0">
      <label style="font-size:11px">Jumlah Bayaran (RM)</label>
      <input type="number" name="amount_paid" step="0.01" min="0.01" value="<?= number_format((float)$inv['balance'], 2, '.', '') ?>" style="width:120px">
    </div>
    <div class="form-group" style="margin:0">
      <label style="font-size:11px">Kaedah</label>
      <select name="payment_method" style="width:130px">
        <option value="Bank Transfer">Bank Transfer</option>
        <option value="Cash">Tunai</option>
        <option value="Cheque">Cek</option>
        <option value="Online">Online</option>
        <option value="DuitNow">DuitNow</option>
        <option value="Lain">Lain</option>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label style="font-size:11px">Rujukan</label>
      <input type="text" name="payment_ref" placeholder="No. rujukan" style="width:150px">
    </div>
    <button type="submit" class="btn btn-gold btn-sm">Rekod Bayaran</button>
  </form>
</div>

<div class="card no-print">
  <form method="post" style="display:flex;gap:10px;align-items:center">
    <input type="hidden" name="action" value="update_status">
    <label>Tukar status:</label>
    <select name="status">
      <option value="unpaid">Tidak Bayar</option>
      <option value="partial">Sebahagian</option>
      <option value="paid">Dibayar</option>
      <option value="overdue">Tertunggak</option>
      <option value="cancelled">Batal</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
  </form>
</div>
<?php endif; ?>

<div class="print-doc">
  <div class="doc-header">
    <div class="doc-logo">
      <img src="../assets/logo-text.png" alt="cucikarpetmasjid.com" style="max-height:60px;width:auto">
    </div>
    <div style="text-align:right">
      <div class="doc-title">INVOICE</div>
      <div style="font-size:14px;margin-top:5px"><strong><?= htmlspecialchars($inv['invoice_no']) ?></strong></div>
      <div style="font-size:12px;color:#666">Dikeluarkan: <?= date('d/m/Y', strtotime($inv['issue_date'])) ?></div>
      <?php if ($inv['due_date']): ?>
      <div style="font-size:12px;color:#666">Akhir Bayaran: <?= date('d/m/Y', strtotime($inv['due_date'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;margin-bottom:20px">
    <div>
      <div style="font-size:13px;text-transform:uppercase;color:#999;margin-bottom:5px">Dari</div>
      <strong><?= htmlspecialchars($company['name']) ?></strong><br>
      <?= htmlspecialchars($company['address']) ?><br>
      <?= htmlspecialchars($company['email']) ?><br>
      <?= htmlspecialchars($company['phone']) ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:13px;text-transform:uppercase;color:#999;margin-bottom:5px">Diinvoicekan Kepada</div>
      <strong><?= htmlspecialchars($inv['client_name']) ?></strong><br>
      <?php if (!empty($inv['client_email'])): ?><?= htmlspecialchars($inv['client_email']) ?><br><?php endif; ?>
      <?= htmlspecialchars($inv['client_phone']) ?><br>
      <?= nl2br(htmlspecialchars($inv['client_address'])) ?>
    </div>
  </div>

  <?php if ($inv['premise']): ?>
  <p style="margin-bottom:15px"><strong>Premis:</strong> <?= htmlspecialchars($inv['premise']) ?></p>
  <?php endif; ?>

  <table class="doc-items">
    <thead>
      <tr><th>Bil</th><th>Penerangan</th><th style="text-align:center">Kuantiti</th><th style="text-align:right">Harga/unit</th><th style="text-align:right">Jumlah</th></tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($items as $item): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($item['desc']) ?></td>
        <td style="text-align:center"><?= (int)$item['qty'] ?></td>
        <td style="text-align:right">RM <?= number_format((float)$item['price'], 2) ?></td>
        <td style="text-align:right">RM <?= number_format($item['qty'] * $item['price'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="doc-totals">
    <div class="row"><span>Subtotal</span><span>RM <?= number_format((float)$inv['subtotal'], 2) ?></span></div>
    <?php if ((float)$inv['discount'] > 0): ?>
    <div class="row"><span>Diskaun</span><span>- RM <?= number_format((float)$inv['discount'], 2) ?></span></div>
    <?php endif; ?>
    <?php if ((float)$inv['tax_amount'] > 0): ?>
    <div class="row"><span>Cukai (<?= (float)$inv['tax_rate'] ?>%)</span><span>RM <?= number_format((float)$inv['tax_amount'], 2) ?></span></div>
    <?php endif; ?>
    <div class="row grand"><span>Jumlah</span><span>RM <?= number_format((float)$inv['total'], 2) ?></span></div>
    <?php if ((float)$inv['amount_paid'] > 0): ?>
    <div class="row"><span>Dibayar</span><span>- RM <?= number_format((float)$inv['amount_paid'], 2) ?></span></div>
    <div class="row grand" style="color:<?= (float)$inv['balance'] > 0 ? '#e74c3c' : '#27ae60' ?>"><span>Baki</span><span>RM <?= number_format((float)$inv['balance'], 2) ?></span></div>
    <?php endif; ?>
  </div>
  <div style="clear:both"></div>

  <?php if ($inv['notes']): ?>
  <div class="doc-section">
    <h3>Terma & Syarat</h3>
    <p><?= nl2br(htmlspecialchars($inv['notes'])) ?></p>
  </div>
  <?php endif; ?>

  <?php if ($inv['payment_method']): ?>
  <div class="doc-section">
    <h3>Maklumat Pembayaran</h3>
    <p><strong>Kaedah:</strong> <?= htmlspecialchars($inv['payment_method']) ?>
    <?php if ($inv['payment_ref']): ?>
    &middot; <strong>Rujukan:</strong> <?= htmlspecialchars($inv['payment_ref']) ?>
    <?php endif; ?>
    </p>
  </div>
  <?php endif; ?>

  <div class="doc-footer">
    <p><?= htmlspecialchars($company['name']) ?> &middot; <?= htmlspecialchars($company['email']) ?> &middot; <?= htmlspecialchars($company['phone']) ?></p>
    <p>Terima kasih atas urusan anda dengan kami.</p>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
