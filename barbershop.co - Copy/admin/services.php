<?php

require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../db/database_connection.php";

$pageTitle = "Services";
$activeAdminPage = "services";

$servicesQuery = "SELECT * FROM services ORDER BY service_id ASC";
$servicesResult = mysqli_query($databaseConnection, $servicesQuery);
$servicesList = mysqli_fetch_all($servicesResult, MYSQLI_ASSOC);

require_once __DIR__ . "/../includes/admin_header.php";
?>

<div class="admin-panel">

    <div class="admin-panel-header">
        <h3><i class="bi bi-plus-circle"></i> Add / Edit Package</h3>
    </div>

    <form method="POST" action="../auth/admin_save_service.php" id="serviceForm">

        <input type="hidden" name="form_action" value="save_service">
        <input type="hidden" name="service_id" id="serviceIdInput" value="0">

        <div class="row g-3">

            <div class="col-md-4">
                <label>Package Name</label>
                <input type="text" name="service_name" id="serviceNameInput" class="admin-input" required>
            </div>

            <div class="col-md-4">
                <label>Description</label>
                <input type="text" name="service_description" id="serviceDescriptionInput" class="admin-input">
            </div>

            <div class="col-md-1">
                <label>Price (&#8369;)</label>
                <input type="number" step="0.01" min="0" name="service_price" id="servicePriceInput" class="admin-input" required>
            </div>

            <div class="col-md-1">
                <label>Minutes</label>
                <input type="number" min="10" name="estimated_duration_minutes" id="serviceDurationInput" class="admin-input" value="30" required>
            </div>

            <div class="col-md-1">
                <label>Status</label>
                <select name="service_status" id="serviceStatusInput" class="admin-input">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>

            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="admin-btn admin-btn-primary w-100">
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>

        </div>

    </form>

</div>


<div class="admin-panel mt-4">

    <div class="admin-panel-header">
        <h3><i class="bi bi-box-seam"></i> All Packages</h3>
    </div>

    <div class="admin-table-wrapper">

        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($servicesList as $index => $service): ?>

                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($service["service_name"]); ?></td>
                        <td><?php echo htmlspecialchars($service["service_description"] ?? ""); ?></td>
                        <td>&#8369;<?php echo number_format((float) $service["service_price"], 2); ?></td>
                        <td><?php echo (int) $service["estimated_duration_minutes"]; ?> mins</td>
                        <td>
                            <span class="status-badge <?php echo $service["service_status"] === "Available" ? "status-confirmed" : "status-cancelled"; ?>">
                                <?php echo htmlspecialchars($service["service_status"]); ?>
                            </span>
                        </td>
                        <td>

                            <button
                                type="button"
                                class="admin-btn admin-btn-small"
                                onclick='fillServiceForm(<?php echo json_encode($service); ?>)'
                            >
                                <i class="bi bi-pencil"></i>
                            </button>

                            <form method="POST" action="../auth/admin_save_service.php" class="d-inline" onsubmit="return confirm('Mark this package as unavailable?');">
                                <input type="hidden" name="form_action" value="delete_service">
                                <input type="hidden" name="service_id" value="<?php echo (int) $service["service_id"]; ?>">
                                <button type="submit" class="admin-btn admin-btn-small admin-btn-danger">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </form>

                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>
        </table>

    </div>

</div>


<script>

function fillServiceForm(service) {

    document.getElementById("serviceIdInput").value = service.service_id;
    document.getElementById("serviceNameInput").value = service.service_name;
    document.getElementById("serviceDescriptionInput").value = service.service_description || "";
    document.getElementById("servicePriceInput").value = service.service_price;
    document.getElementById("serviceDurationInput").value = service.estimated_duration_minutes;
    document.getElementById("serviceStatusInput").value = service.service_status;

    document.getElementById("serviceForm").scrollIntoView({ behavior: "smooth" });
}

</script>

<?php require_once __DIR__ . "/../includes/admin_footer.php"; ?>
