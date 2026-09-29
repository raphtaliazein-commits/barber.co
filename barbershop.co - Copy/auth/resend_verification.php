<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/mail_config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first.");
    redirectTo("../pages/login.php");
}

$customerId = (int) $_SESSION["customer_id"];

$statement = mysqli_prepare($databaseConnection, "
    SELECT full_name, email_address, email_verified_at, email_verification_sent_at
    FROM customers WHERE customer_id = ? LIMIT 1
");
mysqli_stmt_bind_param($statement, "i", $customerId);
mysqli_stmt_execute($statement);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

if (!$customer) {
    redirectTo("../pages/my_bookings.php");
}

if ($customer["email_verified_at"]) {

    setAuthenticationMessage("success", "Your email is already verified.");
    redirectTo("../pages/my_bookings.php");
}


// =====================================================
// SIMPLE RATE LIMIT: at most one resend per 60 seconds,
// so the resend link can't be used to spam someone's inbox.
// =====================================================

if (
    $customer["email_verification_sent_at"] &&
    (time() - strtotime($customer["email_verification_sent_at"])) < 60
) {

    setAuthenticationMessage("error", "Please wait a moment before requesting another verification email.");
    redirectTo("../pages/my_bookings.php");
}

$newToken = generateEmailVerificationToken();

$updateStatement = mysqli_prepare($databaseConnection, "
    UPDATE customers
    SET email_verification_token = ?, email_verification_sent_at = NOW()
    WHERE customer_id = ?
");
mysqli_stmt_bind_param($updateStatement, "si", $newToken, $customerId);
mysqli_stmt_execute($updateStatement);
mysqli_stmt_close($updateStatement);

$verificationLink = buildSiteBaseUrl() . "/pages/verify_email.php?token=" . $newToken;

$emailWasSent = sendVerificationEmail($customer["email_address"], $customer["full_name"], $verificationLink);

if ($emailWasSent) {
    setAuthenticationMessage("success", "Verification email sent! Please check your inbox.");
} else {
    setAuthenticationMessage("error", "We couldn't send the email right now. Please try again later.");
}

redirectTo("../pages/my_bookings.php");
