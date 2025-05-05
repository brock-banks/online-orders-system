<?php
include 'header.php'; // Include header for navigation and session checks

// Ensure the user is logged in and has the appropriate permissions
if (!isLoggedIn()) {
    redirect('index.php');
}

// Archive logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_order'])) {
    $orderId = $_POST['order_id'];

    try {
        // Fetch the order to archive
        $query = query("SELECT * FROM orders WHERE id = ?", [$orderId]);
        $order = $query->fetch();

        if ($order) {
            // Insert the order into the archived_orders table
            query("INSERT INTO archived_orders (id, user_id, invoice_no, details, phone, address, date, delivered_by, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $order['id'],
                $order['user_id'],
                $order['invoice_no'],
                $order['details'],
                $order['phone'],
                $order['address'],
                $order['date'],
                $order['delivered_by'],
                $order['status']
            ]);

            // Delete the order from the orders table
            query("DELETE FROM orders WHERE id = ?", [$orderId]);

            $success = "Order archived and deleted from the orders table successfully!";
        } else {
            $error = "Order not found.";
        }
    } catch (Exception $e) {
        $error = "Failed to archive the order. Please try again.";
    }
}

// Fetch all active orders initially
$ordersQuery = query("SELECT * FROM orders");
$orders = $ordersQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Orders</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">Archive Orders</h2>

    <!-- Search Bar -->
    <div class="mb-3">
        <input type="text" id="searchOrders" class="form-control" placeholder="Search orders by Invoice No, Details, Phone, Address, or Status">
    </div>

    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
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
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" name="archive_order" class="btn btn-danger btn-sm">Archive</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to perform live search
    function loadOrders(search = "") {
        $.ajax({
            url: "fetch_archive_orders.php", // Backend script to fetch filtered orders
            type: "GET",
            data: { search: search },
            success: function(data) {
                $("#ordersTable").html(data); // Populate the table with fetched data
            },
            error: function() {
                alert("Failed to fetch orders. Please try again.");
            }
        });
    }

    // Add event listener for live search
    $("#searchOrders").on("input", function() {
        const searchValue = $(this).val();
        loadOrders(searchValue); // Fetch orders based on search input
    });

</script>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>