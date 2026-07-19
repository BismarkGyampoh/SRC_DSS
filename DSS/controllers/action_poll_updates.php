<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$userId = (int) $_SESSION['user_id'];
$response = [
    'status' => 'success',
    'timestamp' => date('c'),
    'notifications' => [
        'unread_count' => 0,
        'items' => [],
    ],
    'activities' => [],
];

try {
    $nStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $nStmt->execute([':uid' => $userId]);
    $response['notifications']['unread_count'] = (int) ($nStmt->fetchColumn() ?: 0);

    $nListStmt = $pdo->prepare('SELECT notification_id, title, message, type, is_read, created_at FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10');
    $nListStmt->execute([':uid' => $userId]);
    $response['notifications']['items'] = $nListStmt->fetchAll();

    $aStmt = $pdo->prepare(
        'SELECT log_id, user_role, action_type, description, created_at
         FROM activity_logs
         WHERE user_id = :uid OR user_id IS NULL
         ORDER BY created_at DESC
         LIMIT 10'
    );
    $aStmt->execute([':uid' => $userId]);
    $response['activities'] = $aStmt->fetchAll();
} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['message'] = 'Database error';
}

echo json_encode($response);
exit();
