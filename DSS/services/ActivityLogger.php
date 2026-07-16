<?php

class ActivityLogger
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function log(
        PDO $pdo,
        string $actionType,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $userRole = $_SESSION['user_role'] ?? null;

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($userAgent !== null && mb_strlen($userAgent) > 255) {
            $userAgent = mb_substr($userAgent, 0, 255);
        }

        $oldValuesJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newValuesJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO activity_logs (
                    user_id, user_role, action_type, entity_type, entity_id,
                    description, ip_address, user_agent, old_values, new_values
                ) VALUES (
                    :user_id, :user_role, :action_type, :entity_type, :entity_id,
                    :description, :ip_address, :user_agent, :old_values, :new_values
                )'
            );

            $stmt->execute([
                ':user_id'     => $userId,
                ':user_role'   => $userRole,
                ':action_type' => $actionType,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
                ':description' => $description,
                ':ip_address'  => $ipAddress,
                ':user_agent'  => $userAgent,
                ':old_values'  => $oldValuesJson,
                ':new_values'  => $newValuesJson,
            ]);
        } catch (PDOException $e) {
            // Silently fail to avoid disrupting the main workflow
        }
    }
}
