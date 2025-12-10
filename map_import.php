<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

// helper to send JSON and exit
function respond($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// debug logger (temp file)
$logFile = sys_get_temp_dir() . '/map_import.log';
function log_debug($msg) {
    global $logFile;
    @error_log(date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, 3, $logFile);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Use POST to upload CSV.'], 405);
}

if (!isLoggedIn() || !isAdmin()) {
    respond(['success' => false, 'message' => 'Access denied.']);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['file']['error'] ?? 'no_file';
    $msg = 'File upload failed or no file provided. Error code: ' . $err;
    log_debug('Upload error: ' . $msg);
    respond(['success' => false, 'message' => $msg]);
}

$tmp = $_FILES['file']['tmp_name'];
$origName = $_FILES['file']['name'] ?? 'upload.csv';
$sizeBytes = $_FILES['file']['size'] ?? 0;

// Quick file-size check vs PHP limits (helpful message)
$ini_upload = ini_get('upload_max_filesize');
$ini_post = ini_get('post_max_size');
// convert shorthand size to bytes
function sh_to_bytes($val) {
    $val = trim($val);
    if ($val === '') return 0;
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    switch($last) {
        case 'g': return $num * 1024 * 1024 * 1024;
        case 'm': return $num * 1024 * 1024;
        case 'k': return $num * 1024;
        default: return (int)$val;
    }
}
if ($sizeBytes > sh_to_bytes($ini_upload) || $sizeBytes > sh_to_bytes($ini_post)) {
    $msg = "Uploaded file ({$sizeBytes} bytes) exceeds PHP limits (upload_max_filesize={$ini_upload}, post_max_size={$ini_post}).";
    log_debug($msg);
    respond(['success' => false, 'message' => $msg]);
}

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== 'csv' && $ext !== 'txt') {
    respond(['success' => false, 'message' => 'Please upload a CSV file.']);
}

$providedDelim = $_POST['delimiter'] ?? '';

// Read sample for encoding and delimiter detection
$sample = file_get_contents($tmp, false, null, 0, 32768);
if ($sample === false) $sample = '';
// remove common UTF BOM if present for sample
$sample_no_bom = preg_replace("/^\x{FEFF}/u", '', $sample);

// Check for mbstring/iconv availability
$has_mb = function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding');
$has_iconv = function_exists('iconv');
if (!$has_mb && !$has_iconv) {
    $msg = 'Server missing mbstring and iconv extensions — required for robust encoding conversion.';
    log_debug($msg);
    respond(['success' => false, 'message' => $msg]);
}

// Safe encoding detection: prepare a candidate list but only use names supported by mb_list_encodings()
// Accept common names and their canonical alternatives
$preferred_encodings = ['UTF-8', 'CP1256', 'WINDOWS-1256', 'ISO-8859-6', 'WINDOWS-1252', 'ISO-8859-1'];

$enc = false;
if ($has_mb) {
    $available = mb_list_encodings();
    $candidates = [];
    foreach ($preferred_encodings as $pe) {
        foreach ($available as $a) {
            if (strcasecmp($a, $pe) === 0) { $candidates[] = $a; break; }
        }
    }
    // If no preferred candidate matched, fall back to mb_detect_order list
    if (count($candidates) === 0) {
        $candidates = mb_detect_order();
        if (!is_array($candidates)) $candidates = [];
    }

    // mb_detect_encoding expects either array of encoding names or a string; ensure it's an array (non-empty)
    if (count($candidates) > 0) {
        $enc = @mb_detect_encoding($sample_no_bom, $candidates, true);
    } else {
        $enc = @mb_detect_encoding($sample_no_bom, mb_detect_order(), true);
    }
}

// Fallback using iconv heuristics if mb failed or returned false
if ($enc === false && $has_iconv) {
    $tries = ['UTF-8', 'CP1256', 'WINDOWS-1256', 'ISO-8859-6', 'WINDOWS-1252', 'ISO-8859-1'];
    foreach ($tries as $try) {
        $conv = @iconv($try, 'UTF-8//IGNORE', $sample_no_bom);
        if ($conv !== false && strlen(trim($conv)) > 0) { $enc = $try; break; }
    }
}

// Final fallback
if ($enc === false) $enc = 'UTF-8';

