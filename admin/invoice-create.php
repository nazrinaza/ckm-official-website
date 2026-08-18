<?php
/**
 * CKM Admin — Create Invoice (from quotation or standalone)
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$admin = current_admin();
$quoteId = (int)($_GET['quote_id'] ?? 0);

// Prefill from quotation
$prefill = [
    'client_name'=>'','client_email'=>'','client_phone'=>'','client_address'=>'','premise'=>'',
    'service_desc'=>'','items'=>[],'subtotal'=>0,'tax_rate'=>0,'tax_amount'=>0,
    'discount'=>0,'total'=>0,'quotation_id'=>null
];

if ($quoteId) {
    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$quoteId]);
    $q = $stmt->fetch();
    if ($q) {
        $prefill = [
            'client_name'    => $q['client_name'],
            'client_email'   => $q['client_email'] ?? '',
            'client_phone'   => $q['client_phone'] ?? '',
            'client_address' => $q['client_address'] ?? '',
            'premise'        => $q['premise'] ?? '',
            'service_desc'   => $q['service_desc'] ?? '',
            'items'          => json_decode($q['items'], true) ?: [],
            'subtotal'       => (float)$q['subtotal'],
            'tax_rate'       => (float)$q['tax_rate'],
            'tax_amount'     => (float)$q['tax_amount'],
            'discount'       => (float)$q['discount'],
            'total'          => (float)$q['total'],
            'quotation_id'   => $quoteId,
        ];
    }
}

// Load settings
$prefix = 'INV';
try {
    $prefix = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='invoice_prefix'")->fetchColumn() ?: 'INV';
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
    $issueDate    = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
    $dueDate      = trim((string)($_POST['due_date'] ?? ''));
    $postQuoteId  = (int)($_POST['quotation_id'] ?? 0);

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

        $dateStr = date('Ymd');
        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE DATE(created_at) = CURDATE()");
        $stmtC->execute();
        $seq = str_pad((string)($stmtC->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
        $invoiceNo = "{$prefix}-{$dateStr}-{$seq}";

        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_no, quotation_id, client_name, client_email, client_phone, client_address, premise, service_desc, items, subtotal, tax_rate, tax_amount, discount, total, amount_paid, balance, status, issue_date, due_date, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, ?, 0, ?, 'unpaid', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceNo, $postQuoteId ?: null, $clientName, $clientEmail !== '' ? $clientEmail : null, $clientPhone, $clientAddress, $premise, $serviceDesc,
            json_encode($items, JSON_UNESCAPED_UNICODE), $subtotal, $taxRateInput, $taxAmount, $discount, $total,
            $total, // balance = total
            $issueDate ?: date('Y-m-d'), $dueDate ?: null, $notes, $admin['id']
        ]);

        $newId = (int)$pdo->lastInsertId();
        header("Location: invoice-view.php?id={$newId}&new=1");
        exit;
    }
}

$pageTitle = 'Invoice Baru';
include __DIR__ . '/header.php';
?>
<?= $alert ?>

<div class="card">
  <form method="post">
    <input type="hidden" name="quotation_id" value="<?= $prefill['quotation_id'] ?? '' ?>">
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
    <div class="form-row">
      <div class="form-group">
        <label>Tarikh Dikeluarkan</label>
        <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-group">
        <label>Tarikh Akhir Bayaran</label>
        <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>">
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
        <?php if (!empty($prefill['items'])): ?>
          <?php foreach ($prefill['items'] as $item): ?>
          <tr>
            <td class="col-desc"><input type="text" name="item_desc[]" value="<?= htmlspecialchars($item['desc']) ?>"></td>
            <td class="col-qty"><input type="number" name="item_qty[]" value="<?= (int)$item['qty'] ?>" min="1" onchange="calcTotals()"></td>
            <td class="col-price"><input type="number" name="item_price[]" value="<?= $item['price'] ?>" step="0.01" min="0" onchange="calcTotals()"></td>
            <td class="text-center"><span class="line-total"><?= number_format($item['qty'] * $item['price'], 2) ?></span></td>
            <td class="col-action"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
        <tr>
          <td class="col-desc"><input type="text" name="item_desc[]" placeholder="Cth: Cuci karpet dewan utama" required></td>
          <td class="col-qty"><input type="number" name="item_qty[]" value="1" min="1" onchange="calcTotals()"></td>
          <td class="col-price"><input type="number" name="item_price[]" value="0" step="0.01" min="0" onchange="calcTotals()"></td>
          <td class="text-center"><span class="line-total">0.00</span></td>
          <td class="col-action"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">&times;</button></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <button type="button" id="add-item" class="btn btn-outline btn-sm mt-20">+ Tambah Item</button>

    <div class="form-row mt-20">
      <div class="form-group">
        <label>Kadar Cukai (%)</label>
        <input type="number" id="tax_rate" name="tax_rate" value="<?= $prefill['tax_rate'] ?>" step="0.01" min="0" onchange="calcTotals()">
      </div>
      <div class="form-group">
        <label>Diskaun (RM)</label>
        <input type="number" id="discount" name="discount" value="<?= $prefill['discount'] ?>" step="0.01" min="0" onchange="calcTotals()">
      </div>
    </div>

    <div style="background:#f4f4f4;padding:15px;border-radius:6px;margin:15px 0">
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Subtotal:</span><span id="disp-subtotal">RM <?= number_format($prefill['subtotal'], 2) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span>Cukai:</span><span id="disp-tax">RM <?= number_format($prefill['tax_amount'], 2) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:700;font-size:16px;border-top:2px solid #061d2a;margin-top:5px;padding-top:8px"><span>Jumlah:</span><span id="disp-total">RM <?= number_format($prefill['total'], 2) ?></span></div>
      <input type="hidden" id="subtotal" name="subtotal" value="<?= $prefill['subtotal'] ?>">
      <input type="hidden" id="tax_amount" name="tax_amount" value="<?= $prefill['tax_amount'] ?>">
      <input type="hidden" id="total" name="total" value="<?= $prefill['total'] ?>">
    </div>

    <div class="form-group">
      <label>Nota</label>
      <textarea name="notes" rows="3" placeholder="Terma pembayaran, maklumat bank, catatan..."></textarea>
    </div>
    <button type="submit" class="btn btn-gold">Simpan Invoice</button>
    <a class="btn btn-outline" href="invoices.php">Batal</a>
  </form>
</div>
<script src="assets/admin.js"></script>
<?php include __DIR__ . '/footer.php'; ?>
