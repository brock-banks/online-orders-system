<?php
require_once 'config.php'; // Database configuration
require_once 'functions.php'; // Utility functions

header('Content-Type: application/json'); // Return JSON response

// Get filter parameters from the request
$status = $_GET['status'] ?? '';
$deliveredBy = $_GET['deliveredBy'] ?? '';
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';

// Build the SQL query with filters
$sql = "SELECT * FROM orders WHERE 1=1"; // Default condition to make appending filters easier
$params = [];

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if (!empty($deliveredBy)) {
    $sql .= " AND delivered_by LIKE ?";
    $params[] = "%$deliveredBy%";
}

if (!empty($dateFrom)) {
    $sql .= " AND date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $sql .= " AND date <= ?";
    $params[] = $dateTo;
}

// Add sorting by date
$sql .= " ORDER BY date DESC";

try {
    // Execute the query and fetch results
    $orders = query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

    // Return the orders as a JSON response
    echo json_encode($orders);
} catch (PDOException $e) {
    // Log the error and return an error response
    error_log("Error fetching filtered orders: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch orders. Please try again later.']);
    exit;
}