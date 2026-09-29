<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first.");
    redirectTo("../pages/login.php");
}

$customerId = (int) $_SESSION["customer_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../pages/my_bookings.php");
}

$appointmentId = (int) ($_POST["appointment_id"] ?? 0);
$fullName = trim($_POST["full_name"] ?? "");
$emailAddress = trim($_POST["email_address"] ?? "");
$contactNumber = trim($_POST["contact_number"] ?? "");
$message = trim($_POST["message"] ?? "");


// =====================================================
// IDOR PROTECTION: the appointment must belong to this
// customer and must actually be Cancelled.
// =====================================================

$appointmentQuery = "
    SELECT appointment_id FROM appointments
    WHERE appointment_id = ? AND customer_id = ? AND appointment_status = 'Cancelled'
    LIMIT 1
";

$statement = mysqli_prepare($databaseConnection, $appointmentQuery);
mysqli_stmt_bind_param($statement, "ii", $appointmentId, $customerId);
mysqli_stmt_execute($statement);
$validAppointment = mysqli_num_rows(mysqli_stmt_get_result($statement)) > 0;
mysqli_stmt_close($statement);

if (!$validAppointment) {

    setAuthenticationMessage("error", "That booking isn't eligible for a refund request.");
    redirectTo("../pages/my_bookings.php");
}

if (empty($fullName) || empty($contactNumber) || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {

    setAuthenticationMessage("error", "Please complete all required fields.");
    redirectTo("../pages/refund_request.php?appointment_id=" . $appointmentId);
}


// =====================================================
// OPTIONAL PROOF IMAGE UPLOAD
// =====================================================

$proofFileName = null;

if (isset($_FILES["payment_proof"]) && $_FILES["payment_proof"]["error"] === UPLOAD_ERR_OK) {

    $uploadedFile = $_FILES["payment_proof"];

    if ($uploadedFile["size"] <= PAYMENT_PROOF_MAX_FILE_SIZE_BYTES) {

        $imageInformation = getimagesize($uploadedFile["tmp_name"]);
        $allowedMimeTypes = unserialize(ALLOWED_IMAGE_MIME_TYPES);

        if ($imageInformation && in_array($imageInformation["mime"], $allowedMimeTypes, true)) {

            if (!is_dir(REFUND_PROOF_UPLOAD_DIRECTORY)) {
                mkdir(REFUND_PROOF_UPLOAD_DIRECTORY, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));

            if (!in_array($fileExtension, ["jpg", "jpeg", "png", "webp"], true)) {
                $fileExtension = "jpg";
            }

            $proofFileName =
                "refund_" . $appointmentId . "_" . time() . "_" .
                bin2hex(random_bytes(4)) . "." . $fileExtension;

            move_uploaded_file($uploadedFile["tmp_name"], REFUND_PROOF_UPLOAD_DIRECTORY . $proofFileName);
        }
    }
}


// =====================================================
// SAVE THE REQUEST
// =====================================================

$insertQuery = "
    INSERT INTO refund_requests
    (customer_id, appointment_id, full_name, email_address, contact_number, message, payment_proof_image)
    VALUES (?, ?, ?, ?, ?, ?, ?)
";

$statement = mysqli_prepare($databaseConnection, $insertQuery);

$messageToSave = !empty($message) ? $message : null;

mysqli_stmt_bind_param(
    $statement,
    "iisssss",
    $customerId,
    $appointmentId,
    $fullName,
    $emailAddress,
    $contactNumber,
    $messageToSave,
    $proofFileName
);

mysqli_stmt_execute($statement);
mysqli_stmt_close($statement);

setAuthenticationMessage("success", "Your refund request has been sent. Our staff will get back to you soon.");
redirectTo("../pages/my_bookings.php");
