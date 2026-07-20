<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';
requireRole(['Executive Board']);
requireCsrfToken();

header('Content-Type: application/json');

$targetPis = filter_input(INPUT_POST, 'target_pis', FILTER_VALIDATE_FLOAT);

if ($targetPis === false || $targetPis <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Target PIS.']);
    exit;
}

require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';
$modelService = new ModelManagementService($pdo);
$model = $modelService->getModelByName('Knapsack Optimizer');
$modelId = $model !== false ? (int) $model['model_id'] : 1;

$executionId = $modelService->registerExecution([
    'model_id' => $modelId,
    'triggered_by' => (int) $_SESSION['user_id'],
    'academic_term' => getActiveAcademicTerm($pdo),
    'input_snapshot' => ['target_pis' => $targetPis],
    'status' => 'Running',
]);

require_once __DIR__ . '/../../services/model_management/KnapsackOptimizationService.php';
$engine = new KnapsackOptimizationService($pdo);

$engine->calculatePIS();
$result = $engine->runGoalSeeking($targetPis);

$modelService->completeExecution($executionId, [
    'output_snapshot' => $result,
    'execution_time_ms' => 0,
    'status' => 'Completed',
]);

require_once __DIR__ . '/../../services/ActivityLogger.php';
ActivityLogger::log(
    $pdo,
    'GOALSEEKING_RUN',
    'Goal-seeking analysis executed: target PIS=' . $targetPis . ', achieved=' . ($result['achieved_pis'] ?? 0) . ', ExecutionID=' . $executionId,
    'goalseeking',
    null,
    null,
    ['target_pis' => $targetPis, 'achieved_pis' => $result['achieved_pis'] ?? 0, 'required_budget' => $result['required_budget'] ?? 0, 'execution_id' => $executionId]
);

echo json_encode(['status' => 'success', 'data' => $result]);
exit;
