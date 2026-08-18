<?php
/**
 * CKM Admin — Create Quotation
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$admin = current_admin();

// Load from enquiry if provided
$enquiryId = (int)($_GET['enquiry_id'] ?? 0);
$prefill = ['client_name'=>'','client_phone'=>'','client_address'=>'','premise'=>'','service_desc'=>'','enquiry_id'=>null,'client_email'=>''];

if ($enquiryId) {
    $stmt = $pdo->prepare("SELECT * FROM enquiries WHERE id = ?");
    $stmt->execute([$enquiryId]);
    $e = $stmt->fetch();
    if ($e) {
        $prefill = [
            'client_name'    => $e['name'],
            'client_phone'   => $e['phone'],
            'client_address' => $e['location'],
            'premise'        => $e['premise'],
            'service_desc'   => $e['issue'] . ' — ' . $e['premise_type'] . ($e['area'] ? ' (' . $e['area'] . ' sq/ft)' : ''),
            'enquiry_id'     => $enquiryId,
            'client_email'   => $e['email'] ?? '',
        ];
    }
}

// Load settings
$taxRate = 0;
$prefix  = 'Q';
$validDays = 30;
try {
    $taxRate   = (float)($pdo->query("SELECT setting_value FROM settings WHERE setting_key='tax_rate'")->fetchColumn() ?: 0);
    $prefix    = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='quote_prefix'")->fetchColumn() ?: 'Q';
    $validDays = (int)($pdo->query("SELECT setting_value FROM settings WHERE setting_key='quote_valid_days'")->fetchColumn() ?: 30);
} catch (Exception $ex) {}

$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName   = trim((string)($_POST['client_name'] ?? ''));
    $clientEmail  = trim((string)($_POST['client_email'] ?? ''));
    $clientPhone  = trim((string)($_POST['client_phone'] ?? ''));
    $clientAddress= trim((string)($_POST['client_address'] ?? ''));
    $premise      = trim((string)($_POST['premise'] ?? ''));
    $serviceDesc  = trim((string)($_POST['service_desc'] ?? ''));
    $notes        = trim((string)($_POST['notes'] ?? ''));
    $taxRateInput = (float)($_POST['tax_rate'] ?? 0);
    $discount     = (float)($_POST['discount'] ?? 0);
    $validUntil   = trim((string)($_POST['valid_until'] ?? ''));
    $postEnqId    = (int)($_POST['enquiry_id'] ?? 0);

    // Items
    $items = [];
    $descs = $_POST['item_desc'] ?? [];
    $qtys  = $_POST['item_qty']  ?? [];
    $prices= $_POST['item_price']?? [];
    for ($i = 0; $i < count($descs); $i++) {
        if (trim($descs[$i]) !== '') {
            $items[] = [
                'desc'  => trim($descs[$i]),
                'qty'   => (int)($qtys[$i] ?? 1),
                'price' => (float)($prices[$i] ?? 0),
            ];
        }
    }

    if ($clientName === '' || empty($items)) {
        $alert = '<div class="alert alert-error">Sila isi nama klien dan sekurang-kurangnya satu item.</div>';
    } else {
        $subtotal = 0;
        foreach ($items as $item) { $subtotal += $item['qty'] * $item['price']; }
        $taxAmount = ($subtotal - $discount) * ($taxRateInput / 100);
        $total = $subtotal - $discount + $taxAmount;

        // Generate quote no: Q-YYYYMMDD-XXX
        $dateStr = date('Ymd');
        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE DATE(created_at) = CURDATE()");
        $stmtC->execute();
        $seq = str_pad((string)($stmtC->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
        $quoteNo = "{$prefix}-{$dateStr}-{$seq}";

        $stmt = $pdo->prepare("
            INSERT INTO quotations (quote_no, enquiry_id, client_name, client_email, client_phone, client_address, premise, service_desc, items, subtotal, tax_rate, tax_amount, discount, total, valid_until, status, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'draft', ?, ?)
        ");
        $stmt->execute([
            $quoteNo, $postEnqId ?: null, $clientName, $clientEmail !== '' ? $clientEmail : null, $clientPhone, $clientAddress, $premise, $serviceDesc,
            json_encode($items, JSON_UNESCAPED_UNICODE), $subtotal, $taxRateInput, $taxAmount, $discount, $total,
            $validUntil ?: null, $notes, $admin['id']
        ]);

        // Update enquiry status
        if ($postEnqId) {
            $pdo->prepare("UPDATE enquiries SET status='quoted' WHERE id = ? AND status IN('new','contacted')")->execute([$postEnqId]);
        }

        $newId = (int)$pdo->lastInsertId();
        header("Location: quotation-view.php?id={$newId}&new=1");
        exit;
    }
}

$pageTitle = 'Quotation Baru';
include __DIR__ . '/header.php';
?>
<?= $alert ?>

<div class="card">
  <form method="post">
    <input type="hidden" name="enquiry_id" value="<?= $prefill['enquiry_id'] ?? '' ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Nama Klien *</label>
        <input type="text" name="client_name" required value="<?= htmlspecialchars($prefill['client_name']) ?>">
      </div>
      <div class="form-group">
        <label>Email Klien</label>
        <input type="email" name="client_email" value="<?= htmlspecialchars($prefill['client_email']) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>WhatsApp</label>
        <input type="text" name="client_phone" value="<?= htmlspecialchars($prefill['client_phone']) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Alamat</label>
        <input type="text" name="client_address" value="<?= htmlspecialchars($prefill['client_address']) ?>">
      </div>
      <div class="form-group">
        <label>Premis</label>
        <input type="text" name="premise" value="<?= htmlspecialchars($prefill['premise']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Penerangan Servis</label>
      <textarea name="service_desc" rows="3"><?= htmlspecialchars($prefill['service_desc']) ?></textarea>
    </div>

    <div class="card-title mt-20">Item Servis</div>
    <table class="items-table">
      <thead>
        <tr><th class="col-desc">Penerangan</th><th class="col-qty">Kuantiti</th><th class="col-price">Harga/unit (RM)</th><th>Jumlah (RM)</th><th class="col-action"></th></tr>
      </thead>
      <tbody id="items-body">
        <tr>
          <td class="col-desc"><input type="text" name="item_desc[]" placeholder="Cth: Cuci karpet dewan utama" required></td>
          <td class="col-qty"><input type="number" name="item_qty[]" value="1" min="1" onchange="calcTotals()"></td>
          <td class="col-price"><input type="number" name="item_price[]" value="0" step="0.01" min="0" onchange="calcTotals()"></td>
          <td class="text-center"><span class="line-total">0.00</span></td>
          <td class="col-action"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" id="add-item" class="btn btn-outline btn-sm mt-20">+ Tambah Item</button>

    <div class="form-row mt-20">
      <div class="form-group">
        <label>Kadar Cukai (%)</label>
        <input type="number" id="tax_rate" name="tax_rate" value="<?= $taxRate ?>" step="0.01" min="0" onchange="calcTotals()">
      </div>
      <div class="form-group">
        <label>Diskaun (RM)</label>
        <input type="number" id="discount" name="discount" value="0" step="0.01" min="0" onchange="calcTotals()">
      </div>
      <div class="form-group">
        <label>Sah Hingga</label>
        <input type="date" name="valid_until" value="<?= date('Y-m-d', strtotime('+' . $validDays . ' days')) ?>">
      </div>
    </div>

    <div style="background:#f4f4f4;padding:15px;border-radius:6px;margin:15px 0">
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Subtotal:</span><span id="disp-subtotal">RM 0.00</span></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Cukai:</span><span id="disp-tax">RM 0.00</span></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:700;font-size:16px;border-top:2px solid #061d2a;margin-top:5px;padding-top:8px"><span>Jumlah:</span><span id="disp-total">RM 0.00</span></div>
      <input type="hidden" id="subtotal" name="subtotal" value="0">
      <input type="hidden" id="tax_amount" name="tax_amount" value="0">
      <input type="hidden" id="total" name="total" value="0">
    </div>

    <div class="form-group">
      <label>Nota</label>
      <textarea name="notes" rows="3" placeholder="Terma & syarat, catatan tambahan..."></textarea>
    </div>
    <button type="submit" class="btn btn-gold">Simpan Quotation</button>
    <a class="btn btn-outline" href="quotations.php">Batal</a>
  </form>
</div>
<script src="assets/admin.js"></script>
<?php include __DIR__ . '/footer.php'; ?>
