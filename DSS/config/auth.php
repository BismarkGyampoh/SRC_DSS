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

function requirePermission(string $page, string $action = 'view'): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /dss/login.php');
        exit();
    }

    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'Admin') {
        return;
    }

    $allowedActions = ['view', 'edit', 'delete', 'create'];
    if (!in_array($action, $allowedActions, true)) {
        $action = 'view';
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT ' . $action . ' FROM user_permissions WHERE user_id = :uid AND page = :page');
        $stmt->execute([':uid' => (int) $_SESSION['user_id'], ':page' => $page]);
        $allowed = (bool) ($stmt->fetchColumn() ?: false);
        if (!$allowed) {
            header('Location: /dss/views/optimization.php');
            exit();
        }
    } catch (PDOException $e) {
        // If table doesn't exist yet, fall through
    }
}

function safeRedirect(string $default = '/dss/login.php'): void
{
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
        if ($referer !== null && strpos($referer, '/dss/') === 0) {
            header('Location: ' . $referer);
            exit();
        }
    }
    header('Location: ' . $default);
    exit();
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'];
}

/**
 * Returns the active academic term used for storing/filtering projects
 * (e.g. "2025/2026 Semester 1"). The year portion is read from
 * system_config.active_academic_year; the "Semester 1" suffix preserves
 * the existing convention. Falls back to "2025/2026 Semester 1" if the
 * config row is missing so the system keeps working on a fresh install.
 */
function getActiveAcademicTerm(PDO $pdo): string
{
    $year = '2025/2026';
    try {
        $stmt = $pdo->query('SELECT active_academic_year FROM system_config ORDER BY config_id DESC LIMIT 1');
        $row = $stmt->fetch();
        if ($row !== false && !empty($row['active_academic_year'])) {
            $year = (string) $row['active_academic_year'];
        }
    } catch (PDOException $e) {
        // table may not exist yet — use default
    }
    return $year . ' Semester 1';
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
        safeRedirect('/dss/login.php');
    }
}
