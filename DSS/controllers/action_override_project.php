<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/model_management/AuditTrailService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/NotificationService.php';

requireRole(['Executive Board']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken(true);

header('Content-Type: application/json');

$projectId = $_POST['project_id'] ?? '';
$newStatus = $_POST['new_status'] ?? '';
$overrideReason = trim($_POST['override_reason'] ?? '');

if (!is_numeric($projectId) || $newStatus === '' || $overrideReason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid override request. All fields are required.']);
    exit();
}

$validStatuses = ['Accepted', 'Rejected', 'Deferred'];
if (!in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status specified.']);
    exit();
}

$projectId = (int) $projectId;

$stmt = $pdo->prepare('SELECT dss_status, title FROM projects WHERE project_id = :project_id');
$stmt->execute([':project_id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    echo json_encode(['status' => 'error', 'message' => 'Project not found.']);
    exit();
}

$originalStatus = $project['dss_status'];

if ($originalStatus === $newStatus) {
    echo json_encode(['status' => 'error', 'message' => 'Project is already in the selected status.']);
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

    $submitterStmt = $pdo->prepare('SELECT email, username FROM src_users WHERE user_id = (SELECT submitted_by FROM projects WHERE project_id = :pid)');
    $submitterStmt->execute([':pid' => $projectId]);
    $submitter = $submitterStmt->fetch();

    if ($submitter && !empty($submitter['email'])) {
        $emailService = EmailService::createFromDbConfig($pdo);
        $emailBody = '<p>Dear ' . htmlspecialchars($submitter['username'], ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>The status of your project <strong>' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</strong> has been updated to <strong>' . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Reason: ' . htmlspecialchars($overrideReason, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Please log in to the SRC DSS for more details.</p>';
        $emailService->send($submitter['email'], 'Project Status Update - ' . $project['title'], $emailBody);
    }

    $notifService = new NotificationService($pdo);
    $submitterIdStmt = $pdo->prepare('SELECT submitted_by FROM projects WHERE project_id = :pid');
    $submitterIdStmt->execute([':pid' => $projectId]);
    $submitterId = $submitterIdStmt->fetchColumn();
    if ($submitterId) {
        $notifService->create((int) $submitterId, 'Project Status Updated', 'Your project "' . $project['title'] . '" has been changed to ' . $newStatus . '.', 'warning');
    }

    echo json_encode(['status' => 'success', 'message' => 'Project "' . $project['title'] . '" overridden from ' . $originalStatus . ' to ' . $newStatus . '.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error applying override: ' . $e->getMessage()]);
}
exit();
