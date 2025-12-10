<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $placename = trim($_POST['placename'] ?? '');
        if ($placename === '') {
            echo json_encode(['success' => false, 'message' => 'Place name is required.']);
            exit;
        }
        // uniqueness check
        $exists = query("SELECT PlaceID FROM `Map` WHERE PlaceName = ? LIMIT 1", [$placename])->fetch();
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Place name already exists.']);
            exit;
        }
        query("INSERT INTO `Map` (PlaceName) VALUES (?)", [$placename]);
        echo json_encode(['success' => true, 'message' => 'Place added successfully.']);
        exit;
    }

    if ($action === 'edit') {
        $placeid = intval($_POST['placeid'] ?? 0);
        $placename = trim($_POST['placename'] ?? '');
        if ($placeid <= 0 || $placename === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            exit;
        }
        $exists = query("SELECT PlaceID FROM `Map` WHERE PlaceID = ? LIMIT 1", [$placeid])->fetch();
        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Place not found.']);
            exit;
        }
        // check conflict
        $conflict = query("SELECT PlaceID FROM `Map` WHERE PlaceName = ? AND PlaceID != ? LIMIT 1", [$placename, $placeid])->fetch();
        if ($conflict) {
            echo json_encode(['success' => false, 'message' => 'Another place with the same name exists.']);
            exit;
        }
        query("UPDATE `Map` SET PlaceName = ? WHERE PlaceID = ?", [$placename, $placeid]);
        echo json_encode(['success' => true, 'message' => 'Place updated successfully.']);
        exit;
    }

    if ($action === 'delete') {
        $placeid = intval($_POST['placeid'] ?? 0);
        if ($placeid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid place id.']);
            exit;
        }
        $exists = query("SELECT PlaceID FROM `Map` WHERE PlaceID = ? LIMIT 1", [$placeid])->fetch();
        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Place not found.']);
            exit;
        }
        query("DELETE FROM `Map` WHERE PlaceID = ?", [$placeid]);
        echo json_encode(['success' => true, 'message' => 'Place deleted successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Exception $e) {
    error_log('map_action.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;
}