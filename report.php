<?php
require_once 'header.php'; // Include header for navigation and session checks

// Ensure the user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Fetch statistics from the database
$totalOrders = query("SELECT COUNT(*) AS total FROM orders")->fetch()['total'];
$totalArchivedOrders = query("SELECT COUNT(*) AS total FROM archived_orders")->fetch()['total'];
$deliveredOrders = query("SELECT COUNT(*) AS total FROM orders WHERE status = 'delivered'")->fetch()['total'];
$pendingOrders = $totalOrders - $deliveredOrders; // Calculate pending orders

// Fetch detailed data for the report table
$ordersQuery = query("SELECT * FROM orders");
$orders = $ordersQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Report</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .card-stat {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .table thead th {
            background-color: #007bff;
            color: white;
        }
        .filter-section {
            margin-bottom: 20px;
        }
        .export-btn {
            float: right;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-primary text-center mb-4">System Report</h2>

    <!-- Statistics Summary -->
    <div class="row text-center mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3 card-stat">
                <div class="card-body">
                    <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                    <h5 class="card-title">Total Orders</h5>
                    <p class="card-text fs-2"><?php echo $totalOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary mb-3 card-stat">
                <div class="card-body">
                    <i class="fas fa-archive fa-2x mb-2"></i>
                    <h5 class="card-title">Archived Orders</h5>
                    <p class="card-text fs-2"><?php echo $totalArchivedOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3 card-stat">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h5 class="card-title">Delivered Orders</h5>
                    <p class="card-text fs-2"><?php echo $deliveredOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3 card-stat">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h5 class="card-title">Pending Orders</h5>
                    <p class="card-text fs-2"><?php echo $pendingOrders; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Export Section -->
    <div class="filter-section">
        <form method="GET" class="row">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
        <button class="btn btn-success mt-3 export-btn"><i class="fas fa-file-export"></i> Export Report</button>
    </div>

    <!-- Detailed Report Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h3 class="card-title text-center">Detailed Orders Report</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User ID</th>
                            <th>Invoice No</th>
                            <th>Details</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Date</th>
                            <th>Delivered By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['invoice_no']); ?></td>
                                    <td><?php echo htmlspecialchars($order['details']); ?></td>
                                    <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($order['address']); ?></td>
                                    <td><?php echo htmlspecialchars($order['date']); ?></td>
                                    <td><?php echo htmlspecialchars($order['delivered_by']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($order['status'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>