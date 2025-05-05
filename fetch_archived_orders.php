<?php
require 'config.php';
require 'functions.php';

$search = $_GET['search'] ?? '';

// Fetch archived orders based on the search query
$query = query(
    "SELECT * FROM archived_orders WHERE 
    (id LIKE ? OR invoice_no LIKE ? OR details LIKE ? OR phone LIKE ? OR address LIKE ? OR status LIKE ?)",
    ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]
);

$archivedOrders = $query->fetchAll();

if (count($archivedOrders) > 0) {
    foreach ($archivedOrders as $order) {
        echo "<tr>
            <td>" . htmlspecialchars($order['id']) . "</td>
            <td>" . htmlspecialchars($order['user_id']) . "</td>
            <td>" . htmlspecialchars($order['invoice_no']) . "</td>
            <td>" . htmlspecialchars($order['details']) . "</td>
            <td>" . htmlspecialchars($order['phone']) . "</td>
            <td>" . htmlspecialchars($order['address']) . "</td>
            <td>" . htmlspecialchars($order['date']) . "</td>
            <td>" . htmlspecialchars($order['delivered_by']) . "</td>
            <td>" . ucfirst(htmlspecialchars($order['status'])) . "</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='9' class='text-center'>No archived orders found.</td></tr>";
}
?>