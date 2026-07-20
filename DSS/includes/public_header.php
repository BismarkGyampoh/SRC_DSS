<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lang/index.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';

$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$currentPage = strtolower(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$themePreference = $_SESSION['theme_preference'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('site_title'), ENT_QUOTES, 'UTF-8') ?> — Public Portal</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="/dss/public/css/app.css?v=18">
</head>
<body>
    <header class="app-header">
        <div class="app-header-inner">
            <a href="#" class="app-logo">
                <img src="/dss/public/images/logo.png" alt="UMaT SRC" class="app-logo-img">
            </a>

            <nav class="app-nav" id="appNav">
                <a class="app-nav-link <?= $currentPage === '/dss/views/public_dashboard.php' ? 'active' : '' ?>" href="/dss/views/public_dashboard.php">
                    <i class="material-icons">public</i>
                    <span><?= __('public_dashboard') ?></span>
                </a>
                <a class="app-nav-link <?= $currentPage === '/dss/views/public_dashboard.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '#feedback') !== false ? 'active' : '' ?>" href="/dss/views/public_dashboard.php#feedback">
                    <i class="material-icons">feedback</i>
                    <span><?= __('feedback') ?></span>
                </a>
            </nav>

            <div class="app-header-actions">
                <form method="post" action="/dss/controllers/action_change_language.php" class="app-lang-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="language" id="languageInput" value="<?= htmlspecialchars($_SESSION['language'] ?? 'en', ENT_QUOTES, 'UTF-8') ?>">
                    <select id="languageSwitcher" class="app-lang-select" onchange="document.getElementById('languageInput').value=this.value;this.form.submit();">
                        <option value="en" <?= ($_SESSION['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>EN</option>
                        <option value="gh" <?= ($_SESSION['language'] ?? 'en') === 'gh' ? 'selected' : '' ?>>GH</option>
                    </select>
                </form>

                <?php if (isset($_SESSION['user_id'], $_SESSION['user_role'])): ?>
                    <a href="/dss/logout.php" class="app-icon-btn" title="<?= __('logout') ?>" aria-label="<?= __('logout') ?>">
                        <i class="material-icons">exit_to_app</i>
                    </a>
                <?php else: ?>
                    <a href="/dss/login.php" class="app-icon-btn" title="<?= __('login') ?>" aria-label="<?= __('login') ?>">
                        <i class="material-icons">lock</i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="app-main-inner">
            <?php if ($flashMessage !== null): ?>
                <div class="app-alert" role="status">
                    <i class="material-icons app-alert-icon">check_circle</i>
                    <span class="app-alert-msg"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    <button type="button" class="app-alert-close" aria-label="Dismiss" onclick="this.parentElement.remove()"><i class="material-icons">close</i></button>
                </div>
            <?php endif; ?>
