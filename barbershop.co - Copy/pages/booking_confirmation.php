<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../auth/authentication_message.php";

$pageTitle = "Booking Confirmed - BARBERSHOP.CO";

if (!isset($_SESSION["customer_id"])) {
    redirectTo("login.php");
}

$bookingSuccess = $_SESSION["booking_success"] ?? null;
unset($_SESSION["booking_success"]);

if (!$bookingSuccess) {
    redirectTo("my_bookings.php");
}

$baseWebsitePath = "../";

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../styles/style.css?v=11">

</head>

<body class="booking-page">

<div class="booking-page-wrapper">

    <nav class="booking-navigation">
        <div class="booking-navigation-container">
            <a href="../index.php" class="booking-logo">
                <span class="booking-logo-icon"><i class="bi bi-scissors"></i></span>
                <span>BARBERSHOP<span>.CO</span></span>
            </a>
        </div>
    </nav>

    <main class="booking-main-container">
        <div class="container" style="max-width:640px;">

            <div class="confirmation-card">

                <div class="confirmation-icon"><i class="bi bi-check-circle-fill"></i></div>

                <h1>Booking Saved!</h1>
                <p>Your time slot is held. Please pay now to confirm it &mdash; unpaid bookings are not yet guaranteed.</p>

                <div class="confirmation-details">

                    <div class="confirmation-row">
                        <span>Appointment Code</span>
                        <strong><?php echo htmlspecialchars($bookingSuccess["appointment_code"]); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Service</span>
                        <strong><?php echo htmlspecialchars($bookingSuccess["service_name"]); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Barber</span>
                        <strong><?php echo htmlspecialchars($bookingSuccess["barber_name"]); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Date</span>
                        <strong><?php echo date("F d, Y", strtotime($bookingSuccess["appointment_date"])); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Time</span>
                        <strong>
                            <?php echo date("h:i A", strtotime($bookingSuccess["appointment_start_time"])); ?>
                            &ndash;
                            <?php echo date("h:i A", strtotime($bookingSuccess["appointment_end_time"])); ?>
                        </strong>
                    </div>

                </div>

                <div class="confirmation-buttons">
                    <a href="payment.php" class="booking-auto-button">
                        <i class="bi bi-credit-card"></i>
                        PAY ONLINE NOW
                    </a>

                    <a href="my_bookings.php" class="booking-check-button">
                        <i class="bi bi-calendar-check"></i>
                        VIEW MY BOOKINGS
                    </a>
                </div>

            </div>

        </div>
    </main>

    <footer class="booking-footer">
        <p>&copy; <?php echo date("Y"); ?> BARBERSHOP.CO</p>
    </footer>

</div>

</body>
</html>
