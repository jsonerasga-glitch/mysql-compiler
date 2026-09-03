<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';

require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_error('Missing connection id.');
}

$profile = get_connection_profile($id);
if (!$profile) {
    json_error('Connection not found.', 404);
}

$database = $_GET['database'] ?? null;
$table = $_GET['table'] ?? null;

try {
    if ($database && $table) {
        // List columns for a table.
        $pdo = open_target_pdo($profile, $database);
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_KEY AS `key`, COLUMN_DEFAULT AS `default`, EXTRA AS extra
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([$database, $table]);
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($database) {
        // List tables for a database. Views are only shown to the exam
        // admin — students should never see exam_questions_views (or any
        // other view) in their schema tree.
        $pdo = open_target_pdo($profile, $database);
        $sql = 'SELECT TABLE_NAME AS name, TABLE_TYPE AS type
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?';
        if (!is_exam_admin()) {
            $sql .= " AND TABLE_TYPE = 'BASE TABLE'";
        }
        $sql .= ' ORDER BY TABLE_NAME';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$database]);
        json_response($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        // List databases, hiding MySQL's internal system schemas.
        $pdo = open_target_pdo($profile);
        $stmt = $pdo->query('SHOW DATABASES');
        $hidden = ['information_schema', 'performance_schema'];
        $databases = array_values(array_diff($stmt->fetchAll(PDO::FETCH_COLUMN), $hidden));
        json_response($databases);
    }
} catch (PDOException $e) {
    json_error('Could not read schema: ' . $e->getMessage(), 500);
}
