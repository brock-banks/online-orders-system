<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    echo '<tr><td colspan="3" class="text-center text-danger">Access denied.</td></tr>';
    exit;
}

$search = trim($_GET['search'] ?? '');
if ($search === '') {
    $stmt = query("SELECT PlaceID, PlaceName FROM `Map` ORDER BY PlaceID DESC");
    $places = $stmt->fetchAll();
} else {
    $like = "%$search%";
    $stmt = query("SELECT PlaceID, PlaceName FROM `Map` WHERE PlaceName LIKE ? ORDER BY PlaceID DESC", [$like]);
    $places = $stmt->fetchAll();
}

if (count($places) === 0) {
    echo '<tr><td colspan="3" class="text-center text-muted">No places found.</td></tr>';
    exit;
}

foreach ($places as $p) {
    $id = (int)$p['PlaceID'];
    $name = htmlspecialchars($p['PlaceName']);
    echo "<tr>
            <td>{$id}</td>
            <td>{$name}</td>
            <td class=\"text-center row-actions\">
                <button class=\"btn btn-sm btn-secondary edit-place-btn\" data-placeid=\"{$id}\" data-placename=\"" . htmlspecialchars($p['PlaceName'], ENT_QUOTES) . "\">Edit</button>
                <button class=\"btn btn-sm btn-danger delete-place-btn\" data-placeid=\"{$id}\">Delete</button>
            </td>
          </tr>";
}