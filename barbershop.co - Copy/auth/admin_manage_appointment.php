<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";
require_once __DIR__ . "/../includes/booking_helpers.php";

if (!isAdministratorLoggedIn()) {
    redirectTo("../pages/login.php");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../admin/appointments.php");
}

$formAction = $_POST["form_action"] ?? "";
$appointmentId = (int) ($_POST["appointment_id"] ?? 0);

if ($appointmentId <= 0) {
    redirectTo("../admin/appointments.php");
}


// =====================================================
// ASSIGN BARBER + TIME TO A RESERVATION
// This confirms the reservation with a real schedule —
// it becomes "Confirmed" (not "Waiting" yet), since the
// customer still needs to actually show up.
// =====================================================

if ($formAction === "assign_barber") {

    $barberId = (int) ($_POST["barber_id"] ?? 0);
    $appointmentStartTime = trim($_POST["appointment_start_time"] ?? "");

    if ($barberId <= 0 || empty($appointmentStartTime)) {
        setAuthenticationMessage("error", "Please choose a barber and a time.");
        redirectTo("../admin/appointments.php");
    }

    $serviceQuery = "
        SELECT s.estimated_duration_minutes, a.appointment_date
        FROM appointments a
        INNER JOIN services s ON s.service_id = a.service_id
        WHERE a.appointment_id = ? LIMIT 1
    ";

    $statement = mysqli_prepare($databaseConnection, $serviceQuery);
    mysqli_stmt_bind_param($statement, "i", $appointmentId);
    mysqli_stmt_execute($statement);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if (!$row) {
        redirectTo("../admin/appointments.php");
    }

    $durationMinutes = (int) $row["estimated_duration_minutes"];
    $appointmentDate = $row["appointment_date"];

    $startDateTime = new DateTime($appointmentDate . " " . $appointmentStartTime);
    $endDateTime = clone $startDateTime;
    $endDateTime->modify("+" . $durationMinutes . " minutes");
    $appointmentEndTime = $endDateTime->format("H:i:s");

    $updateQuery = "
        UPDATE appointments
        SET barber_id = ?, appointment_start_time = ?, appointment_end_time = ?,
            appointment_status = 'Confirmed', status_updated_at = NOW()
        WHERE appointment_id = ?
        AND appointment_status NOT IN ('Cancelled', 'No Show')
    ";

    $statement = mysqli_prepare($databaseConnection, $updateQuery);
    mysqli_stmt_bind_param($statement, "issi", $barberId, $appointmentStartTime, $appointmentEndTime, $appointmentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    setAuthenticationMessage("success", "Barber and time assigned successfully.");
    redirectTo("../admin/appointments.php");
}


// =====================================================
// CHANGE APPOINTMENT STATUS
// =====================================================

if ($formAction === "update_status") {

    $newStatus = $_POST["appointment_status"] ?? "";

    $allowedStatuses = ["Pending", "Confirmed", "Waiting", "Completed", "Cancelled", "No Show"];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        redirectTo("../admin/appointments.php");
    }


    // =================================================
    // GET THE CURRENT STATUS + BARBER BEFORE UPDATING
    // =================================================

    $lookupStatement = mysqli_prepare($databaseConnection, "SELECT barber_id, appointment_status FROM appointments WHERE appointment_id = ? LIMIT 1");
    mysqli_stmt_bind_param($lookupStatement, "i", $appointmentId);
    mysqli_stmt_execute($lookupStatement);
    $currentAppointment = mysqli_fetch_assoc(mysqli_stmt_get_result($lookupStatement));
    mysqli_stmt_close($lookupStatement);

    if (!$currentAppointment) {
        redirectTo("../admin/appointments.php");
    }


    // =================================================
    // Cancelled / No Show ARE FINAL — once set, they cannot
    // be changed again except by the customer making a
    // brand new booking.
    // =================================================

    if (in_array($currentAppointment["appointment_status"], ["Cancelled", "No Show"], true)) {

        setAuthenticationMessage("error", "This booking is already " . $currentAppointment["appointment_status"] . " and can no longer be changed.");
        redirectTo("../admin/appointments.php");
    }

    $appointmentBarberId = $currentAppointment["barber_id"];

    $updateQuery = "
        UPDATE appointments
        SET appointment_status = ?, status_updated_at = NOW()
        WHERE appointment_id = ?
        AND appointment_status NOT IN ('Cancelled', 'No Show')
    ";

    $statement = mysqli_prepare($databaseConnection, $updateQuery);
    mysqli_stmt_bind_param($statement, "si", $newStatus, $appointmentId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);


    // =================================================
    // FREE UP THE BARBER ONCE THE APPOINTMENT IS OVER
    // =================================================

    if (in_array($newStatus, ["Completed", "Cancelled", "No Show"], true)) {
        releaseBarberIfFree($databaseConnection, $appointmentBarberId ? (int) $appointmentBarberId : null);
    }

    setAuthenticationMessage("success", "Appointment status updated.");
    redirectTo("../admin/appointments.php");
}

redirectTo("../admin/appointments.php");
