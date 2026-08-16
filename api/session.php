<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';

ensure_session();

if (!is_logged_in()) {
    json_response(['logged_in' => false]);
}

$stmt = app_db()->prepare('SELECT id, name, host, port, username, database_name FROM connections WHERE id = ?');
$stmt->execute([$_SESSION['connection_id']]);
$conn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conn) {
    json_response(['logged_in' => false]);
}

json_response(['logged_in' => true, 'connection' => $conn]);
