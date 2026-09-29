<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";
require_once __DIR__ . "/../includes/booking_helpers.php";


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

$appointmentId = (int) ($_POST["appointment_id"] ?? 0);

if ($appointmentId <= 0) {
    redirectTo("../pages/my_bookings.php");
}


// =====================================================
// IDOR PROTECTION: the appointment MUST belong to the
// customer who is currently logged in, and it must still
// be in a cancellable state.
// =====================================================

$appointmentQuery = "
    SELECT appointment_id, barber_id, appointment_status
    FROM appointments
    WHERE appointment_id = ? AND customer_id = ?
    LIMIT 1
";

$statement = mysqli_prepare($databaseConnection, $appointmentQuery);
mysqli_stmt_bind_param($statement, "ii", $appointmentId, $customerId);
mysqli_stmt_execute($statement);
$appointment = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

if (!$appointment) {

    setAuthenticationMessage("error", "That booking could not be found.");
    redirectTo("../pages/my_bookings.php");
}

$cancellableStatuses = ["Pending", "Confirmed", "Waiting"];

if (!in_array($appointment["appointment_status"], $cancellableStatuses, true)) {

    setAuthenticationMessage("error", "This booking can no longer be cancelled.");
    redirectTo("../pages/my_bookings.php");
}


// =====================================================
// CANCEL IT
// =====================================================

$updateQuery = "
    UPDATE appointments
    SET appointment_status = 'Cancelled', status_updated_at = NOW()
    WHERE appointment_id = ?
";

$statement = mysqli_prepare($databaseConnection, $updateQuery);
mysqli_stmt_bind_param($statement, "i", $appointmentId);
mysqli_stmt_execute($statement);
mysqli_stmt_close($statement);

releaseBarberIfFree($databaseConnection, $appointment["barber_id"] ? (int) $appointment["barber_id"] : null);


// =====================================================
// IF THE CUSTOMER HAD ALREADY PAID (VERIFIED) FOR THIS
// APPOINTMENT, OFFER THEM A REFUND REQUEST INSTEAD OF
// JUST SENDING THEM BACK TO MY BOOKINGS.
// =====================================================

$verifiedPaymentQuery = "
    SELECT payment_id FROM payments
    WHERE appointment_id = ? AND payment_status = 'Verified'
    LIMIT 1
";

$statement = mysqli_prepare($databaseConnection, $verifiedPaymentQuery);
mysqli_stmt_bind_param($statement, "i", $appointmentId);
mysqli_stmt_execute($statement);
$hadVerifiedPayment = mysqli_num_rows(mysqli_stmt_get_result($statement)) > 0;
mysqli_stmt_close($statement);

if ($hadVerifiedPayment) {

    setAuthenticationMessage(
        "success",
        "Your booking has been cancelled. Since you already paid, you can request a refund below."
    );

    redirectTo("../pages/refund_request.php?appointment_id=" . $appointmentId);
}

setAuthenticationMessage("success", "Your booking has been cancelled.");
redirectTo("../pages/my_bookings.php");
