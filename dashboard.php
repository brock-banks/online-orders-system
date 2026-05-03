<?php
include 'header.php'; // Ensure this includes session checks and user verification


// Handle "Add Delivery Person" form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_delivery_person'])) {
    $name = $_POST['name'];

    // Validate the input
    if (empty($name)) {
        $error = "Name cannot be empty!";
    } else {
        // Add the delivery person to the database
        $query = query("INSERT INTO delivery_people (name) VALUES (?)", [$name]);

        // Check if the query was successful
        if ($query) {
            $success = "Delivery person added successfully!";
        } else {
            $error = "Failed to add delivery person. Please try again.";
        }
    }
}

// Fetch statistics from the database
$totalOrders = query("SELECT COUNT(*) AS total FROM orders")->fetch()['total'];
$deliveredOrders = query("SELECT COUNT(*) AS total FROM orders WHERE status = 'delivered'")->fetch()['total'];
$pendingOrders = $totalOrders - $deliveredOrders; // Calculate pending orders
$totalArchivedOrders = query("SELECT COUNT(*) AS total FROM archived_orders")->fetch()['total']; // Total Archived Orders

// Fetch recent activities
$recentActivities = query("SELECT * FROM orders ORDER BY date DESC LIMIT 5")->fetchAll();

// Fetch top delivery persons
$topDeliveryPersons = query("SELECT delivered_by, COUNT(*) AS total_deliveries 
                              FROM orders WHERE delivered_by IS NOT NULL 
                              GROUP BY delivered_by ORDER BY total_deliveries DESC LIMIT 5")->fetchAll();

// Fetch delivery trends for the last 5 months (real data for chart)
$deliveryTrendLabels = [];
$deliveryTrendCounts = [];
try {
    $trendsResult = query(
        "SELECT DATE_FORMAT(date, '%b %Y') AS month_label, COUNT(*) AS cnt
         FROM orders
         WHERE status = 'delivered' AND date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY YEAR(date), MONTH(date)
         ORDER BY YEAR(date), MONTH(date)"
    )->fetchAll();
    foreach ($trendsResult as $tr) {
        $deliveryTrendLabels[] = $tr['month_label'];
        $deliveryTrendCounts[] = (int)$tr['cnt'];
    }
} catch (Exception $e) {
    error_log('Dashboard delivery trends error: ' . $e->getMessage());
}
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container mt-5">
    <h2 class="text-primary text-center mb-4">Admin Dashboard</h2>
    
    <!-- Statistics Section -->
    <div class="row text-center mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Orders</h5>
                    <p class="card-text fs-2"><?php echo $totalOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Archived Orders</h5>
                    <p class="card-text fs-2"><?php echo $totalArchivedOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Delivered Orders</h5>
                    <p class="card-text fs-2"><?php echo $deliveredOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Pending Orders</h5>
                    <p class="card-text fs-2"><?php echo $pendingOrders; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts (success/error feedback) -->
    <?php if (isset($success)): ?>
        <div class="alert alert-success text-center"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

<!-- Filters Section -->
<div class="card shadow mb-4">
    <div class="card-body">
        <h3 class="card-title text-center">Filter and Sort Orders</h3>
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="">All</option>
                    <option value="delivered">Delivered</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="deliveredBy" class="form-label">Delivered By</label>
                <input type="text" class="form-control" id="deliveredBy" name="deliveredBy" placeholder="Enter delivery person">
            </div>
            <div class="col-md-3">
                <label for="dateFrom" class="form-label">Date From</label>
                <input type="date" class="form-control" id="dateFrom" name="dateFrom">
            </div>
            <div class="col-md-3">
                <label for="dateTo" class="form-label">Date To</label>
                <input type="date" class="form-control" id="dateTo" name="dateTo">
            </div>
            <div class="col-md-12 text-center">
                <button type="button" class="btn btn-primary" id="applyFilters">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

    <!-- All Orders Table with Live Search -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h3 class="card-title text-center">All Orders</h3>
            <!-- Live Search Input -->
            <div class="mb-3">
                <input type="text" id="searchOrders" class="form-control" placeholder="Search orders by ID, User ID, Details, Invoice No, Phone, Address, Delivered By, or Status">
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>User ID</th>
                            <th>Details</th>
                            <th>Invoice No</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Date</th>
                            <th>Delivered By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <!-- Orders will be dynamically loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<!-- Charts Section -->
<div class="row mb-4">
        <div class="col-md-6">
            <canvas id="orderStatusChart"></canvas>
        </div>
        <div class="col-md-6">
            <canvas id="deliveryTrendsChart"></canvas>
        </div>
    </div>

    <!-- Recent Activities Section -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h3 class="card-title text-center">Recent Activities</h3>
            <ul class="list-group">
                <?php foreach ($recentActivities as $activity): ?>
                    <li class="list-group-item">
                        Order #<?php echo $activity['id']; ?> - <?php echo $activity['status']; ?> - <?php echo $activity['date']; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Top Delivery Persons Section -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h3 class="card-title text-center">Top Delivery Persons</h3>
            <ul class="list-group">
                <?php foreach ($topDeliveryPersons as $person): ?>
                    <li class="list-group-item">
                        <?php echo $person['delivered_by']; ?> - <?php echo $person['total_deliveries']; ?> Deliveries
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Admin Tools (collapsible) -->
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">⚙️ Admin Tools</h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#adminToolsCollapse" aria-expanded="false" aria-controls="adminToolsCollapse">
                Show / Hide
            </button>
        </div>
        <div class="collapse" id="adminToolsCollapse">
            <div class="card-body">
                <div class="row g-4">
                    <!-- Add User Form -->
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Add User</h6>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-control" id="role" name="role">
                                    <option value="admin">Admin</option>
                                    <option value="user">User</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" name="add_user">Add User</button>
                        </form>
                    </div>
                    <!-- Add Delivery Person Form -->
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Add Delivery Person</h6>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" name="add_delivery_person">Add Delivery Person</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Single consolidated loadOrders function
    function loadOrders(filters) {
        // If called from live-search with a string, treat it as a text search across all fields
        if (typeof filters === 'string') {
            filters = { search: filters };
        }
        if (!filters) filters = {};

        $.ajax({
            url: "fetch_orders_filter.php",
            type: "GET",
            data: filters,
            success: function(data) {
                if (data && data.error) {
                    alert(data.error);
                    return;
                }

                if (!Array.isArray(data)) {
                    $("#ordersTable").html(data);
                    return;
                }

                let rows = "";
                data.forEach(order => {
                    rows += `
                        <tr>
                            <td>${order.id}</td>
                            <td>${order.user_id}</td>
                            <td>${order.details}</td>
                            <td>${order.invoice_no}</td>
                            <td>${order.phone}</td>
                            <td>${order.address}</td>
                            <td>${order.date}</td>
                            <td>${order.delivered_by ?? ''}</td>
                            <td>${order.status}</td>
                        </tr>
                    `;
                });

                if (rows === "") {
                    rows = `<tr><td colspan="9" class="text-center text-muted py-3">No orders match the current filters.</td></tr>`;
                }

                $("#ordersTable").html(rows);
            },
            error: function(xhr, status, error) {
                console.error("Error fetching orders:", error);
                alert("Failed to fetch orders. Please try again.");
            }
        });
    }

    // Load all orders on page load with no filters
    loadOrders();

    // Live search
    $("#searchOrders").on("input", function() {
        loadOrders({ search: $(this).val() });
    });

    // Apply filters on button click
    $("#applyFilters").on("click", function () {
        const filters = {
            status: $("#status").val(),
            deliveredBy: $("#deliveredBy").val(),
            dateFrom: $("#dateFrom").val(),
            dateTo: $("#dateTo").val()
        };
        loadOrders(filters);
    });

    // Charts
    const ctx1 = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: ['Delivered', 'Pending'],
            datasets: [{
                data: [<?php echo (int)$deliveredOrders; ?>, <?php echo (int)$pendingOrders; ?>],
                backgroundColor: ['#28a745', '#ffc107']
            }]
        }
    });

    const ctx2 = document.getElementById('deliveryTrendsChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($deliveryTrendLabels); ?>,
            datasets: [{
                label: 'Delivered Orders',
                data: <?php echo json_encode($deliveryTrendCounts); ?>,
                borderColor: '#007bff',
                fill: false
            }]
        }
    });
</script>

<?php
include 'footer.php';
?>
</body>
</html>