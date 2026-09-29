<?php

session_start();

$pageTitle = "Create Account - BARBERSHOP.CO";

$currentUserName = "Guest";

$currentUserProfileImage =
    "../styles/images/logo_for_about.jpg";

require_once "../auth/authentication_message.php";

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($pageTitle); ?>
    </title>

    <!-- Bootstrap 5.3 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <!-- BARBERSHOP.CO CSS -->
    <link
        rel="stylesheet"
        href="../styles/style.css"
    >

</head>


<body class="authentication-page">


<div class="authentication-container">

    <div class="row g-0">


        <!-- =========================================
             LEFT SIDE
             ========================================= -->

        <div class="col-lg-5 authentication-image-section">

            <div class="authentication-image-overlay"></div>

            <div class="authentication-image-content">

                <div class="authentication-logo">

                    <i class="bi bi-scissors"></i>

                    <span>
                        BARBERSHOP<span>.CO</span>
                    </span>

                </div>


                <div>

                    <p class="authentication-small-title">
                        PREMIUM BARBERSHOP EXPERIENCE
                    </p>

                    <h1>
                        YOUR STYLE.
                        <br>
                        YOUR SCHEDULE.
                        <br>
                        <span>YOUR CHOICE.</span>
                    </h1>

                    <p>
                        Create your BARBERSHOP.CO account and
                        start booking your preferred barber
                        at your preferred schedule.
                    </p>

                </div>

            </div>

        </div>


        <!-- =========================================
             RIGHT SIDE
             ========================================= -->

        <div class="col-lg-7 authentication-form-section">

            <div class="authentication-form-container">


                <div class="authentication-heading">

                    <p>
                        GET STARTED
                    </p>

                    <h2>
                        CREATE YOUR ACCOUNT
                    </h2>

                    <span>
                        Register now to book your next appointment.
                    </span>

                </div>

                <?php displayAuthenticationMessage();?>


                <!-- Registration Form -->

                <form
                    action="../auth/register_process.php"
                    method="POST"
                    class="registration-form"
                >


                    <!-- FULL NAME -->

                    <div class="form-group-custom">

                        <label for="fullName">
                            FULL NAME
                        </label>

                        <div class="input-wrapper">

                            <i class="bi bi-person"></i>

                            <input
                                type="text"
                                id="fullName"
                                name="full_name"
                                placeholder="Enter your full name"
                                required
                            >

                        </div>

                    </div>


                    <!-- PHONE NUMBER -->

                    <div class="form-group-custom">

                        <label for="phoneNumber">
                            PHONE NUMBER
                        </label>

                        <div class="input-wrapper">

                            <i class="bi bi-telephone"></i>

                            <input
                                type="tel"
                                id="phoneNumber"
                                name="phone_number"
                                placeholder="09XXXXXXXXX"
                                maxlength="11"
                                required
                            >

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group-custom">

                        <label for="emailAddress">
                            EMAIL ADDRESS
                        </label>

                        <div class="input-wrapper">

                            <i class="bi bi-envelope"></i>

                            <input
                                type="email"
                                id="emailAddress"
                                name="email_address"
                                placeholder="Enter your email address"
                                required
                            >

                        </div>

                        <small style="color:#888; font-size:11px;">
                            We'll send a verification link to this address — please make sure you can access it.
                        </small>

                    </div>


                    <!-- DATE OF BIRTH -->

                    <div class="form-group-custom">

                        <label for="dateOfBirth">
                            DATE OF BIRTH
                        </label>

                        <div class="input-wrapper">

                            <i class="bi bi-calendar-heart"></i>

                            <input
                                type="date"
                                id="dateOfBirth"
                                name="date_of_birth"
                                max="<?php echo date('Y-m-d'); ?>"
                                required
                            >

                        </div>

                        <small style="color:#888; font-size:11px;">
                            We ask for this so we can offer online payment
                            (GCash/PayMaya) only to customers 18 and above.
                        </small>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group-custom">

                        <label for="customerPassword">
                            PASSWORD
                        </label>

                        <div class="input-wrapper">

                            <i class="bi bi-lock"></i>

                            <input
                                type="password"
                                id="customerPassword"
                                name="customer_password"
                                placeholder="Create a password"
                                minlength="8"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordVisibility(
                                    'customerPassword',
                                    this
                                )"
                            >

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group-custom">

                        <label for="confirmCustomerPassword">
                            CONFIRM PASSWORD
                        </label>

                        <div class="input-wrapper">

                            <i class="bi bi-lock-fill"></i>

                            <input
                                type="password"
                                id="confirmCustomerPassword"
                                name="confirm_customer_password"
                                placeholder="Confirm your password"
                                minlength="8"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordVisibility(
                                    'confirmCustomerPassword',
                                    this
                                )"
                            >

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    <!-- TERMS -->

                    <div class="terms-container">

                        <input
                            type="checkbox"
                            id="agreeToTerms"
                            name="agree_to_terms"
                            required
                        >

                        <label for="agreeToTerms">

                            I agree to the
                            <a href="#">
                                Terms and Conditions
                            </a>

                            of BARBERSHOP.CO.

                        </label>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="authentication-submit-button"
                    >

                        CREATE ACCOUNT

                        <i class="bi bi-arrow-right"></i>

                    </button>


                </form>


                <!-- LOGIN -->

                <div class="authentication-footer">

                    <span>
                        Already have an account?
                    </span>

                    <a href="login.php">
                        LOGIN HERE
                    </a>

                </div>


                <!-- BACK HOME -->

                <a
                    href="../index.php"
                    class="back-home-link"
                >

                    <i class="bi bi-arrow-left"></i>

                    Back to Homepage

                </a>


            </div>

        </div>

    </div>

</div>


<script>

function togglePasswordVisibility(
    passwordFieldId,
    buttonElement
) {

    const passwordField =
        document.getElementById(passwordFieldId);

    const passwordIcon =
        buttonElement.querySelector("i");


    if (passwordField.type === "password") {

        passwordField.type = "text";

        passwordIcon.classList.remove("bi-eye");

        passwordIcon.classList.add("bi-eye-slash");

    } else {

        passwordField.type = "password";

        passwordIcon.classList.remove("bi-eye-slash");

        passwordIcon.classList.add("bi-eye");

    }

}

</script>


</body>

</html>