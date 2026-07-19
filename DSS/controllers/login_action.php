<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

requireCsrfToken();

try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL AFTER two_factor_enabled");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER two_factor_secret");
    $pdo->exec("ALTER TABLE src_users ADD COLUMN theme_preference VARCHAR(20) NOT NULL DEFAULT 'light' AFTER email_notifications");
} catch (PDOException $e) { /* columns may already exist */ }

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_regenerate_id(true);
    session_destroy();
    header('Location: ../login.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Invalid credentials';
    header('Location: ../login.php');
    exit();
}

$stmt = $pdo->prepare(
    'SELECT user_id, username, password_hash, user_role
     FROM src_users
     WHERE username = :username
     LIMIT 1'
);
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if ($user === false || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Invalid credentials';
    header('Location: ../login.php');
    exit();
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['user_role'] = $user['user_role'];
$_SESSION['username'] = $user['username'];

$role = $user['user_role'];
$redirectMap = [
    'Financial Secretary' => 'views/constraints.php',
    'Executive Board' => 'views/optimization.php',
    'Projects Coordinator' => 'views/proposal.php',
    'Admin' => 'views/admin_dashboard.php',
    'Faculty Representative' => 'views/public_dashboard.php',
    'Student Representative' => 'views/public_dashboard.php'
];

require_once __DIR__ . '/../services/TwoFactorAuth.php';
$twoFactorStmt = $pdo->prepare('SELECT two_factor_enabled FROM src_users WHERE user_id = :id');
$twoFactorStmt->execute([':id' => (int) $user['user_id']]);
$twoFactorEnabled = (bool) ($twoFactorStmt->fetchColumn() ?: false);

if ($twoFactorEnabled) {
    $_SESSION['two_factor_pending'] = true;
    $_SESSION['post_2fa_redirect'] = '/' . ($redirectMap[$role] ?? 'views/optimization.php');
    header('Location: /dss/views/two_factor_verify.php');
    exit();
}

require_once __DIR__ . '/../services/ActivityLogger.php';
ActivityLogger::log($pdo, 'LOGIN', 'User logged in successfully', 'user', (int) $user['user_id']);

$redirectUrl = $redirectMap[$role] ?? 'login.php';
header('Location: ../' . $redirectUrl);
exit();
