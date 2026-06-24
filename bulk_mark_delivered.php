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

$rawIds      = $_POST['order_ids'] ?? [];
$sendMessages = !empty($_POST['send_messages']);
$templateKey = (string)($_POST['template_key'] ?? 'template_delivered');
if (!in_array($templateKey, ['template_place_order', 'template_delivered'], true)) {
    $templateKey = 'template_delivered';
}

if (!is_array($rawIds)) {
    $rawIds = preg_split('/\s*,\s*/', (string)$rawIds, -1, PREG_SPLIT_NO_EMPTY);
}

$orderIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn($v) => $v > 0)));

if (count($orderIds) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No orders selected.']);
    exit;
}

$processed     = [];   // [{id, invoice_no, delivered_by, whatsapp_url?}]
$skipped       = [];   // [{id, invoice_no?, reason}]
$template      = getMessageTemplate($templateKey);

try {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $orders = query(
        "SELECT * FROM orders WHERE id IN ({$placeholders})",
        $orderIds
    )->fetchAll();

    $byId = [];
    foreach ($orders as $o) {
        $byId[(int)$o['id']] = $o;
    }

    foreach ($orderIds as $id) {
        $order = $byId[$id] ?? null;
        if (!$order) {
            $skipped[] = ['id' => $id, 'reason' => 'Order not found.'];
            continue;
        }

        $invoiceNo = $order['invoice_no'] ?? '';
        $status    = strtolower(trim((string)($order['status'] ?? '')));
        if ($status === 'delivered') {
            $skipped[] = ['id' => $id, 'invoice_no' => $invoiceNo, 'reason' => 'Already delivered.'];
            continue;
        }

        $deliveredBy = trim((string)($order['assigned_to'] ?? ''));
        if ($deliveredBy === '') {
            $skipped[] = ['id' => $id, 'invoice_no' => $invoiceNo, 'reason' => 'No assigned courier.'];
            continue;
        }

        query(
            "UPDATE orders SET status = 'delivered', delivered_by = ? WHERE id = ?",
            [$deliveredBy, $id]
        );

        $entry = [
            'id'           => $id,
            'invoice_no'   => $invoiceNo,
            'delivered_by' => $deliveredBy,
        ];

        if ($sendMessages) {
            $username = '';
            try {
                $u = query("SELECT username FROM users WHERE id = ? LIMIT 1", [(int)($order['user_id'] ?? 0)])->fetch();
                if ($u && isset($u['username'])) $username = $u['username'];
            } catch (Exception $e) {
                $username = '';
            }

            $message = renderMessageTemplate($template, [
                'invoice_no'   => $invoiceNo,
                'phone'        => $order['phone'] ?? '',
                'address'      => $order['address'] ?? '',
                'details'      => $order['details'] ?? '',
                'price'        => $order['price'] ?? '',
                'delivered_by' => $deliveredBy,
                'date'         => $order['date'] ?? '',
                'username'     => $username,
            ]);

            $phone = preg_replace('/[^\d]/', '', (string)($order['phone'] ?? ''));
            $entry['whatsapp_url'] = "https://api.whatsapp.com/send?phone={$phone}&text=" . rawurlencode($message);
        }

        $processed[] = $entry;
    }

    echo json_encode([
        'success'   => true,
        'processed' => $processed,
        'skipped'   => $skipped,
        'message'   => sprintf(
            '%d marked delivered%s%s.',
            count($processed),
            count($skipped) > 0 ? ', ' . count($skipped) . ' skipped' : '',
            $sendMessages ? ' (messages prepared)' : ''
        ),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('bulk_mark_delivered.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while marking orders as delivered.']);
}
