<?php
require_once 'config.php';
require_once 'functions.php';

// Require login and admin for safety


$search = trim($_GET['search'] ?? '');

// Build query with optional search
if ($search === '') {
    $stmt = query("SELECT itemid, itemcode, itemname FROM items ORDER BY itemid DESC");
    $items = $stmt->fetchAll();
} else {
    $like = "%$search%";
    $stmt = query("SELECT itemid, itemcode, itemname FROM items WHERE itemcode LIKE ? OR itemname LIKE ? ORDER BY itemid DESC", [$like, $like]);
    $items = $stmt->fetchAll();
}

if (count($items) === 0) {
    echo '<tr><td colspan="4" class="text-center text-muted">No items found.</td></tr>';
    exit;
}

foreach ($items as $it) {
    $id = (int)$it['itemid'];
    $code = htmlspecialchars($it['itemcode']);
    $name = htmlspecialchars($it['itemname']);

    // Buttons: Edit and Delete. Edit carries data attributes for the modal.
    echo "<tr>
        <td>{$id}</td>
        <td>{$code}</td>
        <td>{$name}</td>
        <td class=\"text-center item-row-actions\">
            <button class=\"btn btn-sm btn-secondary edit-item-btn\" 
                    data-itemid=\"{$id}\" 
                    data-itemcode=\"".htmlspecialchars($it['itemcode'], ENT_QUOTES)."\" 
                    data-itemname=\"".htmlspecialchars($it['itemname'], ENT_QUOTES)."\">
                Edit
            </button>
            <button class=\"btn btn-sm btn-danger delete-item-btn\" data-itemid=\"{$id}\">Delete</button>
        </td>
    </tr>";
}