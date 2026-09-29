<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Verify Email - BARBERSHOP.CO";

$token = trim($_GET["token"] ?? "");

$verificationOutcome = "invalid"; // invalid | already | success

if (!empty($token)) {

    $statement = mysqli_prepare($databaseConnection, "
        SELECT customer_id, email_verified_at
        FROM customers
        WHERE email_verification_token = ?
        LIMIT 1
    ");

    mysqli_stmt_bind_param($statement, "s", $token);
    mysqli_stmt_execute($statement);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if ($customer) {

        if ($customer["email_verified_at"]) {

            $verificationOutcome = "already";

        } else {

            $updateStatement = mysqli_prepare($databaseConnection, "
                UPDATE customers SET email_verified_at = NOW()
                WHERE customer_id = ?
            ");
            mysqli_stmt_bind_param($updateStatement, "i", $customer["customer_id"]);
            mysqli_stmt_execute($updateStatement);
            mysqli_stmt_close($updateStatement);

            $verificationOutcome = "success";
        }
    }
}

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
        <div class="container" style="max-width:560px;">

            <div class="confirmation-card">

                <?php if ($verificationOutcome === "success"): ?>

                    <div class="confirmation-icon"><i class="bi bi-envelope-check-fill"></i></div>
                    <h1>Email Verified!</h1>
                    <p>Your email address has been confirmed. You can now log in to your account.</p>

                <?php elseif ($verificationOutcome === "already"): ?>

                    <div class="confirmation-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <h1>Already Verified</h1>
                    <p>This email address was already verified. You can log in anytime.</p>

                <?php else: ?>

                    <div class="confirmation-icon confirmation-icon-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <h1>Invalid or Expired Link</h1>
                    <p>This verification link isn't valid. If you still need to verify your email, try logging in and requesting a new link.</p>

                <?php endif; ?>

                <div class="confirmation-buttons">
                    <a href="login.php" class="booking-auto-button">
                        <i class="bi bi-box-arrow-in-right"></i>
                        GO TO LOGIN
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
