<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/model_management/KnapsackOptimizationService.php';
require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';

requireRole(['Executive Board']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken();

$modelService = new ModelManagementService($pdo);
$model = $modelService->getModelByName('Knapsack Optimizer');
$modelId = $model !== false ? (int) $model['model_id'] : 1;

$executionId = $modelService->registerExecution([
    'model_id' => $modelId,
    'triggered_by' => (int) $_SESSION['user_id'],
    'academic_term' => getActiveAcademicTerm($pdo),
    'input_snapshot' => [
        'user_id' => (int) $_SESSION['user_id'],
        'timestamp' => date('c'),
    ],
    'status' => 'Running',
]);

$startTime = microtime(true);

$engine = new KnapsackOptimizationService($pdo);
$engine->calculatePIS();
$result = $engine->runKnapsack((int) $_SESSION['user_id']);

$executionTimeMs = (int) round((microtime(true) - $startTime) * 1000);

$modelService->completeExecution($executionId, [
    'output_snapshot' => [
        'accepted_count' => count($result['accepted']),
        'rejected_count' => count($result['rejected']),
        'budget_used' => $result['budget_used'],
        'hours_used' => $result['hours_used'],
    ],
    'execution_time_ms' => $executionTimeMs,
    'status' => 'Completed',
]);

require_once __DIR__ . '/../../services/ActivityLogger.php';
ActivityLogger::log(
    $pdo,
    'ENGINE_RUN',
    'Knapsack optimization engine executed. Accepted=' . count($result['accepted']) . ', Rejected=' . count($result['rejected']) . ', Budget=' . $result['budget_used'] . ' GHS, ExecutionID=' . $executionId,
    'optimization',
    null,
    null,
    ['accepted_count' => count($result['accepted']), 'rejected_count' => count($result['rejected']), 'budget_used' => $result['budget_used'], 'hours_used' => $result['hours_used'], 'execution_id' => $executionId]
);

$_SESSION['flash_message'] = 'Optimization complete. New report generated.';

header('Location: /dss/views/optimization.php');
exit();
