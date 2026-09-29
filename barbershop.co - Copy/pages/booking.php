<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";
require_once __DIR__ . "/../includes/booking_helpers.php";

$pageTitle = "Book an Appointment - BARBERSHOP.CO";


// =====================================================
// CHECK CUSTOMER LOGIN
// =====================================================

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage(
        "error",
        "Please login first before booking an appointment."
    );

    redirectTo("login.php");
}

$customerId = (int) $_SESSION["customer_id"];


// =====================================================
// ONE ACTIVE BOOKING AT A TIME
// =====================================================

$activeAppointment = getCustomerActiveAppointment($databaseConnection, $customerId);

if ($activeAppointment) {

    setAuthenticationMessage(
        "warning",
        "You already have an active booking (" . htmlspecialchars($activeAppointment["appointment_code"]) .
        " &mdash; " . htmlspecialchars($activeAppointment["appointment_status"]) .
        "). You can book again once it's completed or cancelled."
    );

    redirectTo("my_bookings.php");
}


// =====================================================
// GET SELECTED SERVICE
// =====================================================

$selectedServiceId = isset($_GET["service_id"]) ? (int) $_GET["service_id"] : 0;

$selectedService = null;

if ($selectedServiceId > 0) {

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
}

if (!$selectedService) {

    setAuthenticationMessage("error", "The selected service is not available.");
    redirectTo("../index.php#services");
}

$serviceDurationMinutes = (int) $selectedService["estimated_duration_minutes"];


// =====================================================
// DATE SETUP
// =====================================================

$currentDate = date("Y-m-d");

$selectedDate = $_GET["appointment_date"] ?? $currentDate;

if ($selectedDate < $currentDate) {
    $selectedDate = $currentDate;
}

$selectedDateObject = new DateTime($selectedDate);


// =====================================================
// GET AVAILABILITY FOR THIS DATE (shared helper, also
// used by confirm_booking.php so both pages always agree)
// =====================================================

$availability = getBookingAvailability($databaseConnection, $selectedDate, $serviceDurationMinutes);

$bookingStatus = $availability["status"];
$isHoliday = ($bookingStatus === "closed" && $availability["holiday_name"] !== null);
$holidayName = $availability["holiday_name"] ?? "";
$availableTimeSlots = $availability["slots"];

$earliestTimeValue = !empty($availableTimeSlots) ? array_key_first($availableTimeSlots) : null;
$earliestTimeDisplay = $earliestTimeValue ? $availableTimeSlots[$earliestTimeValue] : null;


// =====================================================
// LIST OF BARBERS (for the optional "preferred barber"
// dropdown on the Reservation form)
// =====================================================

