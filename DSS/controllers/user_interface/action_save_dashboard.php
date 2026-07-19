<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/user_interface/DashboardService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

requireCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$dashboardService = new DashboardService($pdo);
$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    $dashboardId = $dashboardService->saveDashboard([
        'user_id' => $userId,
        'dashboard_name' => (string) ($input['dashboard_name'] ?? 'My Dashboard'),
        'layout_config' => $input['layout_config'] ?? [],
        'is_default' => (int) ($input['is_default'] ?? 0),
    ]);

    echo json_encode(['status' => 'success', 'dashboard_id' => $dashboardId]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save dashboard.']);
    exit;
}
