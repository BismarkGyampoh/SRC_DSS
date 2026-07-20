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

$projectIds = $_POST['project_ids'] ?? [];
$newStatus = $_POST['new_status'] ?? '';
$overrideReason = trim($_POST['override_reason'] ?? '');

if (!is_array($projectIds) || $projectIds === [] || $newStatus === '' || $overrideReason === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit();
}

$validStatuses = ['Accepted', 'Rejected', 'Deferred'];
if (!in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status specified.']);
    exit();
}

$projectIds = array_map('intval', array_unique($projectIds));
$updated = 0;
$errors = [];

try {
    $pdo->beginTransaction();

    $auditService = new AuditTrailService($pdo);
    $notifService = new NotificationService($pdo);
    $emailService = EmailService::createFromDbConfig($pdo);

    foreach ($projectIds as $projectId) {
        $stmt = $pdo->prepare('SELECT dss_status, title FROM projects WHERE project_id = :project_id');
        $stmt->execute([':project_id' => $projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            $errors[] = 'Project #' . $projectId . ' not found.';
            continue;
        }

        $originalStatus = $project['dss_status'];
        if ($originalStatus === $newStatus) {
            continue;
        }

        $updateStmt = $pdo->prepare('UPDATE projects SET dss_status = :new_status WHERE project_id = :project_id');
        $updateStmt->execute([
            ':new_status' => $newStatus,
            ':project_id' => $projectId,
        ]);

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
            'Bulk override: Project #' . $projectId . ' status changed from ' . $originalStatus . ' to ' . $newStatus,
            'project',
            $projectId,
            ['dss_status' => $originalStatus],
            ['dss_status' => $newStatus]
        );

        $submitterStmt = $pdo->prepare('SELECT email, username FROM src_users WHERE user_id = (SELECT submitted_by FROM projects WHERE project_id = :pid)');
        $submitterStmt->execute([':pid' => $projectId]);
        $submitter = $submitterStmt->fetch();
        if ($submitter && !empty($submitter['email']) && $emailService->isEnabled()) {
            $emailBody = '<p>Dear ' . htmlspecialchars($submitter['username'], ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>The status of your project <strong>' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</strong> has been updated to <strong>' . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                . '<p>Reason: ' . htmlspecialchars($overrideReason, ENT_QUOTES, 'UTF-8') . '</p>';
            $emailService->send($submitter['email'], 'Project Status Update', $emailBody);
        }

        $submitterIdStmt = $pdo->prepare('SELECT submitted_by FROM projects WHERE project_id = :pid');
        $submitterIdStmt->execute([':pid' => $projectId]);
        $submitterId = $submitterIdStmt->fetchColumn();
        if ($submitterId) {
            $notifService->create((int) $submitterId, 'Project Status Updated', 'Your project "' . $project['title'] . '" has been changed to ' . $newStatus . '.', 'warning');
        }

        $updated++;
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'updated' => $updated,
        'errors' => $errors,
        'message' => $updated . ' project(s) updated successfully.' . ($errors !== [] ? ' Errors: ' . implode(', ', $errors) : ''),
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
exit();
