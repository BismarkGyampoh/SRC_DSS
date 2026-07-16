<?php

class DataManagementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function recordImport(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO data_imports (source_id, imported_by, academic_term, file_path, records_imported, records_rejected, status, started_at, completed_at, error_log)
             VALUES (:source_id, :imported_by, :academic_term, :file_path, :records_imported, :records_rejected, :status, :started_at, :completed_at, :error_log)'
        );

        $stmt->execute([
            ':source_id' => (int) ($data['source_id'] ?? 1),
            ':imported_by' => (int) ($data['imported_by'] ?? 0),
            ':academic_term' => (string) ($data['academic_term'] ?? ''),
            ':file_path' => $data['file_path'] ?? null,
            ':records_imported' => (int) ($data['records_imported'] ?? 0),
            ':records_rejected' => (int) ($data['records_rejected'] ?? 0),
            ':status' => (string) ($data['status'] ?? 'Processing'),
            ':started_at' => $data['started_at'] ?? date('Y-m-d H:i:s'),
            ':completed_at' => $data['completed_at'] ?? null,
            ':error_log' => $data['error_log'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function completeImport(int $importId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE data_imports SET records_imported = :records_imported, records_rejected = :records_rejected, status = :status, completed_at = :completed_at, error_log = :error_log WHERE import_id = :import_id'
        );

        $stmt->execute([
            ':import_id' => $importId,
            ':records_imported' => (int) ($data['records_imported'] ?? 0),
            ':records_rejected' => (int) ($data['records_rejected'] ?? 0),
            ':status' => (string) ($data['status'] ?? 'Completed'),
            ':completed_at' => $data['completed_at'] ?? date('Y-m-d H:i:s'),
            ':error_log' => $data['error_log'] ?? null,
        ]);
    }

    public function recordQualityCheck(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO data_quality_checks (import_id, project_id, check_name, severity, violation_count, details)
             VALUES (:import_id, :project_id, :check_name, :severity, :violation_count, :details)'
        );

        $stmt->execute([
            ':import_id' => isset($data['import_id']) ? (int) $data['import_id'] : null,
            ':project_id' => isset($data['project_id']) ? (int) $data['project_id'] : null,
            ':check_name' => (string) $data['check_name'],
            ':severity' => (string) ($data['severity'] ?? 'Warning'),
            ':violation_count' => (int) ($data['violation_count'] ?? 1),
            ':details' => $data['details'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getImportHistory(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT data_imports.*, data_sources.source_name, src_users.username AS imported_by_username
             FROM data_imports
             INNER JOIN data_sources ON data_sources.source_id = data_imports.source_id
             INNER JOIN src_users ON src_users.user_id = data_imports.imported_by
             ORDER BY data_imports.started_at DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getQualityChecks(int $importId = null, int $projectId = null): array
    {
        $sql = 'SELECT * FROM data_quality_checks WHERE 1=1';
        $params = [];

        if ($importId !== null) {
            $sql .= ' AND import_id = :import_id';
            $params[':import_id'] = $importId;
        }

        if ($projectId !== null) {
            $sql .= ' AND project_id = :project_id';
            $params[':project_id'] = $projectId;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
