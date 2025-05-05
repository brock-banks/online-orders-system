<?php
require_once 'config.php'; // Include database configuration
require_once 'functions.php'; // Include helper functions

if (!isLoggedIn()) {
    redirect('index.php');
}

// Fetch all pending orders (older than 29 days and not "delivered")
$currentDate = new DateTime();
$currentDate->modify('-29 days'); // Subtract 29 days
$pendingOrders = query(
    "SELECT * FROM orders WHERE status != 'delivered' AND date <= ?",
    [$currentDate->format('Y-m-d')]
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .live-search-box {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?> <!-- Include the header -->
<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">Pending Orders</h2>
    <input type="text" id="liveSearch" class="form-control live-search-box" placeholder="Search for orders...">
    <div class="table-responsive">
        <table class="table table-striped table-bordered" id="ordersTable">
            <thead class="table-dark">
                <tr>
                    <th>Order ID</th>
                    <th>User ID</th>
                    <th>Details</th>
                    <th>Invoice Number</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th> <!-- New column for WhatsApp action -->
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pendingOrders)): ?>
                    <?php foreach ($pendingOrders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['details']); ?></td>
                            <td><?php echo htmlspecialchars($order['invoice_no']); ?></td>
                            <td><?php echo htmlspecialchars($order['phone']); ?></td>
                            <td><?php echo htmlspecialchars($order['address']); ?></td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td><?php echo htmlspecialchars($order['date']); ?></td>
                            <td>
                                <!-- Button to open WhatsApp with pre-filled message -->
                                <a 
                                    href="https://wa.me/<?php echo htmlspecialchars($order['phone']); ?>?text=<?php echo urlencode("اهلا بك 
عزيزنا العميل 
نود تذكيرك بأهمية استلام طلبك رقم #
{$order['invoice_no']}
تفادياً لأي عطل نتيجة تكدس البضاعه
"); ?>" 
                                    target="_blank" 
                                    class="btn btn-success btn-sm">
                                    Send WhatsApp
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No pending orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Live search functionality
    $(document).ready(function() {
        $('#liveSearch').on('keyup', function() {
            const value = $(this).val().toLowerCase();
            $('#ordersTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
    });
</script>
</body>
</html>