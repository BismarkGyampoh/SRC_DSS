<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Projects Coordinator', 'Executive Board', 'Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dss/views/proposal.php');
    exit();
}

requireCsrfToken();

$projectId = $_POST['project_id'] ?? '';
$attachmentType = $_POST['attachment_type'] ?? 'project';

if (!is_numeric($projectId)) {
    $_SESSION['flash_message'] = 'Invalid project ID.';
    header('Location: /dss/views/proposal.php');
    exit();
}

$projectId = (int) $projectId;

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_message'] = 'No file uploaded or upload error.';
    header('Location: /dss/views/proposal.php?project_id=' . $projectId);
    exit();
}

$file = $_FILES['attachment'];
$maxSize = 10 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    $_SESSION['flash_message'] = 'File is too large. Maximum 10MB.';
    header('Location: /dss/views/proposal.php?project_id=' . $projectId);
    exit();
}

$allowedTypes = [
    'application/pdf',
    'image/png',
    'image/jpeg',
    'image/jpg',
    'image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain',
    'application/zip',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if (!in_array($mime, $allowedTypes, true)) {
    $_SESSION['flash_message'] = 'Invalid file type. Allowed: PDF, images, DOC, DOCX, TXT, ZIP.';
    header('Location: /dss/views/proposal.php?project_id=' . $projectId);
    exit();
}

$uploadDir = __DIR__ . '/../public/uploads/attachments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'att_' . $projectId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    $_SESSION['flash_message'] = 'Failed to upload file. Please try again.';
    header('Location: /dss/views/proposal.php?project_id=' . $projectId);
    exit();
}

try {
    if ($attachmentType === 'feedback') {
        $feedbackId = $_POST['feedback_id'] ?? 0;
        if (!is_numeric($feedbackId) || (int) $feedbackId <= 0) {
            $_SESSION['flash_message'] = 'Invalid feedback ID.';
            header('Location: /dss/views/feedback.php');
            exit();
        }
        $stmt = $pdo->prepare(
            'INSERT INTO feedback_attachments (feedback_id, uploaded_by, file_name, file_path, file_size, file_type)
             VALUES (:fid, :uid, :fn, :fp, :fs, :ft)'
        );
        $stmt->execute([
            ':fid' => (int) $feedbackId,
            ':uid' => (int) $_SESSION['user_id'],
            ':fn' => $file['name'],
            ':fp' => 'public/uploads/attachments/' . $filename,
            ':fs' => (int) $file['size'],
            ':ft' => $mime,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO project_attachments (project_id, uploaded_by, file_name, file_path, file_size, file_type)
             VALUES (:pid, :uid, :fn, :fp, :fs, :ft)'
        );
        $stmt->execute([
            ':pid' => $projectId,
            ':uid' => (int) $_SESSION['user_id'],
            ':fn' => $file['name'],
            ':fp' => 'public/uploads/attachments/' . $filename,
            ':fs' => (int) $file['size'],
            ':ft' => $mime,
        ]);
    }

    require_once __DIR__ . '/../services/ActivityLogger.php';
    ActivityLogger::log(
        $pdo,
        'CREATE',
        'Attachment uploaded for ' . ($attachmentType === 'feedback' ? 'feedback' : 'project') . ' #' . ($attachmentType === 'feedback' ? $_POST['feedback_id'] : $projectId),
        $attachmentType === 'feedback' ? 'feedback_attachment' : 'project_attachment',
        null,
        null,
        ['file_name' => $file['name'], 'file_type' => $mime]
    );

    $_SESSION['flash_message'] = 'File uploaded successfully.';
} catch (Exception $e) {
    @unlink($uploadDir . $filename);
    $_SESSION['flash_message'] = 'Error uploading file: ' . $e->getMessage();
}

if ($attachmentType === 'feedback') {
    header('Location: /dss/views/feedback.php');
} else {
    header('Location: /dss/views/proposal.php?project_id=' . $projectId);
}
exit();
