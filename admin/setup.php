<?php
/**
 * CKM Admin — Database Setup
 * Run once: https://cucikarpetmasjid.com/admin/setup.php
 * Delete after setup.
 */
declare(strict_types=1);

require_once __DIR__ . '/database.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS admins (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(100) NOT NULL,
        email           VARCHAR(150) NOT NULL UNIQUE,
        password_hash   VARCHAR(255) NOT NULL,
        role            ENUM('admin','super') DEFAULT 'admin',
        active          TINYINT(1) DEFAULT 1,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS enquiries (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        ref_no          VARCHAR(30) NOT NULL UNIQUE,
        name            VARCHAR(100) NOT NULL,
        phone           VARCHAR(30) NOT NULL,
        premise         VARCHAR(140) NOT NULL,
        premise_type    VARCHAR(80) NOT NULL,
        location        VARCHAR(220) NOT NULL,
        area            VARCHAR(80) DEFAULT NULL,
        preferred_date  VARCHAR(20) DEFAULT NULL,
        issue           VARCHAR(160) NOT NULL,
        message         TEXT,
        consent         VARCHAR(20) DEFAULT NULL,
        status          ENUM('new','contacted','quoted','won','lost','archived') DEFAULT 'new',
        notes           TEXT,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS quotations (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        quote_no        VARCHAR(30) NOT NULL UNIQUE,
        enquiry_id      INT DEFAULT NULL,
        client_name     VARCHAR(100) NOT NULL,
        client_phone    VARCHAR(30) DEFAULT NULL,
        client_address  TEXT,
        premise         VARCHAR(140) DEFAULT NULL,
        service_desc    TEXT NOT NULL,
        items           JSON NOT NULL,
        subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
        tax_rate        DECIMAL(5,2) NOT NULL DEFAULT 0,
        tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount        DECIMAL(12,2) NOT NULL DEFAULT 0,
        total           DECIMAL(12,2) NOT NULL DEFAULT 0,
        valid_until     DATE DEFAULT NULL,
        status          ENUM('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
        notes           TEXT,
        created_by      INT DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE SET NULL,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS invoices (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no      VARCHAR(30) NOT NULL UNIQUE,
        quotation_id    INT DEFAULT NULL,
        client_name     VARCHAR(100) NOT NULL,
        client_phone    VARCHAR(30) DEFAULT NULL,
        client_address  TEXT,
        premise         VARCHAR(140) DEFAULT NULL,
        service_desc    TEXT,
        items           JSON NOT NULL,
        subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
        tax_rate        DECIMAL(5,2) NOT NULL DEFAULT 0,
        tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount        DECIMAL(12,2) NOT NULL DEFAULT 0,
        total           DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_paid     DECIMAL(12,2) NOT NULL DEFAULT 0,
        balance         DECIMAL(12,2) NOT NULL DEFAULT 0,
        status          ENUM('unpaid','partial','paid','overdue','cancelled') DEFAULT 'unpaid',
        issue_date      DATE NOT NULL,
        due_date        DATE DEFAULT NULL,
        payment_method  VARCHAR(80) DEFAULT NULL,
        payment_ref     VARCHAR(120) DEFAULT NULL,
        notes           TEXT,
        created_by      INT DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE SET NULL,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS settings (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        setting_key     VARCHAR(80) NOT NULL UNIQUE,
        setting_value   TEXT,
        updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

echo "<!DOCTYPE html><html><head><meta charset='utf8'><title>CKM Admin Setup</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:700px;margin:40px auto;padding:20px;background:#061d2a;color:#f5f2ea}h1{color:#d1a54a}pre{background:#082b3d;padding:15px;border-radius:5px;overflow-x:auto}.ok{color:#90ee90}.err{color:#ff6b6b}a{color:#d1a54a}</style></head><body>";
echo "<h1>CKM Admin Setup</h1>";
echo "<pre>";

$ok = true;
foreach ($queries as $i => $sql) {
    try {
        $pdo->exec($sql);
        $label = explode('(', $sql)[0];
        echo "<span class='ok'>[OK]</span> " . trim($label) . "\n";
    } catch (PDOException $e) {
        echo "<span class='err'>[FAIL]</span> " . $e->getMessage() . "\n";
        $ok = false;
    }
}

// Default admin
$check = $pdo->query("SELECT COUNT(*) FROM admins");
if ($check->fetchColumn() == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (name, email, password_hash, role) VALUES (?, ?, ?, 'super')")
        ->execute(['Admin CKM', 'admin@cucikarpetmasjid.com', $hash]);
    echo "<span class='ok'>[OK]</span> Default admin created: admin@cucikarpetmasjid.com / admin123\n";
} else {
    echo "[INFO] Admin user already exists\n";
}

// Default settings
$defaultSettings = [
    'company_name'    => 'cucikarpetmasjid.com',
    'company_email'   => 'jom@cucikarpetmasjid.com',
    'company_phone'   => '',
    'company_address' => '',
    'tax_rate'        => '0',
    'currency'        => 'RM',
    'quote_prefix'    => 'Q',
    'invoice_prefix'  => 'INV',
    'quote_valid_days'=> '30',
];
$stmtIns = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ($defaultSettings as $k => $v) {
    $stmtIns->execute([$k, $v]);
}
echo "<span class='ok'>[OK]</span> Default settings inserted\n";

echo "\n";
if ($ok) {
    echo "<span class='ok'>Setup selesai! Sila hapuskan fail setup.php</span>\n";
    echo "<a href='index.php'>Ke halaman login &rarr;</a>\n";
} else {
    echo "<span class='err'>Setup tidak lengkap. Semak ralat di atas.</span>\n";
}
echo "</pre></body></html>";
