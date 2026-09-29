<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "My Profile";
$activeAdminPage = "profile";

$adminId = (int) $_SESSION["customer_id"];

$statement = mysqli_prepare($databaseConnection, "SELECT full_name, phone_number, email_address, created_at FROM customers WHERE customer_id = ? LIMIT 1");
mysqli_stmt_bind_param($statement, "i", $adminId);
mysqli_stmt_execute($statement);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel" style="max-width:640px;">

    <div class="admin-panel-header">
        <h3><i class="bi bi-person"></i> Profile Information</h3>
    </div>

    <form method="POST" action="../auth/update_profile.php">

        <input type="hidden" name="form_action" value="update_information">

        <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" class="admin-input" value="<?php echo htmlspecialchars($admin["full_name"]); ?>" required>
        </div>

        <div class="mb-3">
            <label>Email Address</label>
            <input type="email" name="email_address" class="admin-input" value="<?php echo htmlspecialchars($admin["email_address"] ?? ""); ?>" required>
        </div>

        <div class="mb-3">
            <label>Phone Number</label>
            <input type="text" class="admin-input" value="<?php echo htmlspecialchars($admin["phone_number"]); ?>" disabled>
        </div>

        <button type="submit" class="admin-btn admin-btn-primary">
            <i class="bi bi-check-lg"></i> Save Changes
        </button>

    </form>

</div>


<div class="admin-panel mt-4" style="max-width:640px;">

    <div class="admin-panel-header">
        <h3><i class="bi bi-lock"></i> Change Password</h3>
    </div>

    <form method="POST" action="../auth/update_profile.php">

        <input type="hidden" name="form_action" value="change_password">

        <div class="mb-3">
            <label>Current Password</label>
            <input type="password" name="current_password" class="admin-input" required>
        </div>

        <div class="mb-3">
            <label>New Password</label>
            <input type="password" name="new_password" class="admin-input" minlength="8" required>
        </div>

        <div class="mb-3">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_new_password" class="admin-input" minlength="8" required>
        </div>

        <button type="submit" class="admin-btn admin-btn-primary">
            <i class="bi bi-shield-check"></i> Update Password
        </button>

    </form>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
