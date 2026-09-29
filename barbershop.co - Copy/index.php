<?php

require_once "includes/config.php";
require_once "auth/authentication_message.php";
require_once "db/database_connection.php";
require_once "includes/booking_helpers.php";

$pageTitle = "BARBERSHOP.CO - Premium Barbershop";

$baseWebsitePath = "";


// =====================================================
// GET AVAILABLE SERVICES (the 5 packages)
// =====================================================

$serviceQuery = "
    SELECT
        service_id,
        service_name,
        service_description,
        service_price,
        estimated_duration_minutes,
        service_image,
        service_status
    FROM services
    WHERE service_status = 'Available'
    ORDER BY service_id ASC
";

$serviceResult = mysqli_query($databaseConnection, $serviceQuery);


// =====================================================
// GET LIVE QUEUE — ONE CARD PER BARBER
// (scales automatically with however many barbers exist)
//
// Any appointment that's been "Waiting" too long without the
// customer showing up is auto-marked "No Show" first, so the
// queue below is always up to date.
// =====================================================

autoProgressAppointments($databaseConnection);

$currentDate = date("Y-m-d");

$allBarbersQuery = "SELECT barber_id, barber_name, barber_status FROM barbers ORDER BY barber_name ASC";
$allBarbersResult = mysqli_query($databaseConnection, $allBarbersQuery);
$allBarbersList = mysqli_fetch_all($allBarbersResult, MYSQLI_ASSOC);

$barberDailyLoadForQueue = getBarberDailyLoad($databaseConnection, $currentDate);

$queueByBarberId = [];


// -----------------------------------------------------
// "NOW SERVING" — the customer currently being served (checked in
// and within their service's duration) for each barber
// -----------------------------------------------------

$waitingQuery = "
    SELECT a.barber_id, a.status_updated_at, c.full_name AS customer_name,
           s.estimated_duration_minutes
    FROM appointments a
    INNER JOIN customers c ON c.customer_id = a.customer_id
    INNER JOIN services s ON s.service_id = a.service_id
    WHERE a.appointment_date = ?
    AND a.appointment_status = 'Waiting'
    AND a.barber_id IS NOT NULL
";

$waitingStatement = mysqli_prepare($databaseConnection, $waitingQuery);
mysqli_stmt_bind_param($waitingStatement, "s", $currentDate);
mysqli_stmt_execute($waitingStatement);
$waitingResult = mysqli_stmt_get_result($waitingStatement);

while ($waitingRow = mysqli_fetch_assoc($waitingResult)) {

    $waitingBarberId = (int) $waitingRow["barber_id"];

    if (!isset($queueByBarberId[$waitingBarberId])) {
        $queueByBarberId[$waitingBarberId] = ["waiting" => null, "confirmed" => null];
    }

    $queueByBarberId[$waitingBarberId]["waiting"] = $waitingRow;
}

mysqli_stmt_close($waitingStatement);


// -----------------------------------------------------
// "NEXT CUSTOMER" — each barber's next confirmed, scheduled
// appointment today (already has a time and barber assigned)
// -----------------------------------------------------

$confirmedQuery = "
    SELECT a.barber_id, a.appointment_start_time, c.full_name AS customer_name
    FROM appointments a
    INNER JOIN customers c ON c.customer_id = a.customer_id
    WHERE a.appointment_date = ?
    AND a.appointment_status = 'Confirmed'
    AND a.barber_id IS NOT NULL
    AND a.appointment_start_time IS NOT NULL
    ORDER BY a.appointment_start_time ASC
";

$confirmedStatement = mysqli_prepare($databaseConnection, $confirmedQuery);
mysqli_stmt_bind_param($confirmedStatement, "s", $currentDate);
mysqli_stmt_execute($confirmedStatement);
$confirmedResult = mysqli_stmt_get_result($confirmedStatement);

while ($confirmedRow = mysqli_fetch_assoc($confirmedResult)) {

    $confirmedBarberId = (int) $confirmedRow["barber_id"];

    if (!isset($queueByBarberId[$confirmedBarberId])) {
        $queueByBarberId[$confirmedBarberId] = ["waiting" => null, "confirmed" => null, "more_in_queue" => 0];
    }

    if (!isset($queueByBarberId[$confirmedBarberId]["more_in_queue"])) {
        $queueByBarberId[$confirmedBarberId]["more_in_queue"] = 0;
    }

    // Results are ordered earliest-first, so the first one seen per
    // barber is the immediate "next customer"; anything after that
    // is just counted as "more in queue" behind them.
    if ($queueByBarberId[$confirmedBarberId]["confirmed"] === null) {
        $queueByBarberId[$confirmedBarberId]["confirmed"] = $confirmedRow;
    } else {
        $queueByBarberId[$confirmedBarberId]["more_in_queue"]++;
    }
}

