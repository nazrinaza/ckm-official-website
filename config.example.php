<?php
/**
 * CKM Official Website — Configuration
 * Salin fail ini sebagai config.php dan isi nilai di bawah.
 * Jangan kongsi API keys atau masukkan ke JavaScript.
 */
return [
    // Resend Email API (HTTP API, port 443 — bypass hosting SMTP block)
    // Dapatkan API key di https://resend.com/api-keys
    // Verify domain di https://resend.com/domains
    'RESEND_API_KEY'  => '',  // re_xxxxxxxxxxxx
    'RESEND_FROM_EMAIL' => 'jom@cucikarpetmasjid.com',
    'RESEND_FROM_NAME'  => 'cucikarpetmasjid.com',

    // Zoho Mail SMTP (legacy — tidak berfungsi dari cPanel, hosting intercept SMTP)
    'ZOHO_SMTP_HOST' => 'smtp.zoho.com',
    'ZOHO_SMTP_PORT' => 587,
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
