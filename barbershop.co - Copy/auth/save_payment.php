<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";


// =====================================================
// MAKE SURE CUSTOMER IS LOGGED IN
// =====================================================

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first.");
    redirectTo("../pages/login.php");
}

$customerId = (int) $_SESSION["customer_id"];


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../pages/my_bookings.php");
}


// =====================================================
// GET FORM DATA
// =====================================================

$appointmentId = (int) ($_POST["appointment_id"] ?? 0);

$paymentMethod = $_POST["payment_method"] ?? "";

$paymentReferenceNumber = trim($_POST["payment_reference_number"] ?? "");

$allowedPaymentMethods = ["GCash", "PayMaya", "Cash"];

if ($appointmentId <= 0 || !in_array($paymentMethod, $allowedPaymentMethods, true)) {

    setAuthenticationMessage("error", "Please complete the payment form correctly.");
    redirectTo("../pages/payment.php");
}


// =====================================================
// AGE VERIFICATION (SERVER-SIDE, AUTHORITATIVE)
// GCash/PayMaya are only allowed for verified 18+ customers.
// This is checked again here — independent of whatever the
// submitted form says — so it cannot be bypassed by editing
// the page's HTML or sending the request directly.
// =====================================================

if ($paymentMethod === "GCash" || $paymentMethod === "PayMaya") {

    $ageCheckStatement = mysqli_prepare($databaseConnection, "SELECT date_of_birth FROM customers WHERE customer_id = ? LIMIT 1");
    mysqli_stmt_bind_param($ageCheckStatement, "i", $customerId);
    mysqli_stmt_execute($ageCheckStatement);
    $customerDateOfBirth = mysqli_fetch_assoc(mysqli_stmt_get_result($ageCheckStatement))["date_of_birth"] ?? null;
    mysqli_stmt_close($ageCheckStatement);

    if (!isEligibleForOnlinePayment($customerDateOfBirth)) {

        setAuthenticationMessage(
            "error",
            "Online payment (GCash/PayMaya) is only available for customers 18 and above. Please choose Cash instead."
        );

        redirectTo("../pages/payment.php?appointment_id=" . $appointmentId);
    }
}


// =====================================================
// IDOR PROTECTION
// Make sure this appointment actually belongs to the
// customer who is currently logged in.
// =====================================================

$appointmentQuery = "
    SELECT a.appointment_id, a.booking_type, a.appointment_status, s.service_price
    FROM appointments a
    INNER JOIN services s ON s.service_id = a.service_id
    WHERE a.appointment_id = ? AND a.customer_id = ?
    LIMIT 1
";

$appointmentStatement = mysqli_prepare($databaseConnection, $appointmentQuery);
mysqli_stmt_bind_param($appointmentStatement, "ii", $appointmentId, $customerId);
mysqli_stmt_execute($appointmentStatement);
$appointmentResult = mysqli_stmt_get_result($appointmentStatement);
$appointment = mysqli_fetch_assoc($appointmentResult);
mysqli_stmt_close($appointmentStatement);

if (!$appointment) {

    setAuthenticationMessage("error", "That appointment could not be found.");
    redirectTo("../pages/my_bookings.php");
}


// =====================================================
// DETERMINE PAYMENT PURPOSE AND AMOUNT
// (must match the logic in pages/payment.php)
// =====================================================

$existingReservationFeeQuery = "
    SELECT payment_id FROM payments
    WHERE appointment_id = ? AND payment_purpose = 'Reservation Fee'
    AND payment_status IN ('Pending', 'Verified')
    LIMIT 1
";

$existingFeeStatement = mysqli_prepare($databaseConnection, $existingReservationFeeQuery);
mysqli_stmt_bind_param($existingFeeStatement, "i", $appointmentId);
mysqli_stmt_execute($existingFeeStatement);
$existingFeeResult = mysqli_stmt_get_result($existingFeeStatement);
$existingFeeAlreadyPaid = mysqli_num_rows($existingFeeResult) > 0;
mysqli_stmt_close($existingFeeStatement);

