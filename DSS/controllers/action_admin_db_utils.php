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

    if ($action === 'reset_projects') {
        $stmt = $pdo->prepare('UPDATE projects SET dss_status = "Pending"');
        $stmt->execute();
        $updatedCount = $stmt->rowCount();
        
        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'BULK_UPDATE',
            'Reset ' . $updatedCount . ' projects to Pending status',
            'project',
            null,
            null,
            ['updated_count' => $updatedCount, 'new_status' => 'Pending']
        );
    
    $_SESSION['flash_message'] = "Reset {$updatedCount} projects to Pending status.";
    header('Location: ../views/admin_config.php');
    exit();
}

    if ($action === 'truncate_projects') {
        $stmt = $pdo->query('DELETE FROM projects');
        $deletedCount = $stmt->rowCount();
        
        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'BULK_DELETE',
            'Truncated projects table. Deleted ' . $deletedCount . ' projects',
            'project',
            null,
            null,
            ['deleted_count' => $deletedCount]
        );
    
    $_SESSION['flash_message'] = "Truncated projects table. Deleted {$deletedCount} projects.";
    header('Location: ../views/admin_config.php');
    exit();
}

$_SESSION['flash_message'] = 'Invalid action.';
header('Location: ../views/admin_config.php');
exit();
