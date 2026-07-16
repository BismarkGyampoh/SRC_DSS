<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';

requireRole(['Executive Board']);
requireCsrfToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/sandbox.php');
    exit;
}

$simBudget = filter_input(INPUT_POST, 'sim_budget', FILTER_VALIDATE_FLOAT);
$simHours = filter_input(INPUT_POST, 'sim_hours', FILTER_VALIDATE_INT);
$simReachWt = filter_input(INPUT_POST, 'sim_reach_weight', FILTER_VALIDATE_FLOAT);
$simSpeedWt = filter_input(INPUT_POST, 'sim_speed_weight', FILTER_VALIDATE_FLOAT);

if ($simBudget === false || $simBudget < 0) {
    $_SESSION['flash_message'] = 'Invalid simulated budget. Must be a non-negative number.';
    header('Location: ../views/sandbox.php');
    exit;
}

if ($simHours === false || $simHours < 1) {
    $_SESSION['flash_message'] = 'Invalid simulated hours. Must be a positive integer.';
    header('Location: ../views/sandbox.php');
    exit;
}

if ($simReachWt === false || $simReachWt < 0 || $simReachWt > 1) {
    $_SESSION['flash_message'] = 'Invalid simulated reach weight. Must be between 0 and 1.';
    header('Location: ../views/sandbox.php');
    exit;
}

if ($simSpeedWt === false || $simSpeedWt < 0 || $simSpeedWt > 1) {
    $_SESSION['flash_message'] = 'Invalid simulated speed weight. Must be between 0 and 1.';
    header('Location: ../views/sandbox.php');
    exit;
}

$weightSum = $simReachWt + $simSpeedWt;
if (abs($weightSum - 1.0) > 0.0001) {
    $_SESSION['flash_message'] = 'Reach and speed weights must sum to 1.0.';
    header('Location: ../views/sandbox.php');
    exit;
}

require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';
$model = $modelService->getModelByName('Sandbox Simulator');
$modelId = $model !== false ? (int) $model['model_id'] : 5;

$executionId = $modelService->registerExecution([
    'model_id' => $modelId,
    'triggered_by' => (int) $_SESSION['user_id'],
    'academic_term' => '2025/2026 Semester 1',
    'input_snapshot' => [
        'sim_budget' => $simBudget,
        'sim_hours' => $simHours,
        'sim_reach_weight' => $simReachWt,
        'sim_speed_weight' => $simSpeedWt,
    ],
    'status' => 'Running',
]);

require_once __DIR__ . '/../../services/model_management/KnapsackOptimizationService.php';

$knapsackService = new KnapsackOptimizationService($pdo);
$sandboxData = $knapsackService->runSandboxKnapsack($simBudget, $simHours, $simReachWt, $simSpeedWt);

$modelService->completeExecution($executionId, [
    'output_snapshot' => $sandboxData,
    'execution_time_ms' => 0,
    'status' => 'Completed',
]);

require_once __DIR__ . '/../../services/ActivityLogger.php';
ActivityLogger::log(
    $pdo,
    'SANDBOX_RUN',
    'Sandbox simulation executed: Budget=' . $simBudget . ' GHS, Hours=' . $simHours . ', ReachWt=' . $simReachWt . ', SpeedWt=' . $simSpeedWt . ', ExecutionID=' . $executionId,
    'sandbox',
    null,
    null,
    ['sim_budget' => $simBudget, 'sim_hours' => $simHours, 'sim_reach_weight' => $simReachWt, 'sim_speed_weight' => $simSpeedWt, 'execution_id' => $executionId]
);

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'data' => $sandboxData]);
exit;
