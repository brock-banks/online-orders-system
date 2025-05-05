<?php
require 'config.php';
require 'functions.php';



// Default date range (today)
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Append times to the date range
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

// Fetch total price
$totalPriceQuery = query(
    "SELECT SUM(price) as total_price FROM orders WHERE date BETWEEN ? AND ?",
    [$startDateTime, $endDateTime]
);
$totalPrice = $totalPriceQuery->fetch()['total_price'] ?? 0;

// Fetch total order count
$totalOrdersQuery = query(
    "SELECT COUNT(*) as total_orders FROM orders WHERE date BETWEEN ? AND ?",
    [$startDateTime, $endDateTime]
);
$totalOrders = $totalOrdersQuery->fetch()['total_orders'] ?? 0;

// Fetch pending orders count
$pendingOrdersQuery = query(
    "SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'pending' AND date BETWEEN ? AND ?",
    [$startDateTime, $endDateTime]
);
$pendingOrders = $pendingOrdersQuery->fetch()['pending_orders'] ?? 0;

// Fetch delivered orders count
$deliveredOrdersQuery = query(
    "SELECT COUNT(*) as delivered_orders FROM orders WHERE status = 'delivered' AND date BETWEEN ? AND ?",
    [$startDateTime, $endDateTime]
);
$deliveredOrders = $deliveredOrdersQuery->fetch()['delivered_orders'] ?? 0;

// Fetch orders for the table
$ordersQuery = query(
    "SELECT * FROM orders WHERE date BETWEEN ? AND ?",
    [$startDateTime, $endDateTime]
);
$orders = $ordersQuery->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php 
    include 'header.php'
    ?>
<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">View All Orders</h2>

    <!-- Date Filter Form -->
    <form class="row mb-4" method="GET" action="">
        <div class="col-md-4">
            <label for="start_date" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
        </div>
        <div class="col-md-4">
            <label for="end_date" class="form-label">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <!-- Summary Squares -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Price</h5>
                    <p class="card-text"><?php echo number_format($totalPrice, 2); ?> OMR</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Orders</h5>
                    <p class="card-text"><?php echo $totalOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Pending Orders</h5>
                    <p class="card-text"><?php echo $pendingOrders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3">
                <div class="card-body">
                    <h5 class="card-title">Delivered Orders</h5>
                    <p class="card-text"><?php echo $deliveredOrders; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>Invoice No</th>
                    <th>Details</th>
                    <th>Price</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['invoice_no']); ?></td>
                            <td><?php echo htmlspecialchars($order['details']); ?></td>
                            <td><?php echo number_format($order['price'], 2); ?> OMR</td>
                            <td><?php echo htmlspecialchars($order['phone']); ?></td>
                            <td><?php echo htmlspecialchars($order['address']); ?></td>
                            <td><?php echo htmlspecialchars($order['date']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($order['status'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No orders found for the selected date range.</td>
                    </tr>
                <?php endif; ?>
                
            </tbody>
        </table>
        <div class="row mb-4">
    <div class="col-md-4">
        <a href="export_orders_csv.php?start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>" 
           class="btn btn-success w-100">
           Export as CSV
        </a>
    </div>
</div>
    </div>
</div>
<?php
include 'footer.php'
?>
<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>