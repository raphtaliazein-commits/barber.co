<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Customer Requests";
$activeAdminPage = "requests";

if (isset($_GET["mark_resolved"])) {

    $requestId = (int) $_GET["mark_resolved"];
    $statement = mysqli_prepare($databaseConnection, "UPDATE refund_requests SET request_status = 'Resolved' WHERE refund_request_id = ?");
    mysqli_stmt_bind_param($statement, "i", $requestId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

$requestsQuery = "
    SELECT
        r.refund_request_id, r.full_name, r.email_address, r.contact_number,
        r.message, r.payment_proof_image, r.request_status, r.created_at,
        a.appointment_code, a.appointment_date,
        s.service_name, s.service_price
    FROM refund_requests r
    LEFT JOIN appointments a ON a.appointment_id = r.appointment_id
    LEFT JOIN services s ON s.service_id = a.service_id
    ORDER BY (r.request_status = 'Pending') DESC, r.created_at DESC
    LIMIT 100
";

$requestsResult = mysqli_query($databaseConnection, $requestsQuery);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-cash-coin"></i> Refund &amp; Cancellation Requests</h3>
    </div>

    <div class="admin-messages-list">

        <?php if (mysqli_num_rows($requestsResult) === 0): ?>

            <p class="text-center" style="color:#888;">No refund requests yet.</p>

        <?php endif; ?>

        <?php while ($request = mysqli_fetch_assoc($requestsResult)): ?>

            <div class="admin-message-card <?php echo $request["request_status"] === "Pending" ? "admin-message-card-new" : ""; ?>">

                <div class="admin-message-top">
                    <div>
                        <strong><?php echo htmlspecialchars($request["full_name"]); ?></strong>
                        <span style="color:#888; font-size:12px;">
                            &lt;<?php echo htmlspecialchars($request["email_address"]); ?>&gt;
                            &bull; <?php echo htmlspecialchars($request["contact_number"]); ?>
                        </span>
                    </div>
                    <span class="status-badge <?php echo $request["request_status"] === "Pending" ? "status-pending" : "status-confirmed"; ?>">
                        <?php echo htmlspecialchars($request["request_status"]); ?>
                    </span>
                </div>

                <p class="admin-message-subject">
                    <?php if ($request["appointment_code"]): ?>
                        <?php echo htmlspecialchars($request["appointment_code"]); ?> &mdash;
                        <?php echo htmlspecialchars($request["service_name"]); ?>
                        (&#8369;<?php echo number_format((float) $request["service_price"], 2); ?>)
                        <?php if ($request["appointment_date"]): ?>
                            &bull; <?php echo date("M d, Y", strtotime($request["appointment_date"])); ?>
                        <?php endif; ?>
                    <?php else: ?>
                        Appointment no longer on file
                    <?php endif; ?>
                </p>

                <?php if ($request["message"]): ?>
                    <p class="admin-message-body"><?php echo nl2br(htmlspecialchars($request["message"])); ?></p>
                <?php endif; ?>

                <?php if ($request["payment_proof_image"]): ?>
                    <a
                        href="../uploads/refund_proofs/<?php echo htmlspecialchars($request["payment_proof_image"]); ?>"
                        target="_blank"
                        class="admin-btn admin-btn-small mb-2"
                    >
                        <i class="bi bi-image"></i> View Payment Proof
                    </a>
                <?php endif; ?>

                <div class="admin-message-footer">
                    <span><?php echo date("M d, Y h:i A", strtotime($request["created_at"])); ?></span>

                    <?php if ($request["request_status"] === "Pending"): ?>
                        <a href="requests.php?mark_resolved=<?php echo (int) $request["refund_request_id"]; ?>" class="admin-btn admin-btn-small">
                            Mark as Resolved
                        </a>
                    <?php endif; ?>
                </div>

            </div>

        <?php endwhile; ?>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
