<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/user_interface/DashboardService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
    exit;
}

$dashboardService = new DashboardService($pdo);
$userId = (int) $_SESSION['user_id'];
$dashboardId = isset($_GET['dashboard_id']) ? (int) $_GET['dashboard_id'] : null;

try {
    $dashboard = $dashboardService->loadDashboard($userId, $dashboardId);

    if ($dashboard === false) {
        echo json_encode(['status' => 'error', 'message' => 'No dashboard found.']);
        exit;
    }

    $widgets = $dashboardService->getWidgets((int) $dashboard['dashboard_id']);

    echo json_encode([
        'status' => 'success',
        'dashboard' => $dashboard,
        'widgets' => $widgets,
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load dashboard.']);
    exit;
}
