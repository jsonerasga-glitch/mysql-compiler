<?php
require_once __DIR__ . '/response.php';

// Only SELECT/WITH statements may be run for grading — this endpoint executes
// admin-authored "expected answer" text and student-submitted text verbatim
// with json_root's own privileges, so anything else is refused outright.
function is_select_only(string $sql): bool
{
    $trimmed = ltrim($sql);
    $firstWord = strtoupper(preg_split('/\s+/', $trimmed, 2)[0] ?? '');
    if (!in_array($firstWord, ['SELECT', 'WITH'], true)) {
        return false;
    }

    // SELECT ... INTO OUTFILE/DUMPFILE writes to the server's filesystem
    // with the connection's privileges — block it even though the
    // statement starts with SELECT.
    if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b/i', $sql)) {
        return false;
    }

    // Reject stacked statements. The driver doesn't run them by default,
    // but this is cheap defense in depth for SQL text students authored.
    if (preg_match('/;\s*\S/', $sql)) {
        return false;
    }

    return true;
}

function run_select_for_grading(PDO $pdo, string $sql): array
{
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $columns[] = $meta['name'];
    }
    return ['columns' => $columns, 'rows' => $rows];
}

// Grades a student's answer against the expected answer by comparing DATA,
// not SQL text: every column the student selected must exist in the
// expected result, and the values for those columns (projected out of the
// expected result) must match exactly, ignoring row order. This lets
// `SELECT name, age FROM students` grade as correct against an expected
// `SELECT * FROM students` when the underlying data lines up.
function grade_answer(array $expected, array $student): array
{
    $expectedCols = $expected['columns'];
    $studentCols = $student['columns'];

    if (!$studentCols) {
        return ['correct' => false, 'reason' => 'Query returned no columns.'];
    }

    $expectedColsLower = array_map('strtolower', $expectedCols);
    $studentColsLower = array_map('strtolower', $studentCols);

    $invalid = array_values(array_diff(array_unique($studentColsLower), $expectedColsLower));
    if ($invalid) {
        return [
            'correct' => false,
            'reason' => 'Column(s) not present in the expected result: ' . implode(', ', $invalid) . '.',
        ];
    }

    $normalize = function ($value) {
        return $value === null ? null : (string) $value;
    };

    $projectRow = function (array $row, array $cols) use ($normalize) {
        $lower = array_change_key_case($row, CASE_LOWER);
        $out = [];
        foreach ($cols as $c) {
            $out[] = $normalize($lower[strtolower($c)] ?? null);
        }
        return $out;
    };

    $expectedProjected = array_map(fn ($row) => $projectRow($row, $studentCols), $expected['rows']);
    $studentProjected = array_map(fn ($row) => $projectRow($row, $studentCols), $student['rows']);

    $canon = fn ($rows) => array_map('json_encode', $rows);
    $expectedCanon = $canon($expectedProjected);
    $studentCanon = $canon($studentProjected);
    sort($expectedCanon);
    sort($studentCanon);

    if ($expectedCanon !== $studentCanon) {
        return [
            'correct' => false,
            'reason' => sprintf(
                'Result data does not match the expected answer (expected %d row(s), got %d row(s) for the selected columns).',
                count($expectedCanon),
                count($studentCanon)
            ),
        ];
    }

    return ['correct' => true, 'reason' => 'Columns and result data match the expected answer.'];
}
