<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/lab_exam_log.php';

require_login_api();

if (!LAB_EXAM_MODE) {
    json_error('Not found.', 404);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$body = read_json_body();
$eventType = trim((string) ($body['event_type'] ?? ''));
$detail = isset($body['detail']) && $body['detail'] !== null ? substr((string) $body['detail'], 0, 255) : null;

$allowedEvents = ['tab_hidden', 'tab_visible', 'window_blur', 'window_focus', 'page_hide', 'network_info'];
if (!in_array($eventType, $allowedEvents, true)) {
    json_error('Invalid event_type.');
}

log_lab_exam_event(current_owner_username(), $eventType, $detail);

json_response(['ok' => true]);
