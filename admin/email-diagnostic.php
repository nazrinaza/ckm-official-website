<?php
/**
 * CKM Email Diagnostic — Test email sending methods from cPanel server
 * Access: https://cucikarpetmasjid.com/staging/admin/email-diagnostic.php
 * Requires admin login.
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

$configFile = __DIR__ . '/../config.php';
$config = is_file($configFile) ? require $configFile : [];

$smtpHost = $config['ZOHO_SMTP_HOST'] ?? 'smtp.zoho.com';
$smtpPort = (int)($config['ZOHO_SMTP_PORT'] ?? 587);
$smtpUser = $config['ZOHO_SMTP_USER'] ?? '';
$smtpPass = $config['ZOHO_SMTP_PASS'] ?? '';
$fromName = $config['ZOHO_FROM_NAME'] ?? 'cucikarpetmasjid.com';

$results = [];

// 1. Server info
$results['server_ip'] = file_get_contents('https://api.ipify.org?format=text') ?: '(unknown)';
$results['php_version'] = PHP_VERSION;
$results['sendmail_path'] = ini_get('sendmail_path') ?: '(not set)';
$results['smtp_ini'] = ini_get('SMTP') ?: '(not set)';
$results['smtp_port_ini'] = ini_get('smtp_port') ?: '(not set)';

// 2. Check function availability
$results['fsockopen'] = function_exists('fsockopen') ? 'YES' : 'NO';
$results['stream_socket_client'] = function_exists('stream_socket_client') ? 'YES' : 'NO';
$results['curl'] = function_exists('curl_init') ? 'YES' : 'NO';
$results['mail'] = function_exists('mail') ? 'YES' : 'NO';
$results['openssl'] = extension_loaded('openssl') ? 'YES' : 'NO';

// 3. Test TCP connectivity to Zoho SMTP on various ports
$ports = [25, 465, 587];
foreach ($ports as $port) {
    $key = "tcp_zoho_{$port}";
    $remote = ($port === 465) ? "ssl://{$smtpHost}:{$port}" : "{$smtpHost}:{$port}";
    $sock = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if ($sock) {
        $banner = fgets($sock, 515);
        fclose($sock);
        $results[$key] = "OK — Banner: " . trim($banner);
    } else {
        $results[$key] = "FAIL — {$errstr} ({$errno})";
    }
}

// 4. Test PHP mail() to jom@cucikarpetmasjid.com
$testSubject = 'CKM Diagnostic Test — ' . date('Y-m-d H:i:s');
$testBody = '<h2>CKM Email Diagnostic</h2><p>Test email dari cPanel server.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p><p>Server IP: ' . $results['server_ip'] . '</p>';

$mailHeaders = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . $fromName . ' <' . $smtpUser . '>',
    'Reply-To: ' . $fromName . ' <' . $smtpUser . '>',
];
$mailResult = @mail($smtpUser, $testSubject, $testBody, implode("\r\n", $mailHeaders));
$results['php_mail_to_admin'] = $mailResult ? 'TRUE (queued)' : 'FALSE (failed)';
$results['php_mail_error'] = error_get_last() ? (error_get_last()['message'] ?? '(no message)') : '(no error)';

// 5. Test PHP mail() with envelope sender (-f)
$mailResult2 = @mail($smtpUser, $testSubject . ' [with -f]', $testBody, implode("\r\n", $mailHeaders), '-f ' . $smtpUser);
$results['php_mail_with_f'] = $mailResult2 ? 'TRUE (queued)' : 'FALSE (failed)';

// 6. Test PHP mail() to external Gmail
$gmailTest = @mail('bell.test.ckm@gmail.com', $testSubject . ' [to Gmail]', $testBody, implode("\r\n", $mailHeaders), '-f ' . $smtpUser);
$results['php_mail_to_gmail'] = $gmailTest ? 'TRUE (queued)' : 'FALSE (failed)';

// 7. Check email headers — what does the server actually send?
$results['hostname'] = gethostname() ?: '(unknown)';
$results['php_uname'] = php_uname('n') ?: '(unknown)';

// 8. Try SMTP on port 465 (SSL) — many hosts block 587 but allow 465
if ($results['tcp_zoho_465'] !== 'FAIL — ' && strpos($results['tcp_zoho_465'], 'OK') === 0) {
    require_once __DIR__ . '/../smtp.php';
    try {
        $smtp465 = new CkmSmtp($smtpHost, 465, $smtpUser, $smtpPass);
        $sent465 = $smtp465->send($smtpUser, 'CKM SMTP 465 Test', '<h3>Test via port 465 SSL</h3>', $fromName);
        $results['smtp_465_test'] = $sent465 ? 'SUCCESS' : 'FAILED — Log: ' . $smtp465->getLog();
    } catch (Exception $e) {
        $results['smtp_465_test'] = 'ERROR: ' . $e->getMessage();
    }
} else {
    $results['smtp_465_test'] = 'SKIPPED — port 465 not reachable';
}

// 9. Try SMTP on port 25 (plain, no TLS)
if (strpos($results['tcp_zoho_25'] ?? '', 'OK') === 0) {
    require_once __DIR__ . '/../smtp.php';
    try {
        $smtp25 = new CkmSmtp($smtpHost, 25, $smtpUser, $smtpPass);
        $sent25 = $smtp25->send($smtpUser, 'CKM SMTP 25 Test', '<h3>Test via port 25</h3>', $fromName);
        $results['smtp_25_test'] = $sent25 ? 'SUCCESS' : 'FAILED — Log: ' . $smtp25->getLog();
    } catch (Exception $e) {
        $results['smtp_25_test'] = 'ERROR: ' . $e->getMessage();
    }
} else {
    $results['smtp_25_test'] = 'SKIPPED — port 25 not reachable';
}

// 10. Try SMTP on port 587 (STARTTLS)
if (strpos($results['tcp_zoho_587'] ?? '', 'OK') === 0) {
    require_once __DIR__ . '/../smtp.php';
    try {
        $smtp587 = new CkmSmtp($smtpHost, 587, $smtpUser, $smtpPass);
        $sent587 = $smtp587->send($smtpUser, 'CKM SMTP 587 Test', '<h3>Test via port 587 STARTTLS</h3>', $fromName);
        $results['smtp_587_test'] = $sent587 ? 'SUCCESS' : 'FAILED — Log: ' . $smtp587->getLog();
    } catch (Exception $e) {
        $results['smtp_587_test'] = 'ERROR: ' . $e->getMessage();
    }
} else {
    $results['smtp_587_test'] = 'SKIPPED — port 587 not reachable';
}

// Output
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CKM Email Diagnostic</title>';
echo '<style>body{font-family:monospace;max-width:900px;margin:20px auto;padding:20px;background:#061d2a;color:#f5f2ea}';
echo 'h1{color:#d1a54a}table{width:100%;border-collapse:collapse}td{padding:6px 10px;border:1px solid #134d63}';
echo 'td:first-child{color:#efd590;font-weight:bold;width:250px}.ok{color:#27ae60}.fail{color:#e74c3c}</style></head><body>';
echo '<h1>CKM Email Diagnostic</h1>';
echo '<table>';
foreach ($results as $key => $val) {
    $class = '';
    if (strpos((string)$val, 'OK') === 0 || strpos((string)$val, 'SUCCESS') === 0 || strpos((string)$val, 'YES') === 0) $class = 'ok';
    if (strpos((string)$val, 'FAIL') === 0 || strpos((string)$val, 'FALSE') === 0 || strpos((string)$val, 'NO') === 0) $class = 'fail';
    echo "<tr><td>{$key}</td><td class='{$class}'>" . htmlspecialchars((string)$val) . '</td></tr>';
}
echo '</table>';
echo '<p style="margin-top:20px;color:#999">Generated: ' . date('Y-m-d H:i:s') . '</p>';
echo '<p style="color:#d1a54a"><strong>SPF Record:</strong> v=spf1 include:_spf.zoho.com ip4:' . $results['server_ip'] . ' ~all</p>';
echo '<p style="color:#999">Tambah IP cPanel server (' . $results['server_ip'] . ') ke SPF record DNS cucikarpetmasjid.com supaya PHP mail() lulus SPF check.</p>';
echo '</body></html>';
