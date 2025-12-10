<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// Fetch items
$stmt = query("SELECT itemid, itemcode, itemname FROM items ORDER BY itemid ASC");
$items = $stmt->fetchAll();

// Prepare CSV
$filename = 'items_export_' . date('Ymd_His') . '.csv';

// Send headers for download (UTF-8 BOM so Excel opens UTF-8 properly)
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    echo "Unable to create output.";
    exit;
}

// Header row
fputcsv($output, ['itemid', 'itemcode', 'itemname']);

// Data rows
foreach ($items as $it) {
    fputcsv($output, [(int)$it['itemid'], $it['itemcode'], $it['itemname']]);
}

fclose($output);
exit;