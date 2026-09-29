<?php

// =====================================================
// SHARED BOOKING AVAILABILITY LOGIC
// Used by both pages/booking.php and pages/confirm_booking.php
// so the two pages can never disagree on what counts as
// "available".
// =====================================================


/**
 * Maximum number of active (Pending/Confirmed/Waiting) appointments
 * a single barber can carry on one day. Once a barber hits this many,
 * they're treated as fully booked for that day regardless of exact
 * time conflicts, and shown as "Full" instead of selectable.
 */
define("BARBER_MAX_DAILY_SLOTS", 4);


/**
 * How many active appointments each barber already has on a given
 * date. Returns [barber_id => count]; a barber with no active
 * appointments simply won't appear as a key (treat as 0).
 */
function getBarberDailyLoad(mysqli $databaseConnection, string $selectedDate): array
{
    $query = "
        SELECT barber_id, COUNT(*) AS active_count
        FROM appointments
        WHERE appointment_date = ?
        AND appointment_status IN ('Pending', 'Confirmed', 'Waiting')
        AND barber_id IS NOT NULL
        GROUP BY barber_id
    ";

    $statement = mysqli_prepare($databaseConnection, $query);
    mysqli_stmt_bind_param($statement, "s", $selectedDate);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    $loadByBarberId = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $loadByBarberId[(int) $row["barber_id"]] = (int) $row["active_count"];
    }

    mysqli_stmt_close($statement);

    return $loadByBarberId;
}


/**
 * Figure out which exact time slots are open for a given date
 * and service duration.
 *
 * @return array{
 *     status: "available"|"fully_booked"|"closed"|"no_schedule",
 *     holiday_name: string|null,
 *     slots: array<string, string>  "H:i:s" => "h:i A", sorted ascending
 * }
 */
