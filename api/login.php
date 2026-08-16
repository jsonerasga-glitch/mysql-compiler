<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$body = read_json_body();
$host = trim($body['host'] ?? '');
$port = (int) ($body['port'] ?? 3306);
$username = trim($body['username'] ?? '');
$password = (string) ($body['password'] ?? '');
$database = trim($body['database_name'] ?? '');

if ($host === '' || $username === '') {
    json_error('Host and username are required.');
}
$port = $port ?: 3306;

$profile = [
    'host' => $host,
    'port' => $port,
    'username' => $username,
    'password' => $password !== '' ? encrypt_secret($password) : null,
    'database_name' => $database !== '' ? $database : null,
];

try {
    open_target_pdo($profile);
} catch (PDOException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()]);
}

// Credentials are valid — remember this as a saved connection (so it also
// shows up in the sidebar), then start the session.
// The owner of a connection saved from login is always the person logging
// in — scoping the lookup this way means one person's login never hijacks
// or overwrites another person's saved copy of the same host/user pair.
try {
    $pdo = app_db();
    $stmt = $pdo->prepare('SELECT * FROM connections WHERE host = ? AND port = ? AND username = ? AND owner_username = ?');
    $stmt->execute([$host, $port, $username, $username]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $displayName = "$username@$host";
    $passwordToStore = $password !== '' ? $profile['password'] : ($existing['password'] ?? null);

    if ($existing) {
        $id = (int) $existing['id'];
        $update = $pdo->prepare(
            'UPDATE connections SET password = ?, database_name = ?, updated_at = NOW() WHERE id = ?'
        );
        $update->execute([$passwordToStore, $profile['database_name'], $id]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO connections (name, host, port, username, password, database_name, owner_username, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $insert->execute([$displayName, $host, $port, $username, $passwordToStore, $profile['database_name'], $username]);
        $id = (int) $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    json_response([
        'ok' => false,
        'message' => 'App database error: ' . $e->getMessage() . ' — have you run "php database/migrate.php" and set config/database.php?',
    ]);
}

ensure_session();
session_regenerate_id(true);
$_SESSION['logged_in'] = true;
$_SESSION['connection_id'] = $id;
$_SESSION['username'] = $username;

json_response(['ok' => true, 'connection_id' => $id]);
