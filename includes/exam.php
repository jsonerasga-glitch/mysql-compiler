<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';

// Database on the target MySQL server that holds exam data (the `exams`
// table) and the per-student reference tables. Fixed rather than
// user-supplied so a request can never redirect GRANTs at another database.
const EXAM_DATABASE = 'dbms_exam';

// The MySQL login that administers exams — the only account that may see
// exam_setup.php, and the identity used server-side to read the exams
// table (including the answer key) when grading a student's own answer.
const EXAM_ADMIN_USERNAME = 'json_root';

// Only the json_root MySQL login may see or use the exam setup tools.
function is_exam_admin(): bool
{
    return current_owner_username() === EXAM_ADMIN_USERNAME;
}

// Looks up json_root's own saved connection (created the first time they
// logged in) so student-facing endpoints can read the exams table —
// including the answer key — without students ever needing SELECT on it
// themselves. Students only ever get the graded ok/wrong verdict back.
function get_exam_admin_profile(): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM connections WHERE owner_username = ? ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute([EXAM_ADMIN_USERNAME]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function require_exam_admin_api(): void
{
    if (!is_exam_admin()) {
        json_error('Forbidden.', 403);
    }
}

function require_exam_admin_page(): void
{
    if (!is_exam_admin()) {
        http_response_code(403);
        echo 'Forbidden.';
        exit;
    }
}

// GRANT doesn't support bound parameters for identifiers or the username,
// so both table names and the MySQL username are checked against this
// allowlist before ever being concatenated into SQL.
function is_valid_mysql_identifier(string $value): bool
{
    return preg_match('/^[A-Za-z0-9_]{1,64}$/', $value) === 1;
}

// reference_tables is stored as a comma-separated list of table names.
function parse_reference_tables(string $raw): array
{
    $tables = array_map('trim', explode(',', $raw));
    $tables = array_filter($tables, fn ($t) => $t !== '');
    return array_values(array_unique($tables));
}
