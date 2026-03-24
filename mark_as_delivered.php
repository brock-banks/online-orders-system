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

$orderId = (int)($_POST['order_id'] ?? 0);
$deliveredBy = trim($_POST['delivered_by'] ?? '');

if ($orderId <= 0 || $deliveredBy === '') {
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID and delivery person are required.']);
    } else {
        redirect('orders.php');
    }
    exit;
}

try {
    $order = query("SELECT * FROM orders WHERE id = ? LIMIT 1", [$orderId])->fetch();

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

    $message = "مرحباً،\n"
        . "تم استلام طلبك رقم#\n"
        . ($order['invoice_no'] ?? '') . "\n"
        . "تفاصيل الطلب\n"
        . ($order['details'] ?? '') . "\n"
        . "تم التسليم عن طريق\n"
        . $deliveredBy . "\n"
        . "، شكرًا لثقتك بنا!\n"
        . "سعداء بخدمتك، ونتمنى لك تجربة تسوّق رائعة.\n"
        . "تحياتنا،\n"
        . "فريق [ALSHAHEEN ONLINE TEAM]";

    $phone = preg_replace('/[^\d]/', '', (string)($order['phone'] ?? ''));
    $whatsappURL = "https://api.whatsapp.com/send?phone={$phone}&text=" . urlencode($message);

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