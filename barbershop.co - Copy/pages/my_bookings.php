<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";
require_once __DIR__ . "/../includes/booking_helpers.php";

$pageTitle = "My Bookings - BARBERSHOP.CO";

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first.");
    redirectTo("login.php");
}

$customerId = (int) $_SESSION["customer_id"];

$baseWebsitePath = "../";

autoProgressAppointments($databaseConnection);


// =====================================================
// GET ALL APPOINTMENTS FOR THIS CUSTOMER
// =====================================================

$bookingsQuery = "
    SELECT
        a.appointment_id, a.appointment_code, a.appointment_date,
        a.appointment_start_time, a.requested_time, a.booking_type,
        a.appointment_status, a.status_updated_at,
        s.service_name, s.service_price, s.estimated_duration_minutes,
        b.barber_name,
        (
            SELECT p.payment_status FROM payments p
            WHERE p.appointment_id = a.appointment_id
            ORDER BY p.created_at DESC LIMIT 1
        ) AS latest_payment_status,
        (
            SELECT p.payment_purpose FROM payments p
            WHERE p.appointment_id = a.appointment_id
            ORDER BY p.created_at DESC LIMIT 1
        ) AS latest_payment_purpose
    FROM appointments a
    INNER JOIN services s ON s.service_id = a.service_id
    LEFT JOIN barbers b ON b.barber_id = a.barber_id
    WHERE a.customer_id = ?
    ORDER BY a.appointment_date DESC, a.created_at DESC
";

$bookingsStatement = mysqli_prepare($databaseConnection, $bookingsQuery);
mysqli_stmt_bind_param($bookingsStatement, "i", $customerId);
mysqli_stmt_execute($bookingsStatement);
$bookingsResult = mysqli_stmt_get_result($bookingsStatement);

$appointmentStatusBadgeClass = [
    "Pending" => "status-pending",
    "Confirmed" => "status-confirmed",
    "Waiting" => "status-confirmed",
    "Completed" => "status-completed",
    "Cancelled" => "status-cancelled",
    "No Show" => "status-cancelled",
];

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

<body data-auto-refresh-seconds="45">

<?php include __DIR__ . "/../includes/header.php"; ?>

<section class="simple-page-section">
    <div class="container">

        <div class="authentication-message-container">
            <?php displayAuthenticationMessage(); ?>
        </div>

        <div class="section-heading text-center">
            <p class="section-label">MY ACCOUNT</p>
            <h2>MY BOOKINGS</h2>
            <p>Track the status of your appointments and payments.</p>
        </div>


        <div class="bookings-list mt-4">

            <?php if (mysqli_num_rows($bookingsResult) === 0): ?>

                <div class="booking-no-times">
                    <div class="booking-no-times-icon"><i class="bi bi-calendar-x"></i></div>
                    <div>
                        <h4>No Bookings Yet</h4>
                        <p>You haven't booked an appointment yet.</p>
                        <a href="../index.php#services" class="booking-check-button mt-2 d-inline-flex">
                            <i class="bi bi-scissors"></i>
                            BOOK NOW
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <?php while ($booking = mysqli_fetch_assoc($bookingsResult)): ?>

                    <?php
                    $needsPayment =
                        $booking["latest_payment_status"] === null ||
                        $booking["latest_payment_status"] === "Rejected";

                    $statusClass = $appointmentStatusBadgeClass[$booking["appointment_status"]] ?? "status-pending";

                    $countdownTargetTimestamp = null;
                    $countdownLabel = "";
                    $countdownDoneLabel = "";

                    if ($booking["appointment_status"] === "Waiting") {

                        $countdownTargetTimestamp = strtotime($booking["status_updated_at"]) + ((int) $booking["estimated_duration_minutes"] * 60);
                        $countdownLabel = "You're being served — finishing in";
                        $countdownDoneLabel = "All done!";

                    } elseif ($booking["appointment_status"] === "Confirmed" && $booking["appointment_start_time"]) {

                        $countdownTargetTimestamp = strtotime($booking["appointment_date"] . " " . $booking["appointment_start_time"]);
                        $countdownLabel = "Your turn starts in";
                        $countdownDoneLabel = "Starting now";
                    }
                    ?>

                    <div class="booking-card-row">

                        <div class="booking-card-row-main">

                            <div class="booking-card-row-top">
                                <h4><?php echo htmlspecialchars($booking["service_name"]); ?></h4>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($booking["appointment_status"]); ?>
                                </span>
                            </div>

                            <p class="booking-card-row-code">
                                <?php echo htmlspecialchars($booking["appointment_code"]); ?>
                                &bull;
                                <?php echo htmlspecialchars($booking["booking_type"]); ?>
                            </p>

                            <?php if ($booking["appointment_status"] === "Cancelled"): ?>

                                <div class="booking-status-notice booking-status-notice-cancelled">
                                    <i class="bi bi-x-circle"></i>
                                    This booking was cancelled.
                                </div>

                            <?php elseif ($booking["appointment_status"] === "No Show"): ?>

                                <div class="booking-status-notice booking-status-notice-noshow">
                                    <i class="bi bi-exclamation-circle"></i>
                                    Your cooldown time is already done. Please book again, or visit our shop for a walk-in.
                                </div>

                            <?php elseif ($countdownTargetTimestamp !== null): ?>

                                <div class="booking-status-notice booking-status-notice-countdown">
                                    <i class="bi bi-stopwatch"></i>
                                    <?php echo $countdownLabel; ?>
                                    <span
                                        class="live-countdown"
                                        data-countdown-target="<?php echo $countdownTargetTimestamp; ?>"
                                        data-countdown-done="<?php echo htmlspecialchars($countdownDoneLabel); ?>"
                                    >--:--</span>
                                </div>

                            <?php endif; ?>

                            <div class="booking-card-row-details">

                                <span>
                                    <i class="bi bi-calendar3"></i>
                                    <?php echo date("F d, Y", strtotime($booking["appointment_date"])); ?>
                                </span>

                                <span>
                                    <i class="bi bi-clock"></i>
                                    <?php
                                    if ($booking["appointment_start_time"]) {
                                        echo date("h:i A", strtotime($booking["appointment_start_time"]));
                                    } elseif ($booking["requested_time"]) {
                                        echo date("h:i A", strtotime($booking["requested_time"])) . " (preferred)";
                                    } else {
                                        echo "To be assigned";
                                    }
                                    ?>
                                </span>

                                <span>
                                    <i class="bi bi-person"></i>
                                    <?php echo $booking["barber_name"] ? htmlspecialchars($booking["barber_name"]) : "To be assigned"; ?>
                                </span>

                                <span>
                                    <i class="bi bi-tag"></i>
                                    &#8369;<?php echo number_format((float) $booking["service_price"], 2); ?>
                                </span>

                            </div>

                            <?php if ($booking["latest_payment_status"]): ?>
                                <p class="booking-card-row-payment">
                                    Payment (<?php echo htmlspecialchars($booking["latest_payment_purpose"]); ?>):
                                    <strong><?php echo htmlspecialchars($booking["latest_payment_status"]); ?></strong>
                                </p>
                            <?php endif; ?>

                        </div>

                        <?php $cancellableStatuses = ["Pending", "Confirmed", "Waiting"]; ?>

                        <?php if ($needsPayment && !in_array($booking["appointment_status"], ["Cancelled", "No Show", "Completed"], true)): ?>
                            <div class="booking-card-row-action">

                                <a href="payment.php?appointment_id=<?php echo (int) $booking["appointment_id"]; ?>" class="booking-check-button">
                                    <i class="bi bi-credit-card"></i>
                                    PAY NOW
                                </a>

                                <?php if (in_array($booking["appointment_status"], $cancellableStatuses, true)): ?>
                                    <form
                                        method="POST"
                                        action="../auth/cancel_booking.php"
                                        onsubmit="return confirm('Are you sure you want to cancel this booking?');"
                                    >
                                        <input type="hidden" name="appointment_id" value="<?php echo (int) $booking["appointment_id"]; ?>">
                                        <button type="submit" class="booking-cancel-button">
                                            <i class="bi bi-x-circle"></i>
                                            CANCEL BOOKING
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>

                        <?php elseif (in_array($booking["appointment_status"], $cancellableStatuses, true)): ?>

                            <div class="booking-card-row-action">
                                <form
                                    method="POST"
                                    action="../auth/cancel_booking.php"
                                    onsubmit="return confirm('Are you sure you want to cancel this booking?');"
                                >
                                    <input type="hidden" name="appointment_id" value="<?php echo (int) $booking["appointment_id"]; ?>">
                                    <button type="submit" class="booking-cancel-button">
                                        <i class="bi bi-x-circle"></i>
                                        CANCEL BOOKING
                                    </button>
                                </form>
                            </div>

                        <?php endif; ?>

                    </div>

                <?php endwhile; ?>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
