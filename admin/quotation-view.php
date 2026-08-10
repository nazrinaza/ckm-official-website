<?php
/**
 * CKM Admin — Quotation View / Print
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: quotations.php'); exit; }

// Handle status update
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $valid = ['draft','sent','accepted','rejected','expired'];
    if (in_array($newStatus, $valid, true)) {
        $pdo->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$newStatus, $id]);
        $alert = '<div class="alert alert-success">Status quotation dikemas kini.</div>';
    }
}

$stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) { header('Location: quotations.php'); exit; }

$items = json_decode($q['items'], true) ?: [];
$statuses = ['draft'=>'Draf','sent'=>'Dihantar','accepted'=>'Diterima','rejected'=>'Ditolak','expired'=>'Tamat Tempoh'];

// Company info from settings
$company = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($rows as $r) { $company[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $ex) {}
$company['name']    = $company['company_name']    ?? 'cucikarpetmasjid.com';
$company['email']   = $company['company_email']   ?? 'jom@cucikarpetmasjid.com';
$company['phone']   = $company['company_phone']    ?? '';
$company['address'] = $company['company_address'] ?? '';

$pageTitle = 'Quotation ' . htmlspecialchars($q['quote_no']);
include __DIR__ . '/header.php';
?>
<?= $alert ?>
<?php if (isset($_GET['new'])): ?>
<div class="alert alert-success">Quotation berjaya dicipta.</div>
<?php endif; ?>

<div class="card no-print" style="display:flex;gap:10px;justify-content:space-between;align-items:center">
  <div>
    <strong>Status:</strong> <span class="badge badge-<?= $q['status'] ?>"><?= $statuses[$q['status']] ?? $q['status'] ?></span>
    <?php if ($q['status'] === 'accepted'): ?>
      <a class="btn btn-sm btn-gold" href="invoice-create.php?quote_id=<?= $id ?>">Buat Invoice</a>
    <?php endif; ?>
  </div>
  <div>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Cetak / PDF</button>
    <button onclick="sendDocument('quotation', <?= $id ?>, '<?= htmlspecialchars($q['client_email'] ?? '', ENT_QUOTES) ?>')" class="btn btn-gold btn-sm">Hantar Email</button>
    <a class="btn btn-outline btn-sm" href="quotations.php">Kembali</a>
  </div>
</div>

<?php if ($q['status'] === 'draft'): ?>
<div class="card no-print">
  <div class="card-title">Tukar Status</div>
  <form method="post" style="display:flex;gap:10px;align-items:center">
    <select name="status">
      <option value="draft">Draf</option>
      <option value="sent">Dihantar</option>
      <option value="accepted">Diterima</option>
      <option value="rejected">Ditolak</option>
      <option value="expired">Tamat Tempoh</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
  </form>
</div>
<?php elseif ($q['status'] !== 'accepted'): ?>
<div class="card no-print">
  <form method="post" style="display:flex;gap:10px;align-items:center">
    <label>Tukar status:</label>
    <select name="status">
      <option value="sent">Dihantar</option>
      <option value="accepted">Diterima</option>
      <option value="rejected">Ditolak</option>
      <option value="expired">Tamat Tempoh</option>
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
      <div class="doc-title">QUOTATION</div>
      <div style="font-size:14px;margin-top:5px"><strong><?= htmlspecialchars($q['quote_no']) ?></strong></div>
      <div style="font-size:12px;color:#666">Tarikh: <?= date('d/m/Y', strtotime($q['created_at'])) ?></div>
      <?php if ($q['valid_until']): ?>
      <div style="font-size:12px;color:#666">Sah Hingga: <?= date('d/m/Y', strtotime($q['valid_until'])) ?></div>
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
      <div style="font-size:13px;text-transform:uppercase;color:#999;margin-bottom:5px">Kepada</div>
      <strong><?= htmlspecialchars($q['client_name']) ?></strong><br>
      <?php if (!empty($q['client_email'])): ?><?= htmlspecialchars($q['client_email']) ?><br><?php endif; ?>
      <?= htmlspecialchars($q['client_phone']) ?><br>
      <?= nl2br(htmlspecialchars($q['client_address'])) ?>
    </div>
  </div>

  <?php if ($q['premise']): ?>
  <p style="margin-bottom:15px"><strong>Premis:</strong> <?= htmlspecialchars($q['premise']) ?></p>
  <?php endif; ?>
  <?php if ($q['service_desc']): ?>
  <p style="margin-bottom:15px"><strong>Skop Servis:</strong> <?= htmlspecialchars($q['service_desc']) ?></p>
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
    <div class="row"><span>Subtotal</span><span>RM <?= number_format((float)$q['subtotal'], 2) ?></span></div>
    <?php if ((float)$q['discount'] > 0): ?>
    <div class="row"><span>Diskaun</span><span>- RM <?= number_format((float)$q['discount'], 2) ?></span></div>
    <?php endif; ?>
    <?php if ((float)$q['tax_amount'] > 0): ?>
    <div class="row"><span>Cukai (<?= (float)$q['tax_rate'] ?>%)</span><span>RM <?= number_format((float)$q['tax_amount'], 2) ?></span></div>
    <?php endif; ?>
    <div class="row grand"><span>Jumlah</span><span>RM <?= number_format((float)$q['total'], 2) ?></span></div>
  </div>
  <div style="clear:both"></div>

  <?php if ($q['notes']): ?>
  <div class="doc-section">
    <h3>Terma & Syarat</h3>
    <p><?= nl2br(htmlspecialchars($q['notes'])) ?></p>
  </div>
  <?php endif; ?>

  <div class="doc-footer">
    <p><?= htmlspecialchars($company['name']) ?> &middot; <?= htmlspecialchars($company['email']) ?> &middot; <?= htmlspecialchars($company['phone']) ?></p>
    <p>Terima kasih atas urusan anda dengan kami.</p>
  </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
