<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/services/KnapsackOptimizationService.php';

$service = new KnapsackOptimizationService($pdo);

echo "=== SRC DSS Knapsack Optimization Engine Test ===\n\n";

$pisUpdated = $service->calculatePIS();
echo "Step 1: calculatePIS() updated {$pisUpdated} pending project(s).\n\n";

$result = $service->runKnapsack(3);
echo "Step 2: runKnapsack() completed.\n";
echo "  Accepted project IDs: " . (empty($result['accepted']) ? 'none' : implode(', ', $result['accepted'])) . "\n";
echo "  Rejected project IDs: " . (empty($result['rejected']) ? 'none' : implode(', ', $result['rejected'])) . "\n";
echo "  Total PIS:            {$result['total_pis']}\n";
echo "  Budget used:          {$result['budget_used']} GHS\n";
echo "  Volunteer hours used: {$result['hours_used']} hrs\n\n";

$stmt = $pdo->query(
    'SELECT project_id, title, budget_required, volunteer_hours, calculated_pis, dss_status
     FROM projects
     ORDER BY project_id ASC'
);
$projects = $stmt->fetchAll();

echo str_repeat('-', 110) . "\n";
printf(
    "%-4s %-35s %12s %10s %12s %10s\n",
    'ID',
    'Title',
    'Budget',
    'Hours',
    'PIS',
    'Status'
);
echo str_repeat('-', 110) . "\n";

foreach ($projects as $project) {
    printf(
        "%-4d %-35s %12s %10d %12s %10s\n",
        $project['project_id'],
        $project['title'],
        number_format((float) $project['budget_required'], 2),
        (int) $project['volunteer_hours'],
        $project['calculated_pis'] !== null
            ? number_format((float) $project['calculated_pis'], 4)
            : 'N/A',
        $project['dss_status']
    );
}

echo str_repeat('-', 110) . "\n";
