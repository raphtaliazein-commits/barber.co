<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";

$pageTitle = "Payment - BARBERSHOP.CO";

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first.");
    redirectTo("login.php");
}

$customerId = (int) $_SESSION["customer_id"];


// =====================================================
// AGE ELIGIBILITY FOR ONLINE PAYMENT (GCash / PayMaya)
// =====================================================

$customerStatement = mysqli_prepare($databaseConnection, "SELECT date_of_birth FROM customers WHERE customer_id = ? LIMIT 1");
mysqli_stmt_bind_param($customerStatement, "i", $customerId);
mysqli_stmt_execute($customerStatement);
$customerDateOfBirth = mysqli_fetch_assoc(mysqli_stmt_get_result($customerStatement))["date_of_birth"] ?? null;
mysqli_stmt_close($customerStatement);

$onlinePaymentAllowed = isEligibleForOnlinePayment($customerDateOfBirth);

$ageEligibilityMessage = null;

if (!$onlinePaymentAllowed) {

    $ageEligibilityMessage = empty($customerDateOfBirth)
        ? "We don't have your date of birth on file yet. Online payment (GCash/PayMaya) requires age verification (18 and above) — please add it in your profile, or pay via Cash at the shop."
        : "Online payment (GCash/PayMaya) is only available for customers 18 years old and above. Please pay via Cash at the shop &mdash; your booking stays as is.";
}


// =====================================================
// FIND WHICH APPOINTMENT WE ARE PAYING FOR
// =====================================================

$appointmentId = (int) ($_GET["appointment_id"] ?? ($_SESSION["pending_payment_appointment_id"] ?? 0));

if ($appointmentId <= 0) {

    setAuthenticationMessage("error", "No appointment selected for payment.");
    redirectTo("my_bookings.php");
}


// =====================================================
// IDOR PROTECTION: the appointment MUST belong to the
// currently logged in customer.
// =====================================================

$appointmentQuery = "
    SELECT
        a.appointment_id, a.appointment_code, a.appointment_date,
        a.appointment_start_time, a.requested_time, a.booking_type,
        a.appointment_status,
        s.service_name, s.service_price,
        b.barber_name
    FROM appointments a
    INNER JOIN services s ON s.service_id = a.service_id
    LEFT JOIN barbers b ON b.barber_id = a.barber_id
    WHERE a.appointment_id = ? AND a.customer_id = ?
    LIMIT 1
";

$appointmentStatement = mysqli_prepare($databaseConnection, $appointmentQuery);
mysqli_stmt_bind_param($appointmentStatement, "ii", $appointmentId, $customerId);
mysqli_stmt_execute($appointmentStatement);
$appointmentResult = mysqli_stmt_get_result($appointmentStatement);
$appointment = mysqli_fetch_assoc($appointmentResult);
mysqli_stmt_close($appointmentStatement);

if (!$appointment) {

    setAuthenticationMessage("error", "That appointment could not be found.");
    redirectTo("my_bookings.php");
}


// =====================================================
// CHECK IF A PAYMENT ALREADY EXISTS FOR THIS PURPOSE
// =====================================================

$existingReservationFeeQuery = "
    SELECT payment_id, payment_status FROM payments
    WHERE appointment_id = ? AND payment_purpose = 'Reservation Fee'
    LIMIT 1
";

$feeStatement = mysqli_prepare($databaseConnection, $existingReservationFeeQuery);
mysqli_stmt_bind_param($feeStatement, "i", $appointmentId);
mysqli_stmt_execute($feeStatement);
$feeResult = mysqli_stmt_get_result($feeStatement);
$existingReservationFee = mysqli_fetch_assoc($feeResult);
mysqli_stmt_close($feeStatement);

$reservationFeeAlreadyHandled =
    $existingReservationFee &&
    in_array($existingReservationFee["payment_status"], ["Pending", "Verified"], true);


if ($appointment["booking_type"] === "Reservation" && !$reservationFeeAlreadyHandled) {

    $paymentPurpose = "Reservation Fee";
    $paymentAmount = RESERVATION_FEE_AMOUNT;

} else {

    $existingFullPaymentQuery = "
        SELECT payment_id, payment_status FROM payments
        WHERE appointment_id = ? AND payment_purpose = 'Full Payment'
        LIMIT 1
    ";

    $fullStatement = mysqli_prepare($databaseConnection, $existingFullPaymentQuery);
    mysqli_stmt_bind_param($fullStatement, "i", $appointmentId);
    mysqli_stmt_execute($fullStatement);
    $fullResult = mysqli_stmt_get_result($fullStatement);
    $existingFullPayment = mysqli_fetch_assoc($fullResult);
    mysqli_stmt_close($fullStatement);

    if ($existingFullPayment && in_array($existingFullPayment["payment_status"], ["Pending", "Verified"], true)) {

        setAuthenticationMessage("success", "This appointment has already been paid for.");
        redirectTo("my_bookings.php");
    }

    $paymentPurpose = "Full Payment";
    $paymentAmount = (float) $appointment["service_price"];
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
            <a href="my_bookings.php" class="booking-back-link">
                <i class="bi bi-arrow-left"></i>
                My Bookings
            </a>
        </div>
    </nav>

    <main class="booking-main-container">
        <div class="container" style="max-width:640px;">

            <div class="authentication-message-container">
                <?php displayAuthenticationMessage(); ?>
            </div>

            <div class="booking-header text-center">
                <span class="booking-label">SECURE PAYMENT</span>
                <h1>PAY FOR YOUR BOOKING</h1>
                <p>Choose how you'd like to pay for <?php echo htmlspecialchars($appointment["appointment_code"]); ?></p>
            </div>

            <div class="booking-service-card">
                <div class="booking-service-top">
                    <div>
                        <span class="booking-card-label">
                            <?php echo $paymentPurpose === "Reservation Fee" ? "RESERVATION FEE" : "AMOUNT TO PAY"; ?>
                        </span>
                        <h2>&#8369;<?php echo number_format($paymentAmount, 2); ?></h2>
                    </div>
                    <div class="booking-service-icon"><i class="bi bi-wallet2"></i></div>
                </div>

                <p class="booking-service-description">
                    <?php echo htmlspecialchars($appointment["service_name"]); ?>
                    &mdash;
                    <?php echo date("F d, Y", strtotime($appointment["appointment_date"])); ?>
                    <?php if ($appointment["appointment_start_time"]): ?>
                        at <?php echo date("h:i A", strtotime($appointment["appointment_start_time"])); ?>
                    <?php elseif ($appointment["requested_time"]): ?>
                        (preferred time: <?php echo date("h:i A", strtotime($appointment["requested_time"])); ?>)
                    <?php endif; ?>
                    <?php if ($appointment["barber_name"]): ?>
                        with <?php echo htmlspecialchars($appointment["barber_name"]); ?>
                    <?php endif; ?>
                </p>

                <?php if ($paymentPurpose === "Reservation Fee"): ?>
                    <p class="booking-service-description" style="color:#f0c66b;">
                        <i class="bi bi-info-circle"></i>
                        This fee secures your reservation. Our staff will assign your exact
                        time and barber once confirmed.
                    </p>
                <?php endif; ?>
            </div>


            <!-- PAYMENT METHOD FORM -->

            <div class="booking-selection-card">

                <div class="booking-section-title">
                    <div class="booking-section-icon"><i class="bi bi-credit-card"></i></div>
                    <div>
                        <span>PAYMENT METHOD</span>
                        <h3>How would you like to pay?</h3>
                    </div>
                </div>

                <form
                    method="POST"
                    action="../auth/save_payment.php"
                    enctype="multipart/form-data"
                    id="paymentForm"
                >

                    <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">

                    <?php if (!$onlinePaymentAllowed): ?>

                        <div class="payment-age-notice">
                            <i class="bi bi-shield-exclamation"></i>
                            <span>
                                <?php echo $ageEligibilityMessage; ?>
                                <?php if (empty($customerDateOfBirth)): ?>
                                    <a href="profile.php">Update your profile &rarr;</a>
                                <?php endif; ?>
                            </span>
                        </div>

                    <?php endif; ?>

                    <div class="payment-method-options">

                        <label class="payment-method-option <?php echo !$onlinePaymentAllowed ? "is-disabled" : ""; ?>">
                            <input
                                type="radio"
                                name="payment_method"
                                value="GCash"
                                onchange="togglePaymentProofFields(this)"
                                <?php echo !$onlinePaymentAllowed ? "disabled" : "required"; ?>
                            >
                            <span class="payment-method-card">
                                <i class="bi bi-phone"></i>
                                GCash
                                <?php if (!$onlinePaymentAllowed): ?><span class="barber-busy-badge">18+ only</span><?php endif; ?>
                            </span>
                        </label>

                        <label class="payment-method-option <?php echo !$onlinePaymentAllowed ? "is-disabled" : ""; ?>">
                            <input
                                type="radio"
                                name="payment_method"
                                value="PayMaya"
                                onchange="togglePaymentProofFields(this)"
                                <?php echo !$onlinePaymentAllowed ? "disabled" : ""; ?>
                            >
                            <span class="payment-method-card">
                                <i class="bi bi-credit-card-2-front"></i>
                                PayMaya
                                <?php if (!$onlinePaymentAllowed): ?><span class="barber-busy-badge">18+ only</span><?php endif; ?>
                            </span>
                        </label>

                        <label class="payment-method-option">
                            <input
                                type="radio"
                                name="payment_method"
                                value="Cash"
                                onchange="togglePaymentProofFields(this)"
                                <?php echo !$onlinePaymentAllowed ? "checked required" : ""; ?>
                            >
                            <span class="payment-method-card">
                                <i class="bi bi-cash-coin"></i>
                                Cash at Shop
                            </span>
                        </label>

                    </div>


                    <div id="onlinePaymentDetails" style="display:none; margin-top:22px;">

                        <div class="payment-instructions">
                            <p><strong>Send &#8369;<?php echo number_format($paymentAmount, 2); ?> to:</strong></p>
                            <p id="paymentAccountDetails">GCash / PayMaya Number: 0900-000-0000 (BARBERSHOP.CO)</p>
                        </div>

                        <div class="form-group-custom" style="margin-top:16px;">
                            <label for="paymentReferenceNumber">REFERENCE NUMBER</label>
                            <div class="input-wrapper">
                                <i class="bi bi-hash"></i>
                                <input
                                    type="text"
                                    id="paymentReferenceNumber"
                                    name="payment_reference_number"
                                    placeholder="Enter the reference number from your receipt"
                                >
                            </div>
                        </div>

                        <div class="form-group-custom" style="margin-top:16px;">
                            <label for="paymentProof">SCREENSHOT OF PAYMENT (PROOF)</label>
                            <input
                                type="file"
                                id="paymentProof"
                                name="payment_proof"
                                accept="image/png, image/jpeg, image/webp"
                                class="payment-file-input"
                            >
                            <small style="color:#888888; font-size:11px;">JPG, PNG, or WEBP. Max 5MB.</small>
                        </div>

                    </div>


                    <div id="cashPaymentNote" style="display:<?php echo !$onlinePaymentAllowed ? "block" : "none"; ?>; margin-top:22px;">
                        <div class="payment-instructions">
                            <p>
                                <i class="bi bi-info-circle"></i>
                                No need to upload anything. Please pay
                                &#8369;<?php echo number_format($paymentAmount, 2); ?> in cash when you arrive at the shop.
                            </p>
                        </div>
                    </div>


                    <button type="submit" class="login-submit-button" style="margin-top:24px;">
                        CONFIRM PAYMENT
                        <i class="bi bi-arrow-right"></i>
                    </button>

                </form>

            </div>

        </div>
    </main>

    <footer class="booking-footer">
        <p>&copy; <?php echo date("Y"); ?> BARBERSHOP.CO</p>
    </footer>

</div>


<script>

function togglePaymentProofFields(radioElement) {

    const onlineSection = document.getElementById("onlinePaymentDetails");
    const cashSection = document.getElementById("cashPaymentNote");
    const referenceInput = document.getElementById("paymentReferenceNumber");
    const proofInput = document.getElementById("paymentProof");
    const accountDetails = document.getElementById("paymentAccountDetails");

    if (radioElement.value === "Cash") {

        onlineSection.style.display = "none";
        cashSection.style.display = "block";

        referenceInput.removeAttribute("required");
        proofInput.removeAttribute("required");

    } else {

        onlineSection.style.display = "block";
        cashSection.style.display = "none";

        referenceInput.setAttribute("required", "required");
        proofInput.setAttribute("required", "required");

        accountDetails.textContent =
            radioElement.value === "GCash"
                ? "GCash Number: 0900-000-0000 (BARBERSHOP.CO)"
                : "PayMaya Number: 0900-000-0000 (BARBERSHOP.CO)";
    }
}

</script>

</body>
</html>
