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

            self::triggerNotification($pdo, $userId, $userRole, $actionType, $description, $entityType, $entityId, $oldValues, $newValues);
        } catch (PDOException $e) {
            // Silently fail to avoid disrupting the main workflow
        }
    }

    private static function triggerNotification(PDO $pdo, ?int $userId, ?string $userRole, string $actionType, string $description, ?string $entityType, ?int $entityId, ?array $oldValues, ?array $newValues): void
    {
        if ($userId === null) {
            return;
        }

        $title = '';
        $message = $description;
        $type = 'info';

        switch ($actionType) {
            case 'CREATE':
                if ($entityType === 'project') {
                    $title = 'Project Submitted';
                    $message = 'Your project proposal has been submitted for review.';
                    $type = 'success';
                } elseif ($entityType === 'project_feedback') {
                    $title = 'Feedback Received';
                    $message = 'New delivery report has been submitted.';
                    $type = 'info';
                }
                break;
            case 'OVERRIDE':
                $title = 'Project Status Updated';
                $message = 'A project status has been changed.';
                $type = 'warning';
                $recipients = self::getBoardMembers($pdo);
                foreach ($recipients as $recipientId) {
                    if ($recipientId !== $userId) {
                        self::notifyUser($pdo, $recipientId, $title, $message, $type);
                    }
                }
                return;
            case 'EXPORT':
                $title = 'Data Exported';
                $message = 'System data was exported.';
                $type = 'info';
                break;
            default:
                $title = ucfirst(strtolower($actionType));
                $message = $description;
                $type = 'info';
        }

        if ($title !== '') {
            self::notifyUser($pdo, $userId, $title, $message, $type);
        }
    }

    private static function getBoardMembers(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT user_id FROM src_users WHERE user_role IN ('Executive Board','Admin','Projects Coordinator','Financial Secretary')");
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            return [];
        }
    }

    private static function notifyUser(PDO $pdo, int $userId, string $title, string $message, string $type): void
    {
        try {
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :message, :type)');
            $stmt->execute([
                ':uid' => $userId,
                ':title' => $title,
                ':message' => $message,
                ':type' => $type,
            ]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }
}
