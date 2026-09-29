<?php

require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/authentication_message.php";

if (!isset($_SESSION["customer_id"])) {
    redirectTo("../pages/login.php");
}

$customerId = (int) $_SESSION["customer_id"];

$redirectBackPath = isAdministratorLoggedIn() ? "../admin/profile.php" : "../pages/profile.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo($redirectBackPath);
}

$formAction = $_POST["form_action"] ?? "";


// =====================================================
// UPDATE PROFILE INFORMATION
// =====================================================

if ($formAction === "update_information") {

    $fullName = trim($_POST["full_name"] ?? "");
    $emailAddress = trim($_POST["email_address"] ?? "");

    if (strlen($fullName) < 3) {
        setAuthenticationMessage("error", "Your full name must contain at least 3 characters.");
        redirectTo($redirectBackPath);
    }

    if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
        setAuthenticationMessage("error", "Please enter a valid email address.");
        redirectTo($redirectBackPath);
    }

    $checkEmailQuery = "SELECT customer_id FROM customers WHERE email_address = ? AND customer_id != ? LIMIT 1";
    $checkStatement = mysqli_prepare($databaseConnection, $checkEmailQuery);
    mysqli_stmt_bind_param($checkStatement, "si", $emailAddress, $customerId);
    mysqli_stmt_execute($checkStatement);
    $checkResult = mysqli_stmt_get_result($checkStatement);

    if (mysqli_num_rows($checkResult) > 0) {
        mysqli_stmt_close($checkStatement);
        setAuthenticationMessage("error", "This email address is already used by another account.");
        redirectTo($redirectBackPath);
    }

    mysqli_stmt_close($checkStatement);


    // =================================================
    // DATE OF BIRTH
    // (only the customer-facing profile form sends this
    // field — the admin profile form does not, so it's
    // left untouched when absent)
    // =================================================

    $dateOfBirthProvided = array_key_exists("date_of_birth", $_POST);
    $dateOfBirth = trim($_POST["date_of_birth"] ?? "");

    if ($dateOfBirthProvided) {

        if (
            !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateOfBirth, $dateOfBirthParts) ||
            !checkdate((int) $dateOfBirthParts[2], (int) $dateOfBirthParts[3], (int) $dateOfBirthParts[1])
        ) {
            setAuthenticationMessage("error", "Please enter a valid date of birth.");
            redirectTo($redirectBackPath);
        }

        $dateOfBirthObject = new DateTime($dateOfBirth);
        $todayObject = new DateTime("today");

        if ($dateOfBirthObject > $todayObject) {
            setAuthenticationMessage("error", "Your date of birth cannot be in the future.");
            redirectTo($redirectBackPath);
        }
    }


    // =================================================
    // OPTIONAL PROFILE IMAGE UPLOAD
    // =================================================

    $profileImageFileName = null;

    if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] === UPLOAD_ERR_OK) {

        $uploadedFile = $_FILES["profile_image"];

        if ($uploadedFile["size"] <= PROFILE_IMAGE_MAX_FILE_SIZE_BYTES) {

            $imageInformation = getimagesize($uploadedFile["tmp_name"]);
            $allowedMimeTypes = unserialize(ALLOWED_IMAGE_MIME_TYPES);

            if ($imageInformation && in_array($imageInformation["mime"], $allowedMimeTypes, true)) {

                if (!is_dir(PROFILE_IMAGE_UPLOAD_DIRECTORY)) {
                    mkdir(PROFILE_IMAGE_UPLOAD_DIRECTORY, 0755, true);
                }

                $fileExtension = strtolower(pathinfo($uploadedFile["name"], PATHINFO_EXTENSION));

                if (!in_array($fileExtension, ["jpg", "jpeg", "png", "webp"], true)) {
                    $fileExtension = "jpg";
                }

                $profileImageFileName =
                    "profile_" . $customerId . "_" . time() . "." . $fileExtension;

                move_uploaded_file(
                    $uploadedFile["tmp_name"],
                    PROFILE_IMAGE_UPLOAD_DIRECTORY . $profileImageFileName
                );
            }
        }
    }


    // =================================================
    // BUILD THE UPDATE QUERY DYNAMICALLY
    // (full_name + email always change; date_of_birth and
    // profile_image only when provided)
    // =================================================

    $setClauses = ["full_name = ?", "email_address = ?"];
    $bindTypes = "ss";
    $bindValues = [$fullName, $emailAddress];

    if ($dateOfBirthProvided) {
        $setClauses[] = "date_of_birth = ?";
        $bindTypes .= "s";
        $bindValues[] = $dateOfBirth;
    }

    if ($profileImageFileName) {
        $setClauses[] = "profile_image = ?";
        $bindTypes .= "s";
        $bindValues[] = $profileImageFileName;
    }

    $bindTypes .= "i";
    $bindValues[] = $customerId;

    $updateQuery = "UPDATE customers SET " . implode(", ", $setClauses) . " WHERE customer_id = ?";

    $updateStatement = mysqli_prepare($databaseConnection, $updateQuery);
    mysqli_stmt_bind_param($updateStatement, $bindTypes, ...$bindValues);

    mysqli_stmt_execute($updateStatement);
    mysqli_stmt_close($updateStatement);

    $_SESSION["customer_name"] = $fullName;
    $_SESSION["customer_email_address"] = $emailAddress;

    setAuthenticationMessage("success", "Your profile has been updated.");
    redirectTo($redirectBackPath);
}


// =====================================================
// CHANGE PASSWORD
// =====================================================

if ($formAction === "change_password") {

    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmNewPassword = $_POST["confirm_new_password"] ?? "";

    $passwordQuery = "SELECT password_hash FROM customers WHERE customer_id = ? LIMIT 1";
    $passwordStatement = mysqli_prepare($databaseConnection, $passwordQuery);
    mysqli_stmt_bind_param($passwordStatement, "i", $customerId);
    mysqli_stmt_execute($passwordStatement);
    $passwordResult = mysqli_stmt_get_result($passwordStatement);
    $customer = mysqli_fetch_assoc($passwordResult);
    mysqli_stmt_close($passwordStatement);

    if (!$customer || !password_verify($currentPassword, $customer["password_hash"])) {
        setAuthenticationMessage("error", "Your current password is incorrect.");
        redirectTo($redirectBackPath);
    }

    if (strlen($newPassword) < 8) {
        setAuthenticationMessage("error", "Your new password must contain at least 8 characters.");
        redirectTo($redirectBackPath);
    }

    if ($newPassword !== $confirmNewPassword) {
        setAuthenticationMessage("error", "The new passwords do not match.");
        redirectTo($redirectBackPath);
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $updatePasswordQuery = "UPDATE customers SET password_hash = ? WHERE customer_id = ?";
    $updatePasswordStatement = mysqli_prepare($databaseConnection, $updatePasswordQuery);
    mysqli_stmt_bind_param($updatePasswordStatement, "si", $newPasswordHash, $customerId);
    mysqli_stmt_execute($updatePasswordStatement);
    mysqli_stmt_close($updatePasswordStatement);

    setAuthenticationMessage("success", "Your password has been changed.");
    redirectTo($redirectBackPath);
}

redirectTo($redirectBackPath);
