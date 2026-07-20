<?php

require_once __DIR__ . '/../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/optimization.php');
    exit();
}

requireCsrfToken();

$lang = strtolower(trim($_POST['language'] ?? 'en'));
$allowed = ['en', 'gh'];
if (!in_array($lang, $allowed, true)) {
    $lang = 'en';
}

require_once __DIR__ . '/../lang/index.php';
setLanguage($lang);

$_SESSION['flash_message'] = 'Language updated to ' . ($lang === 'gh' ? 'Fante' : 'English') . '.';
safeRedirect('/dss/views/optimization.php');
