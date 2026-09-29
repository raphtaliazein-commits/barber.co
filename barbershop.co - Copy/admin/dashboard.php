<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../includes/booking_helpers.php";

autoProgressAppointments($databaseConnection);

$pageTitle = "Dashboard";
$activeAdminPage = "dashboard";

$today = date("Y-m-d");
$firstDayOfMonth = date("Y-m-01");


// =====================================================
// STAT: CUSTOMERS SERVED TODAY / THIS MONTH
// =====================================================

$servedTodayQuery = "SELECT COUNT(*) AS total FROM appointments WHERE appointment_status = 'Completed' AND appointment_date = ?";
$statement = mysqli_prepare($databaseConnection, $servedTodayQuery);
mysqli_stmt_bind_param($statement, "s", $today);
mysqli_stmt_execute($statement);
$servedToday = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($statement))["total"];
mysqli_stmt_close($statement);

$servedThisMonthQuery = "SELECT COUNT(*) AS total FROM appointments WHERE appointment_status = 'Completed' AND appointment_date >= ?";
$statement = mysqli_prepare($databaseConnection, $servedThisMonthQuery);
mysqli_stmt_bind_param($statement, "s", $firstDayOfMonth);
mysqli_stmt_execute($statement);
$servedThisMonth = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($statement))["total"];
mysqli_stmt_close($statement);


// =====================================================
// STAT: REVENUE (verified payments)
// =====================================================

$revenueTodayQuery = "
    SELECT COALESCE(SUM(p.payment_amount), 0) AS total
    FROM payments p
    WHERE p.payment_status = 'Verified' AND DATE(p.paid_at) = ?
";
$statement = mysqli_prepare($databaseConnection, $revenueTodayQuery);
mysqli_stmt_bind_param($statement, "s", $today);
mysqli_stmt_execute($statement);
$revenueToday = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($statement))["total"];
mysqli_stmt_close($statement);

$revenueMonthQuery = "
    SELECT COALESCE(SUM(p.payment_amount), 0) AS total
    FROM payments p
    WHERE p.payment_status = 'Verified' AND p.paid_at >= ?
";
$statement = mysqli_prepare($databaseConnection, $revenueMonthQuery);
mysqli_stmt_bind_param($statement, "s", $firstDayOfMonth);
mysqli_stmt_execute($statement);
$revenueMonth = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($statement))["total"];
mysqli_stmt_close($statement);


// =====================================================
// STAT: PENDING PAYMENTS / RESERVATIONS TO ASSIGN
// =====================================================

$pendingPaymentsQuery = "
    SELECT COUNT(*) AS total
    FROM payments p
    INNER JOIN appointments a ON a.appointment_id = p.appointment_id
    WHERE p.payment_status = 'Pending'
    AND a.appointment_status NOT IN ('Cancelled', 'No Show')
";
$pendingPayments = (int) mysqli_fetch_assoc(mysqli_query($databaseConnection, $pendingPaymentsQuery))["total"];

$pendingReservationsQuery = "SELECT COUNT(*) AS total FROM appointments WHERE booking_type = 'Reservation' AND barber_id IS NULL AND appointment_status IN ('Pending', 'Confirmed')";
$pendingReservations = (int) mysqli_fetch_assoc(mysqli_query($databaseConnection, $pendingReservationsQuery))["total"];

$todaysAppointmentsQuery = "SELECT COUNT(*) AS total FROM appointments WHERE appointment_date = ? AND appointment_status IN ('Waiting','Confirmed')";
$statement = mysqli_prepare($databaseConnection, $todaysAppointmentsQuery);
mysqli_stmt_bind_param($statement, "s", $today);
mysqli_stmt_execute($statement);
$todaysAppointments = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($statement))["total"];
mysqli_stmt_close($statement);


// =====================================================
// BARBER STATUS LIST
// =====================================================

$barbersQuery = "SELECT barber_id, barber_name, barber_status FROM barbers ORDER BY barber_name ASC";
$barbersResult = mysqli_query($databaseConnection, $barbersQuery);


// =====================================================
// TODAY'S QUEUE (upcoming appointments)
// =====================================================

