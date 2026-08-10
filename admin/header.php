<?php
/** CKM Admin — Sidebar + Topbar (include in every page) */
$admin = current_admin();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="logo">
    <img src="../assets/logo-text.png" alt="cucikarpetmasjid.com" />
  </div>
  <a class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item <?= in_array($currentPage, ['enquiries.php','enquiry-view.php']) ? 'active' : '' ?>" href="enquiries.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v12H8l-4 4z"/></svg>
    Enquiry
  </a>
  <a class="nav-item <?= in_array($currentPage, ['quotations.php','quotation-create.php','quotation-view.php']) ? 'active' : '' ?>" href="quotations.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    Quotation
  </a>
  <a class="nav-item <?= in_array($currentPage, ['invoices.php','invoice-create.php','invoice-view.php']) ? 'active' : '' ?>" href="invoices.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
    Invoice
  </a>
  <a class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
    Tetapan
  </a>
  <a class="nav-item" href="logout.php" style="margin-top:auto;color:#e74c3c">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    Log Keluar
  </a>
</aside>

<div class="main">
  <div class="topbar">
    <h2><?= htmlspecialchars($pageTitle ?? 'CKM Admin') ?></h2>
    <div class="admin-info">
      <strong><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></strong> &middot; <?= htmlspecialchars($admin['email'] ?? '') ?>
    </div>
  </div>
