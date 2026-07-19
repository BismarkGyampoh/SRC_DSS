<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin', 'Faculty Representative', 'Student Representative']);
requireCsrfToken();

$userId = (int) $_SESSION['user_id'];

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    $_SESSION['flash_message'] = 'All password fields are required.';
    header('Location: /dss/views/profile.php');
    exit();
}

if (strlen($new) < 6) {
    $_SESSION['flash_message'] = 'New password must be at least 6 characters.';
    header('Location: /dss/views/profile.php');
    exit();
}

if ($new !== $confirm) {
    $_SESSION['flash_message'] = 'New password and confirmation do not match.';
    header('Location: /dss/views/profile.php');
    exit();
}

$stmt = $pdo->prepare('SELECT password_hash FROM src_users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if ($user === false || !password_verify($current, $user['password_hash'])) {
    $_SESSION['flash_message'] = 'Current password is incorrect.';
    header('Location: /dss/views/profile.php');
    exit();
}

$newHash = password_hash($new, PASSWORD_DEFAULT);
$update = $pdo->prepare('UPDATE src_users SET password_hash = :hash WHERE user_id = :id');
$update->execute([':hash' => $newHash, ':id' => $userId]);

require_once __DIR__ . '/../services/ActivityLogger.php';
ActivityLogger::log($pdo, 'UPDATE', 'User changed their password', 'user', $userId);

$_SESSION['flash_message'] = 'Password changed successfully.';
header('Location: /dss/views/profile.php');
exit();
