<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Projects Coordinator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/drafts.php');
    exit();
}

requireCsrfToken();

$projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if ($projectId === false || $projectId === null) {
    $_SESSION['flash_message'] = 'Invalid project ID.';
    header('Location: /dss/views/drafts.php');
    exit();
}

// Verify the project exists and is a draft
$stmt = $pdo->prepare(
    'SELECT project_id, dss_status FROM projects WHERE project_id = :project_id'
);
$stmt->execute([':project_id' => $projectId]);
$project = $stmt->fetch();

if ($project === false) {
    $_SESSION['flash_message'] = 'Project not found.';
    header('Location: /dss/views/drafts.php');
    exit();
}

if ($project['dss_status'] !== 'Draft') {
    $_SESSION['flash_message'] = 'Only draft projects can be submitted to the queue.';
    header('Location: /dss/views/drafts.php');
    exit();
}

$submitterId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT submitted_by FROM projects WHERE project_id = :project_id');
$stmt->execute([':project_id' => $projectId]);
$dbSubmitterId = (int) ($stmt->fetchColumn() ?: 0);
if ($dbSubmitterId !== $submitterId && $_SESSION['user_role'] !== 'Admin' && $_SESSION['user_role'] !== 'Executive Board') {
    $_SESSION['flash_message'] = 'You can only submit your own drafts.';
    header('Location: /dss/views/drafts.php');
    exit();
}

// Update project status from Draft to Pending
$updateStmt = $pdo->prepare(
    'UPDATE projects SET dss_status = :dss_status WHERE project_id = :project_id'
);
$updateStmt->execute([
    ':dss_status' => 'Pending',
    ':project_id' => $projectId
]);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'UPDATE',
        'Project #' . $projectId . ' status changed from Draft to Pending',
        'project',
        $projectId,
        ['dss_status' => 'Draft'],
        ['dss_status' => 'Pending']
    );

    require_once __DIR__ . '/../services/NotificationService.php';
    $notifService = new NotificationService($pdo);
    $notifService->createForRole('Executive Board', 'New Project Submitted', 'A new project draft has been submitted for review. Project #' . $projectId, 'info');
    $notifService->createForRole('Projects Coordinator', 'Draft Submitted', 'Your draft project #' . $projectId . ' has been submitted to the queue.', 'success');

    $_SESSION['flash_message'] = 'Project submitted to the optimization queue successfully.';

    header('Location: /dss/views/drafts.php');
    exit();
