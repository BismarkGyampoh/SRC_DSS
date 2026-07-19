<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken(true);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$notificationId = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
$userId = (int) $_SESSION['user_id'];

try {
    if ($action === 'mark_read' && $notificationId > 0) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->execute([':uid' => $userId]);
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action === 'delete' && $notificationId > 0) {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE notification_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($action === 'delete_read') {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = :uid AND is_read = 1');
        $stmt->execute([':uid' => $userId]);
        echo json_encode(['status' => 'success']);
        exit();
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit();
