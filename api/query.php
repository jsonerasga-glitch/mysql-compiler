<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/query_log.php';

require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$body = read_json_body();
$id = (int) ($body['id'] ?? 0);
$sql = trim((string) ($body['sql'] ?? ''));
$database = $body['database'] ?? null;

if ($id <= 0) {
    json_error('Missing connection id.');
}
if ($sql === '') {
    json_error('SQL statement is empty.');
}

$profile = get_connection_profile($id);
if (!$profile) {
    json_error('Connection not found.', 404);
}

$firstWord = strtoupper(preg_split('/\s+/', ltrim($sql), 2)[0] ?? '');
$returnsRows = in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH'], true);

$start = microtime(true);

try {
    $pdo = open_target_pdo($profile, $database);

    if ($returnsRows) {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta = $stmt->getColumnMeta($i);
            $columns[] = $meta['name'];
        }
        $timeMs = round((microtime(true) - $start) * 1000, 2);

        log_query_execution($profile, $database, $sql, 'success', 'select', count($rows), null, null, $timeMs);

        json_response([
            'type' => 'select',
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => count($rows),
            'time_ms' => $timeMs,
        ]);
    } else {
        $affected = $pdo->exec($sql);
        $affected = $affected === false ? 0 : $affected;
        $timeMs = round((microtime(true) - $start) * 1000, 2);

        log_query_execution($profile, $database, $sql, 'success', 'exec', null, $affected, null, $timeMs);

        json_response([
            'type' => 'exec',
            'affected_rows' => $affected,
            'time_ms' => $timeMs,
        ]);
    }
} catch (PDOException $e) {
    $timeMs = round((microtime(true) - $start) * 1000, 2);

    log_query_execution($profile, $database, $sql, 'error', null, null, null, $e->getMessage(), $timeMs);

    json_response([
        'type' => 'error',
        'message' => $e->getMessage(),
        'time_ms' => $timeMs,
    ], 200);
}
