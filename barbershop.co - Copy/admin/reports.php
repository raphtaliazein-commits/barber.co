<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Reports";
$activeAdminPage = "reports";

$startDate = $_GET["start_date"] ?? date("Y-m-01");
$endDate = $_GET["end_date"] ?? date("Y-m-d");


// =====================================================
// REVENUE + APPOINTMENT SUMMARY
// =====================================================

$summaryQuery = "
    SELECT
        COUNT(*) AS total_appointments,
        SUM(CASE WHEN appointment_status = 'Completed' THEN 1 ELSE 0 END) AS completed_appointments,
        SUM(CASE WHEN appointment_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_appointments,
        SUM(CASE WHEN appointment_status = 'No Show' THEN 1 ELSE 0 END) AS no_show_appointments,
        SUM(CASE WHEN booking_type = 'Reservation' THEN 1 ELSE 0 END) AS reservation_count
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
";

$statement = mysqli_prepare($databaseConnection, $summaryQuery);
mysqli_stmt_bind_param($statement, "ss", $startDate, $endDate);
mysqli_stmt_execute($statement);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);


$revenueQuery = "
    SELECT COALESCE(SUM(p.payment_amount), 0) AS total_revenue, COUNT(*) AS total_payments
    FROM payments p
    WHERE p.payment_status = 'Verified'
    AND DATE(p.paid_at) BETWEEN ? AND ?
";

$statement = mysqli_prepare($databaseConnection, $revenueQuery);
mysqli_stmt_bind_param($statement, "ss", $startDate, $endDate);
mysqli_stmt_execute($statement);
$revenue = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
mysqli_stmt_close($statement);


// =====================================================
// REVENUE BY SERVICE
// =====================================================

$serviceBreakdownQuery = "
    SELECT
        s.service_name,
        COUNT(a.appointment_id) AS total_bookings,
        COALESCE(SUM(CASE WHEN a.appointment_status = 'Completed' THEN s.service_price ELSE 0 END), 0) AS estimated_revenue
    FROM services s
    LEFT JOIN appointments a ON a.service_id = s.service_id
        AND a.appointment_date BETWEEN ? AND ?
    GROUP BY s.service_id, s.service_name
    ORDER BY total_bookings DESC
";

$statement = mysqli_prepare($databaseConnection, $serviceBreakdownQuery);
mysqli_stmt_bind_param($statement, "ss", $startDate, $endDate);
mysqli_stmt_execute($statement);
$serviceBreakdownResult = mysqli_stmt_get_result($statement);


// =====================================================
// REVENUE BY PAYMENT METHOD
// =====================================================

$methodBreakdownQuery = "
    SELECT p.payment_method, COUNT(*) AS total_payments, COALESCE(SUM(p.payment_amount), 0) AS total_amount
    FROM payments p
    WHERE p.payment_status = 'Verified'
    AND DATE(p.paid_at) BETWEEN ? AND ?
    GROUP BY p.payment_method
";

$statement = mysqli_prepare($databaseConnection, $methodBreakdownQuery);
mysqli_stmt_bind_param($statement, "ss", $startDate, $endDate);
mysqli_stmt_execute($statement);
$methodBreakdownResult = mysqli_stmt_get_result($statement);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-calendar-range"></i> Report Date Range</h3>
    </div>

    <form method="GET" action="reports.php" class="admin-inline-form">
        <div class="row g-3">
            <div class="col-md-4">
                <label>From</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="admin-input">
            </div>
            <div class="col-md-4">
                <label>To</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="admin-input">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="admin-btn admin-btn-primary w-100">Generate</button>
            </div>
        </div>
    </form>

</div>


<div class="admin-stats-grid mt-4">

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-cash-stack"></i></div>
        <div>
            <span class="admin-stat-label">Total Revenue</span>
            <h3>&#8369;<?php echo number_format((float) $revenue["total_revenue"], 2); ?></h3>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-calendar-check"></i></div>
        <div>
            <span class="admin-stat-label">Total Appointments</span>
            <h3><?php echo (int) $summary["total_appointments"]; ?></h3>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-check-circle"></i></div>
        <div>
            <span class="admin-stat-label">Completed</span>
            <h3><?php echo (int) $summary["completed_appointments"]; ?></h3>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-x-circle"></i></div>
        <div>
            <span class="admin-stat-label">Cancelled / No Show</span>
            <h3><?php echo ((int) $summary["cancelled_appointments"]) + ((int) $summary["no_show_appointments"]); ?></h3>
        </div>
    </div>

</div>


<div class="admin-panels-row mt-4">

    <div class="admin-panel">

        <div class="admin-panel-header">
            <h3><i class="bi bi-scissors"></i> Revenue by Package</h3>
        </div>

        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead><tr><th>Package</th><th>Bookings</th><th>Revenue (Completed)</th></tr></thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($serviceBreakdownResult)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["service_name"]); ?></td>
                            <td><?php echo (int) $row["total_bookings"]; ?></td>
                            <td>&#8369;<?php echo number_format((float) $row["estimated_revenue"], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    </div>


    <div class="admin-panel">

        <div class="admin-panel-header">
            <h3><i class="bi bi-wallet2"></i> Revenue by Payment Method</h3>
        </div>

        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead><tr><th>Method</th><th>Payments</th><th>Total</th></tr></thead>
                <tbody>

                    <?php if (mysqli_num_rows($methodBreakdownResult) === 0): ?>
                        <tr><td colspan="3" class="text-center">No verified payments in this range.</td></tr>
                    <?php endif; ?>

                    <?php while ($row = mysqli_fetch_assoc($methodBreakdownResult)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["payment_method"]); ?></td>
                            <td><?php echo (int) $row["total_payments"]; ?></td>
                            <td>&#8369;<?php echo number_format((float) $row["total_amount"], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>

                </tbody>
            </table>
        </div>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
