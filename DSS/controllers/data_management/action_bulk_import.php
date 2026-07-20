<?php

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireRole(['Projects Coordinator', 'Executive Board', 'Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/proposal.php');
    exit();
}

requireCsrfToken();

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_message'] = 'Please select a valid CSV file to upload.';
    header('Location: /dss/views/proposal.php');
    exit();
}

$file = $_FILES['csv_file'];
$allowedTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    $_SESSION['flash_message'] = 'Invalid file type. Only .csv files are allowed.';
    header('Location: /dss/views/proposal.php');
    exit();
}

$handle = fopen($file['tmp_name'], 'r');
if ($handle === false) {
    $_SESSION['flash_message'] = 'Could not read uploaded file.';
    header('Location: /dss/views/proposal.php');
    exit();
}

$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    $_SESSION['flash_message'] = 'CSV file is empty or could not be parsed.';
    header('Location: /dss/views/proposal.php');
    exit();
}

$header = array_map('trim', array_map('strtolower', $header));
$expected = ['title', 'budget', 'volunteer hours', 'reach', 'weeks'];

$missing = array_diff($expected, $header);
if ($missing) {
    fclose($handle);
    $_SESSION['flash_message'] = 'CSV is missing required columns: ' . implode(', ', $missing);
    header('Location: /dss/views/proposal.php');
    exit();
}

$titleIdx = array_search('title', $header);
$budgetIdx = array_search('budget', $header);
$hoursIdx = array_search('volunteer hours', $header);
$reachIdx = array_search('reach', $header);
$weeksIdx = array_search('weeks', $header);

require_once __DIR__ . '/../../services/data_management/DataManagementService.php';
$dataService = new DataManagementService($pdo);

$importId = $dataService->recordImport([
    'source_id' => 2,
    'imported_by' => (int) ($_SESSION['user_id'] ?? 0),
    'academic_term' => getActiveAcademicTerm($pdo),
    'file_path' => $file['name'] ?? null,
    'status' => 'Processing',
]);

$inserted = 0;
$skipped = 0;
$errors = [];

while (($row = fgetcsv($handle)) !== false) {
    $title = trim($row[$titleIdx] ?? '');
    $budget = trim($row[$budgetIdx] ?? '');
    $hours = trim($row[$hoursIdx] ?? '');
    $reach = trim($row[$reachIdx] ?? '');
    $weeks = trim($row[$weeksIdx] ?? '');

    if ($title === '' || $budget === '' || $hours === '' || $reach === '' || $weeks === '') {
        $skipped++;
        $errors[] = "Row skipped: missing required values.";
        $dataService->recordQualityCheck([
            'import_id' => $importId,
            'check_name' => 'Missing required fields',
            'severity' => 'Error',
            'details' => "Row skipped: missing required values for '{$title}'.",
        ]);
        continue;
    }

    $budget = (float) str_replace(',', '', $budget);
    $hours = (int) $hours;
    $reach = (int) $reach;
    $weeks = (int) $weeks;

    if ($budget < 0 || $hours < 1 || $reach < 1 || $weeks < 1) {
        $skipped++;
        $errors[] = "Row skipped for '{$title}': invalid numeric values.";
        $dataService->recordQualityCheck([
            'import_id' => $importId,
            'check_name' => 'Invalid numeric values',
            'severity' => 'Error',
            'details' => "Row skipped for '{$title}': invalid numeric values.",
        ]);
        continue;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO projects (title, academic_term, submitted_by, budget_required, volunteer_hours, student_reach, implementation_weeks, dss_status, created_at)
             VALUES (:title, :term, :user, :budget, :hours, :reach, :weeks, "Pending", NOW())'
        );
        $stmt->execute([
            ':title' => $title,
            ':term' => getActiveAcademicTerm($pdo),
            ':user' => (int) ($_SESSION['user_id'] ?? 0),
            ':budget' => $budget,
            ':hours' => $hours,
            ':reach' => $reach,
            ':weeks' => $weeks,
        ]);
        $inserted++;
    } catch (PDOException $e) {
        $skipped++;
        $errors[] = "Row skipped for '{$title}': database error.";
        $dataService->recordQualityCheck([
            'import_id' => $importId,
            'check_name' => 'Database insert error',
            'severity' => 'Critical',
            'details' => "Row skipped for '{$title}': " . $e->getMessage(),
        ]);
    }
}

fclose($handle);

$dataService->completeImport($importId, [
    'records_imported' => $inserted,
    'records_rejected' => $skipped,
    'status' => $skipped > 0 ? 'Partial' : 'Completed',
    'error_log' => !empty($errors) ? implode("\n", array_slice($errors, 0, 5)) : null,
]);

require_once __DIR__ . '/../../services/ActivityLogger.php';
ActivityLogger::log($pdo, 'BULK_IMPORT', "Imported {$inserted} projects from CSV (Import ID: {$importId})", 'project', 0);

require_once __DIR__ . '/../../services/model_management/AutomationService.php';
$automation = new AutomationService($pdo);
$automation->onImportCompleted($importId, [
    'academic_term' => getActiveAcademicTerm($pdo),
    'records_imported' => $inserted,
    'records_rejected' => $skipped,
]);

if (!empty($errors)) {
    $_SESSION['flash_message'] = "Import complete. Inserted: {$inserted}, Skipped: {$skipped}. Errors: " . implode('; ', array_slice($errors, 0, 5));
} else {
    $_SESSION['flash_message'] = "Successfully imported {$inserted} project(s) from CSV.";
}

header('Location: /dss/views/proposal.php');
exit();
