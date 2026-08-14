<?php
/**
 * CKM Admin — Newsletter Compose & Send
 * Create/edit draft campaigns. Send via Resend API in batches.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../resend.php';

$admin = current_admin();
$message = '';
$messageType = '';

// ── Handle POST: save draft or send ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $action = $_POST['action'] ?? 'draft'; // draft | send
    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    $csrfOK = csrf_verify($_POST['csrf_token'] ?? null);

    if (!$csrfOK) {
        $message = 'Sesi tamat. Sila cuba lagi.';
        $messageType = 'error';
    } elseif ($subject === '' || $body === '') {
        $message = 'Subject dan content tidak boleh kosong.';
        $messageType = 'error';
    } else {
        try {
            if ($campaignId > 0) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE newsletter_campaigns SET subject=?, body=? WHERE id=? AND status='draft'");
                $stmt->execute([$subject, $body, $campaignId]);
            } else {
                // Create new draft
                $stmt = $pdo->prepare("INSERT INTO newsletter_campaigns (subject, body, status, created_by) VALUES (?, ?, 'draft', ?)");
                $stmt->execute([$subject, $body, $admin['id']]);
                $campaignId = (int)$pdo->lastInsertId();
            }

            if ($action === 'send' && $campaignId > 0) {
                // Redirect to send handler
                header("Location: newsletter-compose.php?id={$campaignId}&send=1");
                exit;
            }

            $message = 'Draf disimpan. Campaign #' . $campaignId;
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ── Handle GET: load existing or send ──
$campaignId = (int)($_GET['id'] ?? 0);
$sendMode = isset($_GET['send']);
$campaign = null;
$subject = '';
$body = '';

if ($campaignId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM newsletter_campaigns WHERE id=?");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch();
        if ($campaign) {
            $subject = $campaign['subject'];
            $body = $campaign['body'];
        }
    } catch (Exception $e) {
        $message = 'Error loading campaign: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// ── Send mode: execute batch send ──
if ($sendMode && $campaign && $campaign['status'] === 'draft') {
    // Get config
    $configFile = __DIR__ . '/../config.php';
    $config = is_file($configFile) ? require $configFile : [];
    $apiKey = $config['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY') ?: '';
    $fromEmail = $config['RESEND_FROM_EMAIL'] ?? 'jom@cucikarpetmasjid.com';
    $fromName = $config['RESEND_FROM_NAME'] ?? 'cucikarpetmasjid.com';

    if ($apiKey === '') {
        $message = 'Resend API key tiada. Konfigurasi di config.php.';
        $messageType = 'error';
    } else {
        try {
            // Get active subscribers
            $subs = $pdo->query("SELECT id, email, name FROM newsletter_subscribers WHERE status='active' ORDER BY id")->fetchAll();
            $totalRecipients = count($subs);

            if ($totalRecipients === 0) {
                $message = 'Tiada subscriber aktif. Tambah subscriber dahulu.';
                $messageType = 'error';
            } else {
                // Mark campaign as sending
                $pdo->prepare("UPDATE newsletter_campaigns SET status='sending', total_recipients=? WHERE id=?")
                    ->execute([$totalRecipients, $campaignId]);

                // Create send records
                $insertSend = $pdo->prepare("INSERT INTO newsletter_sends (campaign_id, subscriber_id, status) VALUES (?, ?, 'pending')");
                foreach ($subs as $s) {
                    $insertSend->execute([$campaignId, (int)$s['id']]);
                }

                // Build email HTML
                $unsubBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'cucikarpetmasjid.com');
                $emailHtml = newsletter_email_template($subject, $body, $unsubBase);

                $resend = new CkmResend($apiKey, $fromEmail, $fromName);
                $sent = 0;
                $failed = 0;

                // Get send records with subscriber email
                $stmtSends = $pdo->prepare("
                    SELECT ns.id AS send_id, sub.email, sub.name
                    FROM newsletter_sends ns
                    JOIN newsletter_subscribers sub ON ns.subscriber_id = sub.id
                    WHERE ns.campaign_id=? AND ns.status='pending'
                ");
                $stmtSends->execute([$campaignId]);
                $sendRows = $stmtSends->fetchAll();

                foreach ($sendRows as $row) {
                    $personalBody = str_replace(
                        ['{name}', '{email}', '{unsub_link}'],
                        [
                            htmlspecialchars($row['name'] ?: 'Jemaah'),
                            htmlspecialchars($row['email']),
                            $unsubBase . '/unsubscribe.php?email=' . urlencode($row['email']) . '&c=' . $campaignId
                        ],
                        $emailHtml
                    );

                    $ok = $resend->send($row['email'], $subject, $personalBody);
                    $now = date('Y-m-d H:i:s');
                    if ($ok) {
                        $pdo->prepare("UPDATE newsletter_sends SET status='sent', sent_at=? WHERE id=?")
                            ->execute([$now, (int)$row['send_id']]);
                        $sent++;
                    } else {
                        $err = $resend->getError();
                        $pdo->prepare("UPDATE newsletter_sends SET status='failed', error_msg=?, sent_at=? WHERE id=?")
                            ->execute([mb_substr($err, 0, 500), $now, (int)$row['send_id']]);
                        $failed++;
                    }
                    // Small delay to respect rate limits
                    usleep(100000); // 0.1s
                }

                // Update campaign stats
                $now = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE newsletter_campaigns SET status='sent', total_sent=?, total_failed=?, sent_at=? WHERE id=?")
                    ->execute([$sent, $failed, $now, $campaignId]);

                $message = "Newsletter dihantar! {$sent} berjaya";
                if ($failed > 0) $message .= ", {$failed} gagal";
                $message .= " daripada {$totalRecipients} penerima.";
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Send error: ' . $e->getMessage();
            $messageType = 'error';
            // Reset status to draft on error
            try {
                $pdo->prepare("UPDATE newsletter_campaigns SET status='draft' WHERE id=?")->execute([$campaignId]);
            } catch (Exception $e2) {}
        }
    }
}

/**
 * Build branded HTML email template
 */
