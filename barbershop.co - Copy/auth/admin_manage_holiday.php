<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isAdministratorLoggedIn()) {
    redirectTo("../pages/login.php");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../admin/calendar.php");
}

$formAction = $_POST["form_action"] ?? "";


if ($formAction === "add_holiday") {

    $holidayDate = trim($_POST["holiday_date"] ?? "");
    $holidayName = trim($_POST["holiday_name"] ?? "");

    if (empty($holidayDate) || empty($holidayName)) {
        setAuthenticationMessage("error", "Please provide both a date and a name for the holiday.");
        redirectTo("../admin/calendar.php");
    }

    $insertQuery = "
        INSERT INTO holidays (holiday_date, holiday_name)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE holiday_name = VALUES(holiday_name)
    ";

    $statement = mysqli_prepare($databaseConnection, $insertQuery);
    mysqli_stmt_bind_param($statement, "ss", $holidayDate, $holidayName);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    setAuthenticationMessage("success", "Holiday added. The shop will be marked closed on that date.");
    redirectTo("../admin/calendar.php?month=" . date("Y-m", strtotime($holidayDate)));
}


if ($formAction === "delete_holiday") {

    $holidayId = (int) ($_POST["holiday_id"] ?? 0);

    if ($holidayId > 0) {

        $deleteQuery = "DELETE FROM holidays WHERE holiday_id = ?";
        $statement = mysqli_prepare($databaseConnection, $deleteQuery);
        mysqli_stmt_bind_param($statement, "i", $holidayId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Holiday removed.");
    }

    redirectTo("../admin/calendar.php");
}

redirectTo("../admin/calendar.php");
