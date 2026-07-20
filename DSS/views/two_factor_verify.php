<?php

require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION['two_factor_pending'])) {
    header('Location: /dss/login.php');
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('site_title'), ENT_QUOTES, 'UTF-8') ?> — Two-Factor Verification</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="/dss/public/css/app.css?v=18">
</head>
<body class="login-page">
    <div class="login-panel" style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
        <div class="login-card" style="max-width: 420px; width: 100%;">
            <div class="login-header" style="text-align: center; padding: 2rem 1.5rem 1.5rem;">
                <img src="/dss/public/images/logo.png" alt="UMaT SRC" style="max-height: 50px; margin-bottom: 1rem; display: block; margin-left: auto; margin-right: auto;">
                <h1 style="margin-bottom: 0.25rem;">Security Check</h1>
                <p style="margin: 0;">Enter the 6-digit code from your authenticator app</p>
            </div>
            <div class="login-body" style="padding: 1.5rem;">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert" style="margin-bottom: 1.25rem;">
                        <?= htmlspecialchars($_SESSION['flash_message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                <form method="post" action="/dss/controllers/action_two_factor.php">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field" style="margin-bottom: 1.25rem;">
                        <label for="code" style="display: block; font-size: 0.72rem; font-weight: 700; color: rgba(255, 255, 255, 0.85); margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 1.2px; font-family: 'Outfit', sans-serif;">6-Digit Code</label>
                        <input type="text" id="code" name="code" maxlength="6" required autofocus inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" style="width: 100%; padding: 0 1rem; height: 44px; border: 1.5px solid rgba(255, 255, 255, 0.18); border-radius: var(--radius-sm); font-size: 1.1rem; font-family: 'Inter', sans-serif; background: rgba(0, 0, 0, 0.22); color: #ffffff; text-align: center; letter-spacing: 0.5rem; transition: var(--transition-bounce); box-shadow: inset 0 1px 3px rgba(0,0,0,0.15);">
                    </div>
                    <button type="submit" class="btn" style="width: 100%; height: 44px; margin-top: 0.5rem; background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-dark)); color: #1a1205; font-weight: 700; border-radius: 8px; font-family: 'Outfit', sans-serif; font-size: 0.9rem; cursor: pointer; border: none; text-transform: none; letter-spacing: 0.3px; box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4);">Verify</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
