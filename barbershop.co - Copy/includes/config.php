<?php

// =====================================================
// GLOBAL CONFIGURATION
// Included by almost every page in the website.
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set("Asia/Manila");


// =====================================================
// BUSINESS HOURS
// =====================================================

define("BUSINESS_OPENING_TIME", "08:00:00");
define("BUSINESS_CLOSING_TIME", "19:00:00");
define("BOOKING_TIME_INTERVAL_MINUTES", 30);


// =====================================================
// RESERVATION FEE
// =====================================================

define("RESERVATION_FEE_AMOUNT", 100.00);


// =====================================================
// FILE UPLOAD DIRECTORIES
// =====================================================

define("PAYMENT_PROOF_UPLOAD_DIRECTORY", __DIR__ . "/../uploads/payment_proofs/");
define("PROFILE_IMAGE_UPLOAD_DIRECTORY", __DIR__ . "/../uploads/profile_images/");
define("REFUND_PROOF_UPLOAD_DIRECTORY", __DIR__ . "/../uploads/refund_proofs/");

define("PAYMENT_PROOF_MAX_FILE_SIZE_BYTES", 5 * 1024 * 1024); // 5 MB
define("PROFILE_IMAGE_MAX_FILE_SIZE_BYTES", 3 * 1024 * 1024); // 3 MB

define("ALLOWED_IMAGE_MIME_TYPES", serialize([
    "image/jpeg",
    "image/png",
    "image/webp",
]));


// =====================================================
// AGE REQUIREMENT FOR ONLINE PAYMENTS
// =====================================================

define("MINIMUM_ONLINE_PAYMENT_AGE", 18);


// =====================================================
// SMALL HELPER FUNCTIONS
// =====================================================

function isCustomerLoggedIn(): bool
{
    return isset($_SESSION["customer_logged_in"])
        && $_SESSION["customer_logged_in"] === true;
}

function isAdministratorLoggedIn(): bool
{
    return isCustomerLoggedIn()
        && ($_SESSION["customer_role"] ?? "") === "Administrator";
}

function redirectTo(string $location): void
{
    header("Location: " . $location);
    exit;
}


/**
 * Returns the customer's age in whole years, or null if no
 * date of birth is on file yet.
 */
function calculateAge(?string $dateOfBirth): ?int
{
    if (empty($dateOfBirth)) {
        return null;
    }

    try {
        $birthDate = new DateTime($dateOfBirth);
        $today = new DateTime("today");
        return (int) $birthDate->diff($today)->y;
    } catch (Exception $e) {
        return null;
    }
}


/**
 * Online payment methods (GCash / PayMaya) are only allowed for
 * customers who have confirmed their date of birth AND are old
 * enough. A missing date of birth is treated as NOT eligible
 * (fail closed, not fail open) until the customer provides it.
 */
function isEligibleForOnlinePayment(?string $dateOfBirth): bool
{
    $age = calculateAge($dateOfBirth);

    return $age !== null && $age >= MINIMUM_ONLINE_PAYMENT_AGE;
}


/**
 * Builds this site's own base URL (e.g. "http://localhost/barbershop.co"),
 * figured out from the current request so it works regardless of which
 * subfolder the project is installed into. Used to build links that get
 * emailed to the customer (like the email verification link), which is
 * the one place a plain relative path won't work.
 */
function buildSiteBaseUrl(): string
{
    $protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"] ?? "localhost";

    // SCRIPT_NAME is something like "/barbershop.co/auth/register_process.php"
    // or "/barbershop.co/pages/verify_email.php" — going up two levels from
    // whichever script is currently running gets us the project's root.
    $scriptDirectory = dirname($_SERVER["SCRIPT_NAME"] ?? "/index.php");
    $siteRoot = dirname($scriptDirectory);

    if ($siteRoot === "/" || $siteRoot === "\\" || $siteRoot === ".") {
        $siteRoot = "";
    }

    return $protocol . "://" . $host . $siteRoot;
}

?>
