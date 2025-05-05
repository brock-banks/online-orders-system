<?php
require 'config.php';
require 'functions.php';

$search = $_GET['search'] ?? '';

// Fetch orders based on the search query
$query = query(
    "SELECT * FROM orders WHERE 
    (id LIKE ? OR invoice_no LIKE ? OR details LIKE ? OR phone LIKE ? OR address LIKE ? OR status LIKE ?)",
    ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]
);

$orders = $query->fetchAll();

if (count($orders) > 0) {
    foreach ($orders as $order) {
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
            <td>
                <form method='POST' style='display: inline;'>
                    <input type='hidden' name='order_id' value='" . htmlspecialchars($order['id']) . "'>
                    <button type='submit' name='archive_order' class='btn btn-danger btn-sm'>Archive</button>
                </form>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='10' class='text-center'>No orders found.</td></tr>";
}
?>