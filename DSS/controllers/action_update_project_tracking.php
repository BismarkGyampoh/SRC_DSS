<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Projects Coordinator', 'Executive Board', 'Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken();

try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN actual_budget DECIMAL(12,2) NULL DEFAULT NULL AFTER budget_required");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN budget_variance DECIMAL(12,2) NULL DEFAULT NULL AFTER actual_budget");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN actual_volunteer_hours INT NULL DEFAULT NULL AFTER volunteer_hours");
} catch (PDOException $e) { /* column already exists */ }

$projectId = $_POST['project_id'] ?? '';
$actualBudget = $_POST['actual_budget'][$projectId] ?? null;
$budgetVariance = $_POST['budget_variance'][$projectId] ?? null;
$actualVolunteerHours = $_POST['actual_volunteer_hours'][$projectId] ?? null;

if (!is_numeric($projectId)) {
    $_SESSION['flash_message'] = 'Invalid project ID.';
    safeRedirect('/dss/views/optimization.php');
    exit();
}

$projectId = (int) $projectId;

$updateFields = [];
$params = [':project_id' => $projectId];

if ($actualBudget !== null && $actualBudget !== '') {
    $updateFields[] = 'actual_budget = :actual_budget';
    $params[':actual_budget'] = round((float) $actualBudget, 2);
}

if ($budgetVariance !== null && $budgetVariance !== '') {
    $updateFields[] = 'budget_variance = :budget_variance';
    $params[':budget_variance'] = round((float) $budgetVariance, 2);
}

if ($actualVolunteerHours !== null && $actualVolunteerHours !== '') {
    $updateFields[] = 'actual_volunteer_hours = :actual_volunteer_hours';
    $params[':actual_volunteer_hours'] = (int) $actualVolunteerHours;
}

if ($updateFields === []) {
    $_SESSION['flash_message'] = 'No tracking data provided.';
    safeRedirect('/dss/views/optimization.php');
    exit();
}

try {
    $sql = 'UPDATE projects SET ' . implode(', ', $updateFields) . ' WHERE project_id = :project_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'UPDATE',
        'Tracking data updated for project #' . $projectId,
        'project',
        $projectId,
        null,
        ['actual_budget' => $actualBudget, 'budget_variance' => $budgetVariance, 'actual_volunteer_hours' => $actualVolunteerHours]
    );

    $_SESSION['flash_message'] = 'Tracking data updated successfully.';
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error updating tracking data: ' . $e->getMessage();
}

safeRedirect('/dss/views/optimization.php');
