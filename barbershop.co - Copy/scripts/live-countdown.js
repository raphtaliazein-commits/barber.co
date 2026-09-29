// =====================================================
// LIVE COUNTDOWN + AUTO-REFRESH
// Shared by any page that shows a live queue / cooldown
// timer (index.php, pages/my_bookings.php).
// =====================================================

(function () {

    const countdownElements = document.querySelectorAll(".live-countdown[data-countdown-target]");

    if (countdownElements.length === 0) {
        return;
    }

    function formatRemainingTime(totalSeconds) {

        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        const minutesText = String(minutes).padStart(2, "0") + "min";
        const secondsText = String(seconds).padStart(2, "0") + "sec";

        if (hours > 0) {
            return hours + "h " + minutesText + " : " + secondsText;
        }

        return minutesText + " : " + secondsText;
    }

    function updateCountdowns() {

        const nowInSeconds = Math.floor(Date.now() / 1000);

        countdownElements.forEach(function (element) {

            const targetInSeconds = parseInt(element.dataset.countdownTarget, 10);
            const remainingSeconds = targetInSeconds - nowInSeconds;

            if (remainingSeconds <= 0) {
                element.textContent = element.dataset.countdownDone || "00:00";
                element.classList.add("countdown-done");
                return;
            }

            element.textContent = formatRemainingTime(remainingSeconds);
        });
    }

    updateCountdowns();
    setInterval(updateCountdowns, 1000);

})();


// =====================================================
// AUTO-REFRESH
// Opt in per page with <body data-auto-refresh-seconds="45">
// so the queue/status always reflects what the admin has
// most recently set, without the visitor lifting a finger.
// =====================================================

(function () {

    const refreshSeconds = parseInt(document.body.dataset.autoRefreshSeconds || "0", 10);

    if (refreshSeconds > 0) {
        setTimeout(function () {
            window.location.reload();
        }, refreshSeconds * 1000);
    }

})();
