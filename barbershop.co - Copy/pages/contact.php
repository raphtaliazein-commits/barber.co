<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";

$pageTitle = "Contact Us - BARBERSHOP.CO";
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

<body>

<?php include __DIR__ . "/../includes/header.php"; ?>

<section class="simple-page-hero">
    <div class="container text-center">
        <p class="section-label">GET IN TOUCH</p>
        <h1>CONTACT US</h1>
        <p class="simple-page-hero-text">
            Have a question about a booking, a service, or just want to say hi?
            Send us a message below.
        </p>
    </div>
</section>

<section class="simple-page-section">
    <div class="container">

        <div class="authentication-message-container">
            <?php displayAuthenticationMessage(); ?>
        </div>

        <div class="row g-4">

            <div class="col-lg-5">

                <div class="contact-info-card">

                    <h4>Visit Us</h4>

                    <div class="contact-info-item">
                        <i class="bi bi-geo-alt"></i>
                        <span>Carmona, Cavite, Philippines</span>
                    </div>

                    <div class="contact-info-item">
                        <i class="bi bi-telephone"></i>
                        <span>+63 900 000 0000</span>
                    </div>

                    <div class="contact-info-item">
                        <i class="bi bi-envelope"></i>
                        <span>info@barbershop.co</span>
                    </div>

                    <div class="contact-info-item">
                        <i class="bi bi-clock"></i>
                        <span>Open Daily, 8:00 AM &ndash; 7:00 PM</span>
                    </div>

                </div>

            </div>

            <div class="col-lg-7">

                <div class="booking-selection-card">

                    <form method="POST" action="../auth/save_contact_message.php">

                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label for="fullName">FULL NAME</label>
                                    <div class="input-wrapper">
                                        <i class="bi bi-person"></i>
                                        <input type="text" id="fullName" name="full_name" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label for="emailAddress">EMAIL ADDRESS</label>
                                    <div class="input-wrapper">
                                        <i class="bi bi-envelope"></i>
                                        <input type="email" id="emailAddress" name="email_address" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label for="phoneNumber">PHONE NUMBER (OPTIONAL)</label>
                                    <div class="input-wrapper">
                                        <i class="bi bi-telephone"></i>
                                        <input type="text" id="phoneNumber" name="phone_number">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-custom">
                                    <label for="subject">SUBJECT</label>
                                    <div class="input-wrapper">
                                        <i class="bi bi-chat-dots"></i>
                                        <input type="text" id="subject" name="subject" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-group-custom">
                                    <label for="message">MESSAGE</label>
                                    <textarea id="message" name="message" rows="5" required
                                        style="width:100%; padding:14px; background:#101010; color:#fff; border:1px solid rgba(255,255,255,0.12); box-sizing:border-box;"
                                    ></textarea>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="authentication-submit-button">
                                    SEND MESSAGE
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
