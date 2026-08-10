<?php
/**
 * CKM Official Website — Configuration
 * Salin fail ini sebagai config.php dan isi nilai di bawah.
 * Jangan kongsi API keys atau masukkan ke JavaScript.
 */
return [
    // SendGrid (email)
    'SENDGRID_API_KEY'    => 'SG.xxx...xxxx',
    'SENDGRID_FROM_EMAIL' => 'verified-sender@domainanda.com',
    'ENQUIRY_TO_EMAIL'    => 'sales@domainanda.com',

    // Database (MySQL — for admin system)
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'ckm_admin',
    'DB_USER' => 'db_user_anda',
    'DB_PASS' => 'db_password_anda',
];
