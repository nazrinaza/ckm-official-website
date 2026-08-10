<?php
/**
 * CKM — Enquiry Form Handler
 * Saves to database (admin system) + sends email via Zoho Mail SMTP
 * - Email 1: Notification to CKM admin (jom@cucikarpetmasjid.com)
 * - Email 2: Acknowledgement to customer (if email provided)
 * cucikarpetmasjid.com
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Kaedah permintaan tidak dibenarkan.']);
    exit;
}

$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : [];

// Zoho Mail SMTP config
$smtpHost = $config['ZOHO_SMTP_HOST'] ?? 'smtp.zoho.com';
$smtpPort = (int)($config['ZOHO_SMTP_PORT'] ?? 587);
$smtpUser = $config['ZOHO_SMTP_USER'] ?? '';
$smtpPass = $config['ZOHO_SMTP_PASS'] ?? '';
$fromName = $config['ZOHO_FROM_NAME'] ?? 'cucikarpetmasjid.com';
$toEmail  = $config['ENQUIRY_TO_EMAIL'] ?? $smtpUser;

function field(string $name, int $maxLength): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    return mb_substr($value, 0, $maxLength);
}

// Honeypot
if (field('website', 200) !== '') {
    echo json_encode(['message' => 'Terima kasih. Permohonan anda telah diterima.']);
    exit;
}

$name          = field('name', 100);
$email         = field('email', 150);
$phone         = field('phone', 30);
$premise       = field('premise', 140);
$premiseType   = field('premiseType', 80);
$location      = field('location', 220);
$area          = field('area', 80);
$preferredDate = field('preferredDate', 20);
$issue         = field('issue', 160);
$message       = field('message', 1200);
$consent       = field('consent', 20);

if ($name === '' || $phone === '' || $premise === '' || $premiseType === '' ||
    $location === '' || $issue === '' || $consent !== 'agreed') {
    http_response_code(422);
    echo json_encode(['message' => 'Sila lengkapkan semua ruangan wajib dan persetujuan.']);
    exit;
}

/* ── Save to database ── */
$dbSaved = false;
$refNo   = 'CKM-' . date('Ymd') . '-000';

