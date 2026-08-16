<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/auth.php';

// Only ever returns a connection owned by the currently logged-in user —
// never one belonging to someone else, even if the id is guessed/known.
function get_connection_profile(int $id): ?array
{
    $owner = current_owner_username();
    if ($owner === null) {
        return null;
    }

    $stmt = app_db()->prepare('SELECT * FROM connections WHERE id = ? AND owner_username = ?');
    $stmt->execute([$id, $owner]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function open_target_pdo(array $profile, ?string $database = null): PDO
{
    $db = $database ?? ($profile['database_name'] ?: null);
    $dsn = sprintf(
        'mysql:host=%s;port=%d%s;charset=utf8mb4',
        $profile['host'],
        (int) $profile['port'],
        $db ? ';dbname=' . $db : ''
    );

    $pdo = new PDO($dsn, $profile['username'], decrypt_secret($profile['password']), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    return $pdo;
}
