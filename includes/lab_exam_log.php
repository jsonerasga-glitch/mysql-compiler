<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

function log_lab_exam_event(?string $username, string $eventType, ?string $detail): void
{
    $stmt = app_db()->prepare(
        'INSERT INTO lab_exam_events (db_username, client_ip, event_type, detail, user_agent, occurred_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $username,
        get_client_ip(),
        $eventType,
        $detail,
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}
