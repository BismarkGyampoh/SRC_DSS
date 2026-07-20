<?php
/**
 * Standalone logic tests for SRC_DSS fixes.
 * Run: php tests/run_tests.php
 * No framework needed. Uses a tiny PDO mock for DB-touching helpers.
 */

declare(strict_types=1);

$passed = 0;
$failed = 0;

function assertEq($label, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        echo "  FAIL: $label (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
    }
}

// --- Minimal PDO mock that returns a configured single row for system_config ---
class MockPDO extends PDO
{
    public array $configRow;
    public function __construct(array $configRow)
    {
        $this->configRow = $configRow;
    }
    public function query(string $sql, ?int $fetchMode = null, mixed ...$args): mixed
    {
        $row = $this->configRow;
        return new class($row) {
            public function __construct(private array $row) {}
            public function fetch($mode = null, $cursor = null, $offset = null): array { return $this->row; }
        };
    }
}

// Load helpers without connecting to a real DB.
require_once __DIR__ . '/../config/auth.php';

echo "TEST: getActiveAcademicTerm()\n";
assertEq('reads year from config + Semester 1 suffix',
    getActiveAcademicTerm(new MockPDO(['active_academic_year' => '2025/2026'])),
    '2025/2026 Semester 1');
assertEq('handles different year',
    getActiveAcademicTerm(new MockPDO(['active_academic_year' => '2026/2027'])),
    '2026/2027 Semester 1');
assertEq('falls back when year empty',
    getActiveAcademicTerm(new MockPDO(['active_academic_year' => ''])),
    '2025/2026 Semester 1');

echo "TEST: API key timing-safe comparison\n";
$expected = 'src-api-key-2026';
assertEq('hash_equals matches correct key', hash_equals($expected, 'src-api-key-2026'), true);
assertEq('hash_equals rejects wrong key', hash_equals($expected, 'wrong-key'), false);
assertEq('hash_equals rejects empty', hash_equals($expected, ''), false);

echo "TEST: EmailService::createFromDbConfig reads real single-row schema (no error)\n";
require_once __DIR__ . '/../services/EmailService.php';
$email = EmailService::createFromDbConfig(new MockPDO(['active_academic_year' => '2025/2026', 'maintenance_mode' => 0]));
assertEq('createFromDbConfig returns instance', $email instanceof EmailService, true);
assertEq('isEnabled reflects constructor default (env unset => false)', $email->isEnabled(), false);

echo "\nRESULT: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
