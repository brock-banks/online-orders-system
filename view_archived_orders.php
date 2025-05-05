<?php
include 'header.php'; // Include header for navigation and session checks

// Ensure the user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Fetch all archived orders initially
$archivedOrdersQuery = query("SELECT * FROM archived_orders");
$archivedOrders = $archivedOrdersQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Orders</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">Archived Orders</h2>

    <!-- Search Bar -->
    <div class="mb-3">
        <input type="text" id="searchArchivedOrders" class="form-control" placeholder="Search archived orders by Invoice No, Details, Phone, Address, or Status">
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
                        </tr>
                    </thead>
                    <tbody id="archivedOrdersTable">
                        <?php if (count($archivedOrders) > 0): ?>
                            <?php foreach ($archivedOrders as $order): ?>
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
                                <td colspan="9" class="text-center">No archived orders found.</td>
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
    function loadArchivedOrders(search = "") {
        $.ajax({
            url: "fetch_archived_orders.php", // Backend script to fetch filtered archived orders
            type: "GET",
            data: { search: search },
            success: function(data) {
                $("#archivedOrdersTable").html(data); // Populate the table with fetched data
            },
            error: function() {
                alert("Failed to fetch archived orders. Please try again.");
            }
        });
    }

    // Add event listener for live search
    $("#searchArchivedOrders").on("input", function() {
        const searchValue = $(this).val();
        loadArchivedOrders(searchValue); // Fetch archived orders based on search input
    });

</script>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>