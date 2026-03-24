<?php
require_once 'config.php';
require_once 'functions.php';

$searchType = $_GET['search_type'] ?? '';
$searchValue = trim($_GET['search_value'] ?? '');

$allowedTypes = ['invoice_no', 'phone', 'address'];

if ($searchValue === '' || !in_array($searchType, $allowedTypes, true)) {
    echo "<tr>
        <td colspan='8'>
            <div class='empty-state my-3'>
                <div class='empty-state-title'>No orders loaded</div>
                <div>Select a valid search type and enter a value.</div>
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

            echo "<tr>
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
                echo "<button class='btn btn-sm btn-success markAsDeliveredBtn'
                              data-order-id='{$id}'>
                        Mark Delivered
                      </button>";
            } else {
                echo "<button class='btn btn-sm btn-light' disabled>
                        Delivered
                      </button>";
            }

            echo "<a href='{$deliveredMessageUrl}'
                      target='_blank'
                      class='btn btn-sm btn-outline-success'>
                      Send Delivered Msg
                  </a>
                  </div>
                </td>
            </tr>";
        }
    } else {
        echo "<tr>
            <td colspan='8'>
                <div class='empty-state my-3'>
                    <div class='empty-state-title'>No matching orders found</div>
                    <div>Try a different value or search type.</div>
                </div>
            </td>
        </tr>";
    }
} catch (Exception $e) {
    error_log('fetch_orders.php error: ' . $e->getMessage());
    echo "<tr>
        <td colspan='8'>
            <div class='empty-state my-3'>
                <div class='empty-state-title'>Failed to load orders</div>
                <div>Please try again.</div>
            </div>
        </td>
    </tr>";
}
?>