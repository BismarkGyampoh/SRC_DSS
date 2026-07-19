<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/EmailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/profile.php');
    exit();
}

try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER two_factor_secret");
} catch (PDOException $e) { /* column may already exist */ }

requireCsrfToken();

$userId = (int) $_SESSION['user_id'];
$emailEnabled = isset($_POST['email_notifications']) ? 1 : 0;

try {
    $stmt = $pdo->prepare('UPDATE src_users SET email_notifications = :en WHERE user_id = :id');
    $stmt->execute([':en' => $emailEnabled, ':id' => $userId]);
    $_SESSION['flash_message'] = $emailEnabled ? 'Email notifications enabled.' : 'Email notifications disabled.';
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error updating notification settings.';
}

header('Location: /dss/views/profile.php');
exit();
