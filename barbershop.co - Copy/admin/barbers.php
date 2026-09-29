<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Barbers";
$activeAdminPage = "barbers";

$barbersQuery = "SELECT * FROM barbers ORDER BY barber_name ASC";
$barbersResult = mysqli_query($databaseConnection, $barbersQuery);
$barbersList = mysqli_fetch_all($barbersResult, MYSQLI_ASSOC);


// =====================================================
// FLAG ANY BARBER WITH NO SCHEDULE AT ALL — they would be
// invisible to customers during booking otherwise.
// =====================================================

$barbersWithoutScheduleQuery = "
    SELECT b.barber_id
    FROM barbers b
    LEFT JOIN barber_schedules bs ON bs.barber_id = b.barber_id
    GROUP BY b.barber_id
    HAVING COUNT(bs.barber_schedule_id) = 0
";

$barbersWithoutScheduleResult = mysqli_query($databaseConnection, $barbersWithoutScheduleQuery);
$barberIdsWithoutSchedule = array_column(mysqli_fetch_all($barbersWithoutScheduleResult, MYSQLI_ASSOC), "barber_id");
$barberIdsWithoutSchedule = array_map("intval", $barberIdsWithoutSchedule);

$editBarberId = (int) ($_GET["barber_id"] ?? 0);
$barberSchedules = [];

if ($editBarberId > 0) {

    $scheduleQuery = "SELECT * FROM barber_schedules WHERE barber_id = ?";
    $statement = mysqli_prepare($databaseConnection, $scheduleQuery);
    mysqli_stmt_bind_param($statement, "i", $editBarberId);
    mysqli_stmt_execute($statement);
    $scheduleResult = mysqli_stmt_get_result($statement);

    while ($row = mysqli_fetch_assoc($scheduleResult)) {
        $barberSchedules[$row["schedule_day"]] = $row;
    }

    mysqli_stmt_close($statement);
}

$daysOfWeek = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-person-plus"></i> Add New Barber</h3>
    </div>

    <form method="POST" action="../auth/admin_save_barber.php" class="admin-inline-form">

        <input type="hidden" name="form_action" value="save_barber">

        <div class="row g-3">

            <div class="col-md-4">
                <label>Barber Name</label>
                <input type="text" name="barber_name" class="admin-input" required>
            </div>

            <div class="col-md-5">
                <label>Description</label>
                <input type="text" name="barber_description" class="admin-input">
            </div>

            <div class="col-md-2">
                <label>Status</label>
                <select name="barber_status" class="admin-input">
                    <option value="Available">Available</option>
                    <option value="Busy">Busy</option>
                    <option value="Offline">Offline</option>
                </select>
            </div>

            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="admin-btn admin-btn-primary w-100">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>

        </div>

    </form>

</div>


<div class="admin-panel mt-4">

    <div class="admin-panel-header">
        <h3><i class="bi bi-people"></i> All Barbers</h3>
    </div>

    <div class="admin-table-wrapper">

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($barbersList as $barber): ?>

                    <?php $hasNoSchedule = in_array((int) $barber["barber_id"], $barberIdsWithoutSchedule, true); ?>

                    <tr>
                        <td>
                            <?php echo htmlspecialchars($barber["barber_name"]); ?>

                            <?php if ($hasNoSchedule): ?>
                                <span class="admin-warning-badge" title="This barber has no schedule set — they won't show up when customers try to book.">
                                    <i class="bi bi-exclamation-triangle-fill"></i> No Schedule
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($barber["barber_description"] ?? ""); ?></td>
                        <td>

                            <form method="POST" action="../auth/admin_save_barber.php" class="d-inline">
                                <input type="hidden" name="form_action" value="toggle_status">
                                <input type="hidden" name="barber_id" value="<?php echo (int) $barber["barber_id"]; ?>">
                                <select name="barber_status" class="admin-status-select" onchange="this.form.submit()">
                                    <option value="Available" <?php echo $barber["barber_status"] === "Available" ? "selected" : ""; ?>>Available</option>
                                    <option value="Busy" <?php echo $barber["barber_status"] === "Busy" ? "selected" : ""; ?>>Busy</option>
                                    <option value="Offline" <?php echo $barber["barber_status"] === "Offline" ? "selected" : ""; ?>>Offline</option>
                                </select>
                            </form>

                        </td>
                        <td>

                            <a href="barbers.php?barber_id=<?php echo (int) $barber["barber_id"]; ?>" class="admin-btn admin-btn-small">
                                <i class="bi bi-calendar-week"></i> Schedule
                            </a>

                            <?php if ($hasNoSchedule): ?>
                                <form method="POST" action="../auth/admin_save_barber.php" class="d-inline">
                                    <input type="hidden" name="form_action" value="apply_default_schedule">
                                    <input type="hidden" name="barber_id" value="<?php echo (int) $barber["barber_id"]; ?>">
                                    <button type="submit" class="admin-btn admin-btn-small admin-btn-primary">
                                        <i class="bi bi-magic"></i> Quick Fix
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" action="../auth/admin_save_barber.php" class="d-inline" onsubmit="return confirm('Remove this barber?');">
                                <input type="hidden" name="form_action" value="delete_barber">
                                <input type="hidden" name="barber_id" value="<?php echo (int) $barber["barber_id"]; ?>">
                                <button type="submit" class="admin-btn admin-btn-small admin-btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>

                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>
        </table>

    </div>

</div>


<?php if ($editBarberId > 0): ?>

    <?php
    $selectedBarberName = "";
    foreach ($barbersList as $barber) {
        if ((int) $barber["barber_id"] === $editBarberId) {
            $selectedBarberName = $barber["barber_name"];
        }
    }
    ?>

    <div class="admin-panel mt-4">

        <div class="admin-panel-header">
            <h3><i class="bi bi-calendar-week"></i> Weekly Schedule &mdash; <?php echo htmlspecialchars($selectedBarberName); ?></h3>
        </div>

        <form method="POST" action="../auth/admin_save_barber.php">

            <input type="hidden" name="form_action" value="save_schedule">
            <input type="hidden" name="barber_id" value="<?php echo $editBarberId; ?>">

            <div class="admin-schedule-grid">

                <?php foreach ($daysOfWeek as $day): ?>

                    <?php $existing = $barberSchedules[$day] ?? null; ?>

                    <div class="admin-schedule-row">

                        <label class="admin-schedule-day-toggle">
                            <input type="checkbox" name="working_<?php echo $day; ?>" <?php echo $existing ? "checked" : ""; ?>>
                            <?php echo $day; ?>
                        </label>

                        <input
                            type="time"
                            name="start_<?php echo $day; ?>"
                            value="<?php echo $existing ? substr($existing["start_time"], 0, 5) : "08:00"; ?>"
                            class="admin-input"
                        >

                        <span>to</span>

                        <input
                            type="time"
                            name="end_<?php echo $day; ?>"
                            value="<?php echo $existing ? substr($existing["end_time"], 0, 5) : "19:00"; ?>"
                            class="admin-input"
                        >

                    </div>

                <?php endforeach; ?>

            </div>

            <button type="submit" class="admin-btn admin-btn-primary mt-3">
                <i class="bi bi-check-lg"></i> Save Schedule
            </button>

        </form>

    </div>

<?php endif; ?>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
