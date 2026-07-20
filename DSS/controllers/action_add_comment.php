<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken();

$projectId = $_POST['project_id'] ?? '';
$commentText = trim($_POST['comment_text'] ?? '');
$parentCommentId = $_POST['parent_comment_id'] ?? null;

if (!is_numeric($projectId) || $commentText === '') {
    $_SESSION['flash_message'] = 'Project ID and comment text are required.';
    safeRedirect('/dss/views/optimization.php');
}

$projectId = (int) $projectId;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO project_comments (project_id, user_id, comment_text, parent_comment_id)
         VALUES (:pid, :uid, :ct, :pcid)'
    );
    $stmt->execute([
        ':pid' => $projectId,
        ':uid' => (int) $_SESSION['user_id'],
        ':ct' => $commentText,
        ':pcid' => $parentCommentId !== null && is_numeric($parentCommentId) ? (int) $parentCommentId : null,
    ]);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        'Comment added on project #' . $projectId,
        'project_comment',
        $projectId,
        null,
        ['comment_text' => mb_substr($commentText, 0, 200)]
    );

    $_SESSION['flash_message'] = 'Comment added successfully.';
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error adding comment: ' . $e->getMessage();
}

safeRedirect('/dss/views/optimization.php');
