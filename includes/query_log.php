<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

function log_query_execution(
    array $profile,
    ?string $database,
    string $sql,
    string $status,
    ?string $resultType,
    ?int $rowCount,
    ?int $affectedRows,
    ?string $errorMessage,
    float $timeMs
): void {
    $stmt = app_db()->prepare(
        'INSERT INTO query_log
            (connection_id, connection_name, db_username, db_host, client_ip, target_database, sql_text, status, result_type, row_count, affected_rows, error_message, execution_time_ms, executed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $profile['id'] ?? null,
        $profile['name'] ?? null,
        $profile['username'] ?? null,
        $profile['host'] ?? null,
        get_client_ip(),
        $database ?: ($profile['database_name'] ?? null),
        $sql,
        $status,
        $resultType,
        $rowCount,
        $affectedRows,
        $errorMessage,
        $timeMs,
    ]);
}
