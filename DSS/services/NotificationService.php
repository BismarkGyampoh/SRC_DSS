<?php

class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $userId, string $title, string $message, string $type = 'info'): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :message, :type)'
            );
            $stmt->execute([
                ':uid' => $userId,
                ':title' => $title,
                ':message' => $message,
                ':type' => $type,
            ]);
        } catch (PDOException $e) {
            // Silently fail to avoid disrupting main workflow
        }
    }

    public function createForRole(string $userRole, string $title, string $message, string $type = 'info'): void
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM src_users WHERE user_role = :role');
        $stmt->execute([':role' => $userRole]);
        $users = $stmt->fetchAll();
        foreach ($users as $user) {
            $this->create((int) $user['user_id'], $title, $message, $type);
        }
    }

    public function getUnreadCount(int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
            $stmt->execute([':uid' => $userId]);
            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (PDOException $e) {
            return 0;
        }
    }

    public function getRecent(int $userId, int $limit = 20): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT notification_id, title, message, type, is_read, created_at FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT :limit'
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}
