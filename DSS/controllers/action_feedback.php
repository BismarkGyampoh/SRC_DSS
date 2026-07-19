<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/public_dashboard.php');
    exit();
}

requireCsrfToken();

$projectId = $_POST['project_id'] ?? '';
$studentName = trim($_POST['student_name'] ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$feedbackText = trim($_POST['feedback_text'] ?? '');
$deliveryStatus = $_POST['delivery_status'] ?? 'Delivered';

if (!is_numeric($projectId) || $feedbackText === '') {
    $_SESSION['flash_message'] = 'Project ID and feedback text are required.';
    header('Location: /dss/views/public_dashboard.php');
    exit();
}

$validStatuses = ['Delivered', 'Partially Delivered', 'Not Delivered'];
if (!in_array($deliveryStatus, $validStatuses, true)) {
    $_SESSION['flash_message'] = 'Invalid delivery status.';
    header('Location: /dss/views/public_dashboard.php');
    exit();
}

$stmt = $pdo->prepare('SELECT project_id FROM projects WHERE project_id = :project_id');
$stmt->execute([':project_id' => (int) $projectId]);
if (!$stmt->fetch()) {
    $_SESSION['flash_message'] = 'Project not found.';
    header('Location: /dss/views/public_dashboard.php');
    exit();
}

$stmt = $pdo->prepare(
    'INSERT INTO project_feedback (project_id, student_name, student_id, feedback_text, delivery_status)
     VALUES (:project_id, :student_name, :student_id, :feedback_text, :delivery_status)'
);

$stmt->execute([
    ':project_id'     => (int) $projectId,
    ':student_name'   => $studentName !== '' ? $studentName : null,
    ':student_id'     => $studentId !== '' ? $studentId : null,
    ':feedback_text'  => $feedbackText,
    ':delivery_status'=> $deliveryStatus,
]);

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        'Feedback submitted for project #' . (int) $projectId . ': ' . $deliveryStatus,
        'project_feedback',
        null,
        null,
        ['project_id' => (int) $projectId, 'delivery_status' => $deliveryStatus, 'student_name' => $studentName]
    );

    require_once __DIR__ . '/../services/NotificationService.php';
    $notifService = new NotificationService($pdo);
    $projectStmt = $pdo->prepare('SELECT title, submitted_by FROM projects WHERE project_id = :pid');
    $projectStmt->execute([':pid' => (int) $projectId]);
    $projectRow = $projectStmt->fetch();
    if ($projectRow) {
        $notifService->create((int) $projectRow['submitted_by'], 'Feedback Received', 'Your project "' . $projectRow['title'] . '" received a delivery report: ' . $deliveryStatus . '.', 'info');
        $notifService->createForRole('Projects Coordinator', 'Feedback Received', 'Delivery report submitted for project "' . $projectRow['title'] . '".', 'info');
        $notifService->createForRole('Executive Board', 'Feedback Received', 'Delivery report submitted for project "' . $projectRow['title'] . '".', 'info');
    }

    require_once __DIR__ . '/../services/EmailService.php';
    $emailService = EmailService::createFromDbConfig($pdo);
    if ($emailService->isEnabled() && $projectRow) {
        $submitterStmt = $pdo->prepare('SELECT email, username FROM src_users WHERE user_id = :uid');
        $submitterStmt->execute([':uid' => (int) $projectRow['submitted_by']]);
        $submitter = $submitterStmt->fetch();
        if ($submitter && !empty($submitter['email'])) {
            $body = '<p>Dear ' . htmlspecialchars($submitter['username'], ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>A delivery report has been submitted for your project <strong>' . htmlspecialchars($projectRow['title'], ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                . '<p>Status: <strong>' . htmlspecialchars($deliveryStatus, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                . '<p>Feedback: ' . htmlspecialchars($feedbackText, ENT_QUOTES, 'UTF-8') . '</p>';
            $emailService->send($submitter['email'], 'Delivery Report - ' . $projectRow['title'], $body);
        }
    }

    $_SESSION['flash_message'] = 'Feedback submitted successfully. Thank you for your input.';
    header('Location: /dss/views/public_dashboard.php');
    exit();
