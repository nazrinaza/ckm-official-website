<?php
/** CKM Admin — Logout */
require_once __DIR__ . '/auth.php';
logout();
header('Location: index.php');
exit;