$queueQuery = "
    SELECT a.appointment_code, a.appointment_start_time, a.appointment_status,
           c.full_name AS customer_name, b.barber_name, s.service_name
    FROM appointments a
    INNER JOIN customers c ON c.customer_id = a.customer_id
    INNER JOIN services s ON s.service_id = a.service_id
    LEFT JOIN barbers b ON b.barber_id = a.barber_id
    WHERE a.appointment_date = ?
    AND a.appointment_status IN ('Waiting', 'Confirmed')
    ORDER BY a.appointment_start_time ASC
    LIMIT 8
";
$statement = mysqli_prepare($databaseConnection, $queueQuery);
mysqli_stmt_bind_param($statement, "s", $today);
mysqli_stmt_execute($statement);
$queueResult = mysqli_stmt_get_result($statement);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-stats-grid">

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-calendar-check"></i></div>
        <div>
            <span class="admin-stat-label">Served Today</span>
            <h3><?php echo $servedToday; ?></h3>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-people"></i></div>
        <div>
            <span class="admin-stat-label">Served This Month</span>
            <h3><?php echo $servedThisMonth; ?></h3>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-cash-stack"></i></div>
        <div>
            <span class="admin-stat-label">Revenue Today</span>
            <h3>&#8369;<?php echo number_format($revenueToday, 2); ?></h3>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div>
            <span class="admin-stat-label">Revenue This Month</span>
            <h3>&#8369;<?php echo number_format($revenueMonth, 2); ?></h3>
        </div>
    </div>

</div>


<div class="admin-stats-grid admin-stats-grid-secondary">

    <a href="payments.php" class="admin-stat-card admin-stat-card-link">
        <div class="admin-stat-icon admin-stat-icon-warning"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <span class="admin-stat-label">Pending Payments</span>
            <h3><?php echo $pendingPayments; ?></h3>
        </div>
    </a>

    <a href="appointments.php" class="admin-stat-card admin-stat-card-link">
        <div class="admin-stat-icon admin-stat-icon-warning"><i class="bi bi-bookmark-star"></i></div>
        <div>
            <span class="admin-stat-label">Reservations to Assign</span>
            <h3><?php echo $pendingReservations; ?></h3>
        </div>
    </a>

    <a href="appointments.php" class="admin-stat-card admin-stat-card-link">
        <div class="admin-stat-icon"><i class="bi bi-clock-history"></i></div>
        <div>
            <span class="admin-stat-label">Today's Appointments</span>
            <h3><?php echo $todaysAppointments; ?></h3>
        </div>
    </a>

</div>


<div class="admin-panels-row">

    <div class="admin-panel">

        <div class="admin-panel-header">
            <h3><i class="bi bi-person-badge"></i> Barber Status</h3>
            <a href="barbers.php">Manage &rarr;</a>
        </div>

        <div class="admin-barber-status-list">

            <?php while ($barber = mysqli_fetch_assoc($barbersResult)): ?>

                <div class="admin-barber-status-row">
                    <span><?php echo htmlspecialchars($barber["barber_name"]); ?></span>
                    <span class="status-badge <?php echo $barber["barber_status"] === "Available" ? "status-confirmed" : ($barber["barber_status"] === "Busy" ? "status-pending" : "status-cancelled"); ?>">
                        <?php echo htmlspecialchars($barber["barber_status"]); ?>
                    </span>
                </div>

            <?php endwhile; ?>

        </div>

    </div>


    <div class="admin-panel">

        <div class="admin-panel-header">
            <h3><i class="bi bi-list-check"></i> Today's Queue</h3>
            <a href="appointments.php">View All &rarr;</a>
        </div>

        <div class="admin-table-wrapper">

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Barber</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>

                    <?php if (mysqli_num_rows($queueResult) === 0): ?>
                        <tr><td colspan="6" class="text-center">No appointments today yet.</td></tr>
                    <?php endif; ?>

                    <?php while ($row = mysqli_fetch_assoc($queueResult)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["appointment_code"]); ?></td>
                            <td><?php echo htmlspecialchars($row["customer_name"]); ?></td>
                            <td><?php echo htmlspecialchars($row["service_name"]); ?></td>
                            <td><?php echo $row["barber_name"] ? htmlspecialchars($row["barber_name"]) : "&mdash;"; ?></td>
                            <td><?php echo $row["appointment_start_time"] ? date("h:i A", strtotime($row["appointment_start_time"])) : "&mdash;"; ?></td>
                            <td><span class="status-badge status-confirmed"><?php echo htmlspecialchars($row["appointment_status"]); ?></span></td>
                        </tr>
                    <?php endwhile; ?>

                </tbody>
            </table>

        </div>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
