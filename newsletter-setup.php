<?php
/**
 * CKM — Newsletter Database Setup
 * Creates newsletter_subscribers, newsletter_campaigns, newsletter_sends tables.
 * Run once: visit this page in browser OR import via phpMyAdmin.
 * Safe to re-run — uses IF NOT EXISTS.
 */
declare(strict_types=1);

require_once __DIR__ . '/admin/database.php';

$sql = "
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    source ENUM('website','enquiry','manual','import') DEFAULT 'website',
    status ENUM('active','unsubscribed','bounced') DEFAULT 'active',
    subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('draft','sending','sent','scheduled') DEFAULT 'draft',
    total_recipients INT DEFAULT 0,
    total_sent INT DEFAULT 0,
    total_failed INT DEFAULT 0,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_sends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    status ENUM('pending','sent','failed','opened') DEFAULT 'pending',
    error_msg VARCHAR(500) DEFAULT NULL,
    sent_at DATETIME NULL,
    opened_at DATETIME NULL,
    FOREIGN KEY (campaign_id) REFERENCES newsletter_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$ok = true;
$msg = '';
try {
    $pdo->exec($sql);
    $msg = 'Newsletter tables created successfully (or already existed).';
} catch (PDOException $e) {
    $ok = false;
    $msg = 'Error: ' . $e->getMessage();
}

// Count existing subscribers (in case re-run)
$subCount = 0;
try {
    $subCount = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CKM — Newsletter Setup</title>
<style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f4f4; padding: 40px; }
.box { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
h1 { color: #061d2a; font-size: 22px; margin-bottom: 5px; }
.sub { color: #adb5bd; font-size: 13px; margin-bottom: 25px; }
.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
.alert-ok { background: #d4edda; color: #155724; }
.alert-err { background: #f8d7da; color: #721c24; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #e9ecef; font-size: 13px; }
th { background: #061d2a; color: #f5f2ea; }
code { background: #f5f2ea; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
a { color: #0d3c50; }
</style>
</head>
<body>
<div class="box">
  <h1>CKM Newsletter Setup</h1>
  <div class="sub">cucikarpetmasjid.com — Database Migration</div>
  <div class="alert <?= $ok ? 'alert-ok' : 'alert-err' ?>">
    <?= htmlspecialchars($msg) ?>
  </div>
  <table>
    <thead><tr><th>Table</th><th>Description</th><th>Status</th></tr></thead>
    <tbody>
      <tr><td><code>newsletter_subscribers</code></td><td>Email subscribers with source tracking</td><td>OK</td></tr>
      <tr><td><code>newsletter_campaigns</code></td><td>Campaign subject, body, stats</td><td>OK</td></tr>
      <tr><td><code>newsletter_sends</code></td><td>Per-recipient send tracking</td><td>OK</td></tr>
    </tbody>
  </table>
  <p style="margin-top:20px;font-size:13px;color:#495057">
    Existing subscribers: <strong><?= $subCount ?></strong><br>
    Next step: Go to <a href="admin/newsletter.php">Admin Newsletter Dashboard</a>
  </p>
</div>
</body>
</html>
