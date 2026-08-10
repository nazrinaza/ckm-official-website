<?php
/**
 * CKM Admin — Settings
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$alert = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'company_name'     => trim((string)($_POST['company_name'] ?? '')),
        'company_email'    => trim((string)($_POST['company_email'] ?? '')),
        'company_phone'    => trim((string)($_POST['company_phone'] ?? '')),
        'company_address'  => trim((string)($_POST['company_address'] ?? '')),
        'tax_rate'         => (string)(float)($_POST['tax_rate'] ?? 0),
        'currency'         => trim((string)($_POST['currency'] ?? 'RM')),
        'quote_prefix'     => trim((string)($_POST['quote_prefix'] ?? 'Q')),
        'invoice_prefix'   => trim((string)($_POST['invoice_prefix'] ?? 'INV')),
        'quote_valid_days' => (string)(int)($_POST['quote_valid_days'] ?? 30),
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    $alert = '<div class="alert alert-success">Tetapan disimpan.</div>';

    // Change password
    $newPass = trim((string)($_POST['new_password'] ?? ''));
    if ($newPass !== '') {
        if (strlen($newPass) < 6) {
            $alert .= '<div class="alert alert-error">Kata laluan mesti sekurang-kurangnya 6 aksara.</div>';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admins SET password_hash=? WHERE id=?")->execute([$hash, current_admin()['id']]);
            $alert .= '<div class="alert alert-success">Kata laluan ditukar.</div>';
        }
    }
}

// Load settings
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $e) {}

$pageTitle = 'Tetapan';
include __DIR__ . '/header.php';
?>
<?= $alert ?>

<div class="card">
  <div class="card-title">Maklumat Syarikat</div>
  <form method="post">
    <div class="form-row">
      <div class="form-group">
        <label>Nama Syarikat</label>
        <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? 'cucikarpetmasjid.com') ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="company_email" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Telefon</label>
        <input type="text" name="company_phone" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Alamat</label>
        <input type="text" name="company_address" value="<?= htmlspecialchars($settings['company_address'] ?? '') ?>">
      </div>
    </div>

    <div class="card-title mt-20">Konfigurasi Sistem</div>
    <div class="form-row">
      <div class="form-group">
        <label>Kadar Cukai (%)</label>
        <input type="number" name="tax_rate" value="<?= htmlspecialchars($settings['tax_rate'] ?? '0') ?>" step="0.01" min="0">
      </div>
      <div class="form-group">
        <label>Mata Wang</label>
        <input type="text" name="currency" value="<?= htmlspecialchars($settings['currency'] ?? 'RM') ?>" style="width:80px">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Prefix Quotation</label>
        <input type="text" name="quote_prefix" value="<?= htmlspecialchars($settings['quote_prefix'] ?? 'Q') ?>" style="width:80px">
      </div>
      <div class="form-group">
        <label>Prefix Invoice</label>
        <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV') ?>" style="width:80px">
      </div>
      <div class="form-group">
        <label>Quote Sah (Hari)</label>
        <input type="number" name="quote_valid_days" value="<?= htmlspecialchars($settings['quote_valid_days'] ?? '30') ?>" min="1" style="width:80px">
      </div>
    </div>

    <div class="card-title mt-20">Tukar Kata Laluan</div>
    <div class="form-group">
      <label>Kata Laluan Baru</label>
      <input type="password" name="new_password" placeholder="Biarkan kosong jika tidak menukar" style="width:300px">
    </div>

    <button type="submit" class="btn btn-gold">Simpan Tetapan</button>
  </form>
</div>
<?php include __DIR__ . '/footer.php'; ?>
