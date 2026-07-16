<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

requireCsrfToken();

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

require_once __DIR__ . '/../services/ActivityLogger.php';
ActivityLogger::log($pdo, 'LOGIN', 'User logged in successfully', 'user', (int) $user['user_id']);

$role = $user['user_role'];
$redirectMap = [
    'Financial Secretary' => 'views/constraints.php',
    'Executive Board' => 'views/optimization.php',
    'Projects Coordinator' => 'views/proposal.php',
    'Admin' => 'views/admin_dashboard.php',
    'Faculty Representative' => 'views/public_dashboard.php',
    'Student Representative' => 'views/public_dashboard.php'
];

$redirectUrl = $redirectMap[$role] ?? 'login.php';
header('Location: ../' . $redirectUrl);
exit();
