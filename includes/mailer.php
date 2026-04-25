<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

// =========================
// CONFIGURATION
// =========================
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'aamirjames006@gmail.com'); // mail email ...
define('MAIL_PASSWORD', 'gzhe zfdr cilc eqth');    // my app pass word of google
define('MAIL_PORT', 587);
define('MAIL_FROM_EMAIL', 'aamirjames006@gmail.com');
define('MAIL_FROM_NAME', 'Skill-Share Hub');

// =========================
// FUNCTION
// =========================
function sendMail($toEmail, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        // DEBUG (IMPORTANT for testing)
        $mail->SMTPDebug = 0; // change to 2 if error
        $mail->Debugoutput = 'html';

        // SMTP SETTINGS
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;

        // SSL FIX (IMPORTANT for XAMPP)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // EMAIL SETTINGS
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // OPTIONAL (plain text fallback)
        $mail->AltBody = strip_tags($body);

        return $mail->send();

    } catch (Exception $e) {
        // Show error while testing
        echo "Mailer Error: " . $mail->ErrorInfo;
        return false;
    }
}