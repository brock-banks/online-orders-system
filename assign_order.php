<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

ensureAssignedToColumn();

$orderId    = (int)($_POST['order_id'] ?? 0);
$assignedTo = trim($_POST['assigned_to'] ?? '');

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order id is required.']);
    exit;
}

try {
    $order = query("SELECT id FROM orders WHERE id = ? LIMIT 1", [$orderId])->fetch();
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    if ($assignedTo !== '') {
        $exists = query("SELECT 1 FROM delivery_people WHERE name = ? LIMIT 1", [$assignedTo])->fetch();
        if (!$exists) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "'{$assignedTo}' is not in the delivery people list."]);
            exit;
        }
    }

    query("UPDATE orders SET assigned_to = ? WHERE id = ?", [$assignedTo !== '' ? $assignedTo : null, $orderId]);

    echo json_encode([
        'success'     => true,
        'order_id'    => $orderId,
        'assigned_to' => $assignedTo,
        'message'     => $assignedTo === '' ? 'Assignment cleared.' : "Assigned to {$assignedTo}.",
    ]);
} catch (Exception $e) {
    error_log('assign_order.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while updating assignment.']);
}
