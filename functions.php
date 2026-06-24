<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Idempotent migration: ensure orders.assigned_to exists.
 * Returns true if the column is present after the call, false if it could not be added.
 * Uses $pdo->exec for the DDL (some PDO setups misbehave with prepared ALTER).
 */
function ensureAssignedToColumn() {
    static $exists = null;
    if ($exists !== null) return $exists;
    global $pdo;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'assigned_to'")->fetch();
        if ($col) {
            $exists = true;
            return true;
        }
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `assigned_to` VARCHAR(100) NULL");
        $exists = true;
        return true;
    } catch (Exception $e) {
        error_log('ensureAssignedToColumn failed: ' . $e->getMessage());
        $exists = false;
        return false;
    }
}

function defaultMessageTemplate($key) {
    if ($key === 'template_place_order') {
        return "السلام عليكم ورحمة الله وبركاته\n"
            . "شكرًا لطلبك من [متجر الشاهين للوازم الرحلات والتخييم]!\n"
            . "رقم طلبك هو: # {invoice_no}\n"
            . "مكان الاستلام: {address}\n"
            . "تفاصيل الطلب:\n{details}\n\n"
            . "لمزيد من المعلومات أو المتابعة، يمكنك مراسلتنا على\n"
            . "72202722\n93211636\n\nتحياتنا،\nفريق [ALSHAHEEN ONLINE TEAM]";
    }
    if ($key === 'template_delivered') {
        return "مرحباً،\n"
            . "تم استلام طلبك رقم#\n"
            . "{invoice_no}\n"
            . "تفاصيل الطلب\n"
            . "{details}\n"
            . "تم التسليم عن طريق\n"
            . "{delivered_by}\n"
            . "، شكرًا لثقتك بنا!\n"
            . "سعداء بخدمتك، ونتمنى لك تجربة تسوّق رائعة.\n"
            . "تحياتنا،\n"
            . "فريق [ALSHAHEEN ONLINE TEAM]";
    }
    return '';
}

function getMessageTemplate($key) {
    try {
        $row = query("SELECT value FROM settings WHERE key_name = ? LIMIT 1", [$key])->fetch();
        if ($row && isset($row['value']) && trim((string)$row['value']) !== '') {
            return (string)$row['value'];
        }
    } catch (Exception $e) {
        error_log('getMessageTemplate failed for ' . $key . ': ' . $e->getMessage());
    }
    return defaultMessageTemplate($key);
}

function renderMessageTemplate($template, array $vars) {
    $search = [];
    $replace = [];
    foreach ($vars as $k => $v) {
        $search[] = '{' . $k . '}';
        $replace[] = (string)$v;
    }
    return str_replace($search, $replace, $template);
}