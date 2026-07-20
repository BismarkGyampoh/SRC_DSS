<?php

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

$expectedKey = $apiKey ?? '';
if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing API key']);
    exit();
}

$action = $_GET['action'] ?? '';

if ($action === 'projects') {
    $stmt = $pdo->query(
        "SELECT project_id, title, academic_term, budget_required, volunteer_hours,
                student_reach, implementation_weeks, calculated_pis, dss_status
         FROM projects
         WHERE dss_status = 'Accepted'
         ORDER BY calculated_pis DESC, project_id ASC"
    );
    $projects = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $projects]);
    exit();
}

if ($action === 'status') {
    $stmt = $pdo->query("SELECT dss_status, COUNT(*) as count FROM projects GROUP BY dss_status");
    $statuses = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'data' => $statuses]);
    exit();
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action. Use action=projects or action=status']);
exit();
