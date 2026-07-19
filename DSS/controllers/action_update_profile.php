<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin', 'Faculty Representative', 'Student Representative']);
requireCsrfToken();

$userId = (int) $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../public/uploads/avatars/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Display name
$displayName = trim($_POST['display_name'] ?? '');
if ($displayName === '') {
    $displayName = null;
}

$email = trim($_POST['email'] ?? '');
if ($email === '') {
    $email = null;
}

$phone = trim($_POST['phone'] ?? '');
if ($phone === '') {
    $phone = null;
}

$bio = trim($_POST['bio'] ?? '');
if ($bio === '') {
    $bio = null;
}

// Profile picture
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowedTypes, true)) {
        $_SESSION['flash_message'] = 'Invalid image type. Use PNG, JPG, or WEBP.';
        header('Location: /dss/views/profile.php');
        exit();
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['flash_message'] = 'Image is too large. Maximum 2MB.';
        header('Location: /dss/views/profile.php');
        exit();
    }

    // Remove old picture
    $oldStmt = $pdo->prepare('SELECT profile_picture FROM src_users WHERE user_id = :id');
    $oldStmt->execute([':id' => $userId]);
    $old = $oldStmt->fetch();
    if (!empty($old['profile_picture']) && file_exists($uploadDir . $old['profile_picture'])) {
        @unlink($uploadDir . $old['profile_picture']);
    }

    $ext = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };
    $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $_SESSION['flash_message'] = 'Failed to upload image. Please try again.';
        header('Location: /dss/views/profile.php');
        exit();
    }

    $stmt = $pdo->prepare('UPDATE src_users SET display_name = :dn, email = :em, phone = :ph, bio = :bio, profile_picture = :pp WHERE user_id = :id');
    $stmt->execute([
        ':dn' => $displayName,
        ':em' => $email,
        ':ph' => $phone,
        ':bio' => $bio,
        ':pp' => $filename,
        ':id' => $userId,
    ]);
} else {
    $stmt = $pdo->prepare('UPDATE src_users SET display_name = :dn, email = :em, phone = :ph, bio = :bio WHERE user_id = :id');
    $stmt->execute([
        ':dn' => $displayName,
        ':em' => $email,
        ':ph' => $phone,
        ':bio' => $bio,
        ':id' => $userId,
    ]);
}

$_SESSION['flash_message'] = 'Profile updated successfully.';
header('Location: /dss/views/profile.php');
exit();
