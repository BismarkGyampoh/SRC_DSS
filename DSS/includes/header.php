<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin', 'Faculty Representative', 'Student Representative']);

$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$sessionRole = $_SESSION['user_role'] ?? '';
$userRole = htmlspecialchars($sessionRole, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="csrf_token" value="'
    . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRC DSS — Executive Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="../public/css/app.css">
</head>
<body>
    <div class="navbar-fixed">
        <nav>
            <div class="nav-wrapper container">
                <a href="#" class="brand-logo" style="display: flex; align-items: center; height: 100%; padding-left: 1rem;">
                    <img src="../public/images/logo.png" alt="UMaT SRC" style="max-height: 45px; object-fit: contain;">
                </a>
                <ul class="center-nav">
                    <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board', 'Faculty Representative'])): ?>
                        <li><a class="nav-link <?= $currentPage === 'constraints.php' ? 'active' : '' ?>" href="constraints.php"><i class="material-icons left">settings</i>Term Budget Settings</a></li>
                    <?php endif; ?>
                    <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board'])): ?>
                        <li><a class="nav-link <?= $currentPage === 'budget_analytics.php' ? 'active' : '' ?>" href="budget_analytics.php"><i class="material-icons left">bar_chart</i>Budget Reports</a></li>
                    <?php endif; ?>
                    <?php if ($sessionRole === 'Executive Board'): ?>
                        <li><a class="nav-link <?= $currentPage === 'optimization.php' ? 'active' : '' ?>" href="optimization.php"><i class="material-icons left">check_circle</i>Project Selection Tool</a></li>
                        <li><a class="nav-link <?= $currentPage === 'sandbox.php' ? 'active' : '' ?>" href="sandbox.php"><i class="material-icons left">science</i>Try-Out Tool</a></li>
                        <li><a class="nav-link <?= $currentPage === 'milestones.php' ? 'active' : '' ?>" href="milestones.php"><i class="material-icons left">flag</i>Milestones</a></li>
                    <?php endif; ?>
                    <?php if ($sessionRole === 'Projects Coordinator'): ?>
                        <li><a class="nav-link <?= $currentPage === 'proposal.php' ? 'active' : '' ?>" href="proposal.php"><i class="material-icons left">add_circle</i>Submit Project</a></li>
                        <li><a class="nav-link <?= $currentPage === 'drafts.php' ? 'active' : '' ?>" href="drafts.php"><i class="material-icons left">drafts</i>My Drafts</a></li>
                        <li><a class="nav-link <?= $currentPage === 'rollover.php' ? 'active' : '' ?>" href="rollover.php"><i class="material-icons left">forward</i>Carry-Forward Projects</a></li>
                        <li><a class="nav-link <?= $currentPage === 'milestones.php' ? 'active' : '' ?>" href="milestones.php"><i class="material-icons left">flag</i>Milestones</a></li>
                    <?php endif; ?>
                    <?php if (in_array($sessionRole, ['Faculty Representative', 'Student Representative'])): ?>
                        <li><a class="nav-link <?= $currentPage === 'public_dashboard.php' ? 'active' : '' ?>" href="public_dashboard.php"><i class="material-icons left">public</i>Public Dashboard</a></li>
                    <?php endif; ?>
                    <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board', 'Projects Coordinator', 'Faculty Representative', 'Student Representative'])): ?>
                        <li><a class="nav-link <?= $currentPage === 'feedback.php' ? 'active' : '' ?>" href="feedback.php"><i class="material-icons left">feedback</i>Feedback</a></li>
                    <?php endif; ?>
                    <?php if ($sessionRole === 'Admin'): ?>
                        <li><a class="nav-link <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php"><i class="material-icons left">dashboard</i>System Dashboard</a></li>
                        <li><a class="nav-link <?= $currentPage === 'admin_config.php' ? 'active' : '' ?>" href="admin_config.php"><i class="material-icons left">settings</i>System Settings</a></li>
                        <li><a class="nav-link <?= $currentPage === 'activity_logs.php' ? 'active' : '' ?>" href="activity_logs.php"><i class="material-icons left">history</i>Activity Logs</a></li>
                        <li><a class="nav-link <?= $currentPage === 'data_management/admin_data_management.php' ? 'active' : '' ?>" href="data_management/admin_data_management.php"><i class="material-icons left">storage</i>Data Mgmt</a></li>
                        <li><a class="nav-link <?= $currentPage === 'knowledge_management/admin_knowledge_management.php' ? 'active' : '' ?>" href="knowledge_management/admin_knowledge_management.php"><i class="material-icons left">psychology</i>Knowledge Mgmt</a></li>
                        <li><a class="nav-link <?= $currentPage === 'model_management/admin_model_management.php' ? 'active' : '' ?>" href="model_management/admin_model_management.php"><i class="material-icons left">model_training</i>Model Mgmt</a></li>
                        <li><a class="nav-link <?= $currentPage === 'user_interface/admin_dashboard_manager.php' ? 'active' : '' ?>" href="user_interface/admin_dashboard_manager.php"><i class="material-icons left">dashboard</i>UI Manager</a></li>
                    <?php endif; ?>
                </ul>
                <ul class="right-nav">
                    <li><a href="../logout.php"><i class="material-icons left">exit_to_app</i>Logout</a></li>
                    <li class="user-dropdown">
                        <div class="user-chip">
                            <span class="user-role"><?= $userRole ?></span>
                        </div>
                    </li>
                </ul>
            </div>
        </nav>
    </div>

    <main class="container" style="margin-top: 2rem;">
        <?php if ($flashMessage !== null): ?>
            <div class="card-panel green lighten-4 green-text text-darken-2" role="status">
                <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
