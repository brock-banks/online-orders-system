<?php
require_once 'config.php'; // Include configuration
require_once 'functions.php'; // Include utility functions

if (!isLoggedIn()) {
    redirect('index.php'); // Redirect to login if the user is not logged in
}

// Fetch all receives from the database
$receives = query("SELECT id, amount, date, details, phone, created_at FROM receives ORDER BY date DESC")->fetchAll();

// Calculate total amount of receives
$totalAmount = query("SELECT SUM(amount) AS total FROM receives")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receives Report</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .summary-card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .summary-card .card-body {
            text-align: center;
        }
        .table thead th {
            background-color: #007bff;
            color: white;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        .detail-icon {
            color: #007bff;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?> <!-- Include the header -->

<div class="container mt-5">
    <h2 class="text-primary text-center mb-4"><i class="fas fa-receipt"></i> Receives Report</h2>

    <!-- Receives Summary -->
    <div class="row text-center mb-4">
        <div class="col-md-4 offset-md-4">
            <div class="card summary-card text-primary">
                <div class="card-body">
                    <h4 class="card-title"><i class="fas fa-wallet"></i> Total Amount</h4>
                    <p class="card-text fs-3">$<?php echo number_format($totalAmount, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Receives Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h3 class="card-title text-center">Detailed Receives</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Details</th>
                            <th>Phone</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($receives) > 0): ?>
                            <?php foreach ($receives as $receive): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($receive['id']); ?></td>
                                    <td>$<?php echo number_format($receive['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($receive['date']); ?></td>
                                    <td><?php echo htmlspecialchars($receive['details']); ?></td>
                                    <td><?php echo htmlspecialchars($receive['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($receive['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No receives found.</td>
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
<?php include 'footer.php'; ?>
</body>
</html>