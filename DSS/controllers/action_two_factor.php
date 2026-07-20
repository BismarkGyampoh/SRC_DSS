<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/TwoFactorAuth.php';

requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin', 'Faculty Representative', 'Student Representative']);

$userId = (int) $_SESSION['user_id'];

try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL AFTER two_factor_enabled");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER two_factor_secret");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'light' AFTER email_notifications");
} catch (PDOException $e) { /* columns may already exist */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $action = $_POST['action'] ?? '';
    $code = trim($_POST['code'] ?? '');

    if ($action === 'enable') {
        $secret = $_POST['secret'] ?? '';
        if ($secret === '' || !TwoFactorAuth::verifyCode($secret, $code)) {
            $_SESSION['flash_message'] = 'Invalid verification code.';
            header('Location: /dss/views/two_factor_setup.php');
            exit();
        }
        $stmt = $pdo->prepare('UPDATE src_users SET two_factor_secret = :secret, two_factor_enabled = 1 WHERE user_id = :id');
        $stmt->execute([':secret' => $secret, ':id' => $userId]);
        unset($_SESSION['two_factor_pending_secret']);
        $_SESSION['flash_message'] = 'Two-factor authentication enabled successfully.';
        header('Location: /dss/views/profile.php');
        exit();
    }

    if ($action === 'disable') {
        $code = trim($_POST['code'] ?? '');
        $stmt = $pdo->prepare('SELECT two_factor_secret FROM src_users WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $secret = $stmt->fetchColumn();
        if (!$secret || !TwoFactorAuth::verifyCode($secret, $code)) {
            $_SESSION['flash_message'] = 'Invalid verification code.';
            header('Location: /dss/views/two_factor_setup.php');
            exit();
        }
        $stmt = $pdo->prepare('UPDATE src_users SET two_factor_secret = NULL, two_factor_enabled = 0 WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        unset($_SESSION['two_factor_pending_secret']);
        $_SESSION['flash_message'] = 'Two-factor authentication disabled.';
        header('Location: /dss/views/profile.php');
        exit();
    }

    if ($action === 'verify') {
        $stmt = $pdo->prepare('SELECT two_factor_secret FROM src_users WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $secret = $stmt->fetchColumn();
        if ($secret && TwoFactorAuth::verifyCode($secret, $code)) {
            $_SESSION['two_factor_verified'] = true;
            $_SESSION['two_factor_pending'] = false;
            unset($_SESSION['two_factor_pending_user_id']);
            $_SESSION['flash_message'] = 'Two-factor verification passed.';
            $redirect = $_SESSION['post_2fa_redirect'] ?? '/dss/views/profile.php';
            unset($_SESSION['post_2fa_redirect']);
            header('Location: ' . $redirect);
            exit();
        }
        $_SESSION['flash_message'] = 'Invalid code.';
        header('Location: /dss/views/two_factor_verify.php');
        exit();
    }
}

header('Location: /dss/views/profile.php');
exit();
