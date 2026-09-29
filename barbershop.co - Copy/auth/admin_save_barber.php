<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isAdministratorLoggedIn()) {
    redirectTo("../pages/login.php");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../admin/barbers.php");
}

$formAction = $_POST["form_action"] ?? "";


// =====================================================
// ADD OR EDIT A BARBER
// =====================================================

if ($formAction === "save_barber") {

    $barberId = (int) ($_POST["barber_id"] ?? 0);
    $barberName = trim($_POST["barber_name"] ?? "");
    $barberDescription = trim($_POST["barber_description"] ?? "");
    $barberStatus = $_POST["barber_status"] ?? "Available";

    $allowedStatuses = ["Available", "Busy", "Offline"];

    if (empty($barberName)) {
        setAuthenticationMessage("error", "Please enter the barber's name.");
        redirectTo("../admin/barbers.php");
    }

    if (!in_array($barberStatus, $allowedStatuses, true)) {
        $barberStatus = "Available";
    }

    if ($barberId > 0) {

        $updateQuery = "
            UPDATE barbers
            SET barber_name = ?, barber_description = ?, barber_status = ?
            WHERE barber_id = ?
        ";

        $statement = mysqli_prepare($databaseConnection, $updateQuery);
        mysqli_stmt_bind_param($statement, "sssi", $barberName, $barberDescription, $barberStatus, $barberId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Barber updated successfully.");

    } else {

        $insertQuery = "
            INSERT INTO barbers (barber_name, barber_description, barber_status)
            VALUES (?, ?, ?)
        ";

        $statement = mysqli_prepare($databaseConnection, $insertQuery);
        mysqli_stmt_bind_param($statement, "sss", $barberName, $barberDescription, $barberStatus);
        mysqli_stmt_execute($statement);
        $newBarberId = mysqli_insert_id($databaseConnection);
        mysqli_stmt_close($statement);


        // =============================================
        // GIVE THE NEW BARBER A DEFAULT WEEKLY SCHEDULE
        // (Monday-Saturday, 8:00 AM - 7:00 PM) so they show
        // up immediately when customers are booking, instead
        // of being invisible until someone remembers to set
        // their schedule separately.
        // =============================================

        $defaultWorkingDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        $insertScheduleQuery = "
            INSERT INTO barber_schedules (barber_id, schedule_day, start_time, end_time, schedule_status)
            VALUES (?, ?, ?, ?, 'Available')
        ";

        foreach ($defaultWorkingDays as $day) {

            $scheduleStatement = mysqli_prepare($databaseConnection, $insertScheduleQuery);
            mysqli_stmt_bind_param(
                $scheduleStatement,
                "isss",
                $newBarberId,
                $day,
                BUSINESS_OPENING_TIME,
                BUSINESS_CLOSING_TIME
            );
            mysqli_stmt_execute($scheduleStatement);
            mysqli_stmt_close($scheduleStatement);
        }

        setAuthenticationMessage(
            "success",
            "Barber added with a default Mon-Sat, 8AM-7PM schedule. You can adjust it anytime under \"Schedule\"."
        );
    }

    redirectTo("../admin/barbers.php");
}


// =====================================================
// QUICK STATUS TOGGLE
// =====================================================

if ($formAction === "toggle_status") {

    $barberId = (int) ($_POST["barber_id"] ?? 0);
    $barberStatus = $_POST["barber_status"] ?? "Available";

    $allowedStatuses = ["Available", "Busy", "Offline"];

    if ($barberId > 0 && in_array($barberStatus, $allowedStatuses, true)) {

        $updateQuery = "UPDATE barbers SET barber_status = ? WHERE barber_id = ?";
        $statement = mysqli_prepare($databaseConnection, $updateQuery);
        mysqli_stmt_bind_param($statement, "si", $barberStatus, $barberId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Barber status updated.");
    }

    redirectTo("../admin/barbers.php");
}


// =====================================================
// DELETE A BARBER
// =====================================================

if ($formAction === "delete_barber") {

    $barberId = (int) ($_POST["barber_id"] ?? 0);

    if ($barberId > 0) {

        $deleteQuery = "DELETE FROM barbers WHERE barber_id = ?";
        $statement = mysqli_prepare($databaseConnection, $deleteQuery);
        mysqli_stmt_bind_param($statement, "i", $barberId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Barber removed.");
    }

    redirectTo("../admin/barbers.php");
}


// =====================================================
// QUICK FIX: apply the default Mon-Sat, 8AM-7PM schedule
// to a barber who currently has none at all (this is what
// makes a barber invisible during booking).
// =====================================================

if ($formAction === "apply_default_schedule") {

    $barberId = (int) ($_POST["barber_id"] ?? 0);

    if ($barberId > 0) {

        $defaultWorkingDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

        $insertScheduleQuery = "
            INSERT INTO barber_schedules (barber_id, schedule_day, start_time, end_time, schedule_status)
            VALUES (?, ?, ?, ?, 'Available')
        ";

        foreach ($defaultWorkingDays as $day) {

            $statement = mysqli_prepare($databaseConnection, $insertScheduleQuery);
            mysqli_stmt_bind_param($statement, "isss", $barberId, $day, BUSINESS_OPENING_TIME, BUSINESS_CLOSING_TIME);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }

        setAuthenticationMessage("success", "Default schedule applied. This barber is now bookable.");
    }

    redirectTo("../admin/barbers.php");
}


// =====================================================
// SAVE WEEKLY SCHEDULE FOR A BARBER
// =====================================================

if ($formAction === "save_schedule") {

    $barberId = (int) ($_POST["barber_id"] ?? 0);
    $daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

    if ($barberId <= 0) {
        redirectTo("../admin/barbers.php");
    }

    $deleteOldScheduleQuery = "DELETE FROM barber_schedules WHERE barber_id = ?";
    $statement = mysqli_prepare($databaseConnection, $deleteOldScheduleQuery);
    mysqli_stmt_bind_param($statement, "i", $barberId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    foreach ($daysOfWeek as $day) {

        $isWorkingDay = isset($_POST["working_" . $day]);
        $startTime = trim($_POST["start_" . $day] ?? "08:00");
        $endTime = trim($_POST["end_" . $day] ?? "19:00");

        if (!$isWorkingDay) {
            continue;
        }

        if (empty($startTime) || empty($endTime) || $startTime >= $endTime) {
            continue;
        }

        $insertScheduleQuery = "
            INSERT INTO barber_schedules (barber_id, schedule_day, start_time, end_time, schedule_status)
            VALUES (?, ?, ?, ?, 'Available')
        ";

        $insertStatement = mysqli_prepare($databaseConnection, $insertScheduleQuery);
        mysqli_stmt_bind_param($insertStatement, "isss", $barberId, $day, $startTime, $endTime);
        mysqli_stmt_execute($insertStatement);
        mysqli_stmt_close($insertStatement);
    }

    setAuthenticationMessage("success", "Weekly schedule saved.");
    redirectTo("../admin/barbers.php?barber_id=" . $barberId);
}

redirectTo("../admin/barbers.php");
