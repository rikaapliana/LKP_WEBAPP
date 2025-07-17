<?php
// FILE: config/email_config.php
// Buat file ini di folder config/

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'rikaapliana02@gmail.com');        // Ganti dengan email Anda
define('SMTP_PASSWORD', 'ejit psog kjzn yfhf');        // Ganti dengan app password Anda
define('SMTP_ENCRYPTION', 'tls');                      // tls atau ssl

// Email From Settings
define('FROM_EMAIL', 'rikaapliana02@gmail.com');           // Ganti dengan email Anda
define('FROM_NAME', 'LKP PRADATA KOMPUTER TABALONG');

// Company Information
define('COMPANY_NAME', 'LKP PRADATA KOMPUTER TABALONG');
define('COMPANY_ADDRESS', 'Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571');
define('COMPANY_PHONE', '(0518) 123-456');
define('COMPANY_WHATSAPP', '0822 1359 4215');
define('COMPANY_EMAIL', 'rikaapliana02@gmail.com');
define('COMPANY_WEBSITE', 'https://lkp-pradata.com');

// System URLs
define('SYSTEM_URL', 'http://localhost/lkp_webapp');    // Ganti dengan URL sistem Anda
define('LOGIN_URL', SYSTEM_URL . '/pages/auth/login.php');

// Email Templates Settings
define('EMAIL_LOGO_URL', SYSTEM_URL . '/assets/img/logo.png');
define('EMAIL_FOOTER_TEXT', '© 2025 LKP Pradata Computer. All rights reserved.');

// Alternative SMTP (jika ingin ganti provider)
/*
// Untuk Yahoo Mail
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);

// Untuk Outlook/Hotmail
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);

// Untuk hosting provider
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 465);
define('SMTP_ENCRYPTION', 'ssl');
*/
?>