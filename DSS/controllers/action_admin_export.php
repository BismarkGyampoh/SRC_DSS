<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin']);

$type = (string) ($_GET['type'] ?? '');

    if ($type === 'projects') {
        $stmt = $pdo->query("SELECT project_id, title, budget_required, volunteer_hours, student_reach, implementation_weeks, calculated_pis, dss_status FROM projects ORDER BY project_id ASC");
        $data = $stmt->fetchAll();
        $rowCount = count($data);
        
        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'EXPORT',
            'Exported ' . $rowCount . ' projects to CSV',
            'project',
            null,
            null,
            ['export_type' => 'projects', 'row_count' => $rowCount]
        );
    
    $filename = 'src_projects_export_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Project ID', 'Title', 'Budget Required', 'Volunteer Hours', 'Student Reach', 'Implementation Weeks', 'Calculated PIS', 'DSS Status']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            (int) ($row['project_id'] ?? 0),
            (string) ($row['title'] ?? ''),
            round((float) ($row['budget_required'] ?? 0), 2),
            (int) ($row['volunteer_hours'] ?? 0),
            (int) ($row['student_reach'] ?? 0),
            (int) ($row['implementation_weeks'] ?? 0),
            $row['calculated_pis'] !== null ? round((float) $row['calculated_pis'], 4) : '',
            (string) ($row['dss_status'] ?? '')
        ]);
    }
    
    fclose($output);
    exit();
}

    if ($type === 'users') {
        $stmt = $pdo->query("SELECT user_id, username, user_role FROM src_users ORDER BY user_id ASC");
        $data = $stmt->fetchAll();
        $rowCount = count($data);
        
        require_once __DIR__ . '/../services/ActivityLogger.php';
        ActivityLogger::log(
            $pdo,
            'EXPORT',
            'Exported ' . $rowCount . ' users to CSV',
            'user',
            null,
            null,
            ['export_type' => 'users', 'row_count' => $rowCount]
        );
    
    $filename = 'src_users_export_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'Username', 'Role']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            (int) ($row['user_id'] ?? 0),
            (string) ($row['username'] ?? ''),
            (string) ($row['user_role'] ?? '')
        ]);
    }
    
    fclose($output);
    exit();
}

header('Location: ../views/admin_dashboard.php');
exit();
