<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mysql_client.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';

require_login_api();
require_exam_admin_api();

$profile = get_connection_profile((int) ($_SESSION['connection_id'] ?? 0));
if (!$profile) {
    json_error('Connection not found.', 404);
}

try {
    $pdo = open_target_pdo($profile, EXAM_DATABASE);
} catch (PDOException $e) {
    json_error('Could not connect to ' . EXAM_DATABASE . ': ' . $e->getMessage(), 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query('SELECT exam_id, user_name FROM exams ORDER BY exam_id')->fetchAll(PDO::FETCH_ASSOC);
    json_response(['exams' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = read_json_body();
    $examId = (int) ($body['exam_id'] ?? 0);
    $userName = trim((string) ($body['user_name'] ?? ''));

    if ($examId <= 0) {
        json_error('Missing exam_id.');
    }
    if ($userName !== '' && !is_valid_mysql_identifier($userName)) {
        json_error('Username may only contain letters, numbers, and underscores.');
    }

    $check = $pdo->prepare('SELECT 1 FROM exams WHERE exam_id = ?');
    $check->execute([$examId]);
    if (!$check->fetchColumn()) {
        json_error('Exam not found.', 404);
    }

    $update = $pdo->prepare('UPDATE exams SET user_name = ? WHERE exam_id = ?');
    $update->execute([$userName !== '' ? $userName : null, $examId]);

    json_response(['ok' => true, 'exam_id' => $examId, 'user_name' => $userName !== '' ? $userName : null]);
}

json_error('Method not allowed.', 405);