try {
    $dbHost = $config['DB_HOST'] ?? 'localhost';
    $dbName = $config['DB_NAME'] ?? 'ckm_admin';
    $dbUser = $config['DB_USER'] ?? 'root';
    $dbPass = $config['DB_PASS'] ?? '';
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $dateStr = date('Ymd');
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM enquiries WHERE DATE(created_at) = CURDATE()");
    $stmtCount->execute();
    $seq = str_pad((string)($stmtCount->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    $refNo = "CKM-{$dateStr}-{$seq}";

    $stmt = $pdo->prepare("
        INSERT INTO enquiries (ref_no, name, email, phone, premise, premise_type, location, area, preferred_date, issue, message, consent, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute([$refNo, $name, $email !== '' ? $email : null, $phone, $premise, $premiseType, $location, $area, $preferredDate, $issue, $message, $consent]);
    $dbSaved = true;
} catch (Exception $e) {
    error_log('CKM DB error: ' . $e->getMessage());
}

/* ── Send emails via Zoho Mail SMTP ── */
$adminEmailSent = false;
$ackEmailSent   = false;

if ($smtpUser !== '' && $smtpPass !== '' && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    require_once __DIR__ . '/smtp.php';

    $safe = static fn(string $value): string =>
        htmlspecialchars($value !== '' ? $value : 'Tidak dinyatakan', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    /* ── Email 1: Notification to CKM admin ── */
    $adminSubject = 'Enquiry Lawatan Tapak cucikarpetmasjid.com — ' . $premise;
    $adminHtml = '
<h2>Permohonan Lawatan Tapak cucikarpetmasjid.com</h2>
<p><strong>Rujukan:</strong> ' . $safe($refNo) . '</p>
<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif">
  <tr><td><strong>Nama</strong></td><td>' . $safe($name) . '</td></tr>
  <tr><td><strong>Email</strong></td><td>' . $safe($email) . '</td></tr>
  <tr><td><strong>WhatsApp</strong></td><td>' . $safe($phone) . '</td></tr>
  <tr><td><strong>Premis</strong></td><td>' . $safe($premise) . '</td></tr>
  <tr><td><strong>Jenis premis</strong></td><td>' . $safe($premiseType) . '</td></tr>
  <tr><td><strong>Lokasi</strong></td><td>' . $safe($location) . '</td></tr>
  <tr><td><strong>Anggaran keluasan</strong></td><td>' . $safe($area) . ($area ? ' sq/ft' : '') . '</td></tr>
  <tr><td><strong>Tarikh pilihan</strong></td><td>' . $safe($preferredDate) . '</td></tr>
  <tr><td><strong>Keperluan utama</strong></td><td>' . $safe($issue) . '</td></tr>
  <tr><td><strong>Catatan</strong></td><td>' . nl2br($safe($message)) . '</td></tr>
</table>';

    try {
        $smtp = new CkmSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass);
        $adminEmailSent = $smtp->send($toEmail, $adminSubject, $adminHtml, $fromName);
    } catch (Exception $e) {
        error_log('CKM SMTP admin email error: ' . $e->getMessage());
    }

    /* ── Email 2: Acknowledgement to customer (if email provided) ── */
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $ackSubject = 'Pengesahan Permohonan — ' . $refNo . ' — cucikarpetmasjid.com';
        $ackHtml = '
<div style="max-width:560px;margin:0 auto;font-family:Arial,sans-serif;color:#333">
  <div style="background:#061d2a;padding:20px;text-align:center;border-radius:6px 6px 0 0">
    <h1 style="color:#d1a54a;margin:0;font-size:20px">cucikarpetmasjid.com</h1>
    <p style="color:#f5f2ea;margin:5px 0 0;font-size:13px">Cucian karpet profesional untuk surau &amp; masjid</p>
  </div>
  <div style="padding:25px;border:1px solid #e0e0e0;border-radius:0 0 6px 6px">
    <p>Assalamualaikum dan salam sejahtera <strong>' . $safe($name) . '</strong>,</p>
    <p>Terima kasih kerana menghantar permohonan lawatan tapak kepada <strong>cucikarpetmasjid.com</strong>. Kami telah menerima permohonan anda dan akan menyemak butiran berikut:</p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;margin:15px 0">
      <tr><td style="background:#f5f2ea"><strong>No. Rujukan</strong></td><td style="background:#f5f2ea">' . $safe($refNo) . '</td></tr>
      <tr><td><strong>Premis</strong></td><td>' . $safe($premise) . '</td></tr>
      <tr><td><strong>Lokasi</strong></td><td>' . $safe($location) . '</td></tr>
      <tr><td><strong>Keperluan</strong></td><td>' . $safe($issue) . '</td></tr>
    </table>
    <p>Pasukan kami akan menghubungi anda melalui WhatsApp di nombor <strong>' . $safe($phone) . '</strong> dalam masa 1-2 hari kerja untuk pengesahan dan penjadualan lawatan.</p>
    <p style="background:#f5f2ea;padding:12px;border-radius:4px;font-size:13px"><strong>Nota:</strong> Simpan nombor rujukan <strong>' . $safe($refNo) . '</strong> untuk sebarang pertanyaan lanjut.</p>
    <p>Sekiranya ada maklumat tambahan yang ingin dikongsikan, sila balas email ini atau hubungi kami terus.</p>
    <hr style="border:none;border-top:1px solid #e0e0e0;margin:20px 0">
    <p style="font-size:12px;color:#999">Email ini dihantar secara automatik. Sila jangan balas jika tiada pertanyaan tambahan.</p>
    <p style="font-size:13px;color:#061d2a"><strong>cucikarpetmasjid.com</strong><br>jom@cucikarpetmasjid.com</p>
  </div>
</div>';

        try {
            $smtp2 = new CkmSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass);
            $ackEmailSent = $smtp2->send($email, $ackSubject, $ackHtml, $fromName);
        } catch (Exception $e) {
            error_log('CKM SMTP acknowledgement email error: ' . $e->getMessage());
        }
    }
}

/* ── Response ── */
if ($dbSaved) {
    echo json_encode([
        'message' => 'Terima kasih. Permohonan anda telah diterima dan akan disemak.',
        'ref_no'  => $refNo,
    ]);
} elseif ($adminEmailSent || $ackEmailSent) {
    echo json_encode([
        'message' => 'Terima kasih. Permohonan anda telah diterima.',
    ]);
} else {
    http_response_code(502);
    echo json_encode(['message' => 'Permohonan tidak dapat dihantar sekarang. Sila cuba lagi sebentar.']);
}
