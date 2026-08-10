<?php
/**
 * CKM Admin — Send Document (Quotation/Invoice) via Email
 * AJAX endpoint: POST with type=quotation|invoice, id=N, email=customer@email
 * Uses Zoho Mail SMTP (smtp.php)
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Kaedah tidak dibenarkan.']);
    exit;
}

$type = trim((string)($_POST['type'] ?? ''));
$id   = (int)($_POST['id'] ?? 0);
$email = trim((string)($_POST['email'] ?? ''));

if (!in_array($type, ['quotation', 'invoice'], true) || !$id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak sah.']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Alamat email tidak sah.']);
    exit;
}

// Load config
$configFile = __DIR__ . '/../config.php';
$config = is_file($configFile) ? require $configFile : [];

$smtpHost = $config['ZOHO_SMTP_HOST'] ?? 'smtp.zoho.com';
$smtpPort = (int)($config['ZOHO_SMTP_PORT'] ?? 587);
$smtpUser = $config['ZOHO_SMTP_USER'] ?? '';
$smtpPass = $config['ZOHO_SMTP_PASS'] ?? '';
$fromName = $config['ZOHO_FROM_NAME'] ?? 'cucikarpetmasjid.com';

if ($smtpUser === '' || $smtpPass === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'SMTP belum dikonfigurasi.']);
    exit;
}

// Fetch document + company info
$company = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($rows as $r) { $company[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $ex) {}

$company['name']    = $company['company_name']    ?? 'cucikarpetmasjid.com';
$company['email']   = $company['company_email']   ?? $smtpUser;
$company['phone']   = $company['company_phone']   ?? '';
$company['address'] = $company['company_address'] ?? '';

$safe = static fn(string $v): string => htmlspecialchars($v !== '' ? $v : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Build email HTML
if ($type === 'quotation') {
    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Quotation tidak dijumpai.']);
        exit;
    }
    $docNo  = $doc['quote_no'];
    $items  = json_decode($doc['items'], true) ?: [];
    $docDate = date('d/m/Y', strtotime($doc['created_at']));
    $docType = 'Quotation';
    $docTypeLower = 'sebut harga';

    // Auto-update status to 'sent'
    if ($doc['status'] === 'draft') {
        $pdo->prepare("UPDATE quotations SET status='sent' WHERE id = ?")->execute([$id]);
    }

    $totalsHtml = '<div style="margin-top:15px;border-top:2px solid #061d2a;padding-top:10px">'
        . '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Subtotal</span><span>RM ' . number_format((float)$doc['subtotal'], 2) . '</span></div>';
    if ((float)$doc['discount'] > 0) {
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Diskaun</span><span>- RM ' . number_format((float)$doc['discount'], 2) . '</span></div>';
    }
    if ((float)$doc['tax_amount'] > 0) {
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Cukai (' . (float)$doc['tax_rate'] . '%)</span><span>RM ' . number_format((float)$doc['tax_amount'], 2) . '</span></div>';
    }
    $totalsHtml .= '<div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;border-top:1px solid #ccc;margin-top:5px;padding-top:8px"><span>Jumlah</span><span>RM ' . number_format((float)$doc['total'], 2) . '</span></div></div>';

} else {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Invoice tidak dijumpai.']);
        exit;
    }
    $docNo  = $doc['invoice_no'];
    $items  = json_decode($doc['items'], true) ?: [];
    $docDate = date('d/m/Y', strtotime($doc['issue_date']));
    $docType = 'Invoice';
    $docTypeLower = 'invois';

    $totalsHtml = '<div style="margin-top:15px;border-top:2px solid #061d2a;padding-top:10px">'
        . '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Subtotal</span><span>RM ' . number_format((float)$doc['subtotal'], 2) . '</span></div>';
    if ((float)$doc['discount'] > 0) {
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Diskaun</span><span>- RM ' . number_format((float)$doc['discount'], 2) . '</span></div>';
    }
    if ((float)$doc['tax_amount'] > 0) {
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Cukai (' . (float)$doc['tax_rate'] . '%)</span><span>RM ' . number_format((float)$doc['tax_amount'], 2) . '</span></div>';
    }
    $totalsHtml .= '<div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;border-top:1px solid #ccc;margin-top:5px;padding-top:8px"><span>Jumlah</span><span>RM ' . number_format((float)$doc['total'], 2) . '</span></div>';
    if ((float)$doc['amount_paid'] > 0) {
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0"><span>Dibayar</span><span>- RM ' . number_format((float)$doc['amount_paid'], 2) . '</span></div>';
        $bakiColor = (float)$doc['balance'] > 0 ? '#e74c3c' : '#27ae60';
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;font-weight:700;color:' . $bakiColor . ';border-top:1px solid #ccc;padding-top:5px"><span>Baki</span><span>RM ' . number_format((float)$doc['balance'], 2) . '</span></div>';
    }
    $totalsHtml .= '</div>';
}

// Build items table
$itemsHtml = '<table style="width:100%;border-collapse:collapse;font-size:13px;margin:15px 0">
    <thead><tr style="background:#061d2a;color:#f5f2ea">
        <th style="padding:8px;text-align:left">Bil</th>
        <th style="padding:8px;text-align:left">Penerangan</th>
        <th style="padding:8px;text-align:center">Kuantiti</th>
        <th style="padding:8px;text-align:right">Harga/unit</th>
        <th style="padding:8px;text-align:right">Jumlah</th>
    </tr></thead><tbody>';

$i = 1;
foreach ($items as $item) {
    $itemsHtml .= '<tr style="border-bottom:1px solid #eee">
        <td style="padding:8px">' . $i++ . '</td>
        <td style="padding:8px">' . $safe($item['desc']) . '</td>
        <td style="padding:8px;text-align:center">' . (int)$item['qty'] . '</td>
        <td style="padding:8px;text-align:right">RM ' . number_format((float)$item['price'], 2) . '</td>
        <td style="padding:8px;text-align:right">RM ' . number_format($item['qty'] * $item['price'], 2) . '</td>
    </tr>';
}
$itemsHtml .= '</tbody></table>';

// Full email HTML
$subject = $docType . ' ' . $docNo . ' — cucikarpetmasjid.com';

$html = '
<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#333">
  <div style="background:#061d2a;padding:20px;text-align:center;border-radius:6px 6px 0 0">
    <h1 style="color:#d1a54a;margin:0;font-size:22px">' . $docType . '</h1>
    <p style="color:#f5f2ea;margin:5px 0 0;font-size:13px">cucikarpetmasjid.com</p>
  </div>
  <div style="padding:25px;border:1px solid #e0e0e0;border-radius:0 0 6px 6px">
    <p>Salam ' . $safe($doc['client_name']) . ',</p>
    <p>Berikut adalah butiran ' . $docTypeLower . ' anda daripada <strong>cucikarpetmasjid.com</strong>:</p>
    <table style="width:100%;font-size:14px;margin:10px 0;border-collapse:collapse">
      <tr style="background:#f5f2ea"><td style="padding:8px"><strong>No. ' . $docType . '</strong></td><td style="padding:8px">' . $safe($docNo) . '</td></tr>
      <tr><td style="padding:8px"><strong>Tarikh</strong></td><td style="padding:8px">' . $safe($docDate) . '</td></tr>';
if (!empty($doc['premise'])) {
    $html .= '<tr><td style="padding:8px"><strong>Premis</strong></td><td style="padding:8px">' . $safe($doc['premise']) . '</td></tr>';
}
$html .= '
    </table>
    ' . $itemsHtml . '
    ' . $totalsHtml . '
    <div style="clear:both"></div>';

if (!empty($doc['notes'])) {
    $html .= '<div style="margin-top:15px;border-top:1px solid #eee;padding-top:10px"><strong>Terma &amp; Syarat:</strong><br>' . nl2br($safe($doc['notes'])) . '</div>';
}

$html .= '
    <hr style="border:none;border-top:1px solid #e0e0e0;margin:20px 0">
    <p style="font-size:13px">Sekiranya ada pertanyaan, sila hubungi kami:</p>
    <p style="font-size:13px;color:#061d2a">
      <strong>' . $safe($company['name']) . '</strong><br>
      Email: ' . $safe($company['email']) . '<br>
      Telefon: ' . $safe($company['phone']) . '<br>
      ' . nl2br($safe($company['address'])) . '
    </p>
    <p style="font-size:11px;color:#999;margin-top:15px">Email ini dijana secara automatik oleh sistem admin cucikarpetmasjid.com</p>
  </div>
</div>';

// Send via PHP mail() — cPanel Exim MTA
// Note: Hosting intercepts outbound SMTP (port 25/587 -> local Exim),
// so direct Zoho SMTP is impossible. PHP mail() via Exim is the only option.
// Requires SPF record to include server IP 103.191.76.66.

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $fromName . ' <' . $smtpUser . '>',
    'Reply-To: ' . $fromName . ' <' . $smtpUser . '>',
    'X-Mailer: CKM-Admin/1.0',
    'Date: ' . date(DATE_RFC2822),
    'Message-ID: <' . md5(uniqid('', true)) . '@cucikarpetmasjid.com>',
];

$sent = @mail($email, $encodedSubject, $html, implode("\r\n", $headers), '-f ' . $smtpUser);

if ($sent) {
    echo json_encode(['success' => true, 'message' => $docType . ' berjaya dihantar ke ' . $email]);
} else {
    $lastError = error_get_last();
    error_log('CKM send-document mail() failed: ' . ($lastError['message'] ?? 'unknown'));
    echo json_encode(['success' => false, 'message' => 'Gagal menghantar email. ' . ($lastError['message'] ?? 'Sila hubungi pentadbir.')]);
}
