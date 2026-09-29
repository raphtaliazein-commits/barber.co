<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";
require_once __DIR__ . "/../includes/booking_helpers.php";


// =====================================================
// MAKE SURE CUSTOMER IS LOGGED IN
// =====================================================

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage(
        "error",
        "Please login first before booking an appointment."
    );

    redirectTo("../pages/login.php");
}

$customerId = (int) $_SESSION["customer_id"];


// =====================================================
// MAKE SURE REQUEST IS POST
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../index.php");
}


// =====================================================
// ONE ACTIVE BOOKING AT A TIME
// (authoritative check — this is the endpoint that actually
// inserts into the database, so this is the check that really
// matters; the ones on booking.php / confirm_booking.php are
// just friendlier, earlier warnings)
// =====================================================

$activeAppointment = getCustomerActiveAppointment($databaseConnection, $customerId);

if ($activeAppointment) {

    setAuthenticationMessage(
        "warning",
        "You already have an active booking (" . htmlspecialchars($activeAppointment["appointment_code"]) . "). " .
        "You can book again once it's completed or cancelled."
    );

    redirectTo("../pages/my_bookings.php");
}


// =====================================================
// GET FORM DATA
// =====================================================

$serviceId = (int) ($_POST["service_id"] ?? 0);

$appointmentDate = trim($_POST["appointment_date"] ?? "");

$appointmentStartTime = trim($_POST["appointment_start_time"] ?? "");

$preferredBarberId = (int) ($_POST["preferred_barber_id"] ?? 0);

$customerNotes = trim($_POST["customer_notes"] ?? "");

$bookingType = strtolower(trim($_POST["booking_type"] ?? "auto"));

$bookingType = ($bookingType === "reservation") ? "Reservation" : "Auto Booking";


// =====================================================
// BASIC VALIDATION
// =====================================================

if ($serviceId <= 0 || empty($appointmentDate)) {

    setAuthenticationMessage(
        "error",
        "Please complete all required booking information."
    );

    redirectTo("../pages/booking.php?service_id=" . $serviceId);
}

if ($bookingType === "Auto Booking" && empty($appointmentStartTime)) {

    setAuthenticationMessage(
        "error",
        "Please choose an available time."
    );

    redirectTo("../pages/booking.php?service_id=" . $serviceId);
}


// =====================================================
// GET CURRENT DATE AND TIME
// =====================================================

$currentDate = date("Y-m-d");
$currentTime = date("H:i:s");


// =====================================================
// PREVENT PAST DATE
// =====================================================

if ($appointmentDate < $currentDate) {

    setAuthenticationMessage(
        "error",
        "You cannot book an appointment for a past date."
    );

    redirectTo("../pages/booking.php?service_id=" . $serviceId);
}


// =====================================================
// CHECK IF THE SHOP IS CLOSED TODAY (PAST BUSINESS HOURS)
// =====================================================

if ($appointmentDate === $currentDate && $currentTime >= BUSINESS_CLOSING_TIME) {

    setAuthenticationMessage(
        "error",
        "Booking hours are from 8:00 AM to 7:00 PM. Please choose another date."
    );

    redirectTo("../pages/booking.php?service_id=" . $serviceId);
}


// =====================================================
// CHECK IF THE SELECTED DATE IS A HOLIDAY
// =====================================================

$holidayCheckQuery = "SELECT holiday_name FROM holidays WHERE holiday_date = ? LIMIT 1";

$holidayStatement = mysqli_prepare($databaseConnection, $holidayCheckQuery);
mysqli_stmt_bind_param($holidayStatement, "s", $appointmentDate);
mysqli_stmt_execute($holidayStatement);
$holidayResult = mysqli_stmt_get_result($holidayStatement);
$holidayRow = mysqli_fetch_assoc($holidayResult);
mysqli_stmt_close($holidayStatement);

if ($holidayRow) {

    setAuthenticationMessage(
        "error",
        "The shop is closed on " . htmlspecialchars($appointmentDate) .
        " (" . htmlspecialchars($holidayRow["holiday_name"]) . "). Please choose another date."
    );

    redirectTo("../pages/booking.php?service_id=" . $serviceId);
}


// =====================================================
// GET SERVICE
// =====================================================

$serviceQuery = "
    SELECT service_id, service_name, estimated_duration_minutes, service_status
    FROM services
    WHERE service_id = ? AND service_status = 'Available'
    LIMIT 1
";

$serviceStatement = mysqli_prepare($databaseConnection, $serviceQuery);
mysqli_stmt_bind_param($serviceStatement, "i", $serviceId);
mysqli_stmt_execute($serviceStatement);
$serviceResult = mysqli_stmt_get_result($serviceStatement);
$service = mysqli_fetch_assoc($serviceResult);
mysqli_stmt_close($serviceStatement);

if (!$service) {

    setAuthenticationMessage(
        "error",
        "The selected service is no longer available."
    );

    redirectTo("../index.php#services");
}

