<?php

require_once '../includes/header.php';

// Idempotent: ensure profile columns exist (safe on repeated loads)
try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN display_name VARCHAR(120) NULL DEFAULT NULL AFTER user_role");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER display_name");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL AFTER profile_picture");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN phone VARCHAR(50) NULL DEFAULT NULL AFTER email");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN bio TEXT NULL DEFAULT NULL AFTER phone");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE src_users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER bio");
} catch (PDOException $e) { /* column already exists */ }

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT user_id, username, user_role, display_name, profile_picture, email, phone, bio, email_notifications FROM src_users WHERE user_id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$profile = $stmt->fetch();

$displayName = !empty($profile['display_name']) ? $profile['display_name'] : $profile['username'];
$avatarUrl = !empty($profile['profile_picture']) ? '/dss/public/uploads/avatars/' . htmlspecialchars($profile['profile_picture'], ENT_QUOTES, 'UTF-8') : '';
?>
<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">account_circle</i> My Profile</div>
    <p class="bento-subtext">Personalize your account with a profile picture, display name, and password.</p>

    <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; margin: 1rem 0 1.5rem;">
        <?php if ($avatarUrl !== ''): ?>
            <img src="<?= $avatarUrl ?>" alt="Profile" style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-gold);">
        <?php else: ?>
            <div style="width: 96px; height: 96px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-brand), var(--primary-light)); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; border: 3px solid var(--accent-gold);">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <div>
            <h4 style="margin: 0; color: var(--text-primary);"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="grey-text" style="margin: 0.2rem 0 0;">@<?= htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($profile['user_role'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>

    <div class="row">
        <div class="col s12 m6">
            <h5 style="color: #025928; margin: 0 0 1rem;">Profile Picture &amp; Display Name</h5>
            <form method="post" action="/dss/controllers/action_update_profile.php" enctype="multipart/form-data">
                <?= $csrfField ?>
                <div class="input-field">
                    <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" maxlength="120">
                    <label for="display_name">Display Name</label>
                </div>
                <div class="input-field">
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                    <label for="email">Email</label>
                </div>
                <div class="input-field">
                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="50">
                    <label for="phone">Phone</label>
                </div>
                <div class="input-field">
                    <textarea id="bio" name="bio" class="materialize-textarea" maxlength="500"><?= htmlspecialchars($profile['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <label for="bio">Bio</label>
                </div>
                <div class="file-field input-field">
                    <div class="btn green">
                        <span>Upload Picture</span>
                        <input type="file" name="profile_picture" accept="image/png,image/jpeg,image/jpg,image/webp">
                    </div>
                    <div class="file-path-wrapper">
                        <input class="file-path validate" type="text" placeholder="PNG or JPG, max 2MB">
                    </div>
                    <span class="helper-text">Optional. Square images look best.</span>
                </div>
                <button type="submit" class="btn green"><i class="material-icons left">save</i>Save Profile</button>
            </form>
        </div>

        <div class="col s12 m6">
            <h5 style="color: #025928; margin: 0 0 1rem;">Two-Factor Authentication</h5>
            <p class="grey-text" style="margin-bottom: 1rem;">Add an extra layer of security to your account using TOTP.</p>
            <a href="two_factor_setup.php" class="btn blue"><i class="material-icons left">security</i>Manage 2FA</a>
        </div>

        <div class="col s12 m6">
            <h5 style="color: #025928; margin: 0 0 1rem;">Change Password</h5>
            <form method="post" action="/dss/controllers/action_change_password.php">
                <?= $csrfField ?>
                <div class="input-field">
                    <input type="password" id="current_password" name="current_password" required>
                    <label for="current_password">Current Password</label>
                </div>
                <div class="input-field">
                    <input type="password" id="new_password" name="new_password" minlength="6" required>
                    <label for="new_password">New Password (min 6 chars)</label>
                </div>
                <div class="input-field">
                    <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                    <label for="confirm_password">Confirm New Password</label>
                </div>
                <button type="submit" class="btn blue"><i class="material-icons left">lock</i>Update Password</button>
            </form>
        </div>

        <div class="col s12 m6">
            <h5 style="color: #025928; margin: 0 0 1rem;">Notifications</h5>
            <form method="post" action="/dss/controllers/action_update_notification_settings.php">
                <?= $csrfField ?>
                <div class="input-field" style="margin-top: 0;">
                    <label>
                        <input type="checkbox" id="email_notifications" name="email_notifications" <?= !empty($profile['email_notifications']) && $profile['email_notifications'] != '0' ? 'checked' : '' ?>>
                        <span>Receive email notifications</span>
                    </label>
                    <p class="grey-text" style="font-size: 0.8rem; margin-top: 0.5rem;">Get emails when project statuses change or important events happen.</p>
                </div>
                <button type="submit" class="btn green" style="margin-top: 1rem;"><i class="material-icons left">save</i>Save Settings</button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
