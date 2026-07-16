<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
requireRole(['Executive Board']);
requireCsrfToken();

$academicTerm = trim($_POST['academic_term'] ?? '');

$stmt = $pdo->prepare('SELECT AVG(reach_weight) as avg_reach, AVG(speed_weight) as avg_speed FROM gdss_votes WHERE academic_term = :term');
$stmt->execute([':term' => $academicTerm]);
$averages = $stmt->fetch();

if (!$averages['avg_reach']) {
    $_SESSION['flash_message'] = 'No GDSS votes found for this term.';
    header('Location: ../views/constraints.php');
    exit;
}

$updateStmt = $pdo->prepare('UPDATE semester_constraints SET reach_weight = :reach, speed_weight = :speed WHERE academic_term = :term');
$updateStmt->execute([
    ':reach' => round((float)$averages['avg_reach'], 4),
    ':speed' => round((float)$averages['avg_speed'], 4),
    ':term' => $academicTerm
]);

require_once __DIR__ . '/../services/ActivityLogger.php';
ActivityLogger::log(
    $pdo,
    'UPDATE',
    'GDSS consensus finalized for ' . $academicTerm . ': reach=' . round((float)$averages['avg_reach'], 4) . ', speed=' . round((float)$averages['avg_speed'], 4),
    'semester_constraints',
    null,
    ['reach_weight' => null, 'speed_weight' => null],
    ['reach_weight' => round((float)$averages['avg_reach'], 4), 'speed_weight' => round((float)$averages['avg_speed'], 4)]
);

require_once __DIR__ . '/../services/model_management/AutomationService.php';
$automation = new AutomationService($pdo);
$automation->onConstraintUpdated($academicTerm);

$_SESSION['flash_message'] = 'GDSS Consensus calculated. Master weights updated. Autonomous optimization triggered.';
header('Location: ../views/constraints.php');
exit;
