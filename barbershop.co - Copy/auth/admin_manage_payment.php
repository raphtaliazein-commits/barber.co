<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isAdministratorLoggedIn()) {
    redirectTo("../pages/login.php");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../admin/payments.php");
}

$paymentId = (int) ($_POST["payment_id"] ?? 0);
$decision = $_POST["decision"] ?? "";

if ($paymentId <= 0 || !in_array($decision, ["Verified", "Rejected"], true)) {
    redirectTo("../admin/payments.php");
}

$adminNotes = trim($_POST["admin_notes"] ?? "");

if ($decision === "Verified") {

    $updateQuery = "
        UPDATE payments
        SET payment_status = 'Verified', paid_at = NOW(), admin_notes = ?
        WHERE payment_id = ?
    ";

} else {

    $updateQuery = "
        UPDATE payments
        SET payment_status = 'Rejected', admin_notes = ?
        WHERE payment_id = ?
    ";
}

$statement = mysqli_prepare($databaseConnection, $updateQuery);
mysqli_stmt_bind_param($statement, "si", $adminNotes, $paymentId);
mysqli_stmt_execute($statement);
mysqli_stmt_close($statement);


// =====================================================
// IF THIS APPOINTMENT WAS STILL "Pending" (unpaid) AND ITS
// PAYMENT IS NOW VERIFIED — REGARDLESS OF WHETHER IT WAS A
// RESERVATION FEE OR A FULL PAYMENT — MOVE IT TO "Confirmed".
// For Reservations with no barber yet, this also tells the
// admin it's ready to be assigned (see admin/appointments.php).
// =====================================================

if ($decision === "Verified") {

    $findAppointmentQuery = "
        SELECT p.appointment_id, a.appointment_status
        FROM payments p
        INNER JOIN appointments a ON a.appointment_id = p.appointment_id
        WHERE p.payment_id = ? LIMIT 1
    ";

    $statement = mysqli_prepare($databaseConnection, $findAppointmentQuery);
    mysqli_stmt_bind_param($statement, "i", $paymentId);
    mysqli_stmt_execute($statement);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if ($row && $row["appointment_status"] === "Pending") {

        $updateAppointmentQuery = "UPDATE appointments SET appointment_status = 'Confirmed', status_updated_at = NOW() WHERE appointment_id = ?";
        $statement = mysqli_prepare($databaseConnection, $updateAppointmentQuery);
        mysqli_stmt_bind_param($statement, "i", $row["appointment_id"]);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }
}

setAuthenticationMessage("success", "Payment marked as " . $decision . ".");
redirectTo("../admin/payments.php");
