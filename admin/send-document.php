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

    $totalsHtml = '<table style="width:300px;float:right;border-collapse:collapse;margin-top:15px;font-size:14px">'
        . '<tr><td style="padding:4px 0;border-top:2px solid #061d2a;text-align:left">Subtotal</td><td style="padding:4px 0;border-top:2px solid #061d2a;text-align:right">RM ' . number_format((float)$doc['subtotal'], 2) . '</td></tr>';
    if ((float)$doc['discount'] > 0) {
        $totalsHtml .= '<tr><td style="padding:4px 0;text-align:left">Diskaun</td><td style="padding:4px 0;text-align:right">- RM ' . number_format((float)$doc['discount'], 2) . '</td></tr>';
    }
    if ((float)$doc['tax_amount'] > 0) {
        $totalsHtml .= '<tr><td style="padding:4px 0;text-align:left">Cukai (' . (float)$doc['tax_rate'] . '%)</td><td style="padding:4px 0;text-align:right">RM ' . number_format((float)$doc['tax_amount'], 2) . '</td></tr>';
    }
    $totalsHtml .= '<tr style="font-size:18px;font-weight:700"><td style="padding:8px 0 4px;border-top:1px solid #ccc;text-align:left">Jumlah</td><td style="padding:8px 0 4px;border-top:1px solid #ccc;text-align:right">RM ' . number_format((float)$doc['total'], 2) . '</td></tr></table>';

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

    $totalsHtml = '<table style="width:300px;float:right;border-collapse:collapse;margin-top:15px;font-size:14px">'
        . '<tr><td style="padding:4px 0;border-top:2px solid #061d2a;text-align:left">Subtotal</td><td style="padding:4px 0;border-top:2px solid #061d2a;text-align:right">RM ' . number_format((float)$doc['subtotal'], 2) . '</td></tr>';
    if ((float)$doc['discount'] > 0) {
        $totalsHtml .= '<tr><td style="padding:4px 0;text-align:left">Diskaun</td><td style="padding:4px 0;text-align:right">- RM ' . number_format((float)$doc['discount'], 2) . '</td></tr>';
    }
    if ((float)$doc['tax_amount'] > 0) {
        $totalsHtml .= '<tr><td style="padding:4px 0;text-align:left">Cukai (' . (float)$doc['tax_rate'] . '%)</td><td style="padding:4px 0;text-align:right">RM ' . number_format((float)$doc['tax_amount'], 2) . '</td></tr>';
    }
    $totalsHtml .= '<tr style="font-size:18px;font-weight:700"><td style="padding:8px 0 4px;border-top:1px solid #ccc;text-align:left">Jumlah</td><td style="padding:8px 0 4px;border-top:1px solid #ccc;text-align:right">RM ' . number_format((float)$doc['total'], 2) . '</td></tr>';
    if ((float)$doc['amount_paid'] > 0) {
        $totalsHtml .= '<tr><td style="padding:4px 0;text-align:left">Dibayar</td><td style="padding:4px 0;text-align:right">- RM ' . number_format((float)$doc['amount_paid'], 2) . '</td></tr>';
        $bakiColor = (float)$doc['balance'] > 0 ? '#e74c3c' : '#27ae60';
        $totalsHtml .= '<tr style="font-weight:700;color:' . $bakiColor . '"><td style="padding:5px 0;border-top:1px solid #ccc;text-align:left">Baki</td><td style="padding:5px 0;border-top:1px solid #ccc;text-align:right">RM ' . number_format((float)$doc['balance'], 2) . '</td></tr>';
    }
    $totalsHtml .= '</table>';
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

// Send via Resend API (HTTP port 443 — bypasses hosting SMTP block)
$resendKey = $config['RESEND_API_KEY'] ?? '';
$resendFromEmail = $config['RESEND_FROM_EMAIL'] ?? $smtpUser;
$resendFromName  = $config['RESEND_FROM_NAME'] ?? $fromName;

if ($resendKey !== '') {
    require_once __DIR__ . '/../resend.php';
    $resend = new CkmResend($resendKey, $resendFromEmail, $resendFromName);
    $sent = $resend->send($email, $subject, $html);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => $docType . ' berjaya dihantar ke ' . $email]);
    } else {
        error_log('CKM send-document Resend error: ' . $resend->getError());
        // Fallback to PHP mail()
        $fallbackSent = @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html,
            "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$fromName} <{$smtpUser}>\r\n",
            '-f ' . $smtpUser);
        if ($fallbackSent) {
            echo json_encode(['success' => true, 'message' => $docType . ' dihantar ke ' . $email . ' (fallback)']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghantar email. Sila hubungi pentadbir.']);
        }
    }
} else {
    // No Resend key — use PHP mail() fallback
    $sent = @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html,
        "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: {$fromName} <{$smtpUser}>\r\n",
        '-f ' . $smtpUser);
    if ($sent) {
        echo json_encode(['success' => true, 'message' => $docType . ' dihantar ke ' . $email]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghantar email. Resend API key belum dikonfigurasi.']);
    }
}
