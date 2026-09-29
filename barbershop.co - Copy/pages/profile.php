<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";

$pageTitle = "My Profile - BARBERSHOP.CO";

if (!isset($_SESSION["customer_id"])) {
    setAuthenticationMessage("error", "Please login first.");
    redirectTo("login.php");
}

$customerId = (int) $_SESSION["customer_id"];
$baseWebsitePath = "../";

$customerQuery = "
    SELECT full_name, phone_number, email_address, profile_image, date_of_birth, created_at
    FROM customers WHERE customer_id = ? LIMIT 1
";

$customerStatement = mysqli_prepare($databaseConnection, $customerQuery);
mysqli_stmt_bind_param($customerStatement, "i", $customerId);
mysqli_stmt_execute($customerStatement);
$customerResult = mysqli_stmt_get_result($customerStatement);
$customer = mysqli_fetch_assoc($customerResult);
mysqli_stmt_close($customerStatement);

$profileImageSource = $customer["profile_image"]
    ? "../uploads/profile_images/" . $customer["profile_image"]
    : "../styles/images/logo_for_about.jpg";

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

<body>

<?php include __DIR__ . "/../includes/header.php"; ?>

<section class="simple-page-section">
    <div class="container" style="max-width:760px;">

        <div class="authentication-message-container">
            <?php displayAuthenticationMessage(); ?>
        </div>

        <div class="section-heading text-center">
            <p class="section-label">MY ACCOUNT</p>
            <h2>MY PROFILE</h2>
            <p>Update your personal information and password.</p>
        </div>


        <div class="profile-picture-card mt-4">
            <img src="<?php echo htmlspecialchars($profileImageSource); ?>" alt="Profile picture" class="profile-picture-large">
            <div>
                <h4><?php echo htmlspecialchars($customer["full_name"]); ?></h4>
                <p>Member since <?php echo date("F Y", strtotime($customer["created_at"])); ?></p>
            </div>
        </div>


        <!-- PROFILE INFORMATION FORM -->

        <div class="booking-selection-card mt-4">

            <div class="booking-section-title">
                <div class="booking-section-icon"><i class="bi bi-person"></i></div>
                <div><span>ACCOUNT</span><h3>Profile Information</h3></div>
            </div>

            <form method="POST" action="../auth/update_profile.php" enctype="multipart/form-data">

                <input type="hidden" name="form_action" value="update_information">

                <div class="form-group-custom mb-3">
                    <label for="fullName">FULL NAME</label>
                    <div class="input-wrapper">
                        <i class="bi bi-person"></i>
                        <input type="text" id="fullName" name="full_name" value="<?php echo htmlspecialchars($customer["full_name"]); ?>" required>
                    </div>
                </div>

                <div class="form-group-custom mb-3">
                    <label>PHONE NUMBER</label>
                    <div class="input-wrapper">
                        <i class="bi bi-telephone"></i>
                        <input type="text" value="<?php echo htmlspecialchars($customer["phone_number"]); ?>" disabled>
                    </div>
                    <small style="color:#777; font-size:11px;">Phone number cannot be changed.</small>
                </div>

                <div class="form-group-custom mb-3">
                    <label for="emailAddress">EMAIL ADDRESS</label>
                    <div class="input-wrapper">
                        <i class="bi bi-envelope"></i>
                        <input type="email" id="emailAddress" name="email_address" value="<?php echo htmlspecialchars($customer["email_address"] ?? ""); ?>" required>
                    </div>
                </div>

                <div class="form-group-custom mb-3">
                    <label for="dateOfBirth">DATE OF BIRTH</label>
                    <div class="input-wrapper">
                        <i class="bi bi-calendar-heart"></i>
                        <input
                            type="date"
                            id="dateOfBirth"
                            name="date_of_birth"
                            value="<?php echo htmlspecialchars($customer["date_of_birth"] ?? ""); ?>"
                            max="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                    </div>
                    <small style="color:#888; font-size:11px;">
                        Required for online payment (GCash/PayMaya) eligibility &mdash; must be 18 or above.
                    </small>
                </div>

                <div class="form-group-custom mb-3">
                    <label for="profileImage">PROFILE PICTURE (OPTIONAL)</label>
                    <input type="file" id="profileImage" name="profile_image" accept="image/png, image/jpeg, image/webp" class="payment-file-input">
                </div>

                <button type="submit" class="authentication-submit-button">
                    SAVE CHANGES
                    <i class="bi bi-check-lg"></i>
                </button>

            </form>

        </div>


        <!-- CHANGE PASSWORD FORM -->

        <div class="booking-selection-card mt-4">

            <div class="booking-section-title">
                <div class="booking-section-icon"><i class="bi bi-lock"></i></div>
                <div><span>SECURITY</span><h3>Change Password</h3></div>
            </div>

            <form method="POST" action="../auth/update_profile.php">

                <input type="hidden" name="form_action" value="change_password">

                <div class="form-group-custom mb-3">
                    <label for="currentPassword">CURRENT PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="currentPassword" name="current_password" required>
                    </div>
                </div>

                <div class="form-group-custom mb-3">
                    <label for="newPassword">NEW PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" id="newPassword" name="new_password" minlength="8" required>
                    </div>
                </div>

                <div class="form-group-custom mb-3">
                    <label for="confirmNewPassword">CONFIRM NEW PASSWORD</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" id="confirmNewPassword" name="confirm_new_password" minlength="8" required>
                    </div>
                </div>

                <button type="submit" class="authentication-submit-button">
                    UPDATE PASSWORD
                    <i class="bi bi-shield-check"></i>
                </button>

            </form>

        </div>

    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
