<?php

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    $redirectMap = [
        'Financial Secretary'    => 'views/constraints.php',
        'Executive Board'        => 'views/optimization.php',
        'Projects Coordinator'   => 'views/proposal.php',
        'Admin'                  => 'views/admin_dashboard.php',
        'Faculty Representative' => 'views/public_dashboard.php',
        'Student Representative' => 'views/public_dashboard.php',
    ];
    $alreadyLoggedIn = true;
    $currentRole = $role;
    $currentUsername = $_SESSION['username'] ?? '';
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRC DSS — Executive Login</title>
    <link rel="stylesheet" href="/dss/public/css/app.css?v=17">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-split">
        <div class="login-panel login-panel-image">
            <div class="login-panel-image-inner">
                <img src="/dss/public/images/logo.png" alt="UMaT SRC Logo" style="max-width: 420px; margin-bottom: 1.5rem; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
            </div>
        </div>

        <div class="login-panel login-panel-form">
            <div class="login-card">
        <div class="login-header">
            <h1>SRC Project Selection DSS</h1>
            <p>Students' Representative Council — Executive Portal</p>
        </div>

        <div class="login-body">
            <?php if ($error !== null): ?>
                <div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (isset($alreadyLoggedIn)): ?>
                <div class="alert" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.5); color: #fbbf24; margin-bottom: 1.25rem;">
                    <i class="material-icons" style="font-size: 1.1rem; vertical-align: middle; margin-right: 0.4rem;">info</i>
                    You are already signed in as <strong><?= htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8') ?></strong>
                    (<?= htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8') ?>).
                    <form method="post" action="/dss/controllers/login_action.php" style="display: inline; margin-top: 0.75rem;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" style="background: none; border: none; color: #fbbf24; text-decoration: underline; cursor: pointer; font-size: 0.85rem; padding: 0; font-family: 'Outfit', sans-serif;">Click here to sign out and switch accounts</button>
                    </form>
                </div>
            <?php else: ?>
                <form method="post" action="/dss/controllers/login_action.php" autocomplete="on">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                >
                <div class="field">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit">Sign In</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="login-footer">
            <a href="/dss/views/public_dashboard.php" style="color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.75rem;">
                <i class="material-icons" style="font-size: 0.9rem; vertical-align: middle; margin-right: 0.2rem;">arrow_back</i>Back to Public Dashboard
            </a>
            <p style="margin-top: 0.5rem; font-size: 0.75rem; color: rgba(255,255,255,0.5);">Authorized SRC executives only</p>
        </div>
            </div>
        </div>
    </div>
</body>
</html>