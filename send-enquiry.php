<?php
/**
 * CKM — Enquiry Form Handler
 * Saves to database (admin system) + sends email via SendGrid
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

$apiKey    = $config['SENDGRID_API_KEY'] ?? getenv('SENDGRID_API_KEY') ?: '';
$fromEmail = $config['SENDGRID_FROM_EMAIL'] ?? getenv('SENDGRID_FROM_EMAIL') ?: '';
$toEmail   = $config['ENQUIRY_TO_EMAIL'] ?? getenv('ENQUIRY_TO_EMAIL') ?: '';

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
$dbError = null;

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

    // Generate ref no: CKM-YYYYMMDD-XXX
    $dateStr = date('Ymd');
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM enquiries WHERE DATE(created_at) = CURDATE()");
    $stmtCount->execute();
    $seq = str_pad((string)($stmtCount->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    $refNo = "CKM-{$dateStr}-{$seq}";

    $stmt = $pdo->prepare("
        INSERT INTO enquiries (ref_no, name, phone, premise, premise_type, location, area, preferred_date, issue, message, consent, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute([$refNo, $name, $phone, $premise, $premiseType, $location, $area, $preferredDate, $issue, $message, $consent]);
    $dbSaved = true;
} catch (Exception $e) {
    $dbError = $e->getMessage();
    error_log('CKM DB error: ' . $dbError);
}

/* ── Send email via SendGrid (if configured) ── */
$emailSent = false;

if ($apiKey !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) && filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    $safe = static fn(string $value): string =>
        htmlspecialchars($value !== '' ? $value : 'Tidak dinyatakan', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $subject = 'Enquiry Lawatan Tapak cucikarpetmasjid.com — ' . $premise;
    $html = '
<h2>Permohonan Lawatan Tapak cucikarpetmasjid.com</h2>
<p><strong>Rujukan:</strong> ' . $safe($refNo) . '</p>
<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif">
  <tr><td><strong>Nama</strong></td><td>' . $safe($name) . '</td></tr>
  <tr><td><strong>WhatsApp</strong></td><td>' . $safe($phone) . '</td></tr>
  <tr><td><strong>Premis</strong></td><td>' . $safe($premise) . '</td></tr>
  <tr><td><strong>Jenis premis</strong></td><td>' . $safe($premiseType) . '</td></tr>
  <tr><td><strong>Lokasi</strong></td><td>' . $safe($location) . '</td></tr>
  <tr><td><strong>Anggaran keluasan</strong></td><td>' . $safe($area) . '</td></tr>
  <tr><td><strong>Tarikh pilihan</strong></td><td>' . $safe($preferredDate) . '</td></tr>
  <tr><td><strong>Keperluan utama</strong></td><td>' . $safe($issue) . '</td></tr>
  <tr><td><strong>Catatan</strong></td><td>' . nl2br($safe($message)) . '</td></tr>
</table>';

    $payload = [
        'personalizations' => [[
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
        ]],
        'from' => ['email' => $fromEmail, 'name' => 'cucikarpetmasjid.com'],
        'reply_to' => ['email' => $fromEmail, 'name' => 'cucikarpetmasjid.com'],
        'content' => [['type' => 'text/html', 'value' => $html]],
    ];

    $curl = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $emailSent = ($response !== false && $curlError === '' && $statusCode >= 200 && $statusCode < 300);

    if (!$emailSent) {
        error_log('CKM SendGrid error. HTTP ' . $statusCode . ' ' . $curlError);
    }
}

/* ── Response ── */
if ($dbSaved) {
    echo json_encode([
        'message' => 'Terima kasih. Permohonan anda telah diterima dan akan disemak.',
        'ref_no'  => $refNo,
    ]);
} elseif ($emailSent) {
    echo json_encode([
        'message' => 'Terima kasih. Permohonan anda telah diterima.',
    ]);
} else {
    http_response_code(502);
    echo json_encode(['message' => 'Permohonan tidak dapat dihantar sekarang. Sila cuba lagi sebentar.']);
}
