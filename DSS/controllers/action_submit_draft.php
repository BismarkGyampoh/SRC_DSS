<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Projects Coordinator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/drafts.php');
    exit();
}

requireCsrfToken();

$projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if ($projectId === false || $projectId === null) {
    $_SESSION['flash_message'] = 'Invalid project ID.';
    header('Location: ../views/drafts.php');
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
    header('Location: ../views/drafts.php');
    exit();
}

if ($project['dss_status'] !== 'Draft') {
    $_SESSION['flash_message'] = 'Only draft projects can be submitted to the queue.';
    header('Location: ../views/drafts.php');
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

$_SESSION['flash_message'] = 'Project submitted to the optimization queue successfully.';

header('Location: ../views/drafts.php');
exit();
