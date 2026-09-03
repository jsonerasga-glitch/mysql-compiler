<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';
require_once __DIR__ . '/../includes/query_log.php';

require_login_api();
require_exam_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$body = read_json_body();
$examId = (int) ($body['exam_id'] ?? 0);
if ($examId <= 0) {
    json_error('Missing exam_id.');
}

$profile = get_connection_profile((int) ($_SESSION['connection_id'] ?? 0));
if (!$profile) {
    json_error('Connection not found.', 404);
}

try {
    $pdo = open_target_pdo($profile, EXAM_DATABASE);
} catch (PDOException $e) {
    json_error('Could not connect to ' . EXAM_DATABASE . ': ' . $e->getMessage(), 500);
}

$stmt = $pdo->prepare('SELECT user_name, reference_tables FROM exams WHERE exam_id = ?');
$stmt->execute([$examId]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    json_error('Exam not found.', 404);
}

$userName = trim((string) ($exam['user_name'] ?? ''));
if ($userName === '') {
    json_error('Set a username for this exam before syncing.');
}
if (!is_valid_mysql_identifier($userName)) {
    json_error('Stored username is invalid: ' . $userName);
}

$tables = parse_reference_tables((string) ($exam['reference_tables'] ?? ''));
if (!$tables) {
    json_error('No reference tables configured for this exam.');
}

$invalid = array_values(array_filter($tables, fn ($t) => !is_valid_mysql_identifier($t)));
if ($invalid) {
    json_error('Invalid table name(s) in reference_tables: ' . implode(', ', $invalid));
}

// Each student is assumed to already have a MySQL account named user_name;
// sync only grants read access to the exam's reference tables, it never
// creates accounts. GRANT has no placeholder support for identifiers/users,
// so both the table name and username were validated above before being
// concatenated into the statement.
$results = [];
foreach ($tables as $table) {
    $sql = sprintf('GRANT SELECT ON `%s`.`%s` TO \'%s\'@\'%%\'', EXAM_DATABASE, $table, $userName);
    $start = microtime(true);
    try {
        $pdo->exec($sql);
        $timeMs = round((microtime(true) - $start) * 1000, 2);
        log_query_execution($profile, EXAM_DATABASE, $sql, 'success', 'exec', null, 0, null, $timeMs);
        $results[] = ['table' => $table, 'ok' => true, 'sql' => $sql];
    } catch (PDOException $e) {
        $timeMs = round((microtime(true) - $start) * 1000, 2);
        log_query_execution($profile, EXAM_DATABASE, $sql, 'error', null, null, null, $e->getMessage(), $timeMs);
        $results[] = ['table' => $table, 'ok' => false, 'sql' => $sql, 'error' => $e->getMessage()];
    }
}

$ok = count(array_filter($results, fn ($r) => !$r['ok'])) === 0;

json_response(['ok' => $ok, 'user_name' => $userName, 'results' => $results]);
