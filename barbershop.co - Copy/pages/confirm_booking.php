<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";
require_once __DIR__ . "/../includes/booking_helpers.php";

$pageTitle = "Confirm Your Time - BARBERSHOP.CO";


// =====================================================
// CHECK CUSTOMER LOGIN
// =====================================================

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first before booking an appointment.");
    redirectTo("login.php");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("../index.php#services");
}

$customerId = (int) $_SESSION["customer_id"];


// =====================================================
// ONE ACTIVE BOOKING AT A TIME
// =====================================================

$activeAppointment = getCustomerActiveAppointment($databaseConnection, $customerId);

if ($activeAppointment) {

    setAuthenticationMessage(
        "warning",
        "You already have an active booking (" . htmlspecialchars($activeAppointment["appointment_code"]) . ")."
    );

    redirectTo("my_bookings.php");
}


// =====================================================
// GET FORM DATA
// =====================================================

$selectedServiceId = (int) ($_POST["service_id"] ?? 0);
$selectedDate = trim($_POST["appointment_date"] ?? "");
$preferredTimeInput = trim($_POST["preferred_time"] ?? "");

if ($selectedServiceId <= 0 || empty($selectedDate) || empty($preferredTimeInput)) {

    setAuthenticationMessage("error", "Please complete the booking form.");
    redirectTo("booking.php?service_id=" . $selectedServiceId);
}

// <input type="time"> sends "H:i" — normalize to "H:i:s" for comparisons.
if (strlen($preferredTimeInput) === 5) {
    $preferredTimeInput .= ":00";
}

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $preferredTimeInput)) {

    setAuthenticationMessage("error", "Please enter a valid time.");
    redirectTo("booking.php?service_id=" . $selectedServiceId . "&appointment_date=" . urlencode($selectedDate));
}


// =====================================================
// GET SERVICE
// =====================================================

$serviceQuery = "
    SELECT service_id, service_name, service_description, service_price,
           estimated_duration_minutes, service_status
    FROM services
    WHERE service_id = ? AND service_status = 'Available'
    LIMIT 1
";

$serviceStatement = mysqli_prepare($databaseConnection, $serviceQuery);
mysqli_stmt_bind_param($serviceStatement, "i", $selectedServiceId);
mysqli_stmt_execute($serviceStatement);
$serviceResult = mysqli_stmt_get_result($serviceStatement);
$selectedService = mysqli_fetch_assoc($serviceResult);
mysqli_stmt_close($serviceStatement);

if (!$selectedService) {

    setAuthenticationMessage("error", "The selected service is not available.");
    redirectTo("../index.php#services");
}

$serviceDurationMinutes = (int) $selectedService["estimated_duration_minutes"];


// =====================================================
// BASIC DATE VALIDATION
// =====================================================

$currentDate = date("Y-m-d");

if ($selectedDate < $currentDate) {

    setAuthenticationMessage("error", "You cannot book an appointment for a past date.");
    redirectTo("booking.php?service_id=" . $selectedServiceId);
}

$selectedDateObject = new DateTime($selectedDate);


// =====================================================
// CHECK AVAILABILITY (same helper booking.php uses, so
// the two pages can never disagree)
// =====================================================

$availability = getBookingAvailability($databaseConnection, $selectedDate, $serviceDurationMinutes);

$bookingStatus = $availability["status"];
$availableTimeSlots = $availability["slots"];


// =====================================================
// SHOP CLOSED THAT DAY
// =====================================================

if ($bookingStatus === "closed") {

    setAuthenticationMessage(
        "error",
        $availability["holiday_name"]
            ? "The shop is closed on that date (" . htmlspecialchars($availability["holiday_name"]) . ")."
            : "Booking hours are from 8:00 AM to 7:00 PM. Please choose another date."
    );

    redirectTo("booking.php?service_id=" . $selectedServiceId . "&appointment_date=" . urlencode($selectedDate));
}


// =====================================================
// NO EXACT SLOTS LEFT AT ALL -> SEND BACK TO THE
// RESERVATION OPTION ON booking.php
// =====================================================

if ($bookingStatus === "fully_booked" || $bookingStatus === "no_schedule") {

    setAuthenticationMessage(
        "warning",
        "There are no open exact time slots on this date anymore. You can still reserve your spot below for \xE2\x82\xB1100."
    );

    redirectTo("booking.php?service_id=" . $selectedServiceId . "&appointment_date=" . urlencode($selectedDate));
}


// =====================================================
// COMPARE THE CUSTOMER'S PREFERRED TIME AGAINST THE
// ACTUAL OPEN SLOTS
// =====================================================

$isExactMatch = isset($availableTimeSlots[$preferredTimeInput]);

$finalTimeValue = $isExactMatch
    ? $preferredTimeInput
    : findClosestAvailableTime($availableTimeSlots, $preferredTimeInput);

if ($finalTimeValue === null) {

    // Extremely unlikely (slots became empty between the two
    // checks above), but handle it gracefully anyway.

    setAuthenticationMessage(
        "warning",
        "That time is no longer available. You can reserve your spot below for \xE2\x82\xB1100 instead."
    );

    redirectTo("booking.php?service_id=" . $selectedServiceId . "&appointment_date=" . urlencode($selectedDate));
}

$finalTimeDisplay = $availableTimeSlots[$finalTimeValue];
$preferredTimeDisplay = date("h:i A", strtotime($preferredTimeInput));


// =====================================================
// WHICH BARBERS ARE ACTUALLY FREE AT THE FINAL TIME?
// (busy barbers will be shown but disabled/blocked)
// =====================================================

$barberAvailability = getBarberAvailabilityAtTime(
    $databaseConnection,
    $selectedDate,
    $finalTimeValue,
    $serviceDurationMinutes
);

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
            <a
                href="booking.php?service_id=<?php echo $selectedServiceId; ?>&appointment_date=<?php echo urlencode($selectedDate); ?>"
                class="booking-back-link"
            >
                <i class="bi bi-arrow-left"></i>
                Back to Booking
            </a>
        </div>
    </nav>

    <main class="booking-main-container">
        <div class="container" style="max-width:640px;">

            <div class="authentication-message-container">
                <?php displayAuthenticationMessage(); ?>
            </div>

            <div class="confirmation-card">

                <?php if ($isExactMatch): ?>

                    <div class="confirmation-icon"><i class="bi bi-check-circle-fill"></i></div>

                    <h1>Confirm Your Appointment</h1>
                    <p>
                        You're booking <?php echo htmlspecialchars($selectedService["service_name"]); ?>
                        on <?php echo $selectedDateObject->format("F d, Y"); ?>
                        at <strong><?php echo htmlspecialchars($finalTimeDisplay); ?></strong>.
                        A barber will be assigned automatically.
                    </p>

                <?php else: ?>

                    <div class="confirmation-icon confirmation-icon-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>

                    <h1>That Time Isn't Available</h1>
                    <p>
                        Sorry, <strong><?php echo htmlspecialchars($preferredTimeDisplay); ?></strong> is already taken
                        on <?php echo $selectedDateObject->format("F d, Y"); ?>.
                        The closest available time is:
                    </p>

                    <div class="confirm-time-highlight">
                        <i class="bi bi-clock"></i>
                        <?php echo htmlspecialchars($finalTimeDisplay); ?>
                    </div>

                    <p>Would you like to book this time instead?</p>

                <?php endif; ?>

                <div class="confirmation-details">

                    <div class="confirmation-row">
                        <span>Service</span>
                        <strong><?php echo htmlspecialchars($selectedService["service_name"]); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Date</span>
                        <strong><?php echo $selectedDateObject->format("F d, Y"); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Time</span>
                        <strong><?php echo htmlspecialchars($finalTimeDisplay); ?></strong>
                    </div>

                    <div class="confirmation-row">
                        <span>Price</span>
                        <strong>&#8369;<?php echo number_format((float) $selectedService["service_price"], 2); ?></strong>
                    </div>

                </div>

                <form method="POST" action="../auth/save_booking.php">

                    <input type="hidden" name="service_id" value="<?php echo $selectedServiceId; ?>">
                    <input type="hidden" name="appointment_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                    <input type="hidden" name="appointment_start_time" value="<?php echo htmlspecialchars($finalTimeValue); ?>">
                    <input type="hidden" name="booking_type" value="Auto Booking">

                    <?php if (!empty($barberAvailability)): ?>

                        <div class="confirm-barber-picker">

                            <span class="booking-card-label">CHOOSE YOUR BARBER</span>

                            <div class="barber-select-grid">

                                <label class="barber-select-option">
                                    <input type="radio" name="preferred_barber_id" value="0" checked>
                                    <span class="barber-select-card">
                                        <i class="bi bi-shuffle"></i>
                                        Any Available Barber
                                    </span>
                                </label>

                                <?php foreach ($barberAvailability as $barber): ?>

                                    <label class="barber-select-option <?php echo !$barber["is_available"] ? "is-disabled" : ""; ?>">

                                        <input
                                            type="radio"
                                            name="preferred_barber_id"
                                            value="<?php echo (int) $barber["barber_id"]; ?>"
                                            <?php echo !$barber["is_available"] ? "disabled" : ""; ?>
                                        >

                                        <span class="barber-select-card">
                                            <i class="bi bi-person-badge"></i>
                                            <?php echo htmlspecialchars($barber["barber_name"]); ?>

                                            <?php if ($barber["is_full"]): ?>
                                                <span class="barber-busy-badge">Full <?php echo $barber["slots_used"]; ?>/<?php echo $barber["slots_total"]; ?></span>
                                            <?php elseif (!$barber["is_available"]): ?>
                                                <span class="barber-busy-badge">Busy</span>
                                            <?php else: ?>
                                                <span class="barber-slots-badge"><?php echo $barber["slots_used"]; ?>/<?php echo $barber["slots_total"]; ?> slots</span>
                                            <?php endif; ?>
                                        </span>

                                    </label>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endif; ?>

                    <div class="confirmation-buttons">

                        <button type="submit" class="booking-auto-button">
                            <i class="bi bi-check-lg"></i>
                            <?php echo $isExactMatch ? "CONFIRM & BOOK" : "YES, BOOK AT " . htmlspecialchars($finalTimeDisplay); ?>
                        </button>

                        <a
                            href="booking.php?service_id=<?php echo $selectedServiceId; ?>&appointment_date=<?php echo urlencode($selectedDate); ?>"
                            class="booking-check-button"
                        >
                            <i class="bi bi-arrow-repeat"></i>
                            CHOOSE A DIFFERENT TIME
                        </a>

                    </div>

                </form>

            </div>

        </div>
    </main>

    <footer class="booking-footer">
        <p>&copy; <?php echo date("Y"); ?> BARBERSHOP.CO</p>
    </footer>

</div>

</body>
</html>