$serviceDurationMinutes = (int) $service["estimated_duration_minutes"];

if ($serviceDurationMinutes <= 0) {
    $serviceDurationMinutes = 30;
}


// =====================================================
// GENERATE UNIQUE APPOINTMENT CODE
// =====================================================

$appointmentCode = "BC-" . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));


// =====================================================
// RESERVATION FLOW
// (no specific barber / exact time yet, admin assigns later)
// =====================================================

if ($bookingType === "Reservation") {

    $requestedTime = !empty($appointmentStartTime) ? $appointmentStartTime : null;

    $preferredBarberIdToSave = $preferredBarberId > 0 ? $preferredBarberId : null;

    $insertReservationQuery = "
        INSERT INTO appointments
        (appointment_code, customer_id, barber_id, preferred_barber_id, service_id, appointment_date,
         appointment_start_time, appointment_end_time, requested_time,
         booking_type, appointment_status, customer_notes)
        VALUES
        (?, ?, NULL, ?, ?, ?, NULL, NULL, ?, 'Reservation', 'Pending', ?)
    ";

    $insertStatement = mysqli_prepare($databaseConnection, $insertReservationQuery);

    mysqli_stmt_bind_param(
        $insertStatement,
        "siiisss",
        $appointmentCode,
        $customerId,
        $preferredBarberIdToSave,
        $serviceId,
        $appointmentDate,
        $requestedTime,
        $customerNotes
    );

    if (!mysqli_stmt_execute($insertStatement)) {

        setAuthenticationMessage(
            "error",
            "Something went wrong while saving your reservation. Please try again."
        );

        redirectTo("../pages/booking.php?service_id=" . $serviceId);
    }

    $newAppointmentId = mysqli_insert_id($databaseConnection);

    mysqli_stmt_close($insertStatement);
    mysqli_close($databaseConnection);

    $_SESSION["pending_payment_appointment_id"] = $newAppointmentId;

    setAuthenticationMessage(
        "success",
        "Your reservation request has been saved. Please pay the ₱100 reservation fee to confirm your slot."
    );

    redirectTo("../pages/payment.php");
}


// =====================================================
// AUTO BOOKING FLOW (specific available slot picked)
// =====================================================

$appointmentStartDateTime = new DateTime($appointmentDate . " " . $appointmentStartTime);

$appointmentEndDateTime = clone $appointmentStartDateTime;
$appointmentEndDateTime->modify("+" . $serviceDurationMinutes . " minutes");

$appointmentEndTime = $appointmentEndDateTime->format("H:i:s");


if ($appointmentDate === $currentDate) {

    $currentDateTime = new DateTime($currentDate . " " . $currentTime);

    if ($appointmentStartDateTime <= $currentDateTime) {

        setAuthenticationMessage(
            "error",
            "That appointment time has already passed. Please choose another available time."
        );

        redirectTo(
            "../pages/booking.php?service_id=" . $serviceId .
            "&appointment_date=" . urlencode($appointmentDate)
        );
    }
}

$dateObject = new DateTime($appointmentDate);
$scheduleDay = $dateObject->format("l");


mysqli_begin_transaction($databaseConnection);