if ($appointment["booking_type"] === "Reservation" && !$existingFeeAlreadyPaid) {

    $paymentPurpose = "Reservation Fee";
    $paymentAmount = RESERVATION_FEE_AMOUNT;

} else {

    $paymentPurpose = "Full Payment";
    $paymentAmount = (float) $appointment["service_price"];
}


// =====================================================
// HANDLE PROOF IMAGE UPLOAD (required for GCash / PayMaya)
// =====================================================

$paymentProofFileName = null;

if ($paymentMethod === "GCash" || $paymentMethod === "PayMaya") {

    if (empty($paymentReferenceNumber)) {

        setAuthenticationMessage("error", "Please enter your payment reference number.");
        redirectTo("../pages/payment.php");
    }

    if (
        !isset($_FILES["payment_proof"]) ||
        $_FILES["payment_proof"]["error"] !== UPLOAD_ERR_OK
    ) {

        setAuthenticationMessage("error", "Please upload a screenshot of your payment as proof.");
        redirectTo("../pages/payment.php");
    }

    $uploadedFile = $_FILES["payment_proof"];

    if ($uploadedFile["size"] > PAYMENT_PROOF_MAX_FILE_SIZE_BYTES) {

        setAuthenticationMessage("error", "Your proof image is too large. Maximum size is 5MB.");
        redirectTo("../pages/payment.php");
    }

    $imageInformation = getimagesize($uploadedFile["tmp_name"]);

    $allowedMimeTypes = unserialize(ALLOWED_IMAGE_MIME_TYPES);

    if (!$imageInformation || !in_array($imageInformation["mime"], $allowedMimeTypes, true)) {

        setAuthenticationMessage("error", "Please upload a valid image file (JPG, PNG, or WEBP).");
        redirectTo("../pages/payment.php");
    }

    if (!is_dir(PAYMENT_PROOF_UPLOAD_DIRECTORY)) {
        mkdir(PAYMENT_PROOF_UPLOAD_DIRECTORY, 0755, true);
    }

    $fileExtension = strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, ["jpg", "jpeg", "png", "webp"], true)) {
        $fileExtension = "jpg";
    }

    $paymentProofFileName =
        "proof_" . $appointmentId . "_" . time() . "_" .
        bin2hex(random_bytes(4)) . "." . $fileExtension;

    $destinationPath = PAYMENT_PROOF_UPLOAD_DIRECTORY . $paymentProofFileName;

    if (!move_uploaded_file($uploadedFile["tmp_name"], $destinationPath)) {

        setAuthenticationMessage("error", "Something went wrong while uploading your proof image.");
        redirectTo("../pages/payment.php");
    }
}


// =====================================================
// SAVE PAYMENT RECORD
// =====================================================

$insertPaymentQuery = "
    INSERT INTO payments
    (appointment_id, payment_method, payment_purpose, payment_amount,
     payment_reference_number, payment_proof_image, payment_status)
    VALUES
    (?, ?, ?, ?, ?, ?, 'Pending')
";

$insertStatement = mysqli_prepare($databaseConnection, $insertPaymentQuery);

$referenceNumberToSave = !empty($paymentReferenceNumber) ? $paymentReferenceNumber : null;

mysqli_stmt_bind_param(
    $insertStatement,
    "issdss",
    $appointmentId,
    $paymentMethod,
    $paymentPurpose,
    $paymentAmount,
    $referenceNumberToSave,
    $paymentProofFileName
);

if (!mysqli_stmt_execute($insertStatement)) {

    setAuthenticationMessage("error", "Something went wrong while saving your payment. Please try again.");
    redirectTo("../pages/payment.php");
}

mysqli_stmt_close($insertStatement);
mysqli_close($databaseConnection);

unset($_SESSION["pending_payment_appointment_id"]);

$paymentMessage = ($paymentMethod === "Cash")
    ? "Your booking is confirmed. Please pay in cash at the shop."
    : "Your payment proof was submitted. Our staff will verify it shortly.";

setAuthenticationMessage("success", $paymentMessage);

redirectTo("../pages/my_bookings.php");
