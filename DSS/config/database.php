<?php

// Loads the local (non-committed) connection + secret config.
// This file MUST NOT contain real credentials. Use config/database.local.php
// (see config/database.local.php.example for the template).

$localConfigPath = __DIR__ . '/database.local.php';

if (!file_exists($localConfigPath)) {
    die('Missing config/database.local.php — copy config/database.local.php.example to config/database.local.php and fill in your credentials.');
}

$dbConfig = require $localConfigPath;

if (
    !is_array($dbConfig)
    || empty($dbConfig['host'])
    || empty($dbConfig['name'])
    || empty($dbConfig['user'])
    || !isset($dbConfig['pass'])
) {
    die('Invalid config/database.local.php — it must return an array with host/name/user/pass keys.');
}

$dbHost = (string) $dbConfig['host'];
$dbName = (string) $dbConfig['name'];
$dbUser = (string) $dbConfig['user'];
$dbPass = (string) $dbConfig['pass'];

// API key for the JSON API (api/index.php). Defaults to a clearly-dev value
// so a missing key never equals a real secret.
$apiKey = (string) ($dbConfig['api_key'] ?? 'change-me-in-database-local-php');

try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database Connection Failed');
}