function getBookingAvailability(
    mysqli $databaseConnection,
    string $selectedDate,
    int $serviceDurationMinutes
): array {

    $currentDate = date("Y-m-d");
    $currentTime = date("H:i:s");

    $isToday = ($selectedDate === $currentDate);

    $businessStartTime = BUSINESS_OPENING_TIME;
    $businessEndTime = BUSINESS_CLOSING_TIME;
    $timeIntervalMinutes = BOOKING_TIME_INTERVAL_MINUTES;

    $currentDateTime = new DateTime($currentDate . " " . $currentTime);
    $businessStartDateTime = new DateTime($selectedDate . " " . $businessStartTime);
    $businessEndDateTime = new DateTime($selectedDate . " " . $businessEndTime);


    // =================================================
    // CHECK HOLIDAY
    // =================================================

    $isHoliday = false;
    $holidayName = null;

    $holidayQuery = "SELECT holiday_name FROM holidays WHERE holiday_date = ? LIMIT 1";
    $holidayStatement = mysqli_prepare($databaseConnection, $holidayQuery);
    mysqli_stmt_bind_param($holidayStatement, "s", $selectedDate);
    mysqli_stmt_execute($holidayStatement);
    $holidayResult = mysqli_stmt_get_result($holidayStatement);

    if ($holidayRow = mysqli_fetch_assoc($holidayResult)) {
        $isHoliday = true;
        $holidayName = $holidayRow["holiday_name"];
    }

    mysqli_stmt_close($holidayStatement);


    $bookingStatus = "no_schedule";

    if ($isHoliday) {
        $bookingStatus = "closed";
    } elseif ($isToday && $currentDateTime >= $businessEndDateTime) {
        $bookingStatus = "closed";
    }

    if ($bookingStatus === "closed") {
        return [
            "status" => "closed",
            "holiday_name" => $holidayName,
            "slots" => [],
        ];
    }


    // =================================================
    // GET BARBER SCHEDULES FOR THIS DAY OF THE WEEK
    // (skip any barber already at their daily capacity)
    // =================================================

    $selectedDateObject = new DateTime($selectedDate);
    $selectedDay = $selectedDateObject->format("l");

    $barberDailyLoad = getBarberDailyLoad($databaseConnection, $selectedDate);

    $barberSchedules = [];

    $scheduleQuery = "
        SELECT bs.barber_id, bs.schedule_day, bs.start_time, bs.end_time, bs.schedule_status
        FROM barber_schedules bs
        INNER JOIN barbers b ON b.barber_id = bs.barber_id
        WHERE bs.schedule_day = ?
        AND bs.schedule_status = 'Available'
        AND b.barber_status = 'Available'
        ORDER BY bs.barber_id ASC
    ";

    $scheduleStatement = mysqli_prepare($databaseConnection, $scheduleQuery);
    mysqli_stmt_bind_param($scheduleStatement, "s", $selectedDay);
    mysqli_stmt_execute($scheduleStatement);
    $scheduleResult = mysqli_stmt_get_result($scheduleStatement);

    $anyScheduleExistedBeforeCapacityFilter = false;

    while ($barberSchedule = mysqli_fetch_assoc($scheduleResult)) {

        $anyScheduleExistedBeforeCapacityFilter = true;
        $scheduleBarberId = (int) $barberSchedule["barber_id"];

        if (($barberDailyLoad[$scheduleBarberId] ?? 0) >= BARBER_MAX_DAILY_SLOTS) {
            continue;
        }

        $barberSchedules[] = $barberSchedule;
    }

    mysqli_stmt_close($scheduleStatement);

    if (empty($barberSchedules)) {
        return [
            "status" => $anyScheduleExistedBeforeCapacityFilter ? "fully_booked" : "no_schedule",
            "holiday_name" => null,
            "slots" => [],
        ];
    }


    // =================================================
    // GET EXISTING APPOINTMENTS FOR THIS DATE
    // =================================================

    $appointmentQuery = "
        SELECT barber_id, appointment_start_time, appointment_end_time
        FROM appointments
        WHERE appointment_date = ?
        AND appointment_status IN ('Pending', 'Confirmed', 'Waiting')
        AND barber_id IS NOT NULL
    ";

    $appointmentStatement = mysqli_prepare($databaseConnection, $appointmentQuery);
    mysqli_stmt_bind_param($appointmentStatement, "s", $selectedDate);
    mysqli_stmt_execute($appointmentStatement);
    $appointmentResult = mysqli_stmt_get_result($appointmentStatement);

    $existingAppointments = [];

    while ($appointment = mysqli_fetch_assoc($appointmentResult)) {
        $existingAppointments[] = $appointment;
    }

    mysqli_stmt_close($appointmentStatement);


    // =================================================
    // GENERATE TIME SLOTS
    // =================================================

    $availableTimeSlots = [];
    $bookingStatus = "fully_booked";

    foreach ($barberSchedules as $barberSchedule) {

        $barberId = (int) $barberSchedule["barber_id"];

        $scheduleStart = new DateTime($selectedDate . " " . $barberSchedule["start_time"]);
        $scheduleEnd = new DateTime($selectedDate . " " . $barberSchedule["end_time"]);

        if ($scheduleStart < $businessStartDateTime) {
            $scheduleStart = clone $businessStartDateTime;
        }

        if ($scheduleEnd > $businessEndDateTime) {
            $scheduleEnd = clone $businessEndDateTime;
        }

        if ($scheduleStart >= $scheduleEnd) {
            continue;
        }

        $slotStart = clone $scheduleStart;

        if ($isToday && $slotStart <= $currentDateTime) {

            $minutesNow = ((int) $currentDateTime->format("H") * 60) + (int) $currentDateTime->format("i");

            $nextSlotMinutes = ceil($minutesNow / $timeIntervalMinutes) * $timeIntervalMinutes;

            $nextSlotHour = (int) floor($nextSlotMinutes / 60);
            $nextSlotMinute = $nextSlotMinutes % 60;

            if ($nextSlotMinutes >= 19 * 60) {
                continue;
            }

            $slotStart = new DateTime(
                $selectedDate . " " . sprintf("%02d:%02d:00", $nextSlotHour, $nextSlotMinute)
            );
        }

        while (true) {

            $slotEnd = clone $slotStart;
            $slotEnd->modify("+" . $serviceDurationMinutes . " minutes");

            if ($slotEnd > $businessEndDateTime) {
                break;
            }

            if ($slotEnd > $scheduleEnd) {
                break;
            }

            if ($isToday && $slotStart <= $currentDateTime) {
                $slotStart->modify("+" . $timeIntervalMinutes . " minutes");
                continue;
            }

            $barberIsAvailable = true;

            foreach ($existingAppointments as $existingAppointment) {

                if ((int) $existingAppointment["barber_id"] !== $barberId) {
                    continue;
                }

                $existingStart = new DateTime($selectedDate . " " . $existingAppointment["appointment_start_time"]);
                $existingEnd = new DateTime($selectedDate . " " . $existingAppointment["appointment_end_time"]);

                if ($slotStart < $existingEnd && $slotEnd > $existingStart) {
                    $barberIsAvailable = false;
                    break;
                }
            }

            if ($barberIsAvailable) {

                $timeValue = $slotStart->format("H:i:s");
                $timeDisplay = $slotStart->format("h:i A");

                if (!isset($availableTimeSlots[$timeValue])) {
                    $availableTimeSlots[$timeValue] = $timeDisplay;
                }

                $bookingStatus = "available";
            }

            $slotStart->modify("+" . $timeIntervalMinutes . " minutes");
        }
    }

    ksort($availableTimeSlots);

    if (empty($availableTimeSlots)) {
        $bookingStatus = "fully_booked";
    }

    return [
        "status" => $bookingStatus,
        "holiday_name" => null,
        "slots" => $availableTimeSlots,
    ];
}


