<?php
// Expects $pageTitle and $activeAdminPage to be set before including this file.
$activeAdminPage = $activeAdminPage ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? "Admin - BARBERSHOP.CO"); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../styles/style.css?v=11">

</head>

<body class="admin-body">

<div class="admin-layout">

    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <aside class="admin-sidebar" id="adminSidebar">

        <a href="dashboard.php" class="admin-sidebar-logo">
            <span class="brand-icon"><i class="bi bi-scissors"></i></span>
            <span>BARBERSHOP<span class="brand-dot">.CO</span></span>
        </a>

        <nav class="admin-sidebar-nav">

            <a href="dashboard.php" class="admin-nav-link <?php echo $activeAdminPage === 'dashboard' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <a href="appointments.php" class="admin-nav-link <?php echo $activeAdminPage === 'appointments' ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i> Appointments
            </a>

            <a href="payments.php" class="admin-nav-link <?php echo $activeAdminPage === 'payments' ? 'active' : ''; ?>">
                <i class="bi bi-credit-card"></i> Payments
            </a>

            <a href="barbers.php" class="admin-nav-link <?php echo $activeAdminPage === 'barbers' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> Barbers
            </a>

            <a href="services.php" class="admin-nav-link <?php echo $activeAdminPage === 'services' ? 'active' : ''; ?>">
                <i class="bi bi-scissors"></i> Services
            </a>

            <a href="calendar.php" class="admin-nav-link <?php echo $activeAdminPage === 'calendar' ? 'active' : ''; ?>">
                <i class="bi bi-calendar3"></i> Calendar &amp; Holidays
            </a>

            <a href="reports.php" class="admin-nav-link <?php echo $activeAdminPage === 'reports' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart"></i> Reports
            </a>

            <a href="messages.php" class="admin-nav-link <?php echo $activeAdminPage === 'messages' ? 'active' : ''; ?>">
                <i class="bi bi-envelope"></i> Messages
            </a>

            <?php
            $pendingRefundRequestsCount = (int) mysqli_fetch_assoc(
                mysqli_query($databaseConnection, "SELECT COUNT(*) AS total FROM refund_requests WHERE request_status = 'Pending'")
            )["total"];
            ?>

            <a href="requests.php" class="admin-nav-link <?php echo $activeAdminPage === 'requests' ? 'active' : ''; ?>">
                <i class="bi bi-inbox"></i> Requests
                <?php if ($pendingRefundRequestsCount > 0): ?>
                    <span class="admin-nav-badge"><?php echo $pendingRefundRequestsCount; ?></span>
                <?php endif; ?>
            </a>

            <a href="profile.php" class="admin-nav-link <?php echo $activeAdminPage === 'profile' ? 'active' : ''; ?>">
                <i class="bi bi-person"></i> My Profile
            </a>

            <a href="../auth/logout.php" class="admin-nav-link admin-nav-logout">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>

        </nav>

    </aside>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <div class="admin-main">

        <header class="admin-topbar">

            <button type="button" class="admin-sidebar-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('show')">
                <i class="bi bi-list"></i>
            </button>

            <div class="admin-topbar-title">
                <?php echo htmlspecialchars($pageTitle ?? "Admin"); ?>
            </div>

            <div class="admin-topbar-user">
                <i class="bi bi-person-circle"></i>
                <span><?php echo htmlspecialchars($currentAdminName ?? "Administrator"); ?></span>
            </div>

        </header>

        <main class="admin-content">

            <div class="authentication-message-container">
                <?php displayAuthenticationMessage(); ?>
            </div>