log_debug("Detected encoding: {$enc} for file {$origName}");

// Determine delimiter
$delims = [',',';',"\t"];
$delimiter = ',';
if ($providedDelim) {
    $delimiter = $providedDelim === '\t' ? "\t" : $providedDelim;
} else {
    $best = ',';
    $bestCount = -1;
    foreach ($delims as $d) {
        $c = substr_count($sample_no_bom, $d);
        if ($c > $bestCount) { $bestCount = $c; $best = $d; }
    }
    $delimiter = $best;
}
log_debug("Using delimiter: " . ($delimiter === "\t" ? '\\t' : $delimiter));

// Read entire file and convert to UTF-8 if needed
$raw = file_get_contents($tmp);
if ($raw === false) {
    log_debug('Failed to read uploaded file contents.');
    respond(['success' => false, 'message' => 'Failed to read uploaded file.']);
}

// remove UTF-8 BOM if present
$raw = preg_replace("/^\x{EF}\x{BB}\x{BF}/", '', $raw);

// convert if necessary
$converted = $raw;
if (strtoupper($enc) !== 'UTF-8' && $has_mb) {
    $converted = @mb_convert_encoding($raw, 'UTF-8', $enc);
    if ($converted === false && $has_iconv) {
        $converted = @iconv($enc, 'UTF-8//IGNORE', $raw);
    }
} elseif (strtoupper($enc) !== 'UTF-8' && $has_iconv) {
    $converted = @iconv($enc, 'UTF-8//IGNORE', $raw);
}

if ($converted === false) {
    log_debug("Encoding conversion failed from {$enc} to UTF-8.");
    respond(['success' => false, 'message' => "Failed to convert file encoding from {$enc} to UTF-8."]);
}

// normalize newlines
$converted = str_replace(["\r\n","\r"], "\n", $converted);

// load into stream for fgetcsv
$stream = fopen('php://temp', 'r+');
if ($stream === false) {
    log_debug('Failed to open temp stream.');
    respond(['success' => false, 'message' => 'Server error: cannot create temp stream.']);
}
fwrite($stream, $converted);
rewind($stream);

// import counters
$inserted = 0;
$skipped = 0;
$errors = [];
$preview = [];
$lineNo = 0;

try {
    $pdo->beginTransaction();

    while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
        $lineNo++;
        // skip completely empty rows
        $allEmpty = true;
        foreach ($row as $c) { if (trim((string)$c) !== '') { $allEmpty = false; break; } }
        if ($allEmpty) continue;

        if (count($preview) < 10) {
            $preview[] = array_map(function($c){ return is_string($c) ? $c : (string)$c; }, $row);
        }

        // header detection for first row
        if ($lineNo === 1) {
            $lc0 = strtolower(trim($row[0] ?? ''));
            if (strpos($lc0, 'place') !== false || strpos($lc0, 'name') !== false) {
                continue;
            }
        }

        $placename = trim($row[0] ?? '');
        if ($placename === '') {
            $skipped++;
            $errors[] = "Line {$lineNo}: empty place name";
            continue;
        }

        // check duplicate (use case-insensitive collation) - use utf8mb4 collation
        $exists = query("SELECT PlaceID FROM `Map` WHERE PlaceName COLLATE utf8mb4_general_ci = ? LIMIT 1", [$placename])->fetch();
        if ($exists) {
            $skipped++;
            continue;
        }

        query("INSERT INTO `Map` (PlaceName) VALUES (?)", [$placename]);
        $inserted++;
    }

    $pdo->commit();
    fclose($stream);

    $result = [
        'success' => true,
        'message' => 'Import completed.',
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors,
        'preview' => $preview,
        'detected_encoding' => $enc,
        'used_delimiter' => ($delimiter === "\t" ? '\\t' : $delimiter)
    ];
    log_debug('Import result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
    respond($result);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (is_resource($stream)) fclose($stream);
    $errMsg = $e->getMessage();
    log_debug('Import exception: ' . $errMsg);
    respond([
        'success' => false,
        'message' => 'Server error during import.',
        'error' => $errMsg,
        'detected_encoding' => $enc,
        'used_delimiter' => ($delimiter === "\t" ? '\\t' : $delimiter)
    ], 500);
}