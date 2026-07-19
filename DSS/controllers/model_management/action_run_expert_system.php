<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
requireRole(['Executive Board']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken();

header('Content-Type: application/json');

try {
    $constraintStmt = $pdo->query('SELECT max_available_budget, max_volunteer_hours FROM semester_constraints ORDER BY constraint_id DESC LIMIT 1');
    $constraints = $constraintStmt->fetch();

    if (!$constraints) {
        echo json_encode(['status' => 'error', 'message' => 'No semester constraints found.']);
        exit;
    }

    $projectStmt = $pdo->query("SELECT project_id, title, budget_required, volunteer_hours, student_reach, implementation_weeks FROM projects WHERE dss_status = 'Pending'");
    $projects = $projectStmt->fetchAll();

    require_once __DIR__ . '/../../services/knowledge_management/ProjectExpertSystem.php';
    $expertSystem = new ProjectExpertSystem($pdo);

    $evaluationResults = [];
    foreach ($projects as $project) {
        $advice = $expertSystem->evaluateProjectWithLog($project, $constraints, (int) $project['project_id']);
        $evaluationResults[] = [
            'project_id' => (int) $project['project_id'],
            'title' => $project['title'],
            'budget_required' => (float) $project['budget_required'],
            'volunteer_hours' => (int) $project['volunteer_hours'],
            'student_reach' => (int) $project['student_reach'],
            'implementation_weeks' => (int) $project['implementation_weeks'],
            'advice' => $advice,
        ];
    }

    require_once __DIR__ . '/../../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'AI_ANALYSIS',
        'Expert system analysis executed for ' . count($evaluationResults) . ' pending projects',
        'project',
        null,
        null,
        ['evaluated_count' => count($evaluationResults)]
    );

    echo json_encode(['status' => 'success', 'data' => $evaluationResults]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System error.']);
    exit;
}
