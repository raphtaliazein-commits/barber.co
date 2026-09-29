<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";
require_once __DIR__ . "/../includes/booking_helpers.php";

autoProgressAppointments($databaseConnection);

$pageTitle = "Appointments";
$activeAdminPage = "appointments";

$statusFilter = $_GET["status"] ?? "All";
$dateFilter = $_GET["date"] ?? "";

$whereClauses = [];
$params = [];
$paramTypes = "";

if ($statusFilter !== "All" && !empty($statusFilter)) {
    $whereClauses[] = "a.appointment_status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if (!empty($dateFilter)) {
    $whereClauses[] = "a.appointment_date = ?";
    $params[] = $dateFilter;
    $paramTypes .= "s";
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$appointmentsQuery = "
    SELECT
        a.appointment_id, a.appointment_code, a.appointment_date, a.appointment_start_time,
        a.requested_time, a.booking_type, a.appointment_status,
        a.preferred_barber_id, pb.barber_name AS preferred_barber_name,
        c.full_name AS customer_name, c.phone_number,
        s.service_name, s.service_price,
        b.barber_name, a.barber_id
    FROM appointments a
    INNER JOIN customers c ON c.customer_id = a.customer_id
    INNER JOIN services s ON s.service_id = a.service_id
    LEFT JOIN barbers b ON b.barber_id = a.barber_id
    LEFT JOIN barbers pb ON pb.barber_id = a.preferred_barber_id
    $whereSql
    ORDER BY a.appointment_date DESC, a.created_at DESC
    LIMIT 150
";

$statement = mysqli_prepare($databaseConnection, $appointmentsQuery);

if (!empty($params)) {
    mysqli_stmt_bind_param($statement, $paramTypes, ...$params);
}

mysqli_stmt_execute($statement);
$appointmentsResult = mysqli_stmt_get_result($statement);

$barbersResult = mysqli_query($databaseConnection, "SELECT barber_id, barber_name FROM barbers WHERE barber_status != 'Offline' ORDER BY barber_name ASC");
$barbersList = mysqli_fetch_all($barbersResult, MYSQLI_ASSOC);

$appointmentStatusBadgeClass = [
    "Pending" => "status-pending",
    "Confirmed" => "status-confirmed",
    "Waiting" => "status-confirmed",
    "Completed" => "status-completed",
    "Cancelled" => "status-cancelled",
    "No Show" => "status-cancelled",
];

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-funnel"></i> Filter</h3>
    </div>

    <form method="GET" action="appointments.php" class="admin-inline-form">

        <div class="row g-3">

            <div class="col-md-4">
                <label>Status</label>
                <select name="status" class="admin-input">
                    <?php foreach (["All", "Pending", "Confirmed", "Waiting", "Completed", "Cancelled", "No Show"] as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $statusFilter === $option ? "selected" : ""; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>" class="admin-input">
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="admin-btn admin-btn-primary w-100">Filter</button>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <a href="appointments.php" class="admin-btn w-100 text-center">Reset</a>
            </div>

        </div>

    </form>

</div>


<div class="admin-panel mt-4">

    <div class="admin-panel-header">
        <h3><i class="bi bi-calendar-check"></i> Appointments</h3>
    </div>

    <div class="admin-table-wrapper">

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Barber</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                <?php while ($row = mysqli_fetch_assoc($appointmentsResult)): ?>

                    <tr>
                        <td><?php echo htmlspecialchars($row["appointment_code"]); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row["customer_name"]); ?>
                            <br><small style="color:#888;"><?php echo htmlspecialchars($row["phone_number"]); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row["service_name"]); ?></td>
                        <td><?php echo htmlspecialchars($row["booking_type"]); ?></td>
                        <td><?php echo date("M d, Y", strtotime($row["appointment_date"])); ?></td>
                        <td>
                            <?php
                            if ($row["appointment_start_time"]) {
                                echo date("h:i A", strtotime($row["appointment_start_time"]));
                            } elseif ($row["requested_time"]) {
                                echo date("h:i A", strtotime($row["requested_time"])) . " (pref.)";
                            } else {
                                echo "&mdash;";
                            }
                            ?>
                        </td>
                        <td><?php echo $row["barber_name"] ? htmlspecialchars($row["barber_name"]) : "&mdash;"; ?></td>
                        <td>
                            <span class="status-badge <?php echo $appointmentStatusBadgeClass[$row["appointment_status"]] ?? "status-pending"; ?>">
                                <?php echo htmlspecialchars($row["appointment_status"]); ?>
                            </span>
                        </td>
                        <td>

                            <?php if (!$row["barber_id"] && in_array($row["appointment_status"], ["Pending", "Confirmed"], true)): ?>

                                <button
                                    type="button"
                                    class="admin-btn admin-btn-small admin-btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#assignModal<?php echo (int) $row["appointment_id"]; ?>"
                                >
                                    <i class="bi bi-person-check"></i> Assign
                                </button>

                                <div class="modal fade" id="assignModal<?php echo (int) $row["appointment_id"]; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content admin-modal-content">

                                            <form method="POST" action="../auth/admin_manage_appointment.php">

                                                <input type="hidden" name="form_action" value="assign_barber">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int) $row["appointment_id"]; ?>">

                                                <div class="modal-header border-0">
                                                    <h5 class="modal-title">Assign Barber</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">

                                                    <?php if ($row["preferred_barber_name"]): ?>
                                                        <p style="color:#f0c66b; font-size:12.5px; margin-bottom:14px;">
                                                            <i class="bi bi-star-fill"></i>
                                                            Customer requested: <strong><?php echo htmlspecialchars($row["preferred_barber_name"]); ?></strong>
                                                        </p>
                                                    <?php endif; ?>

                                                    <label>Barber</label>
                                                    <select name="barber_id" class="admin-input mb-3" required>
                                                        <option value="">Choose a barber</option>
                                                        <?php foreach ($barbersList as $barber): ?>
                                                            <option
                                                                value="<?php echo (int) $barber["barber_id"]; ?>"
                                                                <?php echo ((int) $barber["barber_id"] === (int) $row["preferred_barber_id"]) ? "selected" : ""; ?>
                                                            >
                                                                <?php echo htmlspecialchars($barber["barber_name"]); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <label>Time</label>
                                                    <input
                                                        type="time"
                                                        name="appointment_start_time"
                                                        class="admin-input"
                                                        value="<?php echo $row["requested_time"] ? substr($row["requested_time"], 0, 5) : "08:00"; ?>"
                                                        required
                                                    >

                                                </div>

                                                <div class="modal-footer border-0">
                                                    <button type="submit" class="admin-btn admin-btn-primary">Confirm Assignment</button>
                                                </div>

                                            </form>

                                        </div>
                                    </div>
                                </div>

                            <?php endif; ?>

                            <?php if (in_array($row["appointment_status"], ["Cancelled", "No Show"], true)): ?>

                                <span class="admin-status-locked-note" title="This status is final and can't be changed.">
                                    <i class="bi bi-lock-fill"></i> Final
                                </span>

                            <?php else: ?>

                                <form method="POST" action="../auth/admin_manage_appointment.php" class="d-inline">
                                    <input type="hidden" name="form_action" value="update_status">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int) $row["appointment_id"]; ?>">
                                    <select name="appointment_status" class="admin-status-select" onchange="this.form.submit()">
                                        <?php foreach (["Pending", "Confirmed", "Waiting", "Completed", "Cancelled", "No Show"] as $statusOption): ?>
                                            <option value="<?php echo $statusOption; ?>" <?php echo $row["appointment_status"] === $statusOption ? "selected" : ""; ?>><?php echo $statusOption; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>

                            <?php endif; ?>

                        </td>
                    </tr>

                <?php endwhile; ?>

            </tbody>
        </table>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
