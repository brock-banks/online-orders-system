<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// If ?sample=1 return a small sample CSV
if (isset($_GET['sample']) && $_GET['sample'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="map_sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['PlaceName']);
    fputcsv($out, ['Shop1']);
    fputcsv($out, ['Shop2']);
    fputcsv($out, ['Cargo']);
    fclose($out);
    exit;
}

// Otherwise stream full export
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="map_export_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
// header row
fputcsv($out, ['PlaceID', 'PlaceName']);

$stmt = query("SELECT PlaceID, PlaceName FROM `Map` ORDER BY PlaceID ASC");
while ($row = $stmt->fetch()) {
    fputcsv($out, [(int)$row['PlaceID'], $row['PlaceName']]);
}

fclose($out);
exit;