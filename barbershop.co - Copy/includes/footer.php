<?php
$baseWebsitePath = $baseWebsitePath ?? "";
?>

<!-- =========================================
     FOOTER
     ========================================= -->

<footer class="main-footer">

    <div class="container">

        <div class="row gy-4">

            <div class="col-lg-5">

                <div class="footer-brand">

                    <div class="footer-logo">

                        <span class="brand-icon">
                            <i class="bi bi-scissors"></i>
                        </span>

                        BARBERSHOP<span>.CO</span>

                    </div>

                    <p>
                        Your modern online barbershop appointment
                        and scheduling platform.
                    </p>

                </div>

            </div>


            <div class="col-lg-2 col-md-4">

                <h5>QUICK LINKS</h5>

                <ul class="footer-links">
                    <li><a href="<?php echo $baseWebsitePath; ?>index.php">Home</a></li>
                    <li><a href="<?php echo $baseWebsitePath; ?>pages/about.php">About</a></li>
                    <li><a href="<?php echo $baseWebsitePath; ?>index.php#services">Services</a></li>
                    <li><a href="<?php echo $baseWebsitePath; ?>pages/contact.php">Contact</a></li>
                </ul>

            </div>


            <div class="col-lg-2 col-md-4">

                <h5>SERVICES</h5>

                <ul class="footer-links">
                    <li>Haircut</li>
                    <li>Haircut + Beard</li>
                    <li>Premium Package</li>
                </ul>

            </div>


            <div class="col-lg-3 col-md-4">

                <h5>CONTACT</h5>

                <ul class="footer-contact">

                    <li>
                        <i class="bi bi-geo-alt"></i>
                        Carmona, Cavite
                    </li>

                    <li>
                        <i class="bi bi-telephone"></i>
                        +63 900 000 0000
                    </li>

                    <li>
                        <i class="bi bi-envelope"></i>
                        info@barbershop.co
                    </li>

                </ul>

            </div>

        </div>


        <div class="footer-bottom">

            <p>
                &copy; <?php echo date("Y"); ?> BARBERSHOP.CO.
                All Rights Reserved.
            </p>

            <div class="social-links">
                <a href="#"><i class="bi bi-facebook"></i></a>
                <a href="#"><i class="bi bi-instagram"></i></a>
                <a href="#"><i class="bi bi-tiktok"></i></a>
            </div>

        </div>

    </div>

</footer>


<!-- Bootstrap JavaScript -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Live countdowns (notification bell, live queue, my bookings) + auto-refresh -->
<script src="<?php echo $baseWebsitePath; ?>scripts/live-countdown.js"></script>


<!-- =====================================================
     PROFILE + NOTIFICATION DROPDOWN SCRIPT
     (shared by every page that includes header.php)
     ===================================================== -->

<script>

function toggleCustomerProfileMenu() {

    const profileDropdown =
        document.getElementById("customerProfileDropdown");

    if (!profileDropdown) {
        return;
    }

    profileDropdown.classList.toggle("show");

}

function toggleNotificationDropdown() {

    const notificationDropdown =
        document.getElementById("notificationDropdown");

    if (!notificationDropdown) {
        return;
    }

    notificationDropdown.classList.toggle("show");

}

document.addEventListener("click", function (event) {

    const profileWrapper =
        document.querySelector(".customer-profile-wrapper");

    const profileDropdown =
        document.getElementById("customerProfileDropdown");

    if (
        profileWrapper &&
        profileDropdown &&
        !profileWrapper.contains(event.target)
    ) {
        profileDropdown.classList.remove("show");
    }

    const notificationWrapper =
        document.querySelector(".notification-bell-wrapper");

    const notificationDropdown =
        document.getElementById("notificationDropdown");

    if (
        notificationWrapper &&
        notificationDropdown &&
        !notificationWrapper.contains(event.target)
    ) {
        notificationDropdown.classList.remove("show");
    }

});

</script>

</body>

</html>
