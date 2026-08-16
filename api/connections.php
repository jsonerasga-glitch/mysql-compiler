<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mysql_client.php';

require_login_api();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = app_db();
$owner = current_owner_username();

switch ($method) {
    case 'GET':
        $stmt = $pdo->prepare(
            'SELECT id, name, host, port, username, database_name, created_at, updated_at
             FROM connections WHERE owner_username = ? ORDER BY name'
        );
        $stmt->execute([$owner]);
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $body = read_json_body();
        $name = trim($body['name'] ?? '');
        $host = trim($body['host'] ?? '');
        $port = (int) ($body['port'] ?? 3306);
        $username = trim($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $database = trim($body['database_name'] ?? '');

        if ($name === '' || $host === '' || $username === '') {
            json_error('Name, host, and username are required.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO connections (name, host, port, username, password, database_name, owner_username, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $name,
            $host,
            $port ?: 3306,
            $username,
            $password !== '' ? encrypt_secret($password) : null,
            $database !== '' ? $database : null,
            $owner,
        ]);

        json_response(['id' => (int) $pdo->lastInsertId()], 201);
        break;

    case 'PUT':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_error('Missing connection id.');
        }

        // get_connection_profile() only returns rows owned by the current
        // user, so this also rejects attempts to edit someone else's.
        $current = get_connection_profile($id);
        if (!$current) {
            json_error('Connection not found.', 404);
        }

        $body = read_json_body();
        $name = trim($body['name'] ?? $current['name']);
        $host = trim($body['host'] ?? $current['host']);
        $port = (int) ($body['port'] ?? $current['port']);
        $username = trim($body['username'] ?? $current['username']);
        $database = array_key_exists('database_name', $body) ? trim((string) $body['database_name']) : ($current['database_name'] ?? '');

        // Only re-encrypt password if a new one was actually provided (non-empty).
        $passwordToStore = $current['password'];
        if (array_key_exists('password', $body) && $body['password'] !== '') {
            $passwordToStore = encrypt_secret((string) $body['password']);
        }

        if ($name === '' || $host === '' || $username === '') {
            json_error('Name, host, and username are required.');
        }

        $stmt = $pdo->prepare(
            'UPDATE connections SET name = ?, host = ?, port = ?, username = ?, password = ?, database_name = ?, updated_at = NOW()
             WHERE id = ? AND owner_username = ?'
        );
        $stmt->execute([
            $name,
            $host,
            $port ?: 3306,
            $username,
            $passwordToStore,
            $database !== '' ? $database : null,
            $id,
            $owner,
        ]);

        json_response(['ok' => true]);
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_error('Missing connection id.');
        }

        if (!get_connection_profile($id)) {
            json_error('Connection not found.', 404);
        }

        $stmt = $pdo->prepare('DELETE FROM connections WHERE id = ? AND owner_username = ?');
        $stmt->execute([$id, $owner]);
        json_response(['ok' => true]);
        break;

    default:
        json_error('Method not allowed.', 405);
}
