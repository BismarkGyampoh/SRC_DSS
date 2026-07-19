<?php

class ModelManagementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function registerExecution(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO model_executions (model_id, triggered_by, academic_term, input_snapshot, status, created_at)
             VALUES (:model_id, :triggered_by, :academic_term, :input_snapshot, :status, :created_at)'
        );

        $stmt->execute([
            ':model_id' => (int) $data['model_id'],
            ':triggered_by' => (int) $data['triggered_by'],
            ':academic_term' => (string) $data['academic_term'],
            ':input_snapshot' => isset($data['input_snapshot']) ? json_encode($data['input_snapshot']) : null,
            ':status' => (string) ($data['status'] ?? 'Running'),
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function completeExecution(int $executionId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE model_executions SET output_snapshot = :output_snapshot, execution_time_ms = :execution_time_ms, status = :status, completed_at = :completed_at, error_message = :error_message WHERE execution_id = :execution_id'
        );

        $stmt->execute([
            ':execution_id' => $executionId,
            ':output_snapshot' => isset($data['output_snapshot']) ? json_encode($data['output_snapshot']) : null,
            ':execution_time_ms' => isset($data['execution_time_ms']) ? (int) $data['execution_time_ms'] : null,
            ':status' => (string) ($data['status'] ?? 'Completed'),
            ':completed_at' => $data['completed_at'] ?? date('Y-m-d H:i:s'),
            ':error_message' => $data['error_message'] ?? null,
        ]);
    }

    public function getModelCatalog(): array
    {
        $stmt = $this->pdo->query(
            'SELECT dss_models.*, COUNT(model_executions.execution_id) AS run_count
             FROM dss_models
             LEFT JOIN model_executions ON model_executions.model_id = dss_models.model_id
             GROUP BY dss_models.model_id
             ORDER BY dss_models.created_at ASC'
        );

        return $stmt->fetchAll();
    }

    public function getModelParameters(int $modelId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM model_parameters WHERE model_id = :model_id ORDER BY param_name ASC'
        );

        $stmt->execute([':model_id' => $modelId]);

        return $stmt->fetchAll();
    }

    public function updateModelParameter(int $paramId, string $paramValue): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE model_parameters SET param_value = :param_value, updated_at = :updated_at WHERE param_id = :param_id'
        );

        $stmt->execute([
            ':param_id' => $paramId,
            ':param_value' => $paramValue,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getExecutionHistory(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT model_executions.*, dss_models.model_name, dss_models.model_type, src_users.username AS triggered_by_username
             FROM model_executions
             INNER JOIN dss_models ON dss_models.model_id = model_executions.model_id
             INNER JOIN src_users ON src_users.user_id = model_executions.triggered_by
             ORDER BY model_executions.created_at DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getModelByName(string $modelName): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM dss_models WHERE model_name = :model_name LIMIT 1'
        );

        $stmt->execute([':model_name' => $modelName]);

        return $stmt->fetch();
    }
}
