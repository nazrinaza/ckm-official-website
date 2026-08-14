<?php
/**
 * CKM — Public Unsubscribe Page
 * GET: ?email=x&c=CAMPAIGN_ID
 * Updates subscriber status to 'unsubscribed'
 */
declare(strict_types=1);

require_once __DIR__ . '/admin/database.php';

$email = trim(strtolower((string)($_GET['email'] ?? '')));
$campaignId = (int)($_GET['c'] ?? 0);
$done = false;
$error = '';

if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET status='unsubscribed', unsubscribed_at=NOW() WHERE email=?");
        $stmt->execute([$email]);
        $done = $stmt->rowCount() > 0;
        if (!$done) {
            // Maybe already unsubscribed or not found
            $check = $pdo->prepare("SELECT status FROM newsletter_subscribers WHERE email=?");
            $check->execute([$email]);
            $sub = $check->fetch();
            if ($sub && $sub['status'] === 'unsubscribed') {
                $done = true; // Already unsubscribed — show success anyway
            }
        }
    } catch (Exception $e) {
        $error = 'Database error. Sila cuba lagi.';
    }
} else {
    $error = 'Link nyahlanggan tidak sah.';
}

// If tables don't exist yet, show setup message
if ($error && str_contains($error, 'Database') === false && str_contains(strtolower($e->getMessage() ?? ''), 'table') !== false) {
    $error = 'Sistem newsletter belum disediakan.';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Nyahlanggan — cucikarpetmasjid.com</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'Segoe UI', Arial, sans-serif;
  background: linear-gradient(135deg, #061d2a, #0d3c50);
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.box {
  background: #fff; border-radius: 10px; padding: 40px;
  max-width: 450px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,.2);
  text-align: center;
}
.logo { margin-bottom: 25px; }
.logo img { max-height: 50px; width: auto; }
h1 { color: #061d2a; font-size: 22px; margin-bottom: 10px; }
p { color: #495057; font-size: 14px; line-height: 1.6; margin-bottom: 15px; }
.success-icon {
  width: 60px; height: 60px; border-radius: 50%; background: #d4edda;
  display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
  font-size: 30px; color: #27ae60;
}
.error-icon {
  width: 60px; height: 60px; border-radius: 50%; background: #f8d7da;
  display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
  font-size: 30px; color: #e74c3c;
}
a.btn {
  display: inline-block; padding: 10px 24px; background: #d1a54a; color: #061d2a;
  border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px; margin-top: 10px;
}
a.btn:hover { background: #efd590; }
.footer { margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef; font-size: 12px; color: #adb5bd; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <img src="/assets/logo-text.png" alt="cucikarpetmasjid.com" onerror="this.style.display='none'">
  </div>
  <?php if ($done): ?>
    <div class="success-icon">&#10003;</div>
    <h1>Anda telah nyahlanggan</h1>
    <p>Email <strong><?= htmlspecialchars($email) ?></strong> telah dikeluarkan dari senarai newsletter CKM.</p>
    <p>Anda tidak akan menerima email newsletter lagi. Terima kasih.</p>
  <?php elseif ($error): ?>
    <div class="error-icon">!</div>
    <h1>Terdapat masalah</h1>
    <p><?= htmlspecialchars($error) ?></p>
  <?php else: ?>
    <div class="error-icon">?</div>
    <h1>Link tidak sah</h1>
    <p>Link nyahlanggan ini tidak sah atau telah tamat tempoh.</p>
  <?php endif; ?>
  <a href="/" class="btn">Kembali ke Laman Utama</a>
  <div class="footer">
    cucikarpetmasjid.com<br>
    Perkhidmatan cuci karpet profesional untuk masjid &amp; surau
  </div>
</div>
</body>
</html>
