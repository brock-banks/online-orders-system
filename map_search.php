<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

// Require login for security
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

// Accept q (search term) and id (single id lookup)
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    if (!empty($_GET['id'])) {
        // Select2 may request by id to get the text for initial value
        $id = (int)$_GET['id'];
        $row = query("SELECT PlaceID, PlaceName FROM `Map` WHERE PlaceID = ? LIMIT 1", [$id])->fetch();
        if ($row) {
            echo json_encode(['results' => [['id' => (int)$row['PlaceID'], 'text' => $row['PlaceName']]]]);
            exit;
        } else {
            echo json_encode(['results' => []]);
            exit;
        }
    }

    if ($q === '') {
        // return recent or first page
        $stmt = query("SELECT PlaceID, PlaceName FROM `Map` ORDER BY PlaceName ASC LIMIT ? OFFSET ?", [$perPage, $offset]);
        $rows = $stmt->fetchAll();
    } else {
        $like = "%{$q}%";
        $stmt = query("SELECT PlaceID, PlaceName FROM `Map` WHERE PlaceName LIKE ? ORDER BY PlaceName ASC LIMIT ? OFFSET ?", [$like, $perPage, $offset]);
        $rows = $stmt->fetchAll();
    }

    $results = [];
    foreach ($rows as $r) {
        $results[] = ['id' => (int)$r['PlaceID'], 'text' => $r['PlaceName']];
    }

    // If we have perPage results then there might be more pages
    $more = count($results) === $perPage;

    echo json_encode(['results' => $results, 'more' => $more]);
    exit;
} catch (Exception $e) {
    error_log('map_search.php error: ' . $e->getMessage());
    echo json_encode(['results' => []]);
    exit;
}