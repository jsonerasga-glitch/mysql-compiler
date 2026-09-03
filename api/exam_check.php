<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';
require_once __DIR__ . '/../includes/exam_grading.php';
require_once __DIR__ . '/../includes/query_log.php';

// Admin-only: from exam_setup.php, grade one exam's assigned student and
// write the verdict into exams.score (100 on pass, 0 on fail). Unlike the
// student-facing self-check, this is keyed by exam_id rather than the
// caller's own username, and it persists the result.

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
    $examPdo = open_target_pdo($profile, EXAM_DATABASE);
} catch (PDOException $e) {
    json_error('Could not connect to ' . EXAM_DATABASE . ': ' . $e->getMessage(), 500);
}

$stmt = $examPdo->prepare('SELECT exam_id, user_name, exam_answer FROM exams WHERE exam_id = ?');
$stmt->execute([$examId]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    json_error('Exam not found.', 404);
}

$studentUsername = trim((string) ($exam['user_name'] ?? ''));
if ($studentUsername === '') {
    json_error('Set a username for this exam before checking.');
}
if (!is_valid_mysql_identifier($studentUsername)) {
    json_error('Stored username is invalid: ' . $studentUsername);
}

$expectedSql = trim((string) ($exam['exam_answer'] ?? ''));
if ($expectedSql === '' || !is_select_only($expectedSql)) {
    json_error('This exam has no gradable answer key configured yet.');
}

// Persist the pass/fail verdict as a numeric score on the exam row.
function record_exam_score(PDO $pdo, int $examId, int $score): void
{
    $update = $pdo->prepare('UPDATE exams SET score = ? WHERE exam_id = ?');
    $update->execute([$score, $examId]);
}

// The student's own schema is assumed to share their MySQL username — same
// assumption the GRANT sync makes (see exam_setup.php).
try {
    $studentPdo = open_target_pdo($profile, $studentUsername);
} catch (PDOException $e) {
    json_error('Could not open the student database for ' . $studentUsername . '.', 500);
}

try {
    $answerStmt = $studentPdo->prepare(
        'SELECT sql_syntax FROM prelim_exam WHERE exam_id = ? ORDER BY id DESC LIMIT 1'
    );
    $answerStmt->execute([$examId]);
    $studentSql = $answerStmt->fetchColumn();
} catch (PDOException $e) {
    json_error('Could not read the student prelim_exam table: ' . $e->getMessage(), 500);
}

// From here on, every outcome is a grading result: record the score and
// return { correct, score }.

if ($studentSql === false || trim((string) $studentSql) === '') {
    record_exam_score($examPdo, $examId, 0);
    json_response(['ok' => true, 'exam_id' => $examId, 'user_name' => $studentUsername, 'correct' => false, 'score' => 0]);
}

$studentSql = trim((string) $studentSql);
if (!is_select_only($studentSql)) {
    record_exam_score($examPdo, $examId, 0);
    json_response(['ok' => true, 'exam_id' => $examId, 'user_name' => $studentUsername, 'correct' => false, 'score' => 0]);
}

try {
    $start = microtime(true);
    $expected = run_select_for_grading($examPdo, $expectedSql);
    $timeMs = round((microtime(true) - $start) * 1000, 2);
    log_query_execution($profile, EXAM_DATABASE, $expectedSql, 'success', 'select', count($expected['rows']), null, null, $timeMs);
} catch (PDOException $e) {
    json_error('The answer key failed to execute: ' . $e->getMessage(), 500);
}

try {
    $start = microtime(true);
    $student = run_select_for_grading($examPdo, $studentSql);
    $timeMs = round((microtime(true) - $start) * 1000, 2);
    log_query_execution($profile, EXAM_DATABASE, $studentSql, 'success', 'select', count($student['rows']), null, null, $timeMs);
} catch (PDOException $e) {
    $timeMs = round((microtime(true) - $start) * 1000, 2);
    log_query_execution($profile, EXAM_DATABASE, $studentSql, 'error', null, null, null, $e->getMessage(), $timeMs);
    record_exam_score($examPdo, $examId, 0);
    json_response(['ok' => true, 'exam_id' => $examId, 'user_name' => $studentUsername, 'correct' => false, 'score' => 0]);
}

$result = grade_answer($expected, $student);
$score = $result['correct'] ? 100 : 0;
record_exam_score($examPdo, $examId, $score);

json_response([
    'ok' => true,
    'exam_id' => $examId,
    'user_name' => $studentUsername,
    'correct' => $result['correct'],
    'score' => $score,
]);
