<?php
require_once 'config.php';

$currentDate = new DateTime();
$currentDate->modify('-30 days'); // Subtract 30 days

// Query to find orders older than 30 days with status "pending"
$pendingOrders = query(
    "SELECT * FROM orders WHERE status =! 'delivered' AND date <= ?",
    [$currentDate->format('Y-m-d')]
)->fetchAll();

foreach ($pendingOrders as $order) {
    $userId = $order['user_id'];
    $message = "Order #{$order['invoice_no']} has been pending for over 30 days. Please take action.";

    // Insert notification into the notifications table
    query(
        "INSERT INTO notifications (user_id, message) VALUES (?, ?)",
        [$userId, $message]
    );
}

echo "Notifications for pending orders created successfully.";
?>