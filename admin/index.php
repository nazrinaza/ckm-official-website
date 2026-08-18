<?php
/**
 * CKM Admin — Login Page
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email !== '' && $password !== '') {
        if (attempt_login($email, $password)) {
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Email atau kata laluan tidak sah.';
    } else {
        $error = 'Sila isi semua medan.';
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CKM Admin — Log Masuk</title>
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-box">
      <div style="text-align:center;margin-bottom:20px">
        <img src="../assets/logo-text.png" alt="cucikarpetmasjid.com" style="max-height:48px;width:auto" />
      </div>
      <h1>Panel Admin</h1>
      <p class="sub">cucikarpetmasjid.com — Sistem Pengurusan</p>
      <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <label>Email</label>
        <input type="email" name="email" required autofocus placeholder="admin@cucikarpetmasjid.com">
        <label>Kata Laluan</label>
        <input type="password" name="password" required placeholder="••••••••">
        <button type="submit">Log Masuk</button>
      </form>
    </div>
  </div>
</body>
</html>
