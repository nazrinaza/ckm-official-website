<?php
/**
 * CKM Official Website — Configuration
 * Salin fail ini sebagai config.php dan isi nilai di bawah.
 * Jangan kongsi API keys atau masukkan ke JavaScript.
 */
return [
    // Zoho Mail SMTP (untuk sending email — acknowledgement + notification)
    'ZOHO_SMTP_HOST' => 'smtp.zoho.com',
    'ZOHO_SMTP_PORT' => 587,        // 587 = STARTTLS, 465 = SSL
    'ZOHO_SMTP_USER' => 'jom@cucikarpetmasjid.com',
    'ZOHO_SMTP_PASS' => 'password-zoho-anda',
    'ZOHO_FROM_NAME' => 'cucikarpetmasjid.com',
    'ENQUIRY_TO_EMAIL' => 'jom@cucikarpetmasjid.com',

    // Database (MySQL — for admin system)
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'ckm_admin',
    'DB_USER' => 'db_user_anda',
    'DB_PASS' => 'db_password_anda',
];
