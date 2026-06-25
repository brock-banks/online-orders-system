<?php
require_once 'config.php';
require_once 'functions.php';

ensureAssignedToColumn();

$searchType = $_GET['search_type'] ?? '';
$searchValue = trim($_GET['search_value'] ?? '');
$isDefault = !empty($_GET['default']);

$allowedTypes = ['invoice_no', 'phone', 'address'];

// Load message templates once (avoid querying per row inside renderOrderRow)
$messageTemplates = [
    'template_delivered'   => getMessageTemplate('template_delivered'),
    'template_place_order' => getMessageTemplate('template_place_order'),
];

// Load delivery people list for the per-row Assign dropdown
try {
    $deliveryPeopleList = query("SELECT name FROM delivery_people ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log('fetch_orders.php: failed to load delivery_people: ' . $e->getMessage());
    $deliveryPeopleList = [];
}

/**
 * Render a full-width empty-state row using the SVG icon pattern shared with orders.php.
 * $iconKey: 'search' | 'package' | 'warning'
 */
function renderEmptyStateRow($iconKey, $title, $text) {
    $icons = [
        'search'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'package' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'warning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ];
    $icon = $icons[$iconKey] ?? $icons['search'];
    $title = htmlspecialchars($title);
    $text  = htmlspecialchars($text);
    return "<tr><td colspan='9'>
        <div class='empty-state text-center'>
            <div class='empty-state-icon'>{$icon}</div>
            <div class='empty-state-title'>{$title}</div>
            <div class='empty-state-text'>{$text}</div>
        </div>
    </td></tr>";
}

// Default mode: show most recent 20 orders
if ($isDefault) {
    try {
        $orders = query("SELECT * FROM orders ORDER BY date DESC LIMIT 20")->fetchAll();
    } catch (Exception $e) {
        error_log('fetch_orders.php default error: ' . $e->getMessage());
        echo renderEmptyStateRow('warning', 'Failed to load orders', 'Please try again.');
        exit;
    }

    if (count($orders) === 0) {
        echo renderEmptyStateRow('package', 'No orders yet', 'Place your first order to get started.');
        exit;
    }

    foreach ($orders as $order) {
        echo renderOrderRow($order, $messageTemplates, $deliveryPeopleList);
    }
    exit;
}

if ($searchValue === '' || !in_array($searchType, $allowedTypes, true)) {
    echo renderEmptyStateRow('search', 'Enter a search value', 'Pick a valid search type and enter a value above.');
    exit;
}

try {
    $sql = "SELECT * FROM orders WHERE {$searchType} LIKE ? ORDER BY date DESC";
    $orders = query($sql, ["%{$searchValue}%"])->fetchAll();

    if (count($orders) > 0) {
        foreach ($orders as $order) {
            echo renderOrderRow($order, $messageTemplates, $deliveryPeopleList);
        }
    } else {
        echo renderEmptyStateRow('search', 'No matching orders found', 'Try a different search term or adjust the search type.');
    }
} catch (Exception $e) {
    error_log('fetch_orders.php error: ' . $e->getMessage());
    echo renderEmptyStateRow('warning', 'Failed to load orders', 'Please try again.');
}

function renderOrderRow($order, $templates = [], $deliveryPeople = []) {
    $id = htmlspecialchars($order['id']);
    $invoiceNo = htmlspecialchars($order['invoice_no'] ?? '');
    $details = nl2br(htmlspecialchars($order['details'] ?? ''));
    $phone = htmlspecialchars($order['phone'] ?? '');
    $address = htmlspecialchars($order['address'] ?? '');
    $date = htmlspecialchars($order['date'] ?? '');
    $status = strtolower(trim($order['status'] ?? 'pending'));
    $assignedTo = trim((string)($order['assigned_to'] ?? ''));
    $assignedToEsc = htmlspecialchars($assignedTo);

    $deliveredIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
    $pendingIcon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="7.5" x2="12" y2="12"/><line x1="12" y1="12" x2="15.5" y2="13.8"/></svg>';
    $userIcon      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

    $statusBadge = $status === 'delivered'
        ? '<span class="badge-status badge-delivered">' . $deliveredIcon . 'Delivered</span>'
        : '<span class="badge-status badge-pending">' . $pendingIcon . 'Pending</span>';

    // Courier line: only shown for delivered rows now (pending rows show the picker action instead)
    $assignmentLine = '';
    if ($status === 'delivered') {
        $deliveredBy = trim((string)($order['delivered_by'] ?? ''));
        if ($deliveredBy !== '') {
            $deliveredByEsc = htmlspecialchars($deliveredBy);
            $assignmentLine = "<div class='assigned-line text-muted small mt-1' title='Delivered by'>{$userIcon}<span>by {$deliveredByEsc}</span></div>";
        }
    }

    $phoneForWhatsapp = preg_replace('/[^\d]/', '', (string)($order['phone'] ?? ''));

    $templateVars = [
        'invoice_no'   => $order['invoice_no'] ?? '',
        'phone'        => $order['phone'] ?? '',
        'address'      => $order['address'] ?? '',
        'details'      => $order['details'] ?? '',
        'price'        => $order['price'] ?? '',
        'delivered_by' => $order['delivered_by'] ?? '',
        'date'         => $order['date'] ?? '',
        'username'     => '',
    ];

    $deliveredText  = renderMessageTemplate($templates['template_delivered']   ?? '', $templateVars);
    $placeOrderText = renderMessageTemplate($templates['template_place_order'] ?? '', $templateVars);

    $deliveredUrl  = "https://api.whatsapp.com/send?phone={$phoneForWhatsapp}&text=" . rawurlencode($deliveredText);
    $placeOrderUrl = "https://api.whatsapp.com/send?phone={$phoneForWhatsapp}&text=" . rawurlencode($placeOrderText);

    // Bulk-select checkbox — any pending row is eligible
    $bulkEligible = $status !== 'delivered';
    $bulkCheckbox = $bulkEligible
        ? "<input type='checkbox' class='form-check-input row-select' value='{$id}' aria-label='Select order {$invoiceNo} for bulk action'>"
        : "<input type='checkbox' class='form-check-input row-select' disabled aria-label='Cannot bulk-select' title='Already delivered'>";

    $row = "<tr data-order-id='{$id}'>
        <td class='select-cell'>{$bulkCheckbox}</td>
        <td>{$id}</td>
        <td>{$invoiceNo}</td>
        <td class='details-cell'>{$details}</td>
        <td>{$phone}</td>
        <td>{$address}</td>
        <td>{$date}</td>
        <td>{$statusBadge}{$assignmentLine}</td>
        <td>
            <div class='table-actions'>
                <button class='btn btn-sm btn-outline-secondary editOrderBtn'
                        data-order-id='{$id}'>
                    Edit
                </button>";

    if ($status !== 'delivered') {
        // "Mark Delivered ▾" — picking a courier marks delivered + sends delivered WhatsApp message
        $deliverMenu = "<li><h6 class='dropdown-header'>Mark delivered by</h6></li>";
        if (count($deliveryPeople) === 0) {
            $deliverMenu .= "<li><span class='dropdown-item-text text-muted small'>No delivery people configured.<br><a href='settings.php#delivery-people-pane'>Add some</a>.</span></li>";
        } else {
            foreach ($deliveryPeople as $dpName) {
                $dpNameEsc = htmlspecialchars($dpName);
                $deliverMenu .= "<li><a class='dropdown-item deliverByItem' href='#' data-order-id='{$id}' data-delivered-by='{$dpNameEsc}'>{$dpNameEsc}</a></li>";
            }
        }

        $row .= "<div class='btn-group'>
                    <button type='button' class='btn btn-sm btn-success dropdown-toggle' data-bs-toggle='dropdown' aria-expanded='false'>
                        Mark Delivered
                    </button>
                    <ul class='dropdown-menu'>{$deliverMenu}</ul>
                </div>";
    } else {
        $row .= "<button class='btn btn-sm btn-light' disabled>
                Delivered
              </button>";
    }

    $row .= "<div class='btn-group'>
                <button type='button'
                        class='btn btn-sm btn-outline-secondary dropdown-toggle'
                        data-bs-toggle='dropdown'
                        aria-expanded='false'
                        aria-label='Send WhatsApp message'>
                    <span class='d-inline-flex align-items-center gap-1'>
                        <svg viewBox='0 0 24 24' width='14' height='14' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>
                            <path d='M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z'/>
                        </svg>
                        <span>Send</span>
                    </span>
                </button>
                <ul class='dropdown-menu dropdown-menu-end'>
                    <li><h6 class='dropdown-header'>Send WhatsApp message</h6></li>
                    <li>
                        <a class='dropdown-item' href='{$deliveredUrl}' target='_blank' rel='noopener'>
                            Delivered notification
                        </a>
                    </li>
                    <li>
                        <a class='dropdown-item' href='{$placeOrderUrl}' target='_blank' rel='noopener'>
                            Order confirmation
                        </a>
                    </li>
                </ul>
            </div>
            </div>
        </td>
    </tr>";

    return $row;
}
?>