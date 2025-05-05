<?php
require 'config.php';
require 'functions.php';
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template = $_POST['template'];
    query("INSERT INTO message_templates (template) VALUES (?)", [$template]);
    $success = "Message template added successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Message Template</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="card shadow">
        <div class="card-body">
            <h2 class="card-title text-primary text-center mb-4">Add Message Template</h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success text-center"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="template" class="form-label">Message Template</label>
                    <textarea class="form-control" id="template" name="template" rows="5" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">Add Template</button>
            </form>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>