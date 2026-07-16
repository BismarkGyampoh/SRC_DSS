<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Financial Secretary', 'Executive Board']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/constraints.php');
    exit();
}

requireCsrfToken();

$academicTerm = trim($_POST['academic_term'] ?? '');
$maxBudget = $_POST['max_available_budget'] ?? '';
$maxHours = $_POST['max_volunteer_hours'] ?? '';
$reachWeight = $_POST['reach_weight'] ?? '';
$speedWeight = $_POST['speed_weight'] ?? '';

if (
    $academicTerm === ''
    || !is_numeric($maxBudget)
    || !is_numeric($maxHours)
    || !is_numeric($reachWeight)
    || !is_numeric($speedWeight)
    || (float) $maxBudget < 0
    || (int) $maxHours < 1
    || (float) $reachWeight < 0
    || (float) $speedWeight < 0
) {
    $_SESSION['flash_message'] = 'Invalid constraint values. Please check your inputs.';
    header('Location: ../views/constraints.php');
    exit();
}

$reachWeight = round((float) $reachWeight, 4);
$speedWeight = round((float) $speedWeight, 4);
$weightTotal = $reachWeight + $speedWeight;

if (abs($weightTotal - 1.0) > 0.0001) {
    $_SESSION['flash_message'] = 'Reach and speed weights must sum to 1.0.';
    header('Location: ../views/constraints.php');
    exit();
}

$academicAlignment = round((float) ($_POST['academic_alignment'] ?? 0.1500), 4);
$sustainability = round((float) ($_POST['sustainability'] ?? 0.1250), 4);
$healthSafety = round((float) ($_POST['health_safety'] ?? 0.1250), 4);
$digitalInfra = round((float) ($_POST['digital_infra'] ?? 0.1250), 4);
$sportsRecreation = round((float) ($_POST['sports_recreation'] ?? 0.1000), 4);
$hostelWelfare = round((float) ($_POST['hostel_welfare'] ?? 0.1250), 4);
$entrepreneurship = round((float) ($_POST['entrepreneurship'] ?? 0.1250), 4);
$costEfficiency = round((float) ($_POST['cost_efficiency'] ?? 0.1250), 4);

$criteriaTotal = $academicAlignment + $sustainability + $healthSafety + $digitalInfra
    + $sportsRecreation + $hostelWelfare + $entrepreneurship + $costEfficiency;

if (abs($criteriaTotal - 1.0) > 0.0001) {
    $_SESSION['flash_message'] = 'UMaT criteria weights must sum to 1.0. Current sum: ' . number_format($criteriaTotal, 4);
    header('Location: ../views/constraints.php');
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO semester_constraints (
            academic_term,
            max_available_budget,
            max_volunteer_hours,
            reach_weight,
            speed_weight,
            set_by_user_id
        ) VALUES (
            :academic_term,
            :max_available_budget,
            :max_volunteer_hours,
            :reach_weight,
            :speed_weight,
            :set_by_user_id
        )'
    );

    $stmt->execute([
        ':academic_term'         => $academicTerm,
        ':max_available_budget'  => round((float) $maxBudget, 2),
        ':max_volunteer_hours'   => (int) $maxHours,
        ':reach_weight'          => $reachWeight,
        ':speed_weight'          => $speedWeight,
        ':set_by_user_id'        => (int) $_SESSION['user_id'],
    ]);

    $criteriaStmt = $pdo->prepare(
        'INSERT INTO criteria_weights (
            academic_term, academic_alignment, sustainability, health_safety, digital_infra,
            sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency, set_by_user_id
        ) VALUES (
            :academic_term, :academic_alignment, :sustainability, :health_safety, :digital_infra,
            :sports_recreation, :hostel_welfare, :entrepreneurship, :cost_efficiency, :set_by_user_id
        ) ON DUPLICATE KEY UPDATE
            academic_alignment = VALUES(academic_alignment),
            sustainability = VALUES(sustainability),
            health_safety = VALUES(health_safety),
            digital_infra = VALUES(digital_infra),
            sports_recreation = VALUES(sports_recreation),
            hostel_welfare = VALUES(hostel_welfare),
            entrepreneurship = VALUES(entrepreneurship),
            cost_efficiency = VALUES(cost_efficiency),
            set_by_user_id = VALUES(set_by_user_id)'
    );

    $criteriaStmt->execute([
        ':academic_term'       => $academicTerm,
        ':academic_alignment'  => $academicAlignment,
        ':sustainability'      => $sustainability,
        ':health_safety'       => $healthSafety,
        ':digital_infra'       => $digitalInfra,
        ':sports_recreation'   => $sportsRecreation,
        ':hostel_welfare'      => $hostelWelfare,
        ':entrepreneurship'    => $entrepreneurship,
        ':cost_efficiency'     => $costEfficiency,
        ':set_by_user_id'      => (int) $_SESSION['user_id'],
    ]);

    $pdo->commit();

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        'Set semester constraints for ' . $academicTerm . ': Budget=' . round((float) $maxBudget, 2) . ' GHS, Hours=' . (int) $maxHours . ', ReachWeight=' . $reachWeight . ', SpeedWeight=' . $speedWeight,
        'semester_constraints',
        (int) $pdo->lastInsertId(),
        null,
        [
            'academic_term' => $academicTerm,
            'max_available_budget' => round((float) $maxBudget, 2),
            'max_volunteer_hours' => (int) $maxHours,
            'reach_weight' => $reachWeight,
            'speed_weight' => $speedWeight,
        ]
    );

    require_once __DIR__ . '/../services/model_management/AutomationService.php';
    $automation = new AutomationService($pdo);
    $automation->onConstraintUpdated($academicTerm);
    $automation->onCriteriaWeightsUpdated($academicTerm);

    $_SESSION['flash_message'] = 'Semester constraints and UMaT criteria weights saved successfully. Autonomous optimization triggered.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = 'Error saving constraints: ' . $e->getMessage();
}

header('Location: ../views/constraints.php');
exit();