/**
 * Given the list of open slots (from getBookingAvailability) and
 * the time the customer typed in, find the closest actual open
 * slot. Returns null if there are no open slots at all.
 */
function findClosestAvailableTime(array $availableTimeSlots, string $preferredTime): ?string
{
    if (empty($availableTimeSlots)) {
        return null;
    }

    $preferredSeconds = strtotime("2000-01-01 " . $preferredTime);

    $closestTime = null;
    $closestDifference = PHP_INT_MAX;

    foreach ($availableTimeSlots as $timeValue => $timeDisplay) {

        $slotSeconds = strtotime("2000-01-01 " . $timeValue);
        $difference = abs($slotSeconds - $preferredSeconds);

        if ($difference < $closestDifference) {
            $closestDifference = $difference;
            $closestTime = $timeValue;
        }
    }

    return $closestTime;
}


/**
 * A customer is only allowed ONE active appointment at a time.
 * "Active" means anything that hasn't reached a final state yet
 * (Completed, Cancelled, No Show). Returns the active appointment
 * row (with a bit of extra info for a friendly message) or null
 * if the customer is free to book.
 */
function getCustomerActiveAppointment(mysqli $databaseConnection, int $customerId): ?array
{
    $query = "
        SELECT
            a.appointment_id, a.appointment_code, a.appointment_date,
            a.appointment_status, s.service_name
        FROM appointments a
        INNER JOIN services s ON s.service_id = a.service_id
        WHERE a.customer_id = ?
        AND a.appointment_status NOT IN ('Completed', 'Cancelled', 'No Show')
        ORDER BY a.created_at DESC
        LIMIT 1
    ";

    $statement = mysqli_prepare($databaseConnection, $query);
    mysqli_stmt_bind_param($statement, "i", $customerId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $activeAppointment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($statement);

    return $activeAppointment ?: null;
}


/**
 * For a specific date + exact time slot, work out which barbers
 * are free and which are busy. Used so the customer can pick a
 * specific barber instead of letting the system auto-assign one.
 *
 * @return array<int, array{
 *     barber_id:int, barber_name:string, is_available:bool,
 *     is_full:bool, slots_used:int, slots_total:int
 * }>
 */
function getBarberAvailabilityAtTime(
    mysqli $databaseConnection,
    string $selectedDate,
    string $timeValue,
    int $serviceDurationMinutes
): array {

    $selectedDateObject = new DateTime($selectedDate);
    $selectedDay = $selectedDateObject->format("l");

    $slotStart = new DateTime($selectedDate . " " . $timeValue);
    $slotEnd = clone $slotStart;
    $slotEnd->modify("+" . $serviceDurationMinutes . " minutes");

    $barberDailyLoad = getBarberDailyLoad($databaseConnection, $selectedDate);


    // =================================================
    // ONLY BARBERS SCHEDULED TO WORK THAT DAY, AND WHOSE
    // SHIFT ACTUALLY COVERS THIS TIME RANGE, ARE LISTED
    // =================================================

    $barberQuery = "
        SELECT b.barber_id, b.barber_name
        FROM barbers b
        INNER JOIN barber_schedules bs ON bs.barber_id = b.barber_id
        WHERE b.barber_status = 'Available'
        AND bs.schedule_day = ?
        AND bs.schedule_status = 'Available'
        AND bs.start_time <= ?
        AND bs.end_time >= ?
        ORDER BY b.barber_name ASC
    ";

    $statement = mysqli_prepare($databaseConnection, $barberQuery);

    $slotStartTime = $slotStart->format("H:i:s");
    $slotEndTime = $slotEnd->format("H:i:s");

    mysqli_stmt_bind_param($statement, "sss", $selectedDay, $slotStartTime, $slotEndTime);
    mysqli_stmt_execute($statement);
    $barberResult = mysqli_stmt_get_result($statement);

    $barbers = [];

    while ($barber = mysqli_fetch_assoc($barberResult)) {
        $barbers[] = $barber;
    }

    mysqli_stmt_close($statement);


    // =================================================
    // EXISTING APPOINTMENTS THAT DAY (TO DETECT CONFLICTS)
    // =================================================

    $appointmentQuery = "
        SELECT barber_id, appointment_start_time, appointment_end_time
        FROM appointments
        WHERE appointment_date = ?
        AND appointment_status IN ('Pending', 'Confirmed', 'Waiting')
        AND barber_id IS NOT NULL
    ";

    $statement = mysqli_prepare($databaseConnection, $appointmentQuery);
    mysqli_stmt_bind_param($statement, "s", $selectedDate);
    mysqli_stmt_execute($statement);
    $appointmentResult = mysqli_stmt_get_result($statement);

    $existingAppointments = [];

    while ($appointment = mysqli_fetch_assoc($appointmentResult)) {
        $existingAppointments[] = $appointment;
    }

    mysqli_stmt_close($statement);


    // =================================================
    // MARK EACH BARBER AS AVAILABLE, BUSY (time conflict),
    // OR FULL (daily capacity reached) FOR THIS SLOT
    // =================================================

    $result = [];

    foreach ($barbers as $barber) {

        $barberId = (int) $barber["barber_id"];
        $slotsUsed = $barberDailyLoad[$barberId] ?? 0;
        $isFull = $slotsUsed >= BARBER_MAX_DAILY_SLOTS;

        $hasTimeConflict = false;

        foreach ($existingAppointments as $existingAppointment) {

            if ((int) $existingAppointment["barber_id"] !== $barberId) {
                continue;
            }

            $existingStart = new DateTime($selectedDate . " " . $existingAppointment["appointment_start_time"]);
            $existingEnd = new DateTime($selectedDate . " " . $existingAppointment["appointment_end_time"]);

            if ($slotStart < $existingEnd && $slotEnd > $existingStart) {
                $hasTimeConflict = true;
                break;
            }
        }

        $result[] = [
            "barber_id" => $barberId,
            "barber_name" => $barber["barber_name"],
            "is_available" => !$hasTimeConflict && !$isFull,
            "is_full" => $isFull,
            "slots_used" => $slotsUsed,
            "slots_total" => BARBER_MAX_DAILY_SLOTS,
        ];
    }

    return $result;
}


/**
 * Minutes a "Waiting" appointment is given before it's treated as
 * a no-show.
 */
define("WAITING_GRACE_PERIOD_MINUTES", 10);


/**
 * If this barber has no other currently-Waiting appointment, free
 * them back up to "Available". Never touches a barber who has been
 * manually marked "Offline" for the day.
 */
function releaseBarberIfFree(mysqli $databaseConnection, ?int $barberId): void
{
    if (!$barberId) {
        return;
    }

    $stillWaitingQuery = "
        SELECT appointment_id FROM appointments
        WHERE barber_id = ? AND appointment_status = 'Waiting'
        LIMIT 1
    ";

    $statement = mysqli_prepare($databaseConnection, $stillWaitingQuery);
    mysqli_stmt_bind_param($statement, "i", $barberId);
    mysqli_stmt_execute($statement);
    $stillHasWaitingCustomer = mysqli_num_rows(mysqli_stmt_get_result($statement)) > 0;
    mysqli_stmt_close($statement);

    if ($stillHasWaitingCustomer) {
        return;
    }

    $releaseQuery = "
        UPDATE barbers SET barber_status = 'Available'
        WHERE barber_id = ? AND barber_status != 'Offline'
    ";

    $statement = mysqli_prepare($databaseConnection, $releaseQuery);
    mysqli_stmt_bind_param($statement, "i", $barberId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}


/**
 * "Lazy cron": call this at the top of any page that shows live
 * queue/appointment data. Moves appointments through their natural
 * lifecycle automatically, with no real cron job required:
 *
 *   - Pending (unpaid) appointments whose scheduled time has already
 *     passed are auto-Cancelled — nobody ever confirmed payment in
 *     time, so the slot is released.
 *   - Confirmed (paid, scheduled) appointments where the customer
 *     never got marked "Waiting" within the grace period after their
 *     start time are auto-marked "No Show".
 *   - "Waiting" appointments — meaning the customer HAS checked in
 *     and is currently being served — automatically become
 *     "Completed" once the service's full duration has elapsed
 *     since they were marked Waiting. (Admin can still mark it
 *     Completed earlier or later by hand if needed.)
 *
 * In every case, the barber is freed up again afterward.
 */
function autoProgressAppointments(mysqli $databaseConnection): void
{

    // =================================================
    // 1) STALE UNPAID BOOKINGS -> Cancelled
    // =================================================

    $staleUnpaidQuery = "
        SELECT appointment_id, barber_id
        FROM appointments
        WHERE appointment_status = 'Pending'
        AND barber_id IS NOT NULL
        AND appointment_start_time IS NOT NULL
        AND TIMESTAMP(appointment_date, appointment_start_time) < NOW()
    ";

    $staleUnpaidResult = mysqli_query($databaseConnection, $staleUnpaidQuery);
    $staleUnpaidAppointments = mysqli_fetch_all($staleUnpaidResult, MYSQLI_ASSOC);

    foreach ($staleUnpaidAppointments as $appointment) {

        $statement = mysqli_prepare($databaseConnection, "
            UPDATE appointments SET appointment_status = 'Cancelled', status_updated_at = NOW()
            WHERE appointment_id = ?
        ");
        mysqli_stmt_bind_param($statement, "i", $appointment["appointment_id"]);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        releaseBarberIfFree($databaseConnection, (int) $appointment["barber_id"]);
    }


    // =================================================
    // 2) CONFIRMED BUT NEVER CHECKED IN -> No Show
    // =================================================

    $missedCheckInQuery = "
        SELECT appointment_id, barber_id
        FROM appointments
        WHERE appointment_status = 'Confirmed'
        AND barber_id IS NOT NULL
        AND appointment_start_time IS NOT NULL
        AND DATE_ADD(TIMESTAMP(appointment_date, appointment_start_time), INTERVAL " . WAITING_GRACE_PERIOD_MINUTES . " MINUTE) <= NOW()
    ";

    $missedCheckInResult = mysqli_query($databaseConnection, $missedCheckInQuery);
    $missedCheckInAppointments = mysqli_fetch_all($missedCheckInResult, MYSQLI_ASSOC);

    foreach ($missedCheckInAppointments as $appointment) {

        $statement = mysqli_prepare($databaseConnection, "
            UPDATE appointments SET appointment_status = 'No Show', status_updated_at = NOW()
            WHERE appointment_id = ?
        ");
        mysqli_stmt_bind_param($statement, "i", $appointment["appointment_id"]);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        releaseBarberIfFree($databaseConnection, (int) $appointment["barber_id"]);
    }


    // =================================================
    // 3) SERVICE DURATION FINISHED -> Completed
    // =================================================

    $finishedServiceQuery = "
        SELECT a.appointment_id, a.barber_id
        FROM appointments a
        INNER JOIN services s ON s.service_id = a.service_id
        WHERE a.appointment_status = 'Waiting'
        AND DATE_ADD(a.status_updated_at, INTERVAL s.estimated_duration_minutes MINUTE) <= NOW()
    ";

    $finishedServiceResult = mysqli_query($databaseConnection, $finishedServiceQuery);
    $finishedServiceAppointments = mysqli_fetch_all($finishedServiceResult, MYSQLI_ASSOC);

    foreach ($finishedServiceAppointments as $appointment) {

        $statement = mysqli_prepare($databaseConnection, "
            UPDATE appointments SET appointment_status = 'Completed', status_updated_at = NOW()
            WHERE appointment_id = ?
        ");
        mysqli_stmt_bind_param($statement, "i", $appointment["appointment_id"]);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        releaseBarberIfFree($databaseConnection, (int) $appointment["barber_id"]);
    }
}


/**
 * Builds the list of items shown in the customer's notification bell:
 *   - active bookings (Pending/Confirmed/Waiting), with payment status
 *     and a live countdown where relevant
 *   - recently Cancelled/No Show bookings (last 7 days), so the customer
 *     still sees the notice even after it becomes final
 *   - their own refund requests still awaiting review
 *   - a reminder to verify their email, if they haven't yet
 *
 * Each item: icon, title, subtitle, countdown_target (unix timestamp or
 * null), countdown_done_label, badge_class, and optionally action_link +
 * action_label for a clickable action (e.g. "Resend verification email").
 */
function getCustomerNotifications(mysqli $databaseConnection, int $customerId): array
{
    $notifications = [];


    // =================================================
    // EMAIL VERIFICATION REMINDER
    // =================================================

    $statement = mysqli_prepare($databaseConnection, "SELECT email_verified_at FROM customers WHERE customer_id = ? LIMIT 1");
    mysqli_stmt_bind_param($statement, "i", $customerId);
    mysqli_stmt_execute($statement);
    $emailVerifiedAt = mysqli_fetch_assoc(mysqli_stmt_get_result($statement))["email_verified_at"] ?? null;
    mysqli_stmt_close($statement);

    if (!$emailVerifiedAt) {

        $notifications[] = [
            "icon" => "bi-envelope-exclamation",
            "title" => "Please verify your email",
            "subtitle" => "Check your inbox for the verification link we sent you.",
            "countdown_target" => null,
            "countdown_done_label" => null,
            "badge_class" => "status-pending",
            "action_link" => "auth/resend_verification.php",
            "action_label" => "Resend verification email",
        ];
    }


    // =================================================
    // ACTIVE BOOKINGS
    // =================================================

    $activeQuery = "
        SELECT
            a.appointment_code, a.appointment_status, a.status_updated_at,
            a.appointment_date, a.appointment_start_time,
            s.service_name, s.estimated_duration_minutes,
            (
                SELECT p.payment_status FROM payments p
                WHERE p.appointment_id = a.appointment_id
                ORDER BY p.created_at DESC LIMIT 1
            ) AS latest_payment_status
        FROM appointments a
        INNER JOIN services s ON s.service_id = a.service_id
        WHERE a.customer_id = ?
        AND a.appointment_status IN ('Pending', 'Confirmed', 'Waiting')
        ORDER BY a.appointment_date ASC, a.created_at DESC
        LIMIT 5
    ";

    $statement = mysqli_prepare($databaseConnection, $activeQuery);
    mysqli_stmt_bind_param($statement, "i", $customerId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    while ($row = mysqli_fetch_assoc($result)) {

        $paymentLabel = match ($row["latest_payment_status"]) {
            "Verified" => "Payment confirmed",
            "Pending" => "Payment awaiting verification",
            "Rejected" => "Payment was rejected — please pay again",
            default => "Payment not yet submitted",
        };

        $countdownTarget = null;
        $countdownDoneLabel = null;
        $subtitle = $paymentLabel;

        if ($row["appointment_status"] === "Waiting") {

            $countdownTarget = strtotime($row["status_updated_at"]) + ((int) $row["estimated_duration_minutes"] * 60);
            $countdownDoneLabel = "All done!";
            $subtitle = "You're being served now — finishing in";

        } elseif ($row["appointment_status"] === "Confirmed" && $row["appointment_start_time"]) {

            $countdownTarget = strtotime($row["appointment_date"] . " " . $row["appointment_start_time"]);
            $countdownDoneLabel = "Starting now";
            $subtitle = $paymentLabel . " — starts in";
        }

        $notifications[] = [
            "icon" => "bi-calendar-check",
            "title" => $row["service_name"] . " (" . $row["appointment_code"] . ")",
            "subtitle" => $subtitle,
            "countdown_target" => $countdownTarget,
            "countdown_done_label" => $countdownDoneLabel,
            "badge_class" => "status-pending",
        ];
    }

    mysqli_stmt_close($statement);


    // =================================================
    // RECENTLY CANCELLED / NO SHOW (LAST 7 DAYS)
    // =================================================

    $recentTerminalQuery = "
        SELECT a.appointment_code, a.appointment_status, s.service_name
        FROM appointments a
        INNER JOIN services s ON s.service_id = a.service_id
        WHERE a.customer_id = ?
        AND a.appointment_status IN ('Cancelled', 'No Show')
        AND a.status_updated_at >= (NOW() - INTERVAL 7 DAY)
        ORDER BY a.status_updated_at DESC
        LIMIT 5
    ";

    $statement = mysqli_prepare($databaseConnection, $recentTerminalQuery);
    mysqli_stmt_bind_param($statement, "i", $customerId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    while ($row = mysqli_fetch_assoc($result)) {

        $isCancelled = $row["appointment_status"] === "Cancelled";

        $notifications[] = [
            "icon" => $isCancelled ? "bi-x-circle" : "bi-exclamation-circle",
            "title" => $row["service_name"] . " (" . $row["appointment_code"] . ")",
            "subtitle" => $isCancelled
                ? "This booking was cancelled."
                : "Cooldown time ended — please book again or visit us for a walk-in.",
            "countdown_target" => null,
            "countdown_done_label" => null,
            "badge_class" => "status-cancelled",
        ];
    }

    mysqli_stmt_close($statement);


    // =================================================
    // REFUND REQUESTS STILL PENDING REVIEW
    // =================================================

    $refundQuery = "
        SELECT refund_request_id
        FROM refund_requests
        WHERE customer_id = ? AND request_status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 3
    ";

    $statement = mysqli_prepare($databaseConnection, $refundQuery);
    mysqli_stmt_bind_param($statement, "i", $customerId);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    while ($row = mysqli_fetch_assoc($result)) {

        $notifications[] = [
            "icon" => "bi-cash-coin",
            "title" => "Refund Request Submitted",
            "subtitle" => "Our staff is reviewing your refund request.",
            "countdown_target" => null,
            "countdown_done_label" => null,
            "badge_class" => "status-pending",
        ];
    }

    mysqli_stmt_close($statement);

    return $notifications;
}

?>
