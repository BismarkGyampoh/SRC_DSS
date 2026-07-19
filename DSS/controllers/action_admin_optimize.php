<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/admin_config.php');
    exit();
}

requireCsrfToken();

try {
    $tables = ['projects', 'audit_logs', 'src_users', 'semester_constraints', 'gdss_votes'];
    $optimizedCount = 0;
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("OPTIMIZE TABLE {$table}");
        if ($stmt) {
            $optimizedCount++;
        }
    }
    
    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'OPTIMIZE',
        'Database optimization executed on ' . $optimizedCount . ' tables',
        'database',
        null,
        null,
        ['optimized_tables' => $tables, 'optimized_count' => $optimizedCount]
    );
    
    $_SESSION['flash_message'] = "Successfully optimized {$optimizedCount} database tables.";
    header('Location: /dss/views/admin_config.php');
    exit();
} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Database optimization failed: ' . $e->getMessage();
    header('Location: /dss/views/admin_config.php');
    exit();
}
