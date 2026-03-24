<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID.']);
    exit;
}

try {
    $order = query("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId])->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found.']);
        exit;
    }

    echo json_encode([
        'id' => $order['id'],
        'invoice_no' => $order['invoice_no'],
        'details' => $order['details'],
        'phone' => $order['phone'],
        'address' => $order['address'],
        'date' => $order['date'],
        'delivered_by' => $order['delivered_by'] ?? '',
        'status' => $order['status'] ?? 'pending'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    error_log('fetch_order_details.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
    exit;
}
?>