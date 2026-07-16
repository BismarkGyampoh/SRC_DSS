<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

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

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'update_parameter':
            $paramId = (int) $input['param_id'];
            $paramValue = (string) $input['param_value'];

            $stmt = $pdo->prepare(
                'UPDATE model_parameters SET param_value = :param_value, updated_at = :updated_at WHERE param_id = :param_id'
            );
            $stmt->execute([
                ':param_id' => $paramId,
                ':param_value' => $paramValue,
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo json_encode(['status' => 'success']);
            break;

        case 'update_model':
            $modelId = (int) $input['model_id'];
            $version = (string) $input['version'];
            $isActive = (int) ($input['is_active'] ?? 1);

            $stmt = $pdo->prepare(
                'UPDATE dss_models SET version = :version, is_active = :is_active, updated_at = :updated_at WHERE model_id = :model_id'
            );
            $stmt->execute([
                ':model_id' => $modelId,
                ':version' => $version,
                ':is_active' => $isActive,
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);

            echo json_encode(['status' => 'success']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}
