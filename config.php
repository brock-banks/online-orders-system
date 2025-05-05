<?php
$host = 'localhost';
$dbname = 'online_order_system';
$username = 'root'; // Update as per your MySQL user.
$password = ''; // Update as per your MySQL password.

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>