$allBarbersQuery = "SELECT barber_id, barber_name FROM barbers WHERE barber_status != 'Offline' ORDER BY barber_name ASC";
$allBarbersResult = mysqli_query($databaseConnection, $allBarbersQuery);
$allBarbersList = mysqli_fetch_all($allBarbersResult, MYSQLI_ASSOC);

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

            <a href="../index.php" class="booking-back-link">
                <i class="bi bi-arrow-left"></i>
                Back to Home
            </a>

        </div>
    </nav>


    <main class="booking-main-container">
        <div class="container">

            <div class="authentication-message-container">
                <?php displayAuthenticationMessage(); ?>
            </div>

            <div class="booking-header text-center">
                <span class="booking-label">BOOK YOUR APPOINTMENT</span>
                <h1>SELECT YOUR SCHEDULE</h1>
                <p>Choose your preferred date and time.</p>
            </div>


            <!-- SERVICE INFORMATION -->

            <div class="booking-service-card">

                <div class="booking-service-top">
                    <div>
                        <span class="booking-card-label">SELECTED SERVICE</span>
                        <h2><?php echo htmlspecialchars($selectedService["service_name"]); ?></h2>
                    </div>
                    <div class="booking-service-icon"><i class="bi bi-scissors"></i></div>
                </div>

                <p class="booking-service-description">
                    <?php echo htmlspecialchars($selectedService["service_description"] ?? ""); ?>
                </p>

                <div class="booking-service-details">

                    <div class="booking-price">
                        <i class="bi bi-tag"></i>
                        &#8369;<?php echo number_format((float) $selectedService["service_price"], 2); ?>
                    </div>

                    <div class="booking-duration">
                        <i class="bi bi-clock"></i>
                        <?php echo $serviceDurationMinutes; ?> minutes
                    </div>

                </div>

            </div>


            <!-- DATE SELECTION -->

            <div class="booking-selection-card">

                <div class="booking-section-title">
                    <div class="booking-section-icon"><i class="bi bi-calendar3"></i></div>
                    <div>
                        <span>STEP 1</span>
                        <h3>Choose Your Date</h3>
                    </div>
                </div>

                <form method="GET" action="booking.php">

                    <input type="hidden" name="service_id" value="<?php echo $selectedServiceId; ?>">

                    <div class="booking-date-row">

                        <div class="booking-date-field">

                            <label for="appointmentDate">APPOINTMENT DATE</label>

                            <div class="booking-input-wrapper">
                                <i class="bi bi-calendar-event"></i>
                                <input
                                    type="date"
                                    id="appointmentDate"
                                    name="appointment_date"
                                    value="<?php echo htmlspecialchars($selectedDate); ?>"
                                    min="<?php echo $currentDate; ?>"
                                    required
                                >
                            </div>

                        </div>

                        <button type="submit" class="booking-check-button">
                            <i class="bi bi-search"></i>
                            CHECK THIS DATE
                        </button>

                    </div>

                </form>

            </div>


            <!-- AVAILABLE TIMES -->

            <div class="booking-available-section">

                <div class="booking-available-heading">
                    <div>
                        <span class="booking-label">STEP 2</span>
                        <h2><?php echo $selectedDateObject->format("F d, Y"); ?></h2>
                    </div>
                    <div class="booking-calendar-icon"><i class="bi bi-calendar-check"></i></div>
                </div>


                <?php if ($bookingStatus === "available"): ?>

                    <!-- AUTO-ASSIGN EARLIEST AVAILABLE -->

                    <div class="booking-auto-section">

                        <div class="booking-auto-label">
                            <i class="bi bi-lightning-charge-fill"></i>
                            FASTEST OPTION
                        </div>

                        <h3>Let Us Pick Your Barber</h3>

                        <p>
                            We'll automatically assign you to the next available
                            barber for this date &mdash; no need to think about the time.
                        </p>

                        <?php if ($earliestTimeDisplay): ?>
                            <div class="booking-auto-earliest">
                                <i class="bi bi-clock"></i>
                                Earliest available time:
                                <strong><?php echo htmlspecialchars($earliestTimeDisplay); ?></strong>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="../auth/save_booking.php">
                            <input type="hidden" name="service_id" value="<?php echo $selectedServiceId; ?>">
                            <input type="hidden" name="appointment_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                            <input type="hidden" name="appointment_start_time" value="<?php echo htmlspecialchars($earliestTimeValue); ?>">
                            <input type="hidden" name="booking_type" value="Auto Booking">

                            <button type="submit" class="booking-auto-button">
                                <i class="bi bi-magic"></i>
                                AUTO-BOOK EARLIEST SLOT
                            </button>
                        </form>

                    </div>


                    <!-- OR TYPE A PREFERRED TIME -->

                    <div class="booking-specific-section">

                        <div class="booking-specific-heading">
                            <span class="booking-label">OR CHOOSE YOUR OWN TIME</span>
                            <h3>Type Your Preferred Time</h3>
                            <p>
                                Enter any time within business hours. If that exact
                                time is already taken, we'll suggest the closest
                                available time and ask you to confirm before booking.
                            </p>
                        </div>

                        <form method="POST" action="confirm_booking.php" class="booking-preferred-time-form">

                            <input type="hidden" name="service_id" value="<?php echo $selectedServiceId; ?>">
                            <input type="hidden" name="appointment_date" value="<?php echo htmlspecialchars($selectedDate); ?>">

                            <div class="booking-preferred-time-row">

                                <div class="booking-input-wrapper">
                                    <i class="bi bi-clock"></i>
                                    <input
                                        type="time"
                                        name="preferred_time"
                                        min="08:00"
                                        max="19:00"
                                        step="60"
                                        required
                                    >
                                </div>

                                <button type="submit" class="booking-check-button">
                                    <i class="bi bi-arrow-right-circle"></i>
                                    CHECK THIS TIME
                                </button>

                            </div>

                        </form>

                    </div>


                <?php elseif ($bookingStatus === "closed"): ?>

                    <div class="booking-no-times">
                        <div class="booking-no-times-icon"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <h4><?php echo $isHoliday ? "The Shop Is Closed That Day" : "Booking Is Closed for Today"; ?></h4>
                            <p>
                                <?php if ($isHoliday): ?>
                                    We are closed on <?php echo htmlspecialchars($selectedDateObject->format("F d, Y")); ?>
                                    (<?php echo htmlspecialchars($holidayName); ?>). Please choose another date.
                                <?php else: ?>
                                    Booking hours are from <strong>8:00 AM to 7:00 PM</strong>. Please choose another date.
                                <?php endif; ?>
                            </p>

                            <a
                                href="booking.php?service_id=<?php echo $selectedServiceId; ?>&appointment_date=<?php echo date('Y-m-d', strtotime($selectedDate . ' +1 day')); ?>"
                                class="booking-check-button"
                            >
                                <i class="bi bi-calendar-plus"></i>
                                CHECK NEXT DAY
                            </a>
                        </div>
                    </div>


                <?php elseif ($bookingStatus === "fully_booked" || $bookingStatus === "no_schedule"): ?>

                    <!-- NO EXACT SLOT LEFT -> OFFER A RESERVATION INSTEAD -->

                    <div class="booking-auto-section">

                        <div class="booking-auto-label">
                            <i class="bi bi-bookmark-star-fill"></i>
                            RESERVE YOUR SPOT
                        </div>

                        <h3>All Exact Slots Are Taken</h3>

                        <p>
                            Our barbers are fully booked for this date, but you can still
                            reserve a spot with a &#8369;100 reservation fee. We will confirm
                            your exact time and assigned barber as soon as one opens up.
                        </p>

                        <form method="POST" action="../auth/save_booking.php" class="mt-3">

                            <input type="hidden" name="service_id" value="<?php echo $selectedServiceId; ?>">
                            <input type="hidden" name="appointment_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                            <input type="hidden" name="booking_type" value="Reservation">

                            <div class="row g-3 mb-3">

                                <div class="col-sm-6">
                                    <label for="preferredTime" style="display:block; margin-bottom:8px; font-size:11px; font-weight:800; letter-spacing:1px; color:#dddddd;">
                                        PREFERRED TIME (OPTIONAL)
                                    </label>

                                    <div class="booking-input-wrapper">
                                        <i class="bi bi-clock"></i>
                                        <input type="time" name="appointment_start_time" id="preferredTime" min="08:00" max="19:00">
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <label for="preferredBarberSelect" style="display:block; margin-bottom:8px; font-size:11px; font-weight:800; letter-spacing:1px; color:#dddddd;">
                                        PREFERRED BARBER (OPTIONAL)
                                    </label>

                                    <div class="booking-input-wrapper">
                                        <i class="bi bi-person-badge"></i>
                                        <select name="preferred_barber_id" id="preferredBarberSelect">
                                            <option value="0">No preference</option>
                                            <?php foreach ($allBarbersList as $barber): ?>
                                                <option value="<?php echo (int) $barber["barber_id"]; ?>">
                                                    <?php echo htmlspecialchars($barber["barber_name"]); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <button type="submit" class="booking-auto-button">
                                <i class="bi bi-bookmark-check"></i>
                                RESERVE FOR &#8369;100
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>


            <!-- INFO BOX -->

            <div class="booking-information-box">
                <div class="booking-information-icon"><i class="bi bi-info-circle"></i></div>
                <div>
                    <h4>Good to Know</h4>
                    <p>Here's what to expect once you book:</p>
                    <div class="booking-information-items">
                        <span><i class="bi bi-check-circle"></i> Shop hours are 8:00 AM &ndash; 7:00 PM daily.</span>
                        <span><i class="bi bi-check-circle"></i> If your typed time is taken, we'll suggest the closest one and ask you to confirm.</span>
                        <span><i class="bi bi-check-circle"></i> Reservations require a &#8369;100 fee to confirm.</span>
                        <span><i class="bi bi-check-circle"></i> You can pay via GCash, PayMaya, or Cash at the shop.</span>
                        <span><i class="bi bi-check-circle"></i> Track your booking anytime under "My Bookings".</span>
                    </div>
                </div>
            </div>

        </div>
    </main>


    <footer class="booking-footer">
        <p>
            &copy; <?php echo date("Y"); ?> BARBERSHOP.CO
            <span>&bull;</span>
            Your Style. Your Schedule. Your Choice.
        </p>
    </footer>

</div>

</body>

</html>
