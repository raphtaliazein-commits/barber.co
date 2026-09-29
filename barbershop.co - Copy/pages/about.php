<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../auth/authentication_message.php";

$pageTitle = "About Us - BARBERSHOP.CO";
$baseWebsitePath = "../";

$barberQuery = "
    SELECT barber_name, barber_description, barber_status
    FROM barbers ORDER BY barber_id ASC
";

$barberResult = mysqli_query($databaseConnection, $barberQuery);

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
        <p class="section-label">ABOUT US</p>
        <h1>THE BARBER CO.</h1>
        <p class="simple-page-hero-text">
            A modern barbershop rooted in classic craftsmanship,
            located in the heart of Carmona, Cavite.
        </p>
    </div>
</section>

<section class="simple-page-section">
    <div class="container">

        <div class="row g-4 align-items-center mb-5">

            <div class="col-lg-6">
                <p class="section-label">OUR STORY</p>
                <h2 style="margin-bottom:18px;">CRAFTING SHARP LOOKS SINCE DAY ONE</h2>
                <p style="color:var(--light-text-color); line-height:1.8;">
                    BARBERSHOP.CO started with one simple goal: to bring a premium,
                    no-hassle grooming experience to Carmona. We combine traditional
                    barbering skills with a modern online booking system so you can
                    skip the long wait and walk straight to the chair.
                </p>
                <p style="color:var(--light-text-color); line-height:1.8;">
                    Every cut is handled by a trained barber who takes pride in
                    precision, cleanliness, and making sure you leave looking
                    (and feeling) your absolute best.
                </p>
            </div>

            <div class="col-lg-6">
                <img src="../styles/images/dark-wood.jpg" alt="Barbershop interior" class="about-image">
            </div>

        </div>


        <div class="section-heading text-center">
            <p class="section-label">MEET THE TEAM</p>
            <h2>OUR BARBERS</h2>
        </div>

        <div class="row g-4 mt-4">

            <?php while ($barber = mysqli_fetch_assoc($barberResult)): ?>

                <div class="col-md-4">
                    <div class="barber-card">
                        <div class="barber-card-icon"><i class="bi bi-person-badge"></i></div>
                        <h4><?php echo htmlspecialchars($barber["barber_name"]); ?></h4>
                        <p><?php echo htmlspecialchars($barber["barber_description"] ?? ""); ?></p>
                        <span class="status-badge <?php echo $barber["barber_status"] === "Available" ? "status-confirmed" : "status-cancelled"; ?>">
                            <?php echo htmlspecialchars($barber["barber_status"]); ?>
                        </span>
                    </div>
                </div>

            <?php endwhile; ?>

        </div>

    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
