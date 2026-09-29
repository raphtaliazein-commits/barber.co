<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../pages/contact.php");
}

$fullName = trim($_POST["full_name"] ?? "");
$emailAddress = trim($_POST["email_address"] ?? "");
$phoneNumber = trim($_POST["phone_number"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");

if (empty($fullName) || empty($emailAddress) || empty($subject) || empty($message)) {

    setAuthenticationMessage("error", "Please complete all required fields.");
    redirectTo("../pages/contact.php");
}

if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {

    setAuthenticationMessage("error", "Please enter a valid email address.");
    redirectTo("../pages/contact.php");
}

$insertQuery = "
    INSERT INTO contact_messages
    (full_name, email_address, phone_number, subject, message)
    VALUES (?, ?, ?, ?, ?)
";

$insertStatement = mysqli_prepare($databaseConnection, $insertQuery);

$phoneNumberToSave = !empty($phoneNumber) ? $phoneNumber : null;

mysqli_stmt_bind_param(
    $insertStatement,
    "sssss",
    $fullName,
    $emailAddress,
    $phoneNumberToSave,
    $subject,
    $message
);

mysqli_stmt_execute($insertStatement);
mysqli_stmt_close($insertStatement);
mysqli_close($databaseConnection);

setAuthenticationMessage("success", "Your message has been sent. We'll get back to you soon!");
redirectTo("../pages/contact.php");