mysqli_stmt_close($confirmedStatement);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="styles/style.css?v=11">

</head>


<body data-auto-refresh-seconds="45">


<?php include "includes/header.php"; ?>


<!-- =========================================
     HERO SECTION
     ========================================= -->

<section class="hero-section">

    <div class="authentication-message-container">
        <?php displayAuthenticationMessage(); ?>
    </div>

    <div class="hero-overlay"></div>

    <div class="container hero-content">

        <div class="row align-items-center min-vh-100">

            <div class="col-lg-7">

                <p class="hero-small-title">
                    PREMIUM BARBERSHOP EXPERIENCE
                </p>

                <h1 class="hero-title">
                    LOOK SHARP.
                    <br>
                    <span>FEEL CONFIDENT.</span>
                </h1>

                <p class="hero-description">
                    Book your haircut with your preferred barber,
                    choose your schedule, and skip the long waiting time.
                </p>

                <div class="hero-buttons">

                    <a href="pages/booking.php" class="btn btn-primary-custom">
                        BOOK AN APPOINTMENT
                    </a>

                    <a href="#services" class="btn btn-outline-custom">
                        VIEW SERVICES
                    </a>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================
     QUICK INFORMATION SECTION
     ========================================= -->

<section class="quick-information-section">

    <div class="container">

        <div class="row g-4">

            <div class="col-md-4">
                <div class="information-card">
                    <div class="information-icon"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <h5>Easy Booking</h5>
                        <p>Choose your service, barber, date, and preferred time.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="information-card">
                    <div class="information-icon"><i class="bi bi-clock"></i></div>
                    <div>
                        <h5>Check Your Schedule</h5>
                        <p>See available schedules and avoid unnecessary waiting.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="information-card">
                    <div class="information-icon"><i class="bi bi-scissors"></i></div>
                    <div>
                        <h5>Professional Barbers</h5>
                        <p>Choose from our available professional barbers.</p>
                    </div>
                </div>
            </div>

        </div>

    </div>

</section>


<!-- =========================================
     SERVICES SECTION
     ========================================= -->

<section id="services" class="services-section">

    <div class="container">

        <div class="section-heading text-center">
            <p class="section-label">OUR SERVICES</p>
            <h2>CHOOSE YOUR STYLE</h2>
            <p>Select the service that fits your preferred look.</p>
        </div>


        <div class="row g-4 mt-4">

            <?php

            if ($serviceResult && mysqli_num_rows($serviceResult) > 0) {

                $serviceNumber = 1;

                while ($service = mysqli_fetch_assoc($serviceResult)) {

                    $serviceId = (int) $service["service_id"];

                    $serviceName = htmlspecialchars($service["service_name"]);

                    $serviceDescription = htmlspecialchars($service["service_description"] ?? "");

                    $servicePrice = number_format((float) $service["service_price"], 0);

                    $featuredServiceClass = ($serviceNumber === 5) ? " featured-service" : "";

                    ?>

                    <div class="col-lg-4 col-md-6">

                        <div class="service-card<?php echo $featuredServiceClass; ?>">

                            <div class="service-number">
                                <?php echo str_pad($serviceNumber, 2, "0", STR_PAD_LEFT); ?>
                            </div>

                            <div class="service-icon">
                                <i class="bi bi-scissors"></i>
                            </div>

                            <h3><?php echo $serviceName; ?></h3>

                            <p><?php echo $serviceDescription; ?></p>

                            <div class="service-price">
                                &#8369;<?php echo $servicePrice; ?>
                            </div>

                            <a href="pages/booking.php?service_id=<?php echo $serviceId; ?>" class="service-button">
                                BOOK NOW
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                    <?php

                    $serviceNumber++;
                }

            } else {

                ?>

                <div class="col-12">
                    <div class="text-center">
                        <p>No services are currently available.</p>
                    </div>
                </div>

                <?php
            }

            ?>

        </div>

    </div>

</section>


<!-- =========================================
     APPOINTMENT CALL TO ACTION
     ========================================= -->