function newsletter_email_template(string $subject, string $body, string $baseUrl): string
{
    return '<!DOCTYPE html>
<html lang="ms">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Segoe UI,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:20px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
  <!-- Header -->
  <tr>
    <td style="background:#061d2a;padding:25px 30px;text-align:center;">
      <img src="' . $baseUrl . '/assets/logo-text.png" alt="cucikarpetmasjid.com" style="max-width:200px;height:auto;" />
    </td>
  </tr>
  <!-- Gold accent -->
  <tr><td style="background:#d1a54a;height:3px;font-size:0;line-height:0;">&nbsp;</td></tr>
  <!-- Body -->
  <tr>
    <td style="padding:30px;">
      <p style="color:#495057;font-size:14px;margin:0 0 15px;">Assalamualaikum {name},</p>
      <div style="color:#333;font-size:14px;line-height:1.7;">' . $body . '</div>
    </td>
  </tr>
  <!-- Footer -->
  <tr>
    <td style="padding:25px 30px;background:#f5f2ea;border-top:1px solid #e9ecef;">
      <p style="color:#495057;font-size:13px;margin:0 0 10px;">
        <strong>cucikarpetmasjid.com</strong><br>
        Perkhidmatan cuci karpet profesional untuk masjid, surau, musolla, tahfiz &amp; madrasah.
      </p>
      <p style="color:#adb5bd;font-size:12px;margin:10px 0 0;">
        Email ini dihantar kerana anda berlangganan newsletter CKM.<br>
        <a href="{unsub_link}" style="color:#0d3c50;">Nyahlanggan (unsubscribe)</a> |
        <a href="' . $baseUrl . '" style="color:#0d3c50;">Lawati laman web</a>
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

$pageTitle = 'Compose Newsletter';
include __DIR__ . '/header.php';
?>
<?php if ($message): ?>
<div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($sendMode && $campaign && $campaign['status'] !== 'draft'): ?>
<!-- Campaign already sent — show summary -->
<div class="card">
  <div class="card-title">Campaign #<?= (int)$campaign['id'] ?> — <?= htmlspecialchars($campaign['subject']) ?></div>
  <table>
    <tr><th>Status</th><td><span class="badge badge-<?= $campaign['status'] ?>"><?= ucfirst($campaign['status']) ?></span></td></tr>
    <tr><th>Penerima</th><td><?= (int)$campaign['total_recipients'] ?></td></tr>
    <tr><th>Berjaya</th><td style="color:var(--green)"><?= (int)$campaign['total_sent'] ?></td></tr>
    <tr><th>Gagal</th><td style="color:var(--red)"><?= (int)$campaign['total_failed'] ?></td></tr>
    <tr><th>Dihantar Pada</th><td><?= $campaign['sent_at'] ? date('d/m/Y H:i', strtotime($campaign['sent_at'])) : '-' ?></td></tr>
  </table>
  <div class="mt-20">
    <a class="btn btn-outline" href="newsletter.php">&larr; Kembali ke Newsletter</a>
    <a class="btn btn-primary" href="newsletter-compose.php">+ Newsletter Baru</a>
  </div>
</div>
<?php else: ?>
<!-- Compose form -->
<div class="card">
  <div class="card-title"><?= $campaignId > 0 ? 'Edit Campaign #' . $campaignId : 'Compose Newsletter Baru' ?></div>
  <form method="post" action="newsletter-compose.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
    <div class="form-group">
      <label for="subject">Subject Email</label>
      <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($subject) ?>" placeholder="cth: Tips Penjagaan Karpet Masjid Bulanan" required maxlength="500" style="font-size:15px;padding:10px 14px;">
    </div>
    <div class="form-group">
      <label for="body">Kandungan (HTML dibenarkan)</label>
      <textarea id="body" name="body" rows="12" required placeholder="Tulis content newsletter di sini. Anda boleh guna HTML tag seperti &lt;p&gt;, &lt;strong&gt;, &lt;a&gt;, &lt;ul&gt;..." style="font-size:14px;line-height:1.6;"><?= htmlspecialchars($body) ?></textarea>
    </div>
    <div class="flex" style="flex-wrap:wrap;gap:10px">
      <button type="submit" name="action" value="draft" class="btn btn-outline">Simpan Draf</button>
      <button type="submit" name="action" value="send" class="btn btn-gold" onclick="return confirm('Hantar newsletter sekarang? Pastikan anda dah semak content.')">Hantar Newsletter</button>
      <?php if ($campaignId > 0): ?>
      <a class="btn btn-outline" href="newsletter-compose.php?id=<?= $campaignId ?>&send=1" onclick="return confirm('Hantar draft sedia ada?')">Hantar Draf Sedia Ada</a>
      <?php endif; ?>
      <a class="btn btn-outline" href="newsletter.php">Batal</a>
    </div>
  </form>
</div>

<!-- Preview -->
<?php if ($subject || $body): ?>
<div class="card">
  <div class="card-title">Preview</div>
  <div style="border:1px solid #dee2e6;border-radius:6px;overflow:hidden;">
    <div style="background:#061d2a;color:#f5f2ea;padding:15px 20px;font-size:14px;">
      <strong>Subject:</strong> <?= htmlspecialchars($subject ?: '(tiada subject)') ?>
    </div>
    <div style="padding:20px;background:#fff;font-size:14px;line-height:1.7;color:#333;max-height:400px;overflow-y:auto;">
      <?= $body ? $body : '<span style="color:#adb5bd">(tiada content)</span>' ?>
    </div>
    <div style="padding:15px 20px;background:#f5f2ea;border-top:1px solid #e9ecef;font-size:12px;color:#adb5bd;">
      Footer auto: cucikarpetmasjid.com | Unsubscribe link
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
