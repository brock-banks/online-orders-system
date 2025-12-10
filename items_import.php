<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

// Helper to return JSON and exit
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Small environment tweaks for larger files
@set_time_limit(300);
@ini_set('memory_limit', '512M');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method. Use POST.'], 405);
}



// Validate upload presence and error
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['file']['error'] ?? 'no_file';
    jsonResponse(['success' => false, 'message' => "File upload failed or no file selected. Upload error: {$err}"]);
}

$tmpPath = $_FILES['file']['tmp_name'];
$origName = basename($_FILES['file']['name'] ?? 'import.csv');
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    jsonResponse(['success' => false, 'message' => 'Only CSV files are accepted. Please save your Excel sheet as CSV and try again.']);
}

// Security: ensure it really is an uploaded file
if (!is_uploaded_file($tmpPath)) {
    jsonResponse(['success' => false, 'message' => 'Possible file upload issue. is_uploaded_file failed.']);
}

// Small file sanity check
$filesize = filesize($tmpPath);
if ($filesize === 0) {
    jsonResponse(['success' => false, 'message' => 'Uploaded file is empty.']);
}

// Convert PHP warnings to exceptions so we can return JSON on parse errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$inserted = 0;
$skipped = 0;
$lineNo = 0;
$errors = [];

try {
    // Open file (binary-safe)
    $handle = fopen($tmpPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Failed to open uploaded file for reading.');
    }

    // Read a sample to detect BOM and delimiter
    $sample = fread($handle, 8192);
    rewind($handle);

    // Detect and skip UTF-8 BOM
    if (substr($sample, 0, 3) === "\xEF\xBB\xBF") {
        // Advance past BOM
        fseek($handle, 3);
    }

    // Detect delimiter (comma, semicolon, tab) by frequency in sample
    $delimiters = [',', ';', "\t"];
    $detected = ',';
    $bestCount = -1;
    foreach ($delimiters as $d) {
        $count = substr_count($sample, $d);
        if ($count > $bestCount) {
            $bestCount = $count;
            $detected = $d;
        }
    }
    $delimiter = $detected;

    // Prepare DB
    global $pdo;
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Database connection ($pdo) not available.');
    }

    $insertStmt = $pdo->prepare("INSERT INTO items (itemcode, itemname) VALUES (?, ?)");

    // Use transaction for speed and rollback on error
    $pdo->beginTransaction();

    // Loop through CSV rows
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNo++;

        // Skip empty rows entirely
        $allEmpty = true;
        foreach ($row as $c) {
            if (trim((string)$c) !== '') { $allEmpty = false; break; }
        }
        if ($allEmpty) continue;

        // Normalize column count and trim fields
        $cols = array_map(function($v){ return is_null($v) ? '' : trim($v); }, $row);
        $colCount = count($cols);

        // Detect header row (first non-empty line). If any of the first three columns contain words like 'item'/'code'/'name', treat as header and skip.
        if ($lineNo === 1) {
            $firstThree = strtolower(implode('|', array_slice($cols, 0, 3)));
            if (strpos($firstThree, 'item') !== false || strpos($firstThree, 'code') !== false || strpos($firstThree, 'name') !== false) {
                // header row - skip
                continue;
            }
        }

        // Determine itemcode and itemname robustly:
        // Cases:
        // - Common export format (your file): itemid,itemcode,itemname,... => columns 0=itemid, 1=itemcode, 2=itemname
        // - Simpler format: itemcode,itemname,... => columns 0=itemcode, 1=itemname
        // Heuristic: if there are >=3 columns and column0 looks like numeric id and column1 non-empty, use cols[1],cols[2].
        if ($colCount >= 3 && preg_match('/^\d+$/', $cols[0]) && $cols[1] !== '') {
            $itemcode = $cols[1];
            $itemname = $cols[2] ?? '';
        } else {
            // Fallback: use first two columns as code/name
            $itemcode = $cols[0] ?? '';
            $itemname = $cols[1] ?? '';
        }

        // Final trimming
        $itemcode = trim($itemcode);
        $itemname = trim($itemname);

        if ($itemcode === '' || $itemname === '') {
            $skipped++;
            $errors[] = "Line {$lineNo}: missing code or name";
            continue;
        }

        // Check duplicate by itemcode
        $exists = query("SELECT itemid FROM items WHERE itemcode = ? LIMIT 1", [$itemcode])->fetch();
        if ($exists) {
            $skipped++;
            continue;
        }

        // Insert
        $insertStmt->execute([$itemcode, $itemname]);
        $inserted++;
    }

    // Commit
    $pdo->commit();
    fclose($handle);
    restore_error_handler();

    jsonResponse([
        'success' => true,
        'message' => "Import completed. Inserted: {$inserted}. Skipped: {$skipped}.",
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors,
    ]);
} catch (Exception $e) {
    // Roll back transaction if open
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }
    error_log('items_import.php error: ' . $e->getMessage());
    restore_error_handler();
    jsonResponse(['success' => false, 'message' => 'Server error during import: ' . $e->getMessage()], 500);
}