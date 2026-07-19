<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken(true);

header('Content-Type: application/json');

$projectId = $_POST['project_id'] ?? '';
$rating = $_POST['rating'] ?? '';

if (!is_numeric($projectId) || !is_numeric($rating) || (int) $rating < 1 || (int) $rating > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid rating.']);
    exit();
}

$projectId = (int) $projectId;
$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? 'rate';

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM project_votes WHERE project_id = :pid AND user_id = :uid');
        $stmt->execute([':pid' => $projectId, ':uid' => $userId]);
        echo json_encode([
            'status' => 'success',
            'average' => 0,
            'count' => 0,
        ]);
        exit();
    }

    $stmt = $pdo->prepare('SELECT vote_id FROM project_votes WHERE project_id = :pid AND user_id = :uid');
    $stmt->execute([':pid' => $projectId, ':uid' => $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE project_votes SET rating = :r WHERE vote_id = :vid');
        $stmt->execute([':r' => (int) $rating, ':vid' => (int) $existing['vote_id']]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO project_votes (project_id, user_id, rating) VALUES (:pid, :uid, :r)');
        $stmt->execute([':pid' => $projectId, ':uid' => $userId, ':r' => (int) $rating]);
    }

    $avgStmt = $pdo->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as vote_count FROM project_votes WHERE project_id = :pid');
    $avgStmt->execute([':pid' => $projectId]);
    $avg = $avgStmt->fetch();

    echo json_encode([
        'status' => 'success',
        'average' => $avg ? round((float) $avg['avg_rating'], 1) : 0,
        'count' => $avg ? (int) $avg['vote_count'] : 0,
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit();