try {

    $barberQuery = "
        SELECT b.barber_id, b.barber_name, bs.start_time, bs.end_time
        FROM barbers b
        INNER JOIN barber_schedules bs ON b.barber_id = bs.barber_id
        WHERE b.barber_status = 'Available'
        AND bs.schedule_day = ?
        AND bs.schedule_status = 'Available'
        AND bs.start_time <= ?
        AND bs.end_time >= ?
        ORDER BY (b.barber_id = ?) DESC, b.barber_id ASC
        FOR UPDATE
    ";

    $barberStatement = mysqli_prepare($databaseConnection, $barberQuery);

    mysqli_stmt_bind_param(
        $barberStatement,
        "sssi",
        $scheduleDay,
        $appointmentStartTime,
        $appointmentEndTime,
        $preferredBarberId
    );

    mysqli_stmt_execute($barberStatement);
    $barberResult = mysqli_stmt_get_result($barberStatement);

    $assignedBarber = null;

    while ($barber = mysqli_fetch_assoc($barberResult)) {

        $barberId = (int) $barber["barber_id"];


        // =============================================
        // SKIP THIS BARBER IF THEY'RE ALREADY AT THEIR
        // DAILY CAPACITY (BARBER_MAX_DAILY_SLOTS)
        // =============================================

        $dailyLoadQuery = "
            SELECT COUNT(*) AS active_count
            FROM appointments
            WHERE barber_id = ?
            AND appointment_date = ?
            AND appointment_status IN ('Pending', 'Confirmed', 'Waiting')
        ";

        $dailyLoadStatement = mysqli_prepare($databaseConnection, $dailyLoadQuery);
        mysqli_stmt_bind_param($dailyLoadStatement, "is", $barberId, $appointmentDate);
        mysqli_stmt_execute($dailyLoadStatement);
        $activeCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($dailyLoadStatement))["active_count"];
        mysqli_stmt_close($dailyLoadStatement);

        if ($activeCount >= BARBER_MAX_DAILY_SLOTS) {
            continue;
        }

        $existingAppointmentQuery = "
            SELECT appointment_id
            FROM appointments
            WHERE barber_id = ?
            AND appointment_date = ?
            AND appointment_status IN ('Pending', 'Confirmed', 'Waiting')
            AND appointment_start_time < ?
            AND appointment_end_time > ?
            LIMIT 1
            FOR UPDATE
        ";

        $existingStatement = mysqli_prepare($databaseConnection, $existingAppointmentQuery);

        mysqli_stmt_bind_param(
            $existingStatement,
            "isss",
            $barberId,
            $appointmentDate,
            $appointmentEndTime,
            $appointmentStartTime
        );

        mysqli_stmt_execute($existingStatement);
        $existingResult = mysqli_stmt_get_result($existingStatement);
        $hasConflict = mysqli_num_rows($existingResult) > 0;
        mysqli_stmt_close($existingStatement);

        if (!$hasConflict) {
            $assignedBarber = $barber;
            break;
        }
    }

    mysqli_stmt_close($barberStatement);


    // =================================================
    // NO BARBER AVAILABLE AT THAT EXACT SLOT
    // -> fall back to a Reservation instead of failing
    // =================================================

    if (!$assignedBarber) {

        mysqli_rollback($databaseConnection);

        $insertReservationQuery = "
            INSERT INTO appointments
            (appointment_code, customer_id, barber_id, service_id, appointment_date,
             appointment_start_time, appointment_end_time, requested_time,
             booking_type, appointment_status, customer_notes)
            VALUES
            (?, ?, NULL, ?, ?, NULL, NULL, ?, 'Reservation', 'Pending', ?)
        ";

        $insertStatement = mysqli_prepare($databaseConnection, $insertReservationQuery);

        mysqli_stmt_bind_param(
            $insertStatement,
            "siisss",
            $appointmentCode,
            $customerId,
            $serviceId,
            $appointmentDate,
            $appointmentStartTime,
            $customerNotes
        );

        mysqli_stmt_execute($insertStatement);

        $newAppointmentId = mysqli_insert_id($databaseConnection);

        mysqli_stmt_close($insertStatement);
        mysqli_close($databaseConnection);

        $_SESSION["pending_payment_appointment_id"] = $newAppointmentId;

        setAuthenticationMessage(
            "warning",
            "All barbers are busy at that time, so your booking was placed as a Reservation instead. Please pay the ₱100 reservation fee to confirm your slot."
        );

        redirectTo("../pages/payment.php");
    }


    // =================================================
    // SAVE AUTO BOOKING APPOINTMENT
    // =================================================

    $assignedBarberId = (int) $assignedBarber["barber_id"];

    $insertAppointmentQuery = "
        INSERT INTO appointments
        (appointment_code, customer_id, barber_id, service_id, appointment_date,
         appointment_start_time, appointment_end_time, check_in_time,
         booking_type, appointment_status, customer_notes)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, NULL, ?, 'Pending', ?)
    ";

    $insertStatement = mysqli_prepare($databaseConnection, $insertAppointmentQuery);

    mysqli_stmt_bind_param(
        $insertStatement,
        "siiisssss",
        $appointmentCode,
        $customerId,
        $assignedBarberId,
        $serviceId,
        $appointmentDate,
        $appointmentStartTime,
        $appointmentEndTime,
        $bookingType,
        $customerNotes
    );

    if (!mysqli_stmt_execute($insertStatement)) {
        throw new Exception("Unable to save appointment.");
    }

    $newAppointmentId = mysqli_insert_id($databaseConnection);

    mysqli_stmt_close($insertStatement);

    mysqli_commit($databaseConnection);
    mysqli_close($databaseConnection);

    $_SESSION["booking_success"] = [
        "appointment_code" => $appointmentCode,
        "service_name" => $service["service_name"],
        "barber_name" => $assignedBarber["barber_name"],
        "appointment_date" => $appointmentDate,
        "appointment_start_time" => $appointmentStartTime,
        "appointment_end_time" => $appointmentEndTime,
        "appointment_status" => "Pending",
    ];

    $_SESSION["pending_payment_appointment_id"] = $newAppointmentId;

    redirectTo("../pages/booking_confirmation.php");

} catch (Throwable $error) {

    mysqli_rollback($databaseConnection);
    mysqli_close($databaseConnection);

    setAuthenticationMessage(
        "error",
        "Something went wrong while booking your appointment. Please try again."
    );

    redirectTo(
        "../pages/booking.php?service_id=" . $serviceId .
        "&appointment_date=" . urlencode($appointmentDate)
    );
}
