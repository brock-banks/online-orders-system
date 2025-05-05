<?php
require 'config.php';
require 'functions.php';

$search = $_GET['search'] ?? '';

// Fetch orders based on the search query without filtering by user_id
$query = query(
    "SELECT * FROM orders WHERE 
    (id LIKE ? OR invoice_no LIKE ? OR details LIKE ? OR phone LIKE ? OR address LIKE ? OR status LIKE ?)",
    ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]
);

$orders = $query->fetchAll();

if (count($orders) > 0) {
    foreach ($orders as $order) {
        // Define variables from the $order array
        $invoiceNo = htmlspecialchars($order['invoice_no']);
        $address = htmlspecialchars($order['address']);
        $details = htmlspecialchars($order['details']);
        $phone = htmlspecialchars($order['phone']);

        echo "<tr>
            <td>" . htmlspecialchars($order['id']) . "</td>
            <td>" . htmlspecialchars($order['user_id']) . "</td>
            <td>" . $invoiceNo . "</td>
            <td>" . $details . "</td>
            <td>" . $phone . "</td>
            <td>" . $address . "</td>
            <td>" . htmlspecialchars($order['date']) . "</td>
            <td>" . htmlspecialchars($order['delivered_by']) . "</td>
            <td>" . ucfirst(htmlspecialchars($order['status'])) . "</td>
            <td>
                <button class='btn btn-secondary btn-sm editOrderBtn' 
                        data-order-id='" . htmlspecialchars($order['id']) . "' 
                        data-invoice-no='" . $invoiceNo . "'
                        data-details='" . $details . "'
                        data-phone='" . $phone . "'
                        data-address='" . $address . "'
                        data-date='" . htmlspecialchars($order['date']) . "'
                        data-delivered-by='" . htmlspecialchars($order['delivered_by']) . "'>Edit</button>
            </td>
            <td>";
        if ($order['status'] !== 'delivered') {
            echo "<button class='btn btn-success btn-sm markAsDeliveredBtn' data-order-id='" . htmlspecialchars($order['id']) . "'>Mark as Delivered</button>";
        } else {
            echo "<span class='badge bg-success'>Delivered</span>";
        }
        echo "</td>
            <td>
                <a href='https://wa.me/" . $phone . "?text=" . urlencode("السلام عليكم ورحمة الله وبركاته 
شكرًا لطلبك من [متجر الشاهين للوازم الرحلات والتخييم]!
رقم طلبك هو: # $invoiceNo
يرجى الاحتفاظ بهذا الرقم لإستلام
مكان الاستلام $address
تفاصيل الطلب
$details
لمزيد من المعلومات أو المتابعة، يمكنك مراسلتنا على 
72202722
93211636
تحياتنا،
فريق [ALSHAHEEN ONLINE TEAM]") . "' 
                   target='_blank' 
                   class='btn btn-success btn-sm'>
                   Send WhatsApp
                </a>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='12' class='text-center'>No orders found.</td></tr>";
}
?>