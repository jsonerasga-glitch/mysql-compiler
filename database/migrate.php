<?php
// Creates the app's own MySQL database (if needed) and its tables.
// Run this after filling in config/database.php:
//   php database/migrate.php

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT),
        DB_USERNAME,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_DATABASE . '`');

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    // Incremental upgrade for databases created before client_ip tracking was added.
    $hasClientIp = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'query_log' AND COLUMN_NAME = 'client_ip'"
    )->fetchColumn();

    if (!$hasClientIp) {
        $pdo->exec('ALTER TABLE query_log ADD COLUMN client_ip VARCHAR(45) NULL AFTER db_host, ADD INDEX idx_query_log_client_ip (client_ip)');
        echo "Upgraded query_log: added client_ip column.\n";
    }

    // Incremental upgrade for databases created before per-user connection scoping.
    $hasOwner = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'connections' AND COLUMN_NAME = 'owner_username'"
    )->fetchColumn();

    if (!$hasOwner) {
        $pdo->exec('ALTER TABLE connections ADD COLUMN owner_username VARCHAR(255) NULL AFTER database_name, ADD INDEX idx_connections_owner_username (owner_username)');
        // Best-effort backfill: assume existing rows belong to whoever their target username is.
        $pdo->exec('UPDATE connections SET owner_username = username WHERE owner_username IS NULL');
        echo "Upgraded connections: added owner_username column (backfilled from username).\n";
    }

    echo "Migration complete: database `" . DB_DATABASE . "` is ready (connections, query_log).\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
