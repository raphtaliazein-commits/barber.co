<?php

// =====================================================
// ADMIN GUARD
// Include this at the very TOP of every admin page.
// It makes sure only a logged in Administrator can view
// the page.
// =====================================================

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/../auth/authentication_message.php";

if (!isCustomerLoggedIn()) {

    setAuthenticationMessage(
        "error",
        "Please login as an administrator to continue."
    );

    redirectTo("../pages/login.php");
}

if (!isAdministratorLoggedIn()) {

    setAuthenticationMessage(
        "error",
        "You do not have permission to access the admin dashboard."
    );

    redirectTo("../index.php");
}

$currentAdminName = $_SESSION["customer_name"] ?? "Administrator";

?>
