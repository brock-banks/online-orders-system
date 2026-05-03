<?php
require_once 'config.php';
require_once 'functions.php';

$searchType = $_GET['search_type'] ?? '';
$searchValue = trim($_GET['search_value'] ?? '');
$isDefault = !empty($_GET['default']);

$allowedTypes = ['invoice_no', 'phone', 'address'];

// Default mode: show most recent 20 orders
if ($isDefault) {
    try {
        $orders = query("SELECT * FROM orders ORDER BY date DESC LIMIT 20")->fetchAll();
    } catch (Exception $e) {
        error_log('fetch_orders.php default error: ' . $e->getMessage());
        echo "<tr>
            <td colspan='8'>
                <div class='empty-state text-center my-4'>
                    <div style='font-size:2.5rem;'>⚠️</div>
                    <div class='empty-state-title fw-bold mt-2'>Failed to load orders</div>
                    <div class='text-muted small'>Please try again.</div>
                </div>
            </td>
        </tr>";
        exit;
    }

    if (count($orders) === 0) {
        echo "<tr>
            <td colspan='8'>
                <div class='empty-state text-center my-4'>
                    <div style='font-size:2.5rem;'>📦</div>
                    <div class='empty-state-title fw-bold mt-2'>No orders yet</div>
                    <div class='text-muted small'>Place your first order to get started.</div>
                </div>
            </td>
        </tr>";
        exit;
    }

    foreach ($orders as $order) {
        echo renderOrderRow($order);
    }
    exit;
}

if ($searchValue === '' || !in_array($searchType, $allowedTypes, true)) {
    echo "<tr>
        <td colspan='8'>
            <div class='empty-state text-center my-4'>
                <div style='font-size:2.5rem;'>📦</div>
                <div class='empty-state-title fw-bold mt-2'>No orders loaded</div>
                <div class='text-muted small'>Select a valid search type and enter a value.</div>
            </div>
        </td>
    </tr>";
    exit;
}

try {
    $sql = "SELECT * FROM orders WHERE {$searchType} LIKE ? ORDER BY date DESC";
    $orders = query($sql, ["%{$searchValue}%"])->fetchAll();

    if (count($orders) > 0) {
        foreach ($orders as $order) {
            echo renderOrderRow($order);
        }
    } else {
        echo "<tr>
            <td colspan='8'>
                <div class='empty-state text-center my-4'>
                    <div style='font-size:2.5rem;'>🔍</div>
                    <div class='empty-state-title fw-bold mt-2'>No matching orders found</div>
                    <div class='text-muted small'>Try a different search term or clear the search to see recent orders.</div>
                </div>
            </td>
        </tr>";
    }
} catch (Exception $e) {
    error_log('fetch_orders.php error: ' . $e->getMessage());
    echo "<tr>
        <td colspan='8'>
            <div class='empty-state text-center my-4'>
                <div style='font-size:2.5rem;'>⚠️</div>
                <div class='empty-state-title fw-bold mt-2'>Failed to load orders</div>
                <div class='text-muted small'>Please try again.</div>
            </div>
        </td>
    </tr>";
}

function renderOrderRow($order) {
    $id = htmlspecialchars($order['id']);
    $invoiceNo = htmlspecialchars($order['invoice_no'] ?? '');
    $details = nl2br(htmlspecialchars($order['details'] ?? ''));
    $phone = htmlspecialchars($order['phone'] ?? '');
    $address = htmlspecialchars($order['address'] ?? '');
    $date = htmlspecialchars($order['date'] ?? '');
    $status = strtolower(trim($order['status'] ?? 'pending'));

    $statusBadge = $status === 'delivered'
        ? '<span class="badge-status badge-delivered">Delivered</span>'
        : '<span class="badge-status badge-pending">Pending</span>';

    $deliveredMessage = urlencode(
        "مرحباً،\n"
        . "تم استلام طلبك رقم#\n"
        . ($order['invoice_no'] ?? '') . "\n"
        . "تفاصيل الطلب\n"
        . ($order['details'] ?? '') . "\n"
        . "تم التسليم بنجاح.\n"
        . "شكراً لثقتك بنا.\n"
        . "تحياتنا،\n"
        . "فريق [ALSHAHEEN ONLINE TEAM]"
    );

    $phoneForWhatsapp = preg_replace('/[^\d]/', '', (string)($order['phone'] ?? ''));
    $deliveredMessageUrl = "https://api.whatsapp.com/send?phone=" . $phoneForWhatsapp . "&text=" . $deliveredMessage;

    $row = "<tr>
        <td>{$id}</td>
        <td>{$invoiceNo}</td>
        <td class='details-cell'>{$details}</td>
        <td>{$phone}</td>
        <td>{$address}</td>
        <td>{$date}</td>
        <td>{$statusBadge}</td>
        <td>
            <div class='table-actions'>
                <button class='btn btn-sm btn-outline-secondary editOrderBtn'
                        data-order-id='{$id}'>
                    Edit
                </button>";

    if ($status !== 'delivered') {
        $row .= "<button class='btn btn-sm btn-success markAsDeliveredBtn'
                      data-order-id='{$id}'>
                Mark Delivered
              </button>";
    } else {
        $row .= "<button class='btn btn-sm btn-light' disabled>
                Delivered
              </button>";
    }

    $row .= "<a href='{$deliveredMessageUrl}'
                  target='_blank'
                  class='btn btn-sm btn-outline-success'>
                  Send Delivered Msg
              </a>
              </div>
            </td>
        </tr>";

    return $row;
}
?>