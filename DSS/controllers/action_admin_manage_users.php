<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/admin_config.php');
    exit();
}

requireCsrfToken();

$action = $_POST['action'] ?? '';

if ($action === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userRole = $_POST['user_role'] ?? '';
    
    if ($username === '' || $password === '' || $userRole === '') {
        $_SESSION['flash_message'] = 'All fields are required.';
        header('Location: ../views/admin_config.php');
        exit();
    }
    
    if (!in_array($userRole, ['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin'])) {
        $_SESSION['flash_message'] = 'Invalid role specified.';
        header('Location: ../views/admin_config.php');
        exit();
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare(
        'INSERT INTO src_users (username, password_hash, user_role) VALUES (:username, :password_hash, :user_role)'
    );
    
    try {
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $hashedPassword,
            ':user_role' => $userRole,
        ]);
        
        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'CREATE',
            'Created user: ' . $username . ' with role ' . $userRole,
            'user',
            (int) $pdo->lastInsertId(),
            null,
            ['username' => $username, 'user_role' => $userRole]
        );
        
        $_SESSION['flash_message'] = 'User created successfully.';
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Username already exists or database error occurred.';
    }
    
    header('Location: ../views/admin_config.php');
    exit();
}

if ($action === 'delete_user') {
    $userId = $_POST['user_id'] ?? '';
    
    if (!is_numeric($userId) || (int) $userId === (int) $_SESSION['user_id']) {
        $_SESSION['flash_message'] = 'Cannot delete user or invalid user ID.';
        header('Location: ../views/admin_config.php');
        exit();
    }
    
    $stmt = $pdo->prepare('DELETE FROM src_users WHERE user_id = :user_id');
    $stmt->execute([':user_id' => (int) $userId]);
    
    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'DELETE',
        'Deleted user ID: ' . (int) $userId,
        'user',
        (int) $userId,
        ['user_id' => (int) $userId],
        null
    );
    
    $_SESSION['flash_message'] = 'User deleted successfully.';
    header('Location: ../views/admin_config.php');
    exit();
}

if ($action === 'update_role') {
    $userId = $_POST['user_id'] ?? '';
    $newRole = $_POST['user_role'] ?? '';
    
    if (!is_numeric($userId) || (int) $userId === (int) $_SESSION['user_id']) {
        $_SESSION['flash_message'] = 'Cannot modify your own role or invalid user ID.';
        header('Location: ../views/admin_config.php');
        exit();
    }
    
    if (!in_array($newRole, ['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin'])) {
        $_SESSION['flash_message'] = 'Invalid role specified.';
        header('Location: ../views/admin_config.php');
        exit();
    }
    
    $stmt = $pdo->prepare('UPDATE src_users SET user_role = :user_role WHERE user_id = :user_id');
    $stmt->execute([
        ':user_id' => (int) $userId,
        ':user_role' => $newRole,
    ]);
    
    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'UPDATE',
        'Updated role for user ID ' . (int) $userId . ' to ' . $newRole,
        'user',
        (int) $userId,
        ['user_role' => null],
        ['user_role' => $newRole]
    );
    
    $_SESSION['flash_message'] = 'User role updated successfully.';
    header('Location: ../views/admin_config.php');
    exit();
}

$_SESSION['flash_message'] = 'Invalid action.';
header('Location: ../views/admin_config.php');
exit();
