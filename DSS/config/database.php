<?php

try {
    $pdo = new PDO(
        'mysql:host=sql301.infinityfree.com;dbname=if0_41105880_src_dss_db;charset=utf8mb4',
        'if0_41105880',
        'Fra123ncella.',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database Connection Failed');
}
