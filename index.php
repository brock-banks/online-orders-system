<?php
require 'config.php';
require 'functions.php';

// Load settings for branding
try {
    $settingRows = query("SELECT key_name, value FROM settings")->fetchAll();
    $settings = [];
    foreach ($settingRows as $r) $settings[$r['key_name']] = $r['value'] ?? null;
} catch (Exception $e) {
    error_log('index.php: failed to load settings: ' . $e->getMessage());
    $settings = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = query("SELECT * FROM users WHERE username = ?", [$username]);
    $user = $query->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        if ($user['role'] === 'admin') {
            redirect('admin_dashboard.php');
        } else {
            redirect('orders.php');
        }
    } else {
        $error = "Invalid login credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?php echo htmlspecialchars($settings['header_name'] ?? 'Order System'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h2 class="text-primary fw-bold"><?php echo htmlspecialchars($settings['header_name'] ?? 'Order System'); ?></h2>
                        <p class="text-muted mb-0">Sign in to continue</p>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
include 'footer.php';
?>
</body>
</html>