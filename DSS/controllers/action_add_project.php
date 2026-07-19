<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Projects Coordinator']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/proposal.php');
    exit();
}

requireCsrfToken();

$title = trim($_POST['title'] ?? '');
$budgetRequired = $_POST['budget_required'] ?? '';
$volunteerHours = $_POST['volunteer_hours'] ?? '';
$studentReach = $_POST['student_reach'] ?? '';
$implementationWeeks = $_POST['implementation_weeks'] ?? '';
$action = $_POST['action'] ?? 'submit';

if (mb_strlen($title) > 255) {
    $_SESSION['flash_message'] = 'Project title is too long (max 255 characters).';
    header('Location: /dss/views/proposal.php');
    exit();
}
if (mb_strlen($title) < 5) {
    $_SESSION['flash_message'] = 'Project title must be at least 5 characters.';
    header('Location: /dss/views/proposal.php');
    exit();
}
if (!is_numeric($budgetRequired) || (float) $budgetRequired < 0) {
    $_SESSION['flash_message'] = 'Budget must be a positive number.';
    header('Location: /dss/views/proposal.php');
    exit();
}
if ((float) $budgetRequired > 1000000) {
    $_SESSION['flash_message'] = 'Budget exceeds maximum allowed (1,000,000 GHS).';
    header('Location: /dss/views/proposal.php');
    exit();
}
if (!is_numeric($volunteerHours) || (int) $volunteerHours < 0) {
    $_SESSION['flash_message'] = 'Volunteer hours must be a non-negative integer.';
    header('Location: /dss/views/proposal.php');
    exit();
}
if ((int) $volunteerHours > 10000) {
    $_SESSION['flash_message'] = 'Volunteer hours exceeds maximum (10,000).';
    header('Location: /dss/views/proposal.php');
    exit();
}
if (!is_numeric($studentReach) || (int) $studentReach < 0) {
    $_SESSION['flash_message'] = 'Student reach must be a non-negative integer.';
    header('Location: /dss/views/proposal.php');
    exit();
}
if ((int) $studentReach > 100000) {
    $_SESSION['flash_message'] = 'Student reach exceeds maximum (100,000).';
    header('Location: /dss/views/proposal.php');
    exit();
}
if (!is_numeric($implementationWeeks) || (int) $implementationWeeks < 0) {
    $_SESSION['flash_message'] = 'Implementation weeks must be a non-negative integer.';
    header('Location: /dss/views/proposal.php');
    exit();
}
if ((int) $implementationWeeks > 520) {
    $_SESSION['flash_message'] = 'Implementation weeks exceeds maximum (520 / 10 years).';
    header('Location: /dss/views/proposal.php');
    exit();
}

$academicAlignment = $_POST['academic_alignment'] ?? null;
$sustainability = $_POST['sustainability'] ?? null;
$healthSafety = $_POST['health_safety'] ?? null;
$digitalInfra = $_POST['digital_infra'] ?? null;
$sportsRecreation = $_POST['sports_recreation'] ?? null;
$hostelWelfare = $_POST['hostel_welfare'] ?? null;
$entrepreneurship = $_POST['entrepreneurship'] ?? null;
$costEfficiency = $_POST['cost_efficiency'] ?? null;

$criteriaScores = [];
if ($academicAlignment !== null && is_numeric($academicAlignment)) {
    $criteriaScores['academic_alignment'] = max(0, min(100, (int) $academicAlignment));
}
if ($sustainability !== null && is_numeric($sustainability)) {
    $criteriaScores['sustainability'] = max(0, min(100, (int) $sustainability));
}
if ($healthSafety !== null && is_numeric($healthSafety)) {
    $criteriaScores['health_safety'] = max(0, min(100, (int) $healthSafety));
}
if ($digitalInfra !== null && is_numeric($digitalInfra)) {
    $criteriaScores['digital_infra'] = max(0, min(100, (int) $digitalInfra));
}
if ($sportsRecreation !== null && is_numeric($sportsRecreation)) {
    $criteriaScores['sports_recreation'] = max(0, min(100, (int) $sportsRecreation));
}
if ($hostelWelfare !== null && is_numeric($hostelWelfare)) {
    $criteriaScores['hostel_welfare'] = max(0, min(100, (int) $hostelWelfare));
}
if ($entrepreneurship !== null && is_numeric($entrepreneurship)) {
    $criteriaScores['entrepreneurship'] = max(0, min(100, (int) $entrepreneurship));
}
if ($costEfficiency !== null && is_numeric($costEfficiency)) {
    $criteriaScores['cost_efficiency'] = max(0, min(100, (int) $costEfficiency));
}

