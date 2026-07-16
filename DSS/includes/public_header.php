<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';

$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRC DSS — Public Portal</title>
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
                <ul class="right-nav">
                    <li><a href="public_dashboard.php">Dashboard</a></li>
                    <?php if (isset($_SESSION['user_id'], $_SESSION['user_role'])): ?>
                        <li class="user-dropdown">
                            <div class="user-chip">
                                <span class="user-role"><?= htmlspecialchars($_SESSION['user_role'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </li>
                        <li><a href="/dss/logout.php"><i class="material-icons left">exit_to_app</i>Logout</a></li>
                    <?php else: ?>
                        <li><a href="/dss/login.php"><i class="material-icons left">lock</i>Executive Login</a></li>
                    <?php endif; ?>
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
