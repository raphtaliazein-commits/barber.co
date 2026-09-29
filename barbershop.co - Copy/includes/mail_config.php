<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../libs/PHPMailer/Exception.php";
require_once __DIR__ . "/../libs/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/../libs/PHPMailer/SMTP.php";


// =====================================================================
// EMAIL (SMTP) SETTINGS
//
// !!! YOU MUST EDIT THE 3 VALUES BELOW FOR EMAIL VERIFICATION TO WORK !!!
//
// The easiest option is a Gmail account:
//   1. Turn on 2-Step Verification on that Gmail account:
//      https://myaccount.google.com/security
//   2. Create an "App Password" (NOT your normal Gmail password):
//      https://myaccount.google.com/apppasswords
//      (Choose "Mail" as the app - Google will give you a 16-character
//      password like "abcd efgh ijkl mnop". Use that below, spaces or no
//      spaces both work.)
//   3. Put that Gmail address and App Password in the two constants below.
//
// If you skip this setup, registration will still work — the system
// will simply skip sending the email and mark the account as verified
// automatically, so nobody gets locked out. You just won't get the
// "real" verification behavior until this is configured.
// =====================================================================

define("SMTP_HOST", "smtp.gmail.com");
define("SMTP_PORT", 587);
define("SMTP_USERNAME", "your-gmail-address@gmail.com");   // <-- CHANGE THIS
define("SMTP_APP_PASSWORD", "your-16-character-app-password"); // <-- CHANGE THIS
define("SMTP_FROM_NAME", "BARBERSHOP.CO");

// The system treats the placeholder values above as "not configured yet".
define("SMTP_IS_CONFIGURED", SMTP_USERNAME !== "your-gmail-address@gmail.com" && SMTP_APP_PASSWORD !== "your-16-character-app-password");


/**
 * Sends the "verify your email" message to a newly registered customer.
 * Returns true if the email was actually sent, false otherwise (including
 * when SMTP hasn't been configured yet — see SMTP_IS_CONFIGURED above).
 */
function sendVerificationEmail(string $toEmail, string $toName, string $verificationLink): bool
{
    if (!SMTP_IS_CONFIGURED) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = str_replace(" ", "", SMTP_APP_PASSWORD);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(SMTP_USERNAME, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = "Verify your email - BARBERSHOP.CO";

        $safeName = htmlspecialchars($toName);
        $safeLink = htmlspecialchars($verificationLink);

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto;'>
                <h2 style='color:#1a1a1a;'>Welcome to BARBERSHOP.CO, {$safeName}!</h2>
                <p>Please confirm this is your email address by clicking the button below:</p>
                <p style='margin: 28px 0;'>
                    <a href='{$safeLink}' style='background:#d8a84e; color:#1a1a1a; padding:12px 24px; text-decoration:none; font-weight:bold; border-radius:4px; display:inline-block;'>
                        VERIFY MY EMAIL
                    </a>
                </p>
                <p style='color:#666; font-size:13px;'>Or copy and paste this link into your browser:<br>{$safeLink}</p>
                <p style='color:#999; font-size:12px;'>If you didn't create an account with us, you can safely ignore this email.</p>
            </div>
        ";

        $mail->AltBody = "Welcome to BARBERSHOP.CO, {$toName}! Please verify your email by visiting: {$verificationLink}";

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Verification email failed to send: " . $mail->ErrorInfo);
        return false;
    }
}


/**
 * Generates a fresh, random verification token for a customer.
 */
function generateEmailVerificationToken(): string
{
    return bin2hex(random_bytes(32));
}

?>
