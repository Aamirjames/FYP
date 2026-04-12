<?php
// config/mail.php
// ── Gmail SMTP Settings ──────────────────────────────────────
// Step 1: Go to your Gmail → Google Account → Security
// Step 2: Enable 2-Step Verification
// Step 3: Search "App Passwords" → Generate one for "Mail"
// Step 4: Paste the 16-character app password below

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'aamirjames006@gmail.com');   // ← your Gmail
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');    // ← 16-char App Password
define('MAIL_FROM', 'aamirjames006@gmail.com');   // ← same Gmail
define('MAIL_FROM_NAME', 'Skill-Share Hub');