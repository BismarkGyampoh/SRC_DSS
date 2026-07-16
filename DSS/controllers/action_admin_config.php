<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/admin_config.php');
    exit();
}

requireCsrfToken();

$activeAcademicYear = trim($_POST['active_academic_year'] ?? '');
$maintenanceMode = $_POST['maintenance_mode'] ?? '0';

if ($activeAcademicYear === '') {
    $_SESSION['flash_message'] = 'Academic year is required.';
    header('Location: ../views/admin_config.php');
    exit();
}

if (!in_array($maintenanceMode, ['0', '1'])) {
    $_SESSION['flash_message'] = 'Invalid maintenance mode value.';
    header('Location: ../views/admin_config.php');
    exit();
}

    try {
        $stmt = $pdo->prepare('UPDATE system_config SET active_academic_year = :year, maintenance_mode = :mode ORDER BY config_id DESC LIMIT 1');
        $stmt->execute([
            ':year' => $activeAcademicYear,
            ':mode' => (int) $maintenanceMode,
        ]);

        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'UPDATE',
            'System configuration updated: academic_year=' . $activeAcademicYear . ', maintenance_mode=' . (int) $maintenanceMode,
            'system_config',
            1,
            ['active_academic_year' => null, 'maintenance_mode' => null],
            ['active_academic_year' => $activeAcademicYear, 'maintenance_mode' => (int) $maintenanceMode]
        );

    $_SESSION['flash_message'] = 'System configuration updated successfully.';
} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Error updating configuration: ' . $e->getMessage();
}

header('Location: ../views/admin_config.php');
exit();
