<?php
/**
 * CKM Admin — Enquiry Detail View
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: enquiries.php'); exit; }

// Handle status update + notes
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $newNotes  = trim((string)($_POST['notes'] ?? ''));
    $validStatuses = ['new','contacted','quoted','won','lost','archived'];
    if (in_array($newStatus, $validStatuses, true)) {
        $stmt = $pdo->prepare("UPDATE enquiries SET status=?, notes=? WHERE id=?");
        $stmt->execute([$newStatus, $newNotes, $id]);
        $alert = '<div class="alert alert-success">Enquiry dikemas kini.</div>';
    }
}

$stmt = $pdo->prepare("SELECT * FROM enquiries WHERE id = ?");
$stmt->execute([$id]);
$e = $stmt->fetch();

if (!$e) { header('Location: enquiries.php'); exit; }

// Check if quotation already exists for this enquiry
$existingQuote = null;
try {
    $stmtQ = $pdo->prepare("SELECT id, quote_no, status FROM quotations WHERE enquiry_id = ? LIMIT 1");
    $stmtQ->execute([$id]);
    $existingQuote = $stmtQ->fetch();
} catch (Exception $ex) {}

$statuses = ['new'=>'Baru','contacted'=>'Dihubungi','quoted'=>'Dipetik','won'=>'Menang','lost'=>'Hilang','archived'=>'Arkib'];

$pageTitle = 'Enquiry #' . htmlspecialchars($e['ref_no']);
include __DIR__ . '/header.php';
?>
<?= $alert ?>

<div class="card">
  <div class="flex" style="justify-content:space-between;align-items:center;margin-bottom:15px">
    <div class="card-title" style="margin:0">Maklumat Enquiry</div>
    <div>
      <?php if ($existingQuote): ?>
        <a class="btn btn-sm btn-gold" href="quotation-view.php?id=<?= (int)$existingQuote['id'] ?>">Lihat Quotation</a>
      <?php else: ?>
        <a class="btn btn-sm btn-gold" href="quotation-create.php?enquiry_id=<?= $id ?>">+ Buat Quotation</a>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline" href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $e['phone']) ?>" target="_blank">WhatsApp</a>
    </div>
  </div>

  <div class="detail-grid">
    <div class="detail-item"><div class="lbl">Rujukan</div><div class="val"><?= htmlspecialchars($e['ref_no']) ?></div></div>
    <div class="detail-item"><div class="lbl">Status</div><div class="val"><span class="badge badge-<?= $e['status'] ?>"><?= $statuses[$e['status']] ?? $e['status'] ?></span></div></div>
    <div class="detail-item"><div class="lbl">Nama</div><div class="val"><?= htmlspecialchars($e['name']) ?></div></div>
    <div class="detail-item"><div class="lbl">Email</div><div class="val"><?= htmlspecialchars($e['email'] ?: 'Tidak disediakan') ?></div></div>
    <div class="detail-item"><div class="lbl">WhatsApp</div><div class="val"><?= htmlspecialchars($e['phone']) ?></div></div>
    <div class="detail-item"><div class="lbl">Premis</div><div class="val"><?= htmlspecialchars($e['premise']) ?></div></div>
    <div class="detail-item"><div class="lbl">Jenis Premis</div><div class="val"><?= htmlspecialchars($e['premise_type']) ?></div></div>
    <div class="detail-item"><div class="lbl">Lokasi</div><div class="val"><?= htmlspecialchars($e['location']) ?></div></div>
    <div class="detail-item"><div class="lbl">Keluasan</div><div class="val"><?= htmlspecialchars($e['area'] ?: 'Tidak dinyatakan') ?></div></div>
    <div class="detail-item"><div class="lbl">Tarikh Pilihan</div><div class="val"><?= htmlspecialchars($e['preferred_date'] ?: 'Tidak dinyatakan') ?></div></div>
    <div class="detail-item"><div class="lbl">Dicipta</div><div class="val"><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></div></div>
    <div class="detail-item" style="grid-column:1/-1"><div class="lbl">Keperluan Utama</div><div class="val"><?= htmlspecialchars($e['issue']) ?></div></div>
    <div class="detail-item" style="grid-column:1/-1"><div class="lbl">Catatan Pelanggan</div><div class="val"><?= nl2br(htmlspecialchars($e['message'] ?: 'Tiada')) ?></div></div>
  </div>
</div>

<div class="card">
  <div class="card-title">Kemas Kini Status & Nota</div>
  <form method="post">
    <div class="form-row">
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach ($statuses as $k => $v): ?>
            <option value="<?= $k ?>" <?= $e['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Nota Dalaman</label>
      <textarea name="notes" rows="4" placeholder="Nota untuk kegunaan admin..."><?= htmlspecialchars($e['notes'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>
<?php include __DIR__ . '/footer.php'; ?>
