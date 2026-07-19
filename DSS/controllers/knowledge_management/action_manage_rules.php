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
        case 'create':
        case 'update':
            $ruleId = $input['rule_id'] ?? null;
            $categoryId = (int) $input['category_id'];
            $ruleName = (string) $input['rule_name'];
            $conditionJson = (string) $input['condition_json'];
            $recommendation = (string) $input['recommendation'];
            $severity = (string) $input['severity'];
            $isActive = (int) ($input['is_active'] ?? 1);

            if ($ruleId !== null) {
                $stmt = $pdo->prepare(
                    'UPDATE expert_rules SET category_id = :category_id, rule_name = :rule_name, condition_json = :condition_json, recommendation = :recommendation, severity = :severity, is_active = :is_active WHERE rule_id = :rule_id'
                );
                $stmt->execute([
                    ':category_id' => $categoryId,
                    ':rule_name' => $ruleName,
                    ':condition_json' => $conditionJson,
                    ':recommendation' => $recommendation,
                    ':severity' => $severity,
                    ':is_active' => $isActive,
                    ':rule_id' => $ruleId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO expert_rules (category_id, rule_name, condition_json, recommendation, severity, is_active, created_at, updated_at)
                     VALUES (:category_id, :rule_name, :condition_json, :recommendation, :severity, :is_active, :created_at, :updated_at)'
                );
                $stmt->execute([
                    ':category_id' => $categoryId,
                    ':rule_name' => $ruleName,
                    ':condition_json' => $conditionJson,
                    ':recommendation' => $recommendation,
                    ':severity' => $severity,
                    ':is_active' => $isActive,
                    ':created_at' => date('Y-m-d H:i:s'),
                    ':updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            echo json_encode(['status' => 'success']);
            break;

        case 'delete':
            $ruleId = (int) $input['rule_id'];
            $stmt = $pdo->prepare('DELETE FROM expert_rules WHERE rule_id = :rule_id');
            $stmt->execute([':rule_id' => $ruleId]);
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
