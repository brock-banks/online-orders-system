<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$start = $_GET['start'] ?? date('Y-m-d', strtotime('-29 days'));
$end = $_GET['end'] ?? date('Y-m-d');

// sanitize and enforce date format
$startDt = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime('-29 days');
$endDt = DateTime::createFromFormat('Y-m-d', $end) ?: new DateTime();

$startStr = $startDt->format('Y-m-d') . ' 00:00:00';
$endStr = $endDt->format('Y-m-d') . ' 23:59:59';

// Stats
$totalOrders = query("SELECT COUNT(*) AS c FROM orders WHERE date BETWEEN ? AND ?", [$startStr, $endStr])->fetch()['c'] ?? 0;
$totalRevenue = query("SELECT IFNULL(SUM(price),0) AS s FROM orders WHERE date BETWEEN ? AND ?", [$startStr, $endStr])->fetch()['s'] ?? 0;
$delivered = query("SELECT COUNT(*) AS c FROM orders WHERE status = 'delivered' AND date BETWEEN ? AND ?", [$startStr, $endStr])->fetch()['c'] ?? 0;
$pending = query("SELECT COUNT(*) AS c FROM orders WHERE status != 'delivered' AND date BETWEEN ? AND ?", [$startStr, $endStr])->fetch()['c'] ?? 0;
$totalUsers = query("SELECT COUNT(*) AS c FROM users")->fetch()['c'] ?? 0;

// Orders and revenue per day (fill missing dates)
$period = new DatePeriod(
    new DateTime($startDt->format('Y-m-d')),
    new DateInterval('P1D'),
    (new DateTime($endDt->format('Y-m-d')))->modify('+1 day')
);

$labels = [];
foreach ($period as $dt) {
    $labels[] = $dt->format('Y-m-d');
}

// get aggregated orders and revenue grouped by date
$orderRows = query(
    "SELECT DATE(date) AS d, COUNT(*) AS orders_count, IFNULL(SUM(price),0) AS revenue_sum
     FROM orders
     WHERE date BETWEEN ? AND ?
     GROUP BY DATE(date)
     ORDER BY DATE(date) ASC",
    [$startStr, $endStr]
)->fetchAll();

// map results by date
$orderMap = [];
foreach ($orderRows as $r) {
    $orderMap[$r['d']] = ['orders' => (int)$r['orders_count'], 'revenue' => (float)$r['revenue_sum']];
}

$ordersData = [];
$revenueData = [];
foreach ($labels as $lab) {
    $ordersData[] = isset($orderMap[$lab]) ? $orderMap[$lab]['orders'] : 0;
    $revenueData[] = isset($orderMap[$lab]) ? $orderMap[$lab]['revenue'] : 0.00;
}

// Top users by order count (limit 8)
$topUsersRows = query("
    SELECT u.username, u.id AS user_id, COUNT(o.id) AS cnt
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.date BETWEEN ? AND ?
    GROUP BY u.id, u.username
    ORDER BY cnt DESC
    LIMIT 8
", [$startStr, $endStr])->fetchAll();

$topUsersLabels = [];
$topUsersCounts = [];
foreach ($topUsersRows as $r) {
    $topUsersLabels[] = $r['username'] ?: 'user-' . $r['user_id'];
    $topUsersCounts[] = (int)$r['cnt'];
}

// Recent orders (last 20 in range)
$recentOrders = query("SELECT o.id, o.invoice_no, o.user_id, u.username, o.phone, o.address, o.price, o.status, o.date
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.date BETWEEN ? AND ?
    ORDER BY o.date DESC
    LIMIT 20", [$startStr, $endStr])->fetchAll();

// Hour-of-day distribution (0..23)
// Initialize hours array
$hoursLabels = [];
$hoursCounts = array_fill(0, 24, 0);
for ($h = 0; $h < 24; $h++) $hoursLabels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT);

// Query grouped by hour
$hourRows = query("
    SELECT HOUR(date) AS hr, COUNT(*) AS cnt
    FROM orders
    WHERE date BETWEEN ? AND ?
    GROUP BY HOUR(date)
", [$startStr, $endStr])->fetchAll();

foreach ($hourRows as $r) {
    $hr = (int)$r['hr'];
    $hoursCounts[$hr] = (int)$r['cnt'];
}

// Conversion funnel: placed -> delivered (within the selected range)
// If desired, you can refine the funnel steps later (e.g., confirmed payment, shipped, delivered).
$funnelPlaced = (int)$totalOrders;
$funnelDelivered = (int)$delivered;
$funnelConversion = $funnelPlaced > 0 ? round(($funnelDelivered / $funnelPlaced) * 100, 2) : 0.0;

$funnel = [
    'placed' => $funnelPlaced,
    'delivered' => $funnelDelivered,
    'conversion_percent' => $funnelConversion
];

$response = [
    'stats' => [
        'total_orders' => (int)$totalOrders,
        'total_revenue' => (float)$totalRevenue,
        'delivered' => (int)$delivered,
        'pending' => (int)$pending,
        'total_users' => (int)$totalUsers
    ],
    'labels' => $labels,
    'orders' => $ordersData,
    'revenue' => $revenueData,
    'top_users' => [
        'labels' => $topUsersLabels,
        'counts' => $topUsersCounts
    ],
    'recent_orders' => $recentOrders,
    'hours' => [
        'labels' => $hoursLabels,
        'counts' => $hoursCounts
    ],
    'funnel' => $funnel
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;