<?php
require_once 'config.php';
require_once 'functions.php';

$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!isLoggedIn()) {
    if ($isAjax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
    } else {
        redirect('index.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    } else {
        redirect('orders.php');
    }
    exit;
}

ensureAssignedToColumn();

$orderId        = (int)($_POST['order_id'] ?? 0);
$deliveredBy    = trim($_POST['delivered_by'] ?? '');
$useAssignment  = !empty($_POST['use_assignment']);
$templateKey    = (string)($_POST['template_key'] ?? 'template_delivered');
if (!in_array($templateKey, ['template_place_order', 'template_delivered'], true)) {
    $templateKey = 'template_delivered';
}

if ($orderId <= 0) {
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required.']);
    } else {
        redirect('orders.php');
    }
    exit;
}

try {
    $order = query("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId])->fetch();

    // One-click flow: pull courier from the pre-assignment stored on the order.
    if ($useAssignment && $order) {
        $deliveredBy = trim((string)($order['assigned_to'] ?? ''));
    }

    if ($deliveredBy === '') {
        if ($isAjax) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $useAssignment
                    ? 'This order has no assigned courier — assign one first.'
                    : 'Delivery person is required.'
            ]);
        } else {
            redirect('orders.php');
        }
        exit;
    }

    if (!$order) {
        if ($isAjax) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
        } else {
            redirect('orders.php');
        }
        exit;
    }

    query(
        "UPDATE orders SET status = 'delivered', delivered_by = ? WHERE id = ?",
        [$deliveredBy, $orderId]
    );

    $username = '';
    try {
        $u = query("SELECT username FROM users WHERE id = ? LIMIT 1", [(int)($order['user_id'] ?? 0)])->fetch();
        if ($u && isset($u['username'])) $username = $u['username'];
    } catch (Exception $e) {
        $username = '';
    }

    $template = getMessageTemplate($templateKey);
    $message = renderMessageTemplate($template, [
        'invoice_no'   => $order['invoice_no'] ?? '',
        'phone'        => $order['phone'] ?? '',
        'address'      => $order['address'] ?? '',
        'details'      => $order['details'] ?? '',
        'price'        => $order['price'] ?? '',
        'delivered_by' => $deliveredBy,
        'date'         => $order['date'] ?? '',
        'username'     => $username,
    ]);

    $phone = preg_replace('/[^\d]/', '', (string)($order['phone'] ?? ''));
    $whatsappURL = "https://api.whatsapp.com/send?phone={$phone}&text=" . rawurlencode($message);

    if ($isAjax) {
        echo json_encode([
            'success' => true,
            'message' => 'Order marked as delivered successfully.',
            'whatsapp_url' => $whatsappURL
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: {$whatsappURL}");
    exit;

} catch (Exception $e) {
    error_log('mark_as_delivered.php error: ' . $e->getMessage());

    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error while marking order as delivered.']);
    } else {
        redirect('orders.php');
    }
    exit;
}
?>