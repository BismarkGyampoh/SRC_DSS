<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Financial Secretary', 'Executive Board']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/constraints.php');
    exit();
}

requireCsrfToken();

$academicTerm = trim($_POST['academic_term'] ?? '');
$maxBudget = $_POST['max_available_budget'] ?? '';
$maxHours = $_POST['max_volunteer_hours'] ?? '';

if (
    $academicTerm === ''
    || !is_numeric($maxBudget)
    || !is_numeric($maxHours)
    || (float) $maxBudget < 0
    || (int) $maxHours < 1
) {
    $_SESSION['flash_message'] = 'Invalid constraint values. Please check your inputs.';
    header('Location: /dss/views/constraints.php');
    exit();
}

$rawReach = (int) ($_POST['reach_weight'] ?? 0);
$rawSpeed = (int) ($_POST['speed_weight'] ?? 0);
$rawAcademicAlignment = (int) ($_POST['academic_alignment'] ?? 0);
$rawSustainability = (int) ($_POST['sustainability'] ?? 0);
$rawHealthSafety = (int) ($_POST['health_safety'] ?? 0);
$rawDigitalInfra = (int) ($_POST['digital_infra'] ?? 0);
$rawSportsRecreation = (int) ($_POST['sports_recreation'] ?? 0);
$rawHostelWelfare = (int) ($_POST['hostel_welfare'] ?? 0);
$rawEntrepreneurship = (int) ($_POST['entrepreneurship'] ?? 0);
$rawCostEfficiency = (int) ($_POST['cost_efficiency'] ?? 0);

$totalRaw = $rawReach + $rawSpeed + $rawAcademicAlignment + $rawSustainability + $rawHealthSafety
    + $rawDigitalInfra + $rawSportsRecreation + $rawHostelWelfare + $rawEntrepreneurship + $rawCostEfficiency;

if ($totalRaw <= 0) {
    $_SESSION['flash_message'] = 'Please choose an importance level for at least one factor.';
    header('Location: /dss/views/constraints.php');
    exit();
}

// Auto-normalize the raw importance levels (1-4) into weights that sum to 1.0.
$normalize = static function (int $raw) use ($totalRaw): float {
    return round($raw / $totalRaw, 4);
};

$reachWeight = $normalize($rawReach);
$speedWeight = $normalize($rawSpeed);
$academicAlignment = $normalize($rawAcademicAlignment);
$sustainability = $normalize($rawSustainability);
$healthSafety = $normalize($rawHealthSafety);
$digitalInfra = $normalize($rawDigitalInfra);
$sportsRecreation = $normalize($rawSportsRecreation);
$hostelWelfare = $normalize($rawHostelWelfare);
$entrepreneurship = $normalize($rawEntrepreneurship);
$costEfficiency = $normalize($rawCostEfficiency);

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

    require_once __DIR__ . '/../services/NotificationService.php';
    $notifService = new NotificationService($pdo);
    $notifService->createForRole('Executive Board', 'Term Budget Set', 'Semester constraints set for ' . $academicTerm . '. Budget: ' . number_format((float) $maxBudget, 2) . ' GHS.', 'success');
    $notifService->createForRole('Financial Secretary', 'Term Budget Set', 'Semester constraints set for ' . $academicTerm . '. Budget: ' . number_format((float) $maxBudget, 2) . ' GHS.', 'info');

    require_once __DIR__ . '/../services/model_management/AutomationService.php';
    $automation = new AutomationService($pdo);
    $automation->onConstraintUpdated($academicTerm);
    $automation->onCriteriaWeightsUpdated($academicTerm);

    $_SESSION['flash_message'] = 'Semester constraints and UMaT criteria weights saved successfully. Autonomous optimization triggered.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = 'Error saving constraints: ' . $e->getMessage();
}

header('Location: /dss/views/constraints.php');
exit();
