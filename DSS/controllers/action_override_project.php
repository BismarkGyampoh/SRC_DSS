<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/model_management/AuditTrailService.php';

requireRole(['Executive Board']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/optimization.php');
    exit();
}

requireCsrfToken();

$projectId = $_POST['project_id'] ?? '';
$newStatus = $_POST['new_status'] ?? '';
$overrideReason = trim($_POST['override_reason'] ?? '');

if (!is_numeric($projectId) || $newStatus === '' || $overrideReason === '') {
    $_SESSION['flash_message'] = 'Invalid override request. All fields are required.';
    header('Location: ../views/optimization.php');
    exit();
}

$validStatuses = ['Accepted', 'Rejected', 'Deferred'];
if (!in_array($newStatus, $validStatuses, true)) {
    $_SESSION['flash_message'] = 'Invalid status specified.';
    header('Location: ../views/optimization.php');
    exit();
}

$projectId = (int) $projectId;

$stmt = $pdo->prepare('SELECT dss_status, title FROM projects WHERE project_id = :project_id');
$stmt->execute([':project_id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    $_SESSION['flash_message'] = 'Project not found.';
    header('Location: ../views/optimization.php');
    exit();
}

$originalStatus = $project['dss_status'];

if ($originalStatus === $newStatus) {
    $_SESSION['flash_message'] = 'Project is already in the selected status.';
    header('Location: ../views/optimization.php');
    exit();
}

try {
    $updateStmt = $pdo->prepare('UPDATE projects SET dss_status = :new_status WHERE project_id = :project_id');
    $updateStmt->execute([
        ':new_status' => $newStatus,
        ':project_id' => $projectId,
    ]);

    $auditService = new AuditTrailService($pdo);
    $auditService->persistOverrideLog(
        (int) $_SESSION['user_id'],
        $projectId,
        $originalStatus,
        $newStatus,
        $overrideReason
    );

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'OVERRIDE',
        'Project #' . $projectId . ' status overridden from ' . $originalStatus . ' to ' . $newStatus . ' by user',
        'project',
        $projectId,
        ['dss_status' => $originalStatus],
        ['dss_status' => $newStatus, 'override_reason' => $overrideReason]
    );

    $_SESSION['flash_message'] = 'Project "' . $project['title'] . '" overridden from ' . $originalStatus . ' to ' . $newStatus . '.';
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error applying override: ' . $e->getMessage();
}

header('Location: ../views/optimization.php');
exit();
