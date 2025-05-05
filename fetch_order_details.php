<?php
require 'config.php';
require 'functions.php';

// Check if 'id' is passed in the request
if (isset($_GET['id'])) {
    $orderId = $_GET['id'];

    // Query the database to fetch the order details
    $query = query("SELECT * FROM orders WHERE id = ?", [$orderId]);
    $order = $query->fetch();

    if ($order) {
        // Return the order details as JSON
        echo json_encode($order);
    } else {
        // Return an error if the order is not found
        echo json_encode(['error' => 'Order not found.']);
    }
} else {
    // Return an error if 'id' is not provided
    echo json_encode(['error' => 'Order ID not provided.']);
}
?>