<?php
require 'config.php';
require 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role']; // This will be either 'admin' or 'user'

    // Check if the username already exists
    $existingUser = query("SELECT * FROM users WHERE username = ?", [$username])->fetch();
    if ($existingUser) {
        $error = "Username already exists. Please choose a different one.";
    } else {
        // Hash the password and insert the new user into the database
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        query("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", [$username, $hashedPassword, $role]);
        $success = "User registered successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container mt-5">
    <h2>Register</h2>
    <?php if (isset($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
    <?php if (isset($success)) { echo "<div class='alert alert-success'>$success</div>"; } ?>
    <form method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-control" id="role" name="role" required>
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</div>
</body>
</html>