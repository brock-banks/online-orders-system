<?php
require 'config.php';
require 'functions.php';

// Default date range (today)
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Append times to the date range
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';

// Fetch orders for the table
$ordersQuery = query(
    "SELECT * FROM orders WHERE date BETWEEN ? AND ?",
    [$startDateTime, $endDateTime]
);
$orders = $ordersQuery->fetchAll();

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Orders_Report_' . $startDate . '_to_' . $endDate . '.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV column headers
fputcsv($output, ['ID', 'Invoice No', 'Details', 'Price', 'Phone', 'Address', 'Date', 'Status']);

// Write data rows
foreach ($orders as $order) {
    fputcsv($output, [
        $order['id'],
        $order['invoice_no'],
        $order['details'],
        number_format($order['price'], 2),
        $order['phone'],
        $order['address'],
        $order['date'],
        ucfirst($order['status'])
    ]);
}

// Close the output stream
fclose($output);
exit;