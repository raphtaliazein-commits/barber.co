<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Calendar & Holidays";
$activeAdminPage = "calendar";

$monthParam = $_GET["month"] ?? date("Y-m");

$monthDateTime = DateTime::createFromFormat("Y-m", $monthParam) ?: new DateTime();
$monthDateTime->modify("first day of this month");

$monthLabel = $monthDateTime->format("F Y");
$daysInMonth = (int) $monthDateTime->format("t");
$firstWeekdayIndex = (int) $monthDateTime->format("w"); // 0 = Sunday

$previousMonth = (clone $monthDateTime)->modify("-1 month")->format("Y-m");
$nextMonth = (clone $monthDateTime)->modify("+1 month")->format("Y-m");

$monthStart = $monthDateTime->format("Y-m-01");
$monthEnd = $monthDateTime->format("Y-m-t");


// =====================================================
// GET HOLIDAYS THIS MONTH
// =====================================================

$holidaysQuery = "
    SELECT holiday_id, holiday_date, holiday_name
    FROM holidays
    WHERE holiday_date BETWEEN ? AND ?
    ORDER BY holiday_date ASC
";

$statement = mysqli_prepare($databaseConnection, $holidaysQuery);
mysqli_stmt_bind_param($statement, "ss", $monthStart, $monthEnd);
mysqli_stmt_execute($statement);
$holidaysResult = mysqli_stmt_get_result($statement);

$holidaysByDate = [];

while ($holiday = mysqli_fetch_assoc($holidaysResult)) {
    $holidaysByDate[$holiday["holiday_date"]] = $holiday;
}

mysqli_stmt_close($statement);


// =====================================================
// ALL UPCOMING HOLIDAYS (for the list below the calendar)
// =====================================================

$allHolidaysQuery = "SELECT * FROM holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC";
$allHolidaysResult = mysqli_query($databaseConnection, $allHolidaysQuery);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-calendar-plus"></i> Mark a Holiday (Shop Closed)</h3>
    </div>

    <form method="POST" action="../auth/admin_manage_holiday.php" class="admin-inline-form">

        <input type="hidden" name="form_action" value="add_holiday">

        <div class="row g-3">

            <div class="col-md-4">
                <label>Date</label>
                <input type="date" name="holiday_date" class="admin-input" required>
            </div>

            <div class="col-md-6">
                <label>Holiday Name</label>
                <input type="text" name="holiday_name" class="admin-input" placeholder="e.g. New Year's Day" required>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="admin-btn admin-btn-primary w-100">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            </div>

        </div>

    </form>

</div>


<div class="admin-panel mt-4">

    <div class="admin-panel-header">
        <h3><i class="bi bi-calendar3"></i> <?php echo $monthLabel; ?></h3>
        <div>
            <a href="calendar.php?month=<?php echo $previousMonth; ?>" class="admin-btn admin-btn-small"><i class="bi bi-chevron-left"></i></a>
            <a href="calendar.php?month=<?php echo $nextMonth; ?>" class="admin-btn admin-btn-small"><i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <div class="admin-calendar-grid">

        <?php foreach (["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"] as $dayLabel): ?>
            <div class="admin-calendar-weekday"><?php echo $dayLabel; ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $firstWeekdayIndex; $i++): ?>
            <div class="admin-calendar-cell admin-calendar-cell-empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>

            <?php
            $cellDate = $monthDateTime->format("Y-m") . "-" . str_pad($day, 2, "0", STR_PAD_LEFT);
            $isHolidayDate = isset($holidaysByDate[$cellDate]);
            $isToday = $cellDate === date("Y-m-d");
            ?>

            <div class="admin-calendar-cell <?php echo $isHolidayDate ? "admin-calendar-cell-holiday" : ""; ?> <?php echo $isToday ? "admin-calendar-cell-today" : ""; ?>">
                <span class="admin-calendar-date"><?php echo $day; ?></span>

                <?php if ($isHolidayDate): ?>
                    <span class="admin-calendar-holiday-label">
                        <?php echo htmlspecialchars($holidaysByDate[$cellDate]["holiday_name"]); ?>
                    </span>
                <?php endif; ?>
            </div>

        <?php endfor; ?>

    </div>

</div>


<div class="admin-panel mt-4">

    <div class="admin-panel-header">
        <h3><i class="bi bi-list-ul"></i> Upcoming Holidays</h3>
    </div>

    <div class="admin-table-wrapper">

        <table class="admin-table">
            <thead>
                <tr><th>Date</th><th>Name</th><th>Actions</th></tr>
            </thead>
            <tbody>

                <?php if (mysqli_num_rows($allHolidaysResult) === 0): ?>
                    <tr><td colspan="3" class="text-center">No holidays added yet.</td></tr>
                <?php endif; ?>

                <?php while ($holiday = mysqli_fetch_assoc($allHolidaysResult)): ?>
                    <tr>
                        <td><?php echo date("F d, Y (l)", strtotime($holiday["holiday_date"])); ?></td>
                        <td><?php echo htmlspecialchars($holiday["holiday_name"]); ?></td>
                        <td>
                            <form method="POST" action="../auth/admin_manage_holiday.php" onsubmit="return confirm('Remove this holiday?');">
                                <input type="hidden" name="form_action" value="delete_holiday">
                                <input type="hidden" name="holiday_id" value="<?php echo (int) $holiday["holiday_id"]; ?>">
                                <button type="submit" class="admin-btn admin-btn-small admin-btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>

            </tbody>
        </table>

    </div>

</div>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
