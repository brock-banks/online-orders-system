<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    // Only logged-in users should run this (or restrict further)
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// Use a threshold datetime 30 days ago (end of that day) to be safe
$thresholdDt = new DateTime();
$thresholdDt->modify('-30 days');
$thresholdStr = $thresholdDt->format('Y-m-d 23:59:59');

// Query to find orders older than 30 days and NOT delivered
$pendingOrders = query(
    "SELECT * FROM orders WHERE status != 'delivered' AND date <= ?",
    [$thresholdStr]
)->fetchAll();

foreach ($pendingOrders as $order) {
    $userId = $order['user_id'];
    $invoice = $order['invoice_no'] ?? $order['id'];
    $message = "Order #{$invoice} has been pending for over 30 days. Please take action.";

    // Insert notification into the notifications table (create table if missing)
    query(
        "INSERT INTO notifications (user_id, message) VALUES (?, ?)",
        [$userId, $message]
    );
}

echo "Notifications for pending orders created successfully.";