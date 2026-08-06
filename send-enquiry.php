<?php
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

$apiKey = $config['SENDGRID_API_KEY'] ?? getenv('SENDGRID_API_KEY') ?: '';
$fromEmail = $config['SENDGRID_FROM_EMAIL'] ?? getenv('SENDGRID_FROM_EMAIL') ?: '';
$toEmail = $config['ENQUIRY_TO_EMAIL'] ?? getenv('ENQUIRY_TO_EMAIL') ?: '';

if ($apiKey === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(503);
    echo json_encode(['message' => 'Borang belum dikonfigurasi. Sila hubungi pihak cucikarpetmasjid.com secara terus.']);
    exit;
}

function field(string $name, int $maxLength): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    return mb_substr($value, 0, $maxLength);
}

// Honeypot: bot biasanya mengisi medan tersembunyi ini.
if (field('website', 200) !== '') {
    echo json_encode(['message' => 'Terima kasih. Permohonan anda telah diterima.']);
    exit;
}

$name = field('name', 100);
$phone = field('phone', 30);
$premise = field('premise', 140);
$premiseType = field('premiseType', 80);
$location = field('location', 220);
$area = field('area', 80);
$preferredDate = field('preferredDate', 20);
$issue = field('issue', 160);
$message = field('message', 1200);
$consent = field('consent', 20);

if ($name === '' || $phone === '' || $premise === '' || $premiseType === '' ||
    $location === '' || $issue === '' || $consent !== 'agreed') {
    http_response_code(422);
    echo json_encode(['message' => 'Sila lengkapkan semua ruangan wajib dan persetujuan.']);
    exit;
}

$safe = static fn(string $value): string =>
    htmlspecialchars($value !== '' ? $value : 'Tidak dinyatakan', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$subject = 'Enquiry Lawatan Tapak cucikarpetmasjid.com — ' . $premise;
$html = '
<h2>Permohonan Lawatan Tapak cucikarpetmasjid.com</h2>
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

if ($response === false || $curlError !== '' || $statusCode < 200 || $statusCode >= 300) {
    error_log('cucikarpetmasjid.com SendGrid error. HTTP ' . $statusCode . ' ' . $curlError);
    http_response_code(502);
    echo json_encode(['message' => 'Permohonan tidak dapat dihantar sekarang. Sila cuba lagi sebentar.']);
    exit;
}

echo json_encode([
    'message' => 'Terima kasih. Permohonan anda telah diterima dan akan disemak.',
]);
