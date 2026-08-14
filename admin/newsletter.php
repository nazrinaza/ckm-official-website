<?php
/**
 * CKM Admin — Newsletter Dashboard
 * Shows campaign list + subscriber stats + quick actions
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

// Stats
$stats = [
    'subscribers_active' => 0,
    'subscribers_unsubscribed' => 0,
    'subscribers_total' => 0,
    'campaigns_draft' => 0,
    'campaigns_sent' => 0,
    'campaigns_total' => 0,
];

try {
    $stats['subscribers_active'] = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='active'")->fetchColumn();
    $stats['subscribers_unsubscribed'] = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='unsubscribed'")->fetchColumn();
    $stats['subscribers_total'] = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
    $stats['campaigns_draft'] = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_campaigns WHERE status='draft'")->fetchColumn();
    $stats['campaigns_sent'] = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_campaigns WHERE status='sent'")->fetchColumn();
    $stats['campaigns_total'] = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_campaigns")->fetchColumn();
} catch (Exception $e) {
    // tables may not exist yet
}

// Fetch campaigns
$campaigns = [];
try {
    $campaigns = $pdo->query("SELECT * FROM newsletter_campaigns ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) {}

$campaignStatuses = [
    'draft' => 'Draf',
    'sending' => 'Menghantar',
    'sent' => 'Dihantar',
    'scheduled' => 'Dijadualkan'
];

$pageTitle = 'Newsletter';
include __DIR__ . '/header.php';
?>
<!-- Alert if tables not set up -->
<?php if ($stats['subscribers_total'] === 0 && empty($campaigns) && !isset($_GET['setup'])): ?>
<div class="alert alert-info">
  <strong>Penyediaan:</strong> Jalankan <a href="../newsletter-setup.php"><code>newsletter-setup.php</code></a> sekali untuk mencipta table database newsletter.
</div>
<?php endif; ?>

<!-- Stats grid -->
<div class="stats-grid">
  <div class="stat-card" style="border-top-color: var(--green)">
    <div class="num"><?= $stats['subscribers_active'] ?></div>
    <div class="label">Subscriber Aktif</div>
  </div>
  <div class="stat-card" style="border-top-color: var(--red)">
    <div class="num"><?= $stats['subscribers_unsubscribed'] ?></div>
    <div class="label">Nyahlanggan</div>
  </div>
  <div class="stat-card" style="border-top-color: var(--navy-600)">
    <div class="num"><?= $stats['campaigns_draft'] ?></div>
    <div class="label">Draf Campaign</div>
  </div>
  <div class="stat-card" style="border-top-color: var(--gold-500)">
    <div class="num"><?= $stats['campaigns_sent'] ?></div>
    <div class="label">Campaign Dihantar</div>
  </div>
</div>

<!-- Quick actions -->
<div class="flex mb-20" style="flex-wrap:wrap">
  <a class="btn btn-primary" href="newsletter-compose.php">+ Compose Newsletter</a>
  <a class="btn btn-outline" href="newsletter-subscribers.php">Subscribers</a>
  <a class="btn btn-outline" href="newsletter-subscribers.php?import=enquiry">Import dari Enquiry</a>
</div>

<!-- Campaign list -->
<div class="card">
  <div class="card-title">Campaign Terkini</div>
  <?php if (empty($campaigns)): ?>
    <div class="empty-state">Belum ada campaign. Klik "Compose Newsletter" untuk mula.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Subject</th>
        <th>Status</th>
        <th>Penerima</th>
        <th>Dihantar</th>
        <th>Gagal</th>
        <th>Tarikh</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($campaigns as $c): ?>
      <tr>
        <td><strong>#<?= (int)$c['id'] ?></strong></td>
        <td><?= htmlspecialchars(mb_substr($c['subject'], 0, 60)) ?><?= mb_strlen($c['subject']) > 60 ? '...' : '' ?></td>
        <td><span class="badge badge-<?= $c['status'] ?>"><?= $campaignStatuses[$c['status']] ?? $c['status'] ?></span></td>
        <td><?= (int)$c['total_recipients'] ?></td>
        <td style="color:var(--green)"><?= (int)$c['total_sent'] ?></td>
        <td style="color:var(--red)"><?= (int)$c['total_failed'] ?></td>
        <td><?= $c['sent_at'] ? date('d/m/Y H:i', strtotime($c['sent_at'])) : date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
        <td>
          <a class="btn btn-sm btn-outline" href="newsletter-compose.php?id=<?= (int)$c['id'] ?>">Edit</a>
          <?php if ($c['status'] === 'draft'): ?>
          <a class="btn btn-sm btn-gold" href="newsletter-compose.php?id=<?= (int)$c['id'] ?>&send=1" onclick="return confirm('Hantar newsletter ke <?= (int)$c['total_recipients'] ?> subscriber?')">Hantar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
