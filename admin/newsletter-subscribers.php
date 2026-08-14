<?php
/**
 * CKM Admin — Newsletter Subscribers
 * List, add, remove, import from enquiries, export
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/database.php';

$message = '';
$messageType = '';

// ── Handle actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOK = csrf_verify($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if (!$csrfOK) {
        $message = 'Sesi tamat. Sila cuba lagi.';
        $messageType = 'error';
    } elseif ($action === 'add') {
        $email = trim(strtolower((string)($_POST['email'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Email tidak sah.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, name, source, status) VALUES (?, ?, 'manual', 'active') ON DUPLICATE KEY UPDATE name=VALUES(name), status='active'");
                $stmt->execute([$email, $name]);
                $message = "Subscriber ditambah: {$email}";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'remove') {
        $id = (int)($_POST['subscriber_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id=?")->execute([$id]);
            $message = 'Subscriber dipadam.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'import_enquiry') {
        // Import emails from enquiries table
        try {
            $enquiries = $pdo->query("SELECT DISTINCT email, name FROM enquiries WHERE email IS NOT NULL AND email != '' AND status='won'")->fetchAll();
            $imported = 0;
            $stmtIns = $pdo->prepare("INSERT INTO newsletter_subscribers (email, name, source, status) VALUES (?, ?, 'enquiry', 'active') ON DUPLICATE KEY UPDATE status='active'");
            foreach ($enquiries as $e) {
                if (filter_var($e['email'], FILTER_VALIDATE_EMAIL)) {
                    $stmtIns->execute([strtolower($e['email']), $e['name']]);
                    $imported++;
                }
            }
            $message = "{$imported} email diimport dari enquiries (status: Menang).";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Import error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'import_all_enquiry') {
        // Import ALL enquiry emails (not just won)
        try {
            $enquiries = $pdo->query("SELECT DISTINCT email, name FROM enquiries WHERE email IS NOT NULL AND email != ''")->fetchAll();
            $imported = 0;
            $stmtIns = $pdo->prepare("INSERT INTO newsletter_subscribers (email, name, source, status) VALUES (?, ?, 'enquiry', 'active') ON DUPLICATE KEY UPDATE status='active'");
            foreach ($enquiries as $e) {
                if (filter_var($e['email'], FILTER_VALIDATE_EMAIL)) {
                    $stmtIns->execute([strtolower($e['email']), $e['name']]);
                    $imported++;
                }
            }
            $message = "{$imported} email diimport dari semua enquiries.";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Import error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// ── Filters ──
$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$showImport = isset($_GET['import']);

$where = [];
$params = [];
if ($statusFilter && $statusFilter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where[] = '(email LIKE ? OR name LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$clause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM newsletter_subscribers {$clause}");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;
$totalPages = max(1, (int)ceil($total / $perPage));

// Fetch
$subscribers = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers {$clause} ORDER BY subscribed_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $subscribers = $stmt->fetchAll();
} catch (Exception $e) {}

$statuses = ['active' => 'Aktif', 'unsubscribed' => 'Nyahlanggan', 'bounced' => 'Bounce'];
$sourceLabels = ['website' => 'Website', 'enquiry' => 'Enquiry', 'manual' => 'Manual', 'import' => 'Import'];

$pageTitle = 'Subscribers';
include __DIR__ . '/header.php';
?>
<?php if ($message): ?>
<div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Import section -->
<?php if ($showImport || isset($_GET['import'])): ?>
<div class="card" style="border-left:4px solid var(--gold-500)">
  <div class="card-title">Import dari Enquiry</div>
  <p style="font-size:13px;color:var(--gray-700);margin-bottom:15px">
    Import email dari table <code>enquiries</code> ke subscriber list. Hanya email yang sah akan diimport. Duplikat akan diabaikan.
  </p>
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="import_enquiry">
    <button type="submit" class="btn btn-gold">Import Enquiry "Menang" Sahaja</button>
  </form>
  <form method="post" style="display:inline;margin-left:10px">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="import_all_enquiry">
    <button type="submit" class="btn btn-outline">Import Semua Enquiry</button>
  </form>
  <p style="font-size:12px;color:var(--gray-500);margin-top:10px">
    <strong>Menang sahaja:</strong> Hanya enquiry yang berstatus "Menang" (jadi customer).<br>
    <strong>Semua:</strong> Semua enquiry yang ada email.
  </p>
</div>
<?php endif; ?>

<!-- Add subscriber -->
<div class="card">
  <div class="card-title">Tambah Subscriber Manual</div>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="flex:1;min-width:200px">
      <label>Nama (opsyenal)</label>
      <input type="text" name="name" placeholder="cth: Ahmad bin Ali">
    </div>
    <div class="form-group" style="flex:2;min-width:250px">
      <label>Email</label>
      <input type="email" name="email" placeholder="email@contoh.com" required>
    </div>
    <button type="submit" class="btn btn-primary">Tambah</button>
  </form>
</div>

<!-- Filter bar -->
<div class="filter-bar">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap">
    <select name="status" onchange="this.form.submit()">
      <option value="all">Semua Status</option>
      <?php foreach ($statuses as $k => $v): ?>
        <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari email atau nama..." style="width:250px">
    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
    <?php if ($search || $statusFilter): ?>
      <a class="btn btn-outline btn-sm" href="newsletter-subscribers.php">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Subscriber list -->
<div class="card">
  <div class="card-title">Senarai Subscriber (<?= $total ?>)</div>
  <?php if (empty($subscribers)): ?>
    <div class="empty-state">Tiada subscriber dijumpai.</div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Email</th>
        <th>Nama</th>
        <th>Sumber</th>
        <th>Status</th>
        <th>Langgan</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subscribers as $s): ?>
      <tr>
        <td><?= (int)$s['id'] ?></td>
        <td><strong><?= htmlspecialchars($s['email']) ?></strong></td>
        <td><?= htmlspecialchars($s['name'] ?: '-') ?></td>
        <td><span class="badge" style="background:var(--gray-200);color:var(--gray-700)"><?= $sourceLabels[$s['source']] ?? $s['source'] ?></span></td>
        <td>
          <?php if ($s['status'] === 'active'): ?>
            <span class="badge badge-paid">Aktif</span>
          <?php elseif ($s['status'] === 'unsubscribed'): ?>
            <span class="badge badge-cancelled">Nyahlanggan</span>
          <?php else: ?>
            <span class="badge badge-overdue">Bounce</span>
          <?php endif; ?>
        </td>
        <td><?= date('d/m/Y', strtotime($s['subscribed_at'])) ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Padam subscriber ini?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="subscriber_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger" style="padding:4px 10px;font-size:11px">Padam</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a class="<?= $i === $page ? 'current' : '' ?>" href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
