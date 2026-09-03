<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';

require_login_api();
require_exam_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$examId = (int) ($_GET['exam_id'] ?? 0);
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

$stmt = $pdo->prepare('SELECT exam_question FROM exams WHERE exam_id = ?');
$stmt->execute([$examId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    json_error('Exam not found.', 404);
}

json_response(['exam_id' => $examId, 'question' => $row['exam_question']]);
