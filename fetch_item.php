<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

$itemcode = $_GET['itemcode'] ?? '';
$itemcode = trim($itemcode);

if ($itemcode === '') {
    echo json_encode(['error' => 'Item code is required.']);
    exit;
}

try {
    // Attempt exact match first. Adjust query if you want case-insensitive or partial matches.
    $stmt = query("SELECT itemid, itemcode, itemname FROM items WHERE itemcode = ? LIMIT 1", [$itemcode]);
    $item = $stmt->fetch();

    if ($item) {
        // Return item data
        echo json_encode([
            'itemid' => $item['itemid'],
            'itemcode' => $item['itemcode'],
            'itemname' => $item['itemname']
        ]);
        exit;
    }

    // If not found, try case-insensitive match
    $stmt2 = query("SELECT itemid, itemcode, itemname FROM items WHERE LOWER(itemcode) = LOWER(?) LIMIT 1", [$itemcode]);
    $item2 = $stmt2->fetch();
    if ($item2) {
        echo json_encode([
            'itemid' => $item2['itemid'],
            'itemcode' => $item2['itemcode'],
            'itemname' => $item2['itemname']
        ]);
        exit;
    }

    echo json_encode(['error' => 'Item not found.']);
    exit;
} catch (Exception $e) {
    error_log('Error in fetch_item.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Server error.']);
    exit;
}