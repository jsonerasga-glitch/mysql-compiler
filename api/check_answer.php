<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';
require_once __DIR__ . '/../includes/exam_grading.php';
require_once __DIR__ . '/../includes/query_log.php';

// Self-service: any logged-in user checks their OWN assigned exam. The
// answer key lives in dbms_exam.exams, which students are never GRANTed
// access to (that would leak it) — so this always reads it through
// json_root's own saved connection, server-side, and only ever returns a
// bare pass/fail verdict (never the answer key, expected result, or any
// hint about what's wrong) to the caller.

require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$studentUsername = current_owner_username();
if ($studentUsername === null || !is_valid_mysql_identifier($studentUsername)) {
    json_error('Could not determine your username.');
}

$adminProfile = get_exam_admin_profile();
if (!$adminProfile) {
    json_error('Exam system is not set up yet. Ask your instructor to log in once.', 500);
}

try {
    $examPdo = open_target_pdo($adminProfile, EXAM_DATABASE);
} catch (PDOException $e) {
    json_error('Could not reach the exam database right now. Try again later.', 500);
}

$stmt = $examPdo->prepare('SELECT exam_id, exam_answer FROM exams WHERE user_name = ? ORDER BY exam_id DESC LIMIT 1');
$stmt->execute([$studentUsername]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    json_error('No exam is currently assigned to your account.', 404);
}

$examId = (int) $exam['exam_id'];
$expectedSql = trim((string) ($exam['exam_answer'] ?? ''));
if ($expectedSql === '' || !is_select_only($expectedSql)) {
    json_error('This exam has no gradable answer key configured yet. Ask your instructor.', 500);
}

// The student's own schema is assumed to share their MySQL username — same
// assumption the GRANT sync makes (see exam_setup.php).
try {
    $studentPdo = open_target_pdo($adminProfile, $studentUsername);
} catch (PDOException $e) {
    json_error('Could not open your database.', 500);
}

try {
    $answerStmt = $studentPdo->prepare(
        'SELECT sql_syntax FROM prelim_exam WHERE exam_id = ? ORDER BY id DESC LIMIT 1'
    );
    $answerStmt->execute([$examId]);
    $studentSql = $answerStmt->fetchColumn();
} catch (PDOException $e) {
    json_error('Could not read your prelim_exam table: ' . $e->getMessage(), 500);
}

// Every path below is a grading outcome, not a system error, so each one
// reports a bare pass/fail — no reason, no hint about what was wrong.

if ($studentSql === false || trim((string) $studentSql) === '') {
    json_response(['ok' => true, 'exam_id' => $examId, 'correct' => false, 'student_sql' => null]);
}

$studentSql = trim((string) $studentSql);
if (!is_select_only($studentSql)) {
    json_response(['ok' => true, 'exam_id' => $examId, 'correct' => false, 'student_sql' => $studentSql]);
}

try {
    $start = microtime(true);
    $expected = run_select_for_grading($examPdo, $expectedSql);
    $timeMs = round((microtime(true) - $start) * 1000, 2);
    log_query_execution($adminProfile, EXAM_DATABASE, $expectedSql, 'success', 'select', count($expected['rows']), null, null, $timeMs);
} catch (PDOException $e) {
    json_error('The answer key failed to execute. Ask your instructor.', 500);
}

try {
    $start = microtime(true);
    $student = run_select_for_grading($examPdo, $studentSql);
    $timeMs = round((microtime(true) - $start) * 1000, 2);
    log_query_execution($adminProfile, EXAM_DATABASE, $studentSql, 'success', 'select', count($student['rows']), null, null, $timeMs);
} catch (PDOException $e) {
    $timeMs = round((microtime(true) - $start) * 1000, 2);
    log_query_execution($adminProfile, EXAM_DATABASE, $studentSql, 'error', null, null, null, $e->getMessage(), $timeMs);
    json_response(['ok' => true, 'exam_id' => $examId, 'correct' => false, 'student_sql' => $studentSql]);
}

$result = grade_answer($expected, $student);

json_response([
    'ok' => true,
    'exam_id' => $examId,
    'correct' => $result['correct'],
    'student_sql' => $studentSql,
]);
