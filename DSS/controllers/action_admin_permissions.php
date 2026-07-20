<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $canView = $_POST['can_view'] ?? [];
    $canEdit = $_POST['can_edit'] ?? [];
    $canDelete = $_POST['can_delete'] ?? [];

    $allPages = ['optimization.php', 'proposal.php', 'feedback.php', 'milestones.php', 'constraints.php', 'budget_analytics.php', 'admin_dashboard.php', 'admin_config.php', 'activity_logs.php', 'templates.php'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO user_permissions (user_id, page, can_view, can_edit, can_delete)
             VALUES (:uid, :page, :cv, :ce, :cd)
             ON DUPLICATE KEY UPDATE can_view = :cv, can_edit = :ce, can_delete = :cd'
        );

        $users = $pdo->query('SELECT user_id FROM src_users')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($users as $userId) {
            foreach ($allPages as $page) {
                $cv = isset($canView[$userId][$page]) ? 1 : 0;
                $ce = isset($canEdit[$userId][$page]) ? 1 : 0;
                $cd = isset($canDelete[$userId][$page]) ? 1 : 0;
                $stmt->execute([
                    ':uid' => (int) $userId,
                    ':page' => $page,
                    ':cv' => $cv,
                    ':ce' => $ce,
                    ':cd' => $cd,
                ]);
            }
        }

        $pdo->commit();
        $_SESSION['flash_message'] = 'Permissions updated successfully.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = 'Error updating permissions: ' . $e->getMessage();
    }
}

header('Location: /dss/views/admin_config.php');
exit();
