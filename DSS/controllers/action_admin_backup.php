<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/admin_config.php');
    exit();
}

requireCsrfToken();

try {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
    
    $dbHost = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$dbName) {
        $_SESSION['backup_message'] = 'Could not determine database name.';
        $_SESSION['backup_success'] = false;
        header('Location: ../views/admin_config.php');
        exit();
    }
    
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s',
        'localhost',
        'root',
        '',
        $dbName,
        $backupFile
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($backupFile)) {
        $_SESSION['backup_message'] = 'Database backup created successfully: ' . basename($backupFile);
        $_SESSION['backup_success'] = true;
        
        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'BACKUP',
            'Database backup created: ' . basename($backupFile),
            'backup',
            null,
            null,
            ['backup_file' => basename($backupFile), 'return_code' => $returnCode]
        );
    } else {
        $_SESSION['backup_message'] = 'Failed to create database backup. Please check server configuration.';
        $_SESSION['backup_success'] = false;
    }
} catch (Exception $e) {
    $_SESSION['backup_message'] = 'Backup error: ' . $e->getMessage();
    $_SESSION['backup_success'] = false;
}

header('Location: ../views/admin_config.php');
exit();
