<?php
/**
 * CKM Admin — Database Connection (PDO MySQL)
 * cucikarpetmasjid.com
 */
declare(strict_types=1);

// Load config if exists
$configFile = __DIR__ . '/../config.php';
$config = is_file($configFile) ? require $configFile : [];

$dbHost = $config['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbName = $config['DB_NAME'] ?? getenv('DB_NAME') ?: 'ckm_admin';
$dbUser = $config['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$dbPass = $config['DB_PASS'] ?? getenv('DB_PASS') ?: '';
$dbChar = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbChar}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die('Sambungan pangkalan data gagal: ' . htmlspecialchars($e->getMessage()));
}
