<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'light' AFTER email_notifications");
} catch (PDOException $e) { /* column may already exist */ }

requireCsrfToken();

$theme = strtolower(trim($_POST['theme'] ?? ''));
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'light';
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('UPDATE src_users SET theme_preference = :theme WHERE user_id = :id');
    $stmt->execute([':theme' => $theme, ':id' => $userId]);
    $_SESSION['theme_preference'] = $theme;
} catch (Exception $e) {
    // Silently fail
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'theme' => $theme]);
exit();
