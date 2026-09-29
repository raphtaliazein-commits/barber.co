<?php

session_start();

require_once "../db/database_connection.php";
require_once "authentication_message.php";


// =====================================================
// MAKE SURE THE FORM WAS SUBMITTED USING POST
// =====================================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: ../pages/login.php");

    exit;
}


// =====================================================
// GET LOGIN FORM DATA
// =====================================================

$phoneNumber =
    trim($_POST["phone_number"] ?? "");

$customerPassword =
    $_POST["customer_password"] ?? "";


// =====================================================
// VALIDATE REQUIRED FIELDS
// =====================================================

if (
    empty($phoneNumber) ||
    empty($customerPassword)
) {

    setAuthenticationMessage(
        "error",
        "Please enter your phone number and password."
    );

    header("Location: ../pages/login.php");

    exit;
}


// =====================================================
// FIND CUSTOMER BY PHONE NUMBER
// =====================================================

$findCustomerQuery = "
    SELECT
        customer_id,
        full_name,
        phone_number,
        email_address,
        password_hash,
        customer_role,
        account_status
    FROM customers
    WHERE phone_number = ?
    LIMIT 1
";


$customerStatement = mysqli_prepare(
    $databaseConnection,
    $findCustomerQuery
);


if (!$customerStatement) {

    setAuthenticationMessage(
        "error",
        "Something went wrong while processing your login."
    );

    header("Location: ../pages/login.php");

    exit;
}


mysqli_stmt_bind_param(
    $customerStatement,
    "s",
    $phoneNumber
);


mysqli_stmt_execute(
    $customerStatement
);


$customerResult =
    mysqli_stmt_get_result(
        $customerStatement
    );


// =====================================================
// CHECK CUSTOMER
// =====================================================

if (
    mysqli_num_rows($customerResult) === 0
) {

    mysqli_stmt_close(
        $customerStatement
    );

    setAuthenticationMessage(
        "error",
        "Invalid phone number or password."
    );

    header("Location: ../pages/login.php");

    exit;
}


$customer =
    mysqli_fetch_assoc(
        $customerResult
    );


mysqli_stmt_close(
    $customerStatement
);


// =====================================================
// CHECK ACCOUNT STATUS
// =====================================================

if (
    $customer["account_status"] !== "Active"
) {

    setAuthenticationMessage(
        "error",
        "Your account is currently inactive."
    );

    header("Location: ../pages/login.php");

    exit;
}


// =====================================================
// VERIFY PASSWORD
// =====================================================

if (
    !password_verify(
        $customerPassword,
        $customer["password_hash"]
    )
) {

    setAuthenticationMessage(
        "error",
        "Invalid phone number or password."
    );

    header("Location: ../pages/login.php");

    exit;
}


// =====================================================
// LOGIN SUCCESS
// =====================================================

session_regenerate_id(true);


// Store customer information in the session

$_SESSION["customer_id"] =
    $customer["customer_id"];

$_SESSION["customer_name"] =
    $customer["full_name"];

$_SESSION["customer_phone_number"] =
    $customer["phone_number"];

$_SESSION["customer_email_address"] =
    $customer["email_address"];

$_SESSION["customer_role"] =
    $customer["customer_role"];

$_SESSION["customer_logged_in"] =
    true;


// =====================================================
// CLOSE DATABASE CONNECTION
// =====================================================

mysqli_close(
    $databaseConnection
);


// =====================================================
// SUCCESS MESSAGE
// =====================================================

setAuthenticationMessage(
    "success",
    "Welcome back, " .
    $customer["full_name"] .
    "!"
);


// =====================================================
// REDIRECT BASED ON ROLE
// =====================================================

if ($customer["customer_role"] === "Administrator") {

    header("Location: ../admin/dashboard.php");

    exit;
}

header("Location: ../index.php");

exit;

?>