<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Messages";
$activeAdminPage = "messages";

if (isset($_GET["mark_read"])) {

    $messageId = (int) $_GET["mark_read"];
    $statement = mysqli_prepare($databaseConnection, "UPDATE contact_messages SET message_status = 'Read' WHERE contact_message_id = ?");
    mysqli_stmt_bind_param($statement, "i", $messageId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

$messagesQuery = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 100";
$messagesResult = mysqli_query($databaseConnection, $messagesQuery);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-envelope"></i> Contact Messages</h3>
    </div>

    <div class="admin-messages-list">

        <?php if (mysqli_num_rows($messagesResult) === 0): ?>

            <p class="text-center" style="color:#888;">No messages yet.</p>

        <?php endif; ?>

        <?php while ($message = mysqli_fetch_assoc($messagesResult)): ?>

            <div class="admin-message-card <?php echo $message["message_status"] === "New" ? "admin-message-card-new" : ""; ?>">

                <div class="admin-message-top">
                    <div>
                        <strong><?php echo htmlspecialchars($message["full_name"]); ?></strong>
                        <span style="color:#888; font-size:12px;">
                            &lt;<?php echo htmlspecialchars($message["email_address"]); ?>&gt;
                            <?php if ($message["phone_number"]): ?> &bull; <?php echo htmlspecialchars($message["phone_number"]); ?><?php endif; ?>
                        </span>
                    </div>
                    <span class="status-badge <?php echo $message["message_status"] === "New" ? "status-pending" : "status-confirmed"; ?>">
                        <?php echo htmlspecialchars($message["message_status"]); ?>
                    </span>
                </div>

                <p class="admin-message-subject"><?php echo htmlspecialchars($message["subject"]); ?></p>
                <p class="admin-message-body"><?php echo nl2br(htmlspecialchars($message["message"])); ?></p>

                <div class="admin-message-footer">
                    <span><?php echo date("M d, Y h:i A", strtotime($message["created_at"])); ?></span>

                    <?php if ($message["message_status"] === "New"): ?>
                        <a href="messages.php?mark_read=<?php echo (int) $message["contact_message_id"]; ?>" class="admin-btn admin-btn-small">
                            Mark as Read
                        </a>
                    <?php endif; ?>
                </div>

            </div>

        <?php endwhile; ?>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
