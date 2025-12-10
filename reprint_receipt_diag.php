<?php
// Diagnostic helper for reprint_receipt.php failures.
// Save as reprint_receipt_diag.php in the same folder as reprint_receipt.php and open in browser.
// This file is for debugging only — remove it after use.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

$out = [
    'ok' => false,
    'time' => date('c'),
    'php_version' => PHP_VERSION,
    'pdo_exists' => false,
    'db_current' => null,
    'recent_orders_sample' => null,
    'errors' => [],
];

try {
    // test query() helper if available
    if (!function_exists('query')) {
        $out['errors'][] = "query() helper not found (functions.php may not be loaded correctly).";
        echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        exit;
    }

    // test database connection and a few simple checks
    try {
        $dbNameRow = query("SELECT DATABASE() AS db")->fetch();
        $out['db_current'] = $dbNameRow['db'] ?? null;
        $out['pdo_exists'] = isset($GLOBALS['pdo']) && ($GLOBALS['pdo'] instanceof PDO);
    } catch (Exception $e) {
        $out['errors'][] = "Database basic check failed: " . $e->getMessage();
    }

    // Show table structure for orders (if exists)
    try {
        $stmt = $GLOBALS['pdo']->query("SHOW CREATE TABLE `orders`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $out['orders_create'] = isset($row['Create Table']) ? substr($row['Create Table'], 0, 3000) : $row;
    } catch (Exception $e) {
        $out['errors'][] = "SHOW CREATE TABLE `orders` failed: " . $e->getMessage();
    }

    // Try the same search query used by reprint_receipt.php (recent orders)
    try {
        $rows = query("SELECT o.id, o.invoice_no, o.phone, o.address, o.date, o.price, o.status, u.username
                       FROM orders o
                       LEFT JOIN users u ON o.user_id = u.id
                       ORDER BY o.date DESC
                       LIMIT 20")->fetchAll();
        $out['recent_orders_sample'] = $rows ?: [];
    } catch (Exception $e) {
        $out['errors'][] = "Selecting recent orders failed: " . $e->getMessage();
    }

    $out['ok'] = count($out['errors']) === 0;
} catch (Throwable $t) {
    $out['errors'][] = "Fatal: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine();
}

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);