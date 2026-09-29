<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";

$pageTitle = "Request a Refund - BARBERSHOP.CO";

if (!isset($_SESSION["customer_id"])) {

    setAuthenticationMessage("error", "Please login first.");
    redirectTo("login.php");
}

$customerId = (int) $_SESSION["customer_id"];
$baseWebsitePath = "../";

$appointmentId = (int) ($_GET["appointment_id"] ?? 0);


// =====================================================
// IDOR PROTECTION: only allow this for the customer's own
// cancelled appointment.
// =====================================================

$appointmentQuery = "
    SELECT a.appointment_id, a.appointment_code, a.appointment_status,
           s.service_name, s.service_price
    FROM appointments a
    INNER JOIN services s ON s.service_id = a.service_id
    WHERE a.appointment_id = ? AND a.customer_id = ?
    LIMIT 1
";

$statement = mysqli_prepare($databaseConnection, $appointmentQuery);
mysqli_stmt_bind_param($statement, "ii", $appointmentId, $customerId);
mysqli_stmt_execute($statement);
$appointment = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

if (!$appointment || $appointment["appointment_status"] !== "Cancelled") {

    setAuthenticationMessage("error", "That booking isn't eligible for a refund request.");
    redirectTo("my_bookings.php");
}


// =====================================================
// PREFILL FROM THE CUSTOMER'S OWN PROFILE
// =====================================================

$customerStatement = mysqli_prepare($databaseConnection, "SELECT full_name, email_address, phone_number FROM customers WHERE customer_id = ? LIMIT 1");
mysqli_stmt_bind_param($customerStatement, "i", $customerId);
mysqli_stmt_execute($customerStatement);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($customerStatement));
mysqli_stmt_close($customerStatement);

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
                <span class="booking-label">CANCELLED &amp; ALREADY PAID</span>
                <h1>REQUEST A REFUND</h1>
                <p>
                    Your booking (<?php echo htmlspecialchars($appointment["appointment_code"]); ?>) for
                    <?php echo htmlspecialchars($appointment["service_name"]); ?> was cancelled.
                    Since you already paid, let us know below and our staff will process your refund.
                </p>
            </div>

            <div class="booking-selection-card">

                <div class="booking-section-title">
                    <div class="booking-section-icon"><i class="bi bi-cash-coin"></i></div>
                    <div>
                        <span>REFUND REQUEST</span>
                        <h3>Tell Us About Your Payment</h3>
                    </div>
                </div>

                <form method="POST" action="../auth/save_refund_request.php" enctype="multipart/form-data">

                    <input type="hidden" name="appointment_id" value="<?php echo $appointmentId; ?>">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label for="fullName">FULL NAME</label>
                                <div class="input-wrapper">
                                    <i class="bi bi-person"></i>
                                    <input type="text" id="fullName" name="full_name" value="<?php echo htmlspecialchars($customer["full_name"]); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label for="emailAddress">EMAIL ADDRESS</label>
                                <div class="input-wrapper">
                                    <i class="bi bi-envelope"></i>
                                    <input type="email" id="emailAddress" name="email_address" value="<?php echo htmlspecialchars($customer["email_address"] ?? ""); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label for="contactNumber">CONTACT NUMBER</label>
                                <div class="input-wrapper">
                                    <i class="bi bi-telephone"></i>
                                    <input type="text" id="contactNumber" name="contact_number" value="<?php echo htmlspecialchars($customer["phone_number"]); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label for="paymentProof">PROOF OF PAYMENT (OPTIONAL)</label>
                                <input type="file" id="paymentProof" name="payment_proof" accept="image/png, image/jpeg, image/webp" class="payment-file-input">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group-custom">
                                <label for="message">MESSAGE</label>
                                <textarea id="message" name="message" rows="4"
                                    placeholder="Let us know anything else about your refund request..."
                                    style="width:100%; padding:14px; background:#101010; color:#fff; border:1px solid rgba(255,255,255,0.12); box-sizing:border-box;"
                                ></textarea>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-3 flex-wrap">
                            <button type="submit" class="booking-auto-button">
                                <i class="bi bi-send"></i>
                                SUBMIT REFUND REQUEST
                            </button>

                            <a href="my_bookings.php" class="booking-check-button">
                                SKIP FOR NOW
                            </a>
                        </div>

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
