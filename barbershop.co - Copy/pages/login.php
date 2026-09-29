<?php

session_start();

$pageTitle = "Login - BARBERSHOP.CO";

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


        <!-- =====================================================
             LEFT SIDE
             ===================================================== -->

        <div class="col-lg-5 authentication-image-section">

            <div class="authentication-image-overlay"></div>


            <div class="authentication-image-content">


                <!-- LOGO -->

                <div class="authentication-logo">

                    <i class="bi bi-scissors"></i>

                    <span>
                        BARBERSHOP<span>.CO</span>
                    </span>

                </div>


                <!-- MAIN MESSAGE -->

                <div>

                    <p class="authentication-small-title">

                        PREMIUM BARBERSHOP EXPERIENCE

                    </p>


                    <h1>

                        LOOK SHARP.

                        <br>

                        <span>FEEL CONFIDENT.</span>

                    </h1>


                    <p>

                        Book your preferred barber,
                        choose your schedule,
                        and enjoy a hassle-free
                        barbershop experience.

                    </p>

                </div>


            </div>

        </div>



        <!-- =====================================================
             RIGHT SIDE
             ===================================================== -->

        <div class="col-lg-7 authentication-form-section">


            <div class="authentication-form-container">


                <!-- HEADING -->

                <div class="authentication-heading">

                    <p>

                        WELCOME BACK

                    </p>


                    <h2>

                        LOGIN

                    </h2>


                    <span>

                        Enter your account details to continue.

                    </span>

                </div>


                <!-- AUTHENTICATION MESSAGE -->

                <?php

                displayAuthenticationMessage();

                ?>


                <!-- =================================================
                     LOGIN FORM
                     ================================================= -->

                <form
                    action="../auth/login_process.php"
                    method="POST"
                    class="login-form"
                >


                    <!-- PHONE NUMBER -->

                    <div class="authentication-input-group">


                        <label
                            for="customerPhoneNumber"
                            class="authentication-input-label"
                        >

                            PHONE NUMBER

                        </label>


                        <div class="authentication-input-wrapper">


                            <i class="bi bi-telephone"></i>


                            <input
                                type="tel"
                                id="customerPhoneNumber"
                                name="phone_number"
                                class="authentication-input"
                                placeholder="Enter your phone number"
                                maxlength="11"
                                required
                            >


                        </div>


                    </div>



                    <!-- PASSWORD -->

                    <div class="authentication-input-group">


                        <label
                            for="customerPassword"
                            class="authentication-input-label"
                        >

                            PASSWORD

                        </label>


                        <div class="authentication-input-wrapper">


                            <i class="bi bi-lock"></i>


                            <input
                                type="password"
                                id="customerPassword"
                                name="customer_password"
                                class="authentication-input"
                                placeholder="Enter your password"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle-button"
                                onclick="
                                    togglePasswordVisibility(
                                        'customerPassword',
                                        this
                                    )
                                "
                            >

                                <i class="bi bi-eye"></i>

                            </button>


                        </div>


                    </div>



                    <!-- LOGIN BUTTON -->

                    <button
                        type="submit"
                        class="login-submit-button"
                    >

                        LOGIN

                        <i class="bi bi-arrow-right"></i>

                    </button>


                </form>



                <!-- =================================================
                     REGISTER
                     ================================================= -->

                <div class="login-register-link">

                    <span>

                        Don't have an account?

                    </span>


                    <a href="register.php">

                        CREATE AN ACCOUNT

                    </a>

                </div>



                <!-- =================================================
                     BACK HOME
                     ================================================= -->

                <div class="login-back-home">

                    <a href="../index.php">

                        <i class="bi bi-arrow-left"></i>

                        Back to Homepage

                    </a>

                </div>


            </div>

        </div>


    </div>

</div>



<!-- =========================================================
     PASSWORD VISIBILITY
     ========================================================= -->

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

        passwordIcon.classList.remove(
            "bi-eye"
        );

        passwordIcon.classList.add(
            "bi-eye-slash"
        );

    }

    else {

        passwordField.type = "password";

        passwordIcon.classList.remove(
            "bi-eye-slash"
        );

        passwordIcon.classList.add(
            "bi-eye"
        );

    }

}

</script>


</body>

</html>