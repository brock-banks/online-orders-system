<?php
require 'config.php';
require 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'];
    $deliveredBy = $_POST['delivered_by'];

    // Fetch order details
    $order = query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
    if (!$order) {
        die("Order not found.");
    }

    // Update order status
    query("UPDATE orders SET status = 'delivered', delivered_by = ? WHERE id = ?", [$deliveredBy, $orderId]);

    // Prepare the WhatsApp message
    $message = "مرحباً،
تم استلام طلبك رقم# 
{$order['invoice_no']}
تفاصيل الطلب
{$order['details']}
تم التسليم عن طريق
$deliveredBy
، شكرًا لثقتك بنا!
سعداء بخدمتك، ونتمنى لك تجربة تسوّق رائعة.
تحياتنا،
فريق [ALSHAHEEN ONLINE TEAM]";
    $phone = $order['phone']; // Ensure this is in E.164 format (e.g., 1234567890)

    // Encode the message for the WhatsApp URL
    $encodedMessage = urlencode($message);

    // Generate the WhatsApp URL
    $whatsappURL = "https://wa.me/$phone/?text=$encodedMessage";

    // Output JavaScript to open WhatsApp in a new tab and redirect back to orders page
    echo "<script>
        // Open WhatsApp in a new tab
        window.open('$whatsappURL', '_blank');
        // Redirect back to orders page
        window.location.href = 'orders.php';
    </script>";
    exit;
}
?>

