<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';

// Self-service: shows the current user's own assigned exam question, read
// from the exam_questions_views view rather than the exams table itself —
// the view exposes only user_name/exam_question, not the answer key. Still
// executed through json_root's own saved connection so this works even
// before any GRANT is set up on the view for students.

require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $pdo = open_target_pdo($adminProfile, EXAM_DATABASE);
    $stmt = $pdo->prepare('SELECT exam_question FROM exam_questions_views WHERE user_name = ? LIMIT 1');
    $stmt->execute([$studentUsername]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    json_error('Could not read exam_questions_views: ' . $e->getMessage(), 500);
}

if (!$row) {
    json_error('No exam is currently assigned to your account.', 404);
}

json_response(['question' => $row['exam_question']]);
