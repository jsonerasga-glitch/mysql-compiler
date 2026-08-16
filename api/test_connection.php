<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$body = read_json_body();

// Either test an already-saved connection by id, or test raw credentials
// (used by the "Test Connection" button in the add/edit modal before saving).
if (!empty($body['id'])) {
    $profile = get_connection_profile((int) $body['id']);
    if (!$profile) {
        json_error('Connection not found.', 404);
    }
    if (!empty($body['password'])) {
        $profile['password'] = encrypt_secret($body['password']);
    }
} else {
    $profile = [
        'host' => trim($body['host'] ?? ''),
        'port' => (int) ($body['port'] ?? 3306),
        'username' => trim($body['username'] ?? ''),
        'password' => !empty($body['password']) ? encrypt_secret($body['password']) : null,
        'database_name' => trim($body['database_name'] ?? ''),
    ];

    if ($profile['host'] === '' || $profile['username'] === '') {
        json_error('Host and username are required.');
    }
}

try {
    open_target_pdo($profile);
    json_response(['ok' => true, 'message' => 'Connection successful.']);
} catch (PDOException $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 200);
}
