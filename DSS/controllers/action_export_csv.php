<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin', 'Executive Board']);

$type = (string) ($_GET['type'] ?? '');

$filename = 'src_export_' . $type . '_' . date('Y-m-d') . '.csv';

try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN actual_budget DECIMAL(12,2) NULL DEFAULT NULL AFTER budget_required");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN budget_variance DECIMAL(12,2) NULL DEFAULT NULL AFTER actual_budget");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN actual_volunteer_hours INT NULL DEFAULT NULL AFTER volunteer_hours");
} catch (PDOException $e) { /* column already exists */ }

if ($type === 'projects') {
    $stmt = $pdo->query("SELECT project_id, title, academic_term, budget_required, volunteer_hours, student_reach, implementation_weeks, calculated_pis, dss_status, actual_budget, budget_variance, actual_volunteer_hours FROM projects ORDER BY project_id ASC");
    $data = $stmt->fetchAll();
    $rowCount = count($data);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log($pdo, 'EXPORT', 'Exported ' . $rowCount . ' projects to CSV', 'project', null, null, ['export_type' => 'projects', 'row_count' => $rowCount]);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Project ID', 'Title', 'Academic Term', 'Budget Required', 'Volunteer Hours', 'Student Reach', 'Implementation Weeks', 'Calculated PIS', 'DSS Status', 'Actual Budget', 'Budget Variance', 'Actual Volunteer Hours']);

    foreach ($data as $row) {
        fputcsv($output, [
            (int) ($row['project_id'] ?? 0),
            (string) ($row['title'] ?? ''),
            (string) ($row['academic_term'] ?? ''),
            round((float) ($row['budget_required'] ?? 0), 2),
            (int) ($row['volunteer_hours'] ?? 0),
            (int) ($row['student_reach'] ?? 0),
            (int) ($row['implementation_weeks'] ?? 0),
            $row['calculated_pis'] !== null ? round((float) $row['calculated_pis'], 4) : '',
            (string) ($row['dss_status'] ?? ''),
            $row['actual_budget'] !== null ? round((float) $row['actual_budget'], 2) : '',
            $row['budget_variance'] !== null ? round((float) $row['budget_variance'], 2) : '',
            $row['actual_volunteer_hours'] !== null ? (int) $row['actual_volunteer_hours'] : '',
        ]);
    }
    fclose($output);
    exit();
}

if ($type === 'overrides') {
    $stmt = $pdo->query("SELECT override_id, project_id, original_status, new_status, override_reason, override_by, created_at FROM project_overrides ORDER BY created_at DESC");
    $data = $stmt->fetchAll();
    $rowCount = count($data);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log($pdo, 'EXPORT', 'Exported ' . $rowCount . ' overrides to CSV', 'project_override', null, null, ['export_type' => 'overrides', 'row_count' => $rowCount]);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Override ID', 'Project ID', 'Original Status', 'New Status', 'Reason', 'Override By', 'Created At']);

    foreach ($data as $row) {
        fputcsv($output, [
            (int) ($row['override_id'] ?? 0),
            (int) ($row['project_id'] ?? 0),
            (string) ($row['original_status'] ?? ''),
            (string) ($row['new_status'] ?? ''),
            (string) ($row['override_reason'] ?? ''),
            (int) ($row['override_by'] ?? 0),
            (string) ($row['created_at'] ?? ''),
        ]);
    }
    fclose($output);
    exit();
}

if ($type === 'activity') {
    $stmt = $pdo->query("SELECT log_id, user_id, user_role, action_type, entity_type, entity_id, description, ip_address, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 10000");
    $data = $stmt->fetchAll();
    $rowCount = count($data);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log($pdo, 'EXPORT', 'Exported ' . $rowCount . ' activity logs to CSV', 'activity_log', null, null, ['export_type' => 'activity', 'row_count' => $rowCount]);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'User ID', 'User Role', 'Action Type', 'Entity Type', 'Entity ID', 'Description', 'IP Address', 'Created At']);

    foreach ($data as $row) {
        fputcsv($output, [
            (int) ($row['log_id'] ?? 0),
            $row['user_id'] !== null ? (int) $row['user_id'] : '',
            (string) ($row['user_role'] ?? ''),
            (string) ($row['action_type'] ?? ''),
            (string) ($row['entity_type'] ?? ''),
            $row['entity_id'] !== null ? (int) $row['entity_id'] : '',
            (string) ($row['description'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
            (string) ($row['created_at'] ?? ''),
        ]);
    }
    fclose($output);
    exit();
}

header('Location: /dss/views/admin_dashboard.php');
exit();
