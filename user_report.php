<?php
require_once 'config.php'; // Use require_once for configuration
require_once 'functions.php'; // Use require_once for utility functions

if (!isLoggedIn()) {
    redirect('index.php'); // Redirect to login if the user is not logged in
}

// Fetch all users with their order counts
$users = query("
    SELECT u.id AS user_id, u.username, COUNT(o.id) AS order_count
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    GROUP BY u.id, u.username
    ORDER BY u.username
")->fetchAll();

// Fetch all orders grouped by user
$orders = query("
    SELECT o.id AS order_id, o.user_id, o.details, o.invoice_no, o.phone, o.address, o.date, o.status
    FROM orders o
    ORDER BY o.user_id, o.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .user-section {
            margin-bottom: 40px;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .order-list {
            padding-left: 20px;
            display: none; /* Initially hidden */
        }
        .order-item {
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .order-item strong {
            display: inline-block;
            width: 100px;
        }
        .toggle-btn {
            cursor: pointer;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?> <!-- Include the header -->
<div class="container mt-5">
    <h2 class="text-primary text-center mb-4"><i class="fas fa-users"></i> User Report</h2>
    <?php if (count($users) > 0): ?>
        <?php foreach ($users as $user): ?>
            <div class="user-section">
                <!-- User Header with Name and Order Count -->
                <div class="user-header">
                    <h4 class="text-primary">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </h4>
                    <div>
                        <span class="badge bg-primary fs-6"><?php echo $user['order_count']; ?> Orders</span>
                        <?php if ($user['order_count'] > 0): ?>
                            <span class="toggle-btn text-primary" onclick="toggleOrders(<?php echo $user['user_id']; ?>)">
                                <i class="fas fa-chevron-down"></i> Show Orders
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User's Orders -->
                <?php if ($user['order_count'] > 0): ?>
                    <ul class="order-list list-unstyled" id="orders-<?php echo $user['user_id']; ?>">
                        <?php foreach ($orders as $order): ?>
                            <?php if ($order['user_id'] === $user['user_id']): ?>
                                <li class="order-item">
                                    <strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?><br>
                                    <strong>Details:</strong> <?php echo htmlspecialchars($order['details']); ?><br>
                                    <strong>Invoice:</strong> <?php echo htmlspecialchars($order['invoice_no']); ?><br>
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?><br>
                                    <strong>Address:</strong> <?php echo htmlspecialchars($order['address']); ?><br>
                                    <strong>Date:</strong> <?php echo htmlspecialchars($order['date']); ?><br>
                                    <strong>Status:</strong> <span class="text-capitalize"><?php echo htmlspecialchars($order['status']); ?></span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No orders found for this user.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center text-danger">No users found.</p>
    <?php endif; ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to toggle the visibility of orders
    function toggleOrders(userId) {
        const orderList = document.getElementById(`orders-${userId}`);
        const toggleBtn = orderList.previousElementSibling.querySelector('.toggle-btn i');

        if (orderList.style.display === 'none' || orderList.style.display === '') {
            orderList.style.display = 'block';
            toggleBtn.classList.remove('fa-chevron-down');
            toggleBtn.classList.add('fa-chevron-up');
            toggleBtn.parentElement.innerHTML = `<i class="fas fa-chevron-up"></i> Hide Orders`;
        } else {
            orderList.style.display = 'none';
            toggleBtn.classList.remove('fa-chevron-up');
            toggleBtn.classList.add('fa-chevron-down');
            toggleBtn.parentElement.innerHTML = `<i class="fas fa-chevron-down"></i> Show Orders`;
        }
    }
</script>
<?php include 'footer.php'; ?>
</body>
</html>