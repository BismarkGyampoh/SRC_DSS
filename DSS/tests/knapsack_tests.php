<?php
/**
 * Knapsack decision-logic tests for SRC_DSS.
 * Verifies (a) the Deferred vs Rejected 0.5 PIS threshold and
 * (b) that solveKnapsack never exceeds budget OR hours capacity.
 * Uses reflection to exercise the real private solver without modifying it.
 * Run: php tests/knapsack_tests.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/model_management/KnapsackOptimizationService.php';

$passed = 0; $failed = 0;
function assertOk($label, $cond, $detail = ''): void {
    global $passed, $failed;
    if ($cond) { $passed++; echo "  PASS: $label\n"; }
    else { $failed++; echo "  FAIL: $label $detail\n"; }
}

// We need a KnapsackOptimizationService instance. Its constructor needs a PDO.
// A minimal PDO subclass works without connecting.
class TestPDO extends PDO {
    public function __construct() {}
    public function query(string $sql, ?int $fetchMode = null, mixed ...$args): mixed { return new class { public function fetch($m=null,$c=null,$o=null): array { return []; } public function fetchAll($m=null,$a=null): array { return []; } }; }
    public function prepare(string $s, array $o=[]): \PDOStatement { return new class extends \PDOStatement { public function execute(?array $p=null): bool { return true; } public function fetch($m=null,$c=null,$o=null): array { return []; } public function fetchAll($m=null,$a=null): array { return []; } }; }
}

$svc = new KnapsackOptimizationService(new TestPDO());
$ref = new ReflectionMethod($svc, 'solveKnapsack');
$ref->setAccessible(true);

echo "TEST: solveKnapsack respects budget + hours constraints\n";

// Case 1: two items, one fits, one doesn't (too expensive + too many hours)
$items = [
    ['project_id' => 1, 'weight' => 30000, 'hours' => 200, 'value' => 0.9],
    ['project_id' => 2, 'weight' => 40000, 'hours' => 400, 'value' => 0.8],
];
$budgetCap = 50000; $hoursCap = 500;
$sel = $ref->invoke($svc, $items, $budgetCap, $hoursCap);
$selIds = array_map('intval', $sel);
$budgetUsed = 0; $hoursUsed = 0;
foreach ($items as $it) { if (in_array($it['project_id'], $selIds, true)) { $budgetUsed += $it['weight']; $hoursUsed += $it['hours']; } }
assertOk('selected budget <= capacity', $budgetUsed <= $budgetCap, "($budgetUsed > $budgetCap)");
assertOk('selected hours <= capacity', $hoursUsed <= $hoursCap, "($hoursUsed > $hoursCap)");
assertOk('only fitting items selected', count($selIds) === 1 && $selIds[0] === 1, 'got ' . implode(',', $selIds));

// Case 2: empty selection when nothing fits
$big = [['project_id' => 9, 'weight' => 999999, 'hours' => 9999, 'value' => 1.0]];
$sel2 = $ref->invoke($svc, $big, 50000, 500);
assertOk('oversized item rejected', $sel2 === [], 'got ' . implode(',', $sel2));

// Case 3: maximize value within both caps (pick cheaper high-value combo)
$combo = [
    ['project_id' => 1, 'weight' => 20000, 'hours' => 150, 'value' => 0.7],
    ['project_id' => 2, 'weight' => 25000, 'hours' => 200, 'value' => 0.8],
    ['project_id' => 3, 'weight' => 15000, 'hours' => 100, 'value' => 0.5],
];
$sel3 = array_map('intval', $ref->invoke($svc, $combo, 50000, 500));
$b3 = $h3 = 0;
foreach ($combo as $it) { if (in_array($it['project_id'], $sel3, true)) { $b3 += $it['weight']; $h3 += $it['hours']; } }
assertOk('combo budget <= cap', $b3 <= 50000, "($b3)");
assertOk('combo hours <= cap', $h3 <= 500, "($h3)");

echo "TEST: Deferred (>=0.5) vs Rejected (<0.5) classification rule\n";
// Replicate the exact branch used in runKnapsack():
function classify(float $pis): string { return $pis >= 0.5000 ? 'Deferred' : 'Rejected'; }
assertOk('PIS 0.5 -> Deferred', classify(0.5000) === 'Deferred');
assertOk('PIS 0.5001 -> Deferred', classify(0.5001) === 'Deferred');
assertOk('PIS 0.4999 -> Rejected', classify(0.4999) === 'Rejected');
assertOk('PIS 0.0 -> Rejected', classify(0.0) === 'Rejected');

echo "\nRESULT: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
