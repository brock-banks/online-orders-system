<?php
require 'config.php';
require 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'];
    $details = $_POST['details'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    try {
        // Validate inputs (you can add more validation here)
        if (empty($orderId) || empty($details) || empty($phone) || empty($address)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        // Update the order in the database
        query("UPDATE orders SET details = ?, phone = ?, address = ? WHERE id = ?", [$details, $phone, $address, $orderId]);

        // Return a success response
        echo json_encode(['success' => true, 'message' => 'Order updated successfully!']);
    } catch (Exception $e) {
        // Handle any errors
        echo json_encode(['success' => false, 'message' => 'Failed to update the order. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>