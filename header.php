<?php
require_once 'config.php'; // Use require_once for configuration
require_once 'functions.php'; // Use require_once for utility functions

if (!isLoggedIn()) {
    redirect('index.php'); // Redirect to login if the user is not logged in
}

// Fetch the header logo and header name from the database
$headerLogo = query("SELECT value FROM settings WHERE key_name = 'header_logo'")->fetch()['value'] ?? '';
$headerName = query("SELECT value FROM settings WHERE key_name = 'header_name'")->fetch()['value'] ?? 'Order System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($headerName); ?></title>
   
    
    <!-- Include Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Include Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php
// Dynamically set the theme classes for the navbar
$navbarClass = isset($currentTheme) && $currentTheme === 'dark' ? 'navbar-dark bg-dark' : 'navbar-light bg-light';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <?php if ($headerLogo): ?>
                <img src="<?php echo htmlspecialchars($headerLogo); ?>" alt="Logo" style="height: 40px; margin-right: 10px;">
            <?php endif; ?>
            <?php echo htmlspecialchars($headerName); ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="place_order.php">Place Order</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_receive.php">Add Receive</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="view_orders.php">Daily report</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="archive_orders.php">Archive Orders</a>
                </li>
                <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Notifications <span class="badge bg-danger" id="notification-count">
                        <?php
                        // Get the count of pending orders older than 29 days
                        $currentDate = new DateTime();
                        $currentDate->modify('-29 days');
                        $pendingCount = query(
                            "SELECT COUNT(*) AS count FROM orders WHERE status != 'delivered' AND date <= ?",
                            [$currentDate->format('Y-m-d')]
                        )->fetch()['count'];

                        echo $pendingCount; // Display the count
                        ?>
                    </span>
                </a>
                <ul class="dropdown-menu" aria-labelledby="notificationsDropdown">
                    <?php if ($pendingCount > 0): ?>
                        <li><a class="dropdown-item" href="pending_orders.php">View Pending Orders</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="#">No pending orders</a></li>
                    <?php endif; ?>
                </ul>
            </li>
                <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Report
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="reportDropdown">
                            <li><a class="dropdown-item" href="report.php">General Report</a></li>
                            <li><a class="dropdown-item" href="user_report.php">User Report</a></li>
                            <li><a class="dropdown-item" href="receives_report.php">Receives Report</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_template.php">Message Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                <?php endif; ?>
            </ul>
            <a href="logout.php" class="btn btn-outline-light">Logout</a>
        </div>
    </div>
</nav>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>