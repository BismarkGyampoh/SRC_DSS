<?php

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

function isMaintenanceMode(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query('SELECT maintenance_mode FROM system_config ORDER BY config_id DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row !== false && (bool) $row['maintenance_mode'];
    } catch (Exception $e) {
        return false;
    }
}

function enforceMaintenanceMode(PDO $pdo): void
{
    if (isMaintenanceMode($pdo)) {
        $allowedDuringMaintenance = ['Admin'];
        if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowedDuringMaintenance, true)) {
            $_SESSION['flash_message'] = 'System is currently in maintenance mode. Please try again later.';
            header('Location: /dss/login.php');
            exit();
        }
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    enforceMaintenanceMode($pdo);
}

function requireRole(array $allowedRoles): void
{
    if (
        !isset($_SESSION['user_id'], $_SESSION['user_role'])
        || !in_array($_SESSION['user_role'], $allowedRoles, true)
    ) {
        header('Location: /dss/login.php');
        exit();
    }
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'];
}

function requireCsrfToken(bool $jsonResponse = false): void
{
    $submitted = $_POST['csrf_token'] ?? '';

    if (
        $submitted === ''
        || !isset($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $submitted)
    ) {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 'error',
                'message' => 'Security validation failed. Please refresh the page and try again.',
            ]);
            exit();
        }

        $_SESSION['flash_message'] = 'Security validation failed. Please try again.';

        if (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], '/views/')) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        } else {
            header('Location: /dss/login.php');
        }
        exit();
    }
}