$dssStatus = ($action === 'draft') ? 'Draft' : 'Pending';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO projects (
            title,
            academic_term,
            submitted_by,
            budget_required,
            volunteer_hours,
            student_reach,
            implementation_weeks,
            calculated_pis,
            dss_status,
            academic_alignment,
            sustainability,
            health_safety,
            digital_infra,
            sports_recreation,
            hostel_welfare,
            entrepreneurship,
            cost_efficiency
        ) VALUES (
            :title,
            :academic_term,
            :submitted_by,
            :budget_required,
            :volunteer_hours,
            :student_reach,
            :implementation_weeks,
            NULL,
            :dss_status,
            :academic_alignment,
            :sustainability,
            :health_safety,
            :digital_infra,
            :sports_recreation,
            :hostel_welfare,
            :entrepreneurship,
            :cost_efficiency
        )'
    );

    $stmt->execute([
        ':title'                => $title,
        ':academic_term'        => '2025/2026 Semester 1',
        ':submitted_by'         => (int) $_SESSION['user_id'],
        ':budget_required'      => round((float) $budgetRequired, 2),
        ':volunteer_hours'      => (int) $volunteerHours,
        ':student_reach'        => (int) $studentReach,
        ':implementation_weeks' => (int) $implementationWeeks,
        ':dss_status'           => $dssStatus,
        ':academic_alignment'   => $criteriaScores['academic_alignment'] ?? null,
        ':sustainability'       => $criteriaScores['sustainability'] ?? null,
        ':health_safety'        => $criteriaScores['health_safety'] ?? null,
        ':digital_infra'        => $criteriaScores['digital_infra'] ?? null,
        ':sports_recreation'    => $criteriaScores['sports_recreation'] ?? null,
        ':hostel_welfare'       => $criteriaScores['hostel_welfare'] ?? null,
        ':entrepreneurship'     => $criteriaScores['entrepreneurship'] ?? null,
        ':cost_efficiency'      => $criteriaScores['cost_efficiency'] ?? null,
    ]);

    $projectId = (int) $pdo->lastInsertId();

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        ($action === 'draft' ? 'Saved draft' : 'Submitted proposal') . ' for project: ' . $title,
        'project',
        $projectId,
        null,
        [
            'title' => $title,
            'budget_required' => round((float) $budgetRequired, 2),
            'volunteer_hours' => (int) $volunteerHours,
            'student_reach' => (int) $studentReach,
            'implementation_weeks' => (int) $implementationWeeks,
            'dss_status' => $dssStatus,
        ]
    );

    if ((float) $budgetRequired > 50000) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO data_quality_checks (project_id, check_name, severity, violation_count, details)
                 VALUES (:pid, :name, :sev, 1, :det)'
            );
            $stmt->execute([
                ':pid' => $projectId,
                ':name' => 'High Budget Alert',
                ':sev' => 'Warning',
                ':det' => 'Budget exceeds 50,000 GHS threshold',
            ]);
        } catch (PDOException $e) { /* table may not exist */ }
    }

    if ((int) $volunteerHours > 200) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO data_quality_checks (project_id, check_name, severity, violation_count, details)
                 VALUES (:pid, :name, :sev, 1, :det)'
            );
            $stmt->execute([
                ':pid' => $projectId,
                ':name' => 'High Volunteer Hours Alert',
                ':sev' => 'Warning',
                ':det' => 'Volunteer hours exceed 200 threshold',
            ]);
        } catch (PDOException $e) { /* table may not exist */ }
    }

    if (!empty($criteriaScores)) {
        $scoreStmt = $pdo->prepare(
            'INSERT INTO project_criteria_scores (project_id, criteria, score)
             VALUES (:project_id, :criteria, :score)'
        );

        foreach ($criteriaScores as $criteria => $score) {
            $scoreStmt->execute([
                ':project_id' => $projectId,
                ':criteria'   => $criteria,
                ':score'      => $score,
            ]);
        }
    }

    $pdo->commit();

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        ($action === 'draft' ? 'Saved draft' : 'Submitted proposal') . ' for project: ' . $title,
        'project',
        $projectId,
        null,
        [
            'title' => $title,
            'budget_required' => round((float) $budgetRequired, 2),
            'volunteer_hours' => (int) $volunteerHours,
            'student_reach' => (int) $studentReach,
            'implementation_weeks' => (int) $implementationWeeks,
            'dss_status' => $dssStatus,
        ]
    );

    require_once __DIR__ . '/../services/model_management/AutomationService.php';
    $automation = new AutomationService($pdo);
    $automation->onProjectCreated($projectId, [
        'budget_required' => round((float) $budgetRequired, 2),
        'volunteer_hours' => (int) $volunteerHours,
    ]);

    $message = ($action === 'draft') ? 'Project draft saved successfully.' : 'Project proposal submitted successfully.';
    $_SESSION['flash_message'] = $message;
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = 'Error saving project: ' . $e->getMessage();
}

header('Location: /dss/views/proposal.php');
exit();
