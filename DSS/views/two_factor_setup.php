<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lang/index.php';
require_once __DIR__ . '/../services/TwoFactorAuth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /dss/login.php');
    exit();
}

try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL AFTER two_factor_enabled");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER two_factor_secret");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'light' AFTER email_notifications");
} catch (PDOException $e) { /* columns may already exist */ }

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT two_factor_secret, two_factor_enabled, email FROM src_users WHERE user_id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    die('User not found.');
}

$enabled = !empty($user['two_factor_enabled']);
$secret = $user['two_factor_secret'] ?? '';

if ($enabled) {
    header('Location: /dss/views/profile.php');
    exit();
}

if ($secret === '' && empty($_SESSION['two_factor_pending_secret'])) {
    try {
        $_SESSION['two_factor_pending_secret'] = TwoFactorAuth::generateSecret();
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Could not generate security key. Please try again.';
        header('Location: /dss/views/profile.php');
        exit();
    }
}

$pendingSecret = $_SESSION['two_factor_pending_secret'] ?? $secret;
$qrUrl = $pendingSecret ? TwoFactorAuth::getQrCodeUrl($pendingSecret, $user['email'] ?? 'user@example.com') : '';

$currentPage = strtolower(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('site_title'), ENT_QUOTES, 'UTF-8') ?> — Two-Factor Setup</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="/dss/public/css/app.css?v=17">
</head>
<body>
    <header class="app-header">
        <div class="app-header-inner">
            <a href="#" class="app-logo">
                <img src="/dss/public/images/logo.png" alt="UMaT SRC" class="app-logo-img">
            </a>

            <nav class="app-nav" id="appNav">
                <a class="app-nav-link <?= $currentPage === '/dss/views/profile.php' ? 'active' : '' ?>" href="/dss/views/profile.php">
                    <i class="material-icons">account_circle</i>
                    <span>Profile</span>
                </a>
            </nav>

            <div class="app-header-actions">
                <a href="/dss/views/profile.php" class="app-icon-btn" title="Back to Profile" aria-label="Back to Profile">
                    <i class="material-icons">arrow_back</i>
                </a>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="app-main-inner">
            <div class="bento-card bento-span-full">
                <div class="bento-card-header"><i class="material-icons">security</i> Two-Factor Authentication Setup</div>
                <p class="bento-subtext">Add an extra layer of security to your account. You will need an authenticator app like Google Authenticator, Authy, or any TOTP app.</p>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="app-alert" role="status">
                    <i class="material-icons app-alert-icon">error</i>
                    <span class="app-alert-msg"><?= htmlspecialchars($_SESSION['flash_message'], ENT_QUOTES, 'UTF-8') ?></span>
                    <button type="button" class="app-alert-close" aria-label="Dismiss" onclick="this.parentElement.remove()"><i class="material-icons">close</i></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <?php if ($secret === ''): ?>
                <div class="bento-card bento-span-full">
                    <div class="bento-card-header" style="color: var(--accent-gold);"><i class="material-icons">qr_code</i> Step 1: Scan QR Code</div>
                    <p class="bento-subtext">Open your authenticator app and scan the QR code below, or enter the secret key manually.</p>

                    <div style="display: flex; flex-wrap: wrap; gap: 2rem; margin-top: 1.5rem; align-items: flex-start;">
                        <div style="flex: 0 0 auto;">
                            <?php if ($qrUrl !== ''): ?>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qrUrl) ?>" alt="2FA QR Code" style="border-radius: 12px; border: 2px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <?php else: ?>
                                <div style="width: 200px; height: 200px; border-radius: 12px; border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; background: var(--bg-surface-hover);">
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">QR unavailable</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1 1 280px; min-width: 280px;">
                            <p style="font-weight: 600; margin-bottom: 0.5rem;">Secret Key</p>
                            <div class="card-panel" style="font-family: monospace; font-size: 1.1rem; word-break: break-all; text-align: center; margin-bottom: 1rem;">
                                <?= htmlspecialchars($pendingSecret, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <p style="font-size: 0.85rem; color: var(--text-secondary);">Enter this key manually if you cannot scan the QR code.</p>
                        </div>
                    </div>
                </div>

                <div class="bento-card bento-span-full">
                    <div class="bento-card-header" style="color: var(--accent-gold);"><i class="material-icons">verified_user</i> Step 2: Verify Code</div>
                    <p class="bento-subtext">Enter the 6-digit code shown in your authenticator app to confirm setup.</p>

                    <form method="post" action="/dss/controllers/action_two_factor.php" style="margin-top: 1.5rem; max-width: 400px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="enable">
                        <input type="hidden" name="secret" value="<?= htmlspecialchars($pendingSecret, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="input-field" style="margin-bottom: 1.5rem;">
                            <input type="text" id="code" name="code" maxlength="6" required autofocus inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                            <label for="code">Enter 6-digit code</label>
                        </div>
                        <button type="submit" class="btn green" style="width: 100%;"><i class="material-icons left">check</i>Enable Two-Factor Authentication</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="bento-card bento-span-full">
                    <div class="bento-card-header"><i class="material-icons">shield</i> Two-Factor Authentication Active</div>
                    <p class="bento-subtext">Your account is protected with two-factor authentication. You will need to enter a code from your authenticator app when you log in.</p>

                    <div style="margin-top: 1.5rem;">
                        <form method="post" action="/dss/controllers/action_two_factor.php" style="max-width: 400px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="disable">
                            <div class="input-field" style="margin-bottom: 1.5rem;">
                                <input type="text" id="code" name="code" maxlength="6" required autofocus inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
                                <label for="code">Enter 6-digit code to disable</label>
                            </div>
                            <button type="submit" class="btn" style="width: 100%; background: linear-gradient(135deg, var(--primary-brand), var(--primary-light));"><i class="material-icons left">lock_open</i>Disable Two-Factor Authentication</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        M.updateTextFields();
    });
    </script>
</body>
</html>
