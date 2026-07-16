<?php
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';
requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board']);
requireCsrfToken();

$academicTerm = trim($_POST['academic_term'] ?? '');
$reachWeight = (float) ($_POST['reach_weight'] ?? 0);
$speedWeight = (float) ($_POST['speed_weight'] ?? 0);
$justification = trim($_POST['justification'] ?? '');

if (abs(($reachWeight + $speedWeight) - 1.0) > 0.0001) {
    $_SESSION['flash_message'] = 'Your GDSS weights must sum to 1.0.';
    header('Location: ../views/constraints.php');
    exit;
}

$modelService = new ModelManagementService($pdo);
$model = $modelService->getModelByName('GDSS Aggregator');
$modelId = $model !== false ? (int) $model['model_id'] : 4;

$executionId = $modelService->registerExecution([
    'model_id' => $modelId,
    'triggered_by' => (int) $_SESSION['user_id'],
    'academic_term' => $academicTerm,
    'input_snapshot' => [
        'reach_weight' => $reachWeight,
        'speed_weight' => $speedWeight,
        'justification' => $justification,
    ],
    'status' => 'Running',
]);

$stmt = $pdo->prepare(
    'INSERT INTO gdss_votes (academic_term, user_id, reach_weight, speed_weight, justification) 
     VALUES (:term, :user, :reach, :speed, :justification) 
     ON DUPLICATE KEY UPDATE reach_weight = :reach, speed_weight = :speed, justification = :justification'
);
$stmt->execute([
    ':term' => $academicTerm,
    ':user' => $_SESSION['user_id'],
    ':reach' => $reachWeight,
    ':speed' => $speedWeight,
    ':justification' => $justification !== '' ? $justification : null,
]);

$modelService->completeExecution($executionId, [
    'output_snapshot' => [
        'academic_term' => $academicTerm,
        'reach_weight' => $reachWeight,
        'speed_weight' => $speedWeight,
    ],
    'execution_time_ms' => 0,
    'status' => 'Completed',
]);

require_once __DIR__ . '/../../services/ActivityLogger.php';
ActivityLogger::log(
    $pdo,
    'CREATE',
    'GDSS vote submitted for ' . $academicTerm . ': reach=' . $reachWeight . ', speed=' . $speedWeight . ', ExecutionID=' . $executionId,
    'gdss_vote',
    null,
    null,
    ['academic_term' => $academicTerm, 'reach_weight' => $reachWeight, 'speed_weight' => $speedWeight, 'justification' => $justification, 'execution_id' => $executionId]
);

$_SESSION['flash_message'] = 'GDSS Weight preferences submitted securely.';
header('Location: ../views/constraints.php');
exit;
