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

if ($action === 'purge_old') {
    $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)');
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    
    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'PURGE',
        'Purged ' . $deletedCount . ' audit logs older than 6 months',
        'audit_log',
        null,
        null,
        ['deleted_count' => $deletedCount, 'older_than' => '6 months']
    );
    
    $_SESSION['flash_message'] = "Purged {$deletedCount} audit logs older than 6 months.";
    header('Location: ../views/admin_config.php');
    exit();
}

if ($action === 'purge_all') {
    $stmt = $pdo->query('DELETE FROM audit_logs');
    $deletedCount = $stmt->rowCount();
    
    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'PURGE',
        'Purged all ' . $deletedCount . ' audit logs',
        'audit_log',
        null,
        null,
        ['deleted_count' => $deletedCount]
    );
    
    $_SESSION['flash_message'] = "Purged all {$deletedCount} audit logs from the system.";
    header('Location: ../views/admin_config.php');
    exit();
}

$_SESSION['flash_message'] = 'Invalid action.';
header('Location: ../views/admin_config.php');
exit();
