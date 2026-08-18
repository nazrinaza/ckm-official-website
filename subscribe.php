<?php
/**
 * CKM — Public Newsletter Signup
 * POST endpoint for website subscription form.
 * Accepts: email (required), name (optional)
 * Returns: JSON response (for AJAX) or redirect (for form submit)
 */
declare(strict_types=1);

require_once __DIR__ . '/admin/database.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

// Basic rate limit check (same IP, max 5 signups per hour)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/ckm_sub_rate_' . md5($ip);
if (file_exists($rateFile)) {
    $data = json_decode((string)file_get_contents($rateFile), true);
    if ($data && ($data['count'] ?? 0) >= 5 && (time() - $data['time']) < 3600) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'msg' => 'Terlalu banyak percubaan. Cuba lagi nanti.']);
        exit;
    }
}

$email = trim(strtolower((string)($_POST['email'] ?? '')));
$name = trim((string)($_POST['name'] ?? ''));

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Email tidak sah.']);
    exit;
}

// Honeypot check (if bot filled hidden field)
if (!empty($_POST['website_url'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Spam detected.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO newsletter_subscribers (email, name, source, status)
        VALUES (?, ?, 'website', 'active')
        ON DUPLICATE KEY UPDATE
            status = IF(status = 'unsubscribed', 'active', status),
            name = IF(VALUES(name) != '', VALUES(name), name)
    ");
    $stmt->execute([$email, $name]);

    // Update rate limit
    $count = 1;
    if (file_exists($rateFile)) {
        $data = json_decode((string)file_get_contents($rateFile), true);
        $count = ($data['count'] ?? 0) + 1;
    }
    file_put_contents($rateFile, json_encode(['count' => $count, 'time' => time()]));

    echo json_encode([
        'ok' => true,
        'msg' => 'Terima kasih! Anda telah berlangganan newsletter CKM.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Ralat pelayan. Sila cuba lagi.']);
}
