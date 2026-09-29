<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isAdministratorLoggedIn()) {
    redirectTo("../pages/login.php");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../admin/services.php");
}

$formAction = $_POST["form_action"] ?? "";


if ($formAction === "save_service") {

    $serviceId = (int) ($_POST["service_id"] ?? 0);
    $serviceName = trim($_POST["service_name"] ?? "");
    $serviceDescription = trim($_POST["service_description"] ?? "");
    $servicePrice = (float) ($_POST["service_price"] ?? 0);
    $estimatedDurationMinutes = (int) ($_POST["estimated_duration_minutes"] ?? 30);
    $serviceStatus = $_POST["service_status"] ?? "Available";

    if (empty($serviceName) || $servicePrice <= 0) {
        setAuthenticationMessage("error", "Please enter a valid service name and price.");
        redirectTo("../admin/services.php");
    }

    if (!in_array($serviceStatus, ["Available", "Unavailable"], true)) {
        $serviceStatus = "Available";
    }

    if ($estimatedDurationMinutes <= 0) {
        $estimatedDurationMinutes = 30;
    }

    if ($serviceId > 0) {

        $updateQuery = "
            UPDATE services
            SET service_name = ?, service_description = ?, service_price = ?,
                estimated_duration_minutes = ?, service_status = ?
            WHERE service_id = ?
        ";

        $statement = mysqli_prepare($databaseConnection, $updateQuery);
        mysqli_stmt_bind_param(
            $statement, "ssdisi",
            $serviceName, $serviceDescription, $servicePrice,
            $estimatedDurationMinutes, $serviceStatus, $serviceId
        );
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Service updated successfully.");

    } else {

        $insertQuery = "
            INSERT INTO services
            (service_name, service_description, service_price, estimated_duration_minutes, service_status)
            VALUES (?, ?, ?, ?, ?)
        ";

        $statement = mysqli_prepare($databaseConnection, $insertQuery);
        mysqli_stmt_bind_param(
            $statement, "ssdis",
            $serviceName, $serviceDescription, $servicePrice,
            $estimatedDurationMinutes, $serviceStatus
        );
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Service added successfully.");
    }

    redirectTo("../admin/services.php");
}


if ($formAction === "delete_service") {

    $serviceId = (int) ($_POST["service_id"] ?? 0);

    if ($serviceId > 0) {

        $updateQuery = "UPDATE services SET service_status = 'Unavailable' WHERE service_id = ?";
        $statement = mysqli_prepare($databaseConnection, $updateQuery);
        mysqli_stmt_bind_param($statement, "i", $serviceId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        setAuthenticationMessage("success", "Service marked as unavailable.");
    }

    redirectTo("../admin/services.php");
}

redirectTo("../admin/services.php");
