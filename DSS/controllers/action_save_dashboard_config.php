<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken();

$userId = (int) $_SESSION['user_id'];
$configJson = file_get_contents('php://input');
if ($configJson === false || $configJson === '') {
    $configJson = json_encode(['widgets' => ['stats', 'projects', 'activity']]);
}

try {
    $decoded = json_decode($configJson, true);
    if (!is_array($decoded)) {
        $decoded = ['widgets' => ['stats', 'projects', 'activity']];
    }
    $configJson = json_encode($decoded);

    $stmt = $pdo->prepare(
        'INSERT INTO user_dashboard_config (user_id, config_json) VALUES (:uid, :cfg)
         ON DUPLICATE KEY UPDATE config_json = :cfg, updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([':uid' => $userId, ':cfg' => $configJson]);

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit();
