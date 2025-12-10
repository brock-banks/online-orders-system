<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}


$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $itemcode = trim($_POST['itemcode'] ?? '');
        $itemname = trim($_POST['itemname'] ?? '');

        if ($itemcode === '' || $itemname === '') {
            echo json_encode(['success' => false, 'message' => 'Item code and name are required.']);
            exit;
        }

        // Check uniqueness of itemcode
        $exists = query("SELECT itemid FROM items WHERE itemcode = ? LIMIT 1", [$itemcode])->fetch();
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Item code already exists.']);
            exit;
        }

        query("INSERT INTO items (itemcode, itemname) VALUES (?, ?)", [$itemcode, $itemname]);

        echo json_encode(['success' => true, 'message' => 'Item added successfully.']);
        exit;
    }

    if ($action === 'edit') {
        $itemid = intval($_POST['itemid'] ?? 0);
        $itemcode = trim($_POST['itemcode'] ?? '');
        $itemname = trim($_POST['itemname'] ?? '');

        if ($itemid <= 0 || $itemcode === '' || $itemname === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            exit;
        }

        // Ensure item exists
        $exists = query("SELECT itemid FROM items WHERE itemid = ? LIMIT 1", [$itemid])->fetch();
        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }

        // Check uniqueness of itemcode among other rows
        $conflict = query("SELECT itemid FROM items WHERE itemcode = ? AND itemid != ? LIMIT 1", [$itemcode, $itemid])->fetch();
        if ($conflict) {
            echo json_encode(['success' => false, 'message' => 'Another item with the same code exists.']);
            exit;
        }

        query("UPDATE items SET itemcode = ?, itemname = ? WHERE itemid = ?", [$itemcode, $itemname, $itemid]);

        echo json_encode(['success' => true, 'message' => 'Item updated successfully.']);
        exit;
    }

    if ($action === 'delete') {
        $itemid = intval($_POST['itemid'] ?? 0);
        if ($itemid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item id.']);
            exit;
        }

        // Ensure item exists
        $exists = query("SELECT itemid FROM items WHERE itemid = ? LIMIT 1", [$itemid])->fetch();
        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }

        query("DELETE FROM items WHERE itemid = ?", [$itemid]);

        echo json_encode(['success' => true, 'message' => 'Item deleted successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Exception $e) {
    error_log('items_action.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}