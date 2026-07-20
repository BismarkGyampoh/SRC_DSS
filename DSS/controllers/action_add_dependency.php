<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/proposal.php');
    exit();
}

requireCsrfToken();

$projectId = $_POST['project_id'] ?? '';
$dependsOn = $_POST['depends_on_project_id'] ?? '';
$dependencyType = $_POST['dependency_type'] ?? 'Prerequisite';

if (!is_numeric($projectId) || !is_numeric($dependsOn) || (int) $projectId === (int) $dependsOn) {
    $_SESSION['flash_message'] = 'Invalid dependency. Select two different projects.';
    header('Location: /dss/views/proposal.php');
    exit();
}

$projectId = (int) $projectId;
$dependsOn = (int) $dependsOn;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO project_dependencies (project_id, depends_on_project_id, dependency_type)
         VALUES (:pid, :dpid, :dtype)
         ON DUPLICATE KEY UPDATE dependency_type = :dtype'
    );
    $stmt->execute([
        ':pid' => $projectId,
        ':dpid' => $dependsOn,
        ':dtype' => $dependencyType,
    ]);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        'Dependency linked: Project #' . $projectId . ' depends on Project #' . $dependsOn,
        'project_dependency',
        $projectId,
        null,
        ['depends_on_project_id' => $dependsOn, 'dependency_type' => $dependencyType]
    );

    $_SESSION['flash_message'] = 'Dependency linked successfully.';
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error linking dependency: ' . $e->getMessage();
}

header('Location: /dss/views/proposal.php');
exit();