<section class="appointment-section">

    <div class="container">

        <div class="appointment-content">

            <div>
                <p class="section-label">READY FOR YOUR NEXT LOOK?</p>
                <h2>BOOK YOUR APPOINTMENT TODAY.</h2>
                <p>Select your preferred barber and schedule your appointment in just a few clicks.</p>
            </div>

            <div>
                <a href="pages/booking.php" class="btn btn-primary-custom appointment-button">
                    BOOK AN APPOINTMENT
                </a>
            </div>

        </div>

    </div>

</section>


<!-- =========================================
     QUEUE SECTION
     ========================================= -->

<section class="queue-section">

    <div class="container">

        <div class="section-heading text-center">
            <p class="section-label">LIVE QUEUE</p>
            <h2>CHECK THE CURRENT QUEUE</h2>
            <p>Know the current queue before visiting the barbershop.</p>
        </div>


        <div class="queue-grid mt-4">

            <?php if (empty($allBarbersList)): ?>

                <div class="queue-card">
                    <p class="text-center mb-0">No barbers are set up yet.</p>
                </div>

            <?php endif; ?>

            <?php foreach ($allBarbersList as $barber): ?>

                <?php
                $barberId = (int) $barber["barber_id"];
                $barberQueue = $queueByBarberId[$barberId] ?? ["waiting" => null, "confirmed" => null, "more_in_queue" => 0];
                $waitingCustomer = $barberQueue["waiting"];
                $confirmedCustomer = $barberQueue["confirmed"];
                $moreInQueueCount = $barberQueue["more_in_queue"] ?? 0;

                $barberStatusClass = match ($barber["barber_status"]) {
                    "Available" => "status-confirmed",
                    "Busy" => "status-pending",
                    default => "status-cancelled",
                };

                if ($waitingCustomer) {
                    $waitingTargetTimestamp = strtotime($waitingCustomer["status_updated_at"]) + ((int) $waitingCustomer["estimated_duration_minutes"] * 60);
                }

                if ($confirmedCustomer) {
                    $confirmedTargetTimestamp = strtotime($currentDate . " " . $confirmedCustomer["appointment_start_time"]);
                }
                ?>

                <div class="queue-card">

                    <div class="queue-card-barber">
                        <span><?php echo htmlspecialchars($barber["barber_name"]); ?></span>
                        <span class="status-badge <?php echo $barberStatusClass; ?>">
                            <?php echo htmlspecialchars($barber["barber_status"]); ?>
                        </span>
                    </div>

                    <p class="queue-slots-note">
                        <?php echo $barberDailyLoadForQueue[(int) $barber["barber_id"]] ?? 0; ?>/<?php echo BARBER_MAX_DAILY_SLOTS; ?> booked today
                    </p>

                    <span class="queue-label">NOW SERVING</span>

                    <?php if ($waitingCustomer): ?>

                        <h3>Serving <?php echo htmlspecialchars($waitingCustomer["customer_name"]); ?></h3>

                        <p class="queue-countdown-note">
                            Finishing in
                            <span
                                class="live-countdown queue-countdown"
                                data-countdown-target="<?php echo $waitingTargetTimestamp; ?>"
                                data-countdown-done="All done!"
                            >--:--</span>
                        </p>

                    <?php else: ?>

                        <h3>No customer yet</h3>

                    <?php endif; ?>

                    <span class="queue-divider"></span>

                    <span class="queue-label">NEXT CUSTOMER</span>

                    <?php if ($confirmedCustomer): ?>

                        <h4><?php echo htmlspecialchars($confirmedCustomer["customer_name"]); ?></h4>

                        <p class="queue-countdown-note">
                            Starts in
                            <span
                                class="live-countdown queue-countdown"
                                data-countdown-target="<?php echo $confirmedTargetTimestamp; ?>"
                                data-countdown-done="Starting now"
                            >--:--</span>
                        </p>

                        <?php if ($moreInQueueCount > 0): ?>
                            <p class="queue-more-note">
                                +<?php echo $moreInQueueCount; ?> more waiting after
                            </p>
                        <?php endif; ?>

                    <?php else: ?>

                        <h4>No one in line</h4>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

        <p class="queue-hours-note text-center">
            Business hours: <strong>8:00 AM &ndash; 7:00 PM</strong>
        </p>

    </div>

</section>


<?php include "includes/footer.php"; ?>
