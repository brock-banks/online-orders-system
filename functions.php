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