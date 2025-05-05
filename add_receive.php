<?php
require_once 'config.php'; // Include configuration
require_once 'functions.php'; // Include utility functions

if (!isLoggedIn()) {
    redirect('index.php'); // Redirect to login if the user is not logged in
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $details = $_POST['details'];
    $phone = $_POST['phone'];

    // Insert the receive into the database
    query("INSERT INTO receives (amount, date, details, phone) VALUES (?, ?, ?, ?)", 
        [$amount, $date, $details, $phone]);

    $success = "Receive added successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Receive</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?> <!-- Include the header -->
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="card-title text-center text-primary mb-4">Add Receive</h2>
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success text-center"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" placeholder="Enter amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="details" class="form-label">Details</label>
                            <textarea class="form-control" id="details" name="details" rows="3" placeholder="Enter details" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter phone number" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add Receive</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>