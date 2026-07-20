<?php

require_once '../includes/header.php';

$usersStmt = $pdo->query("SELECT user_id, username, user_role, created_at FROM src_users ORDER BY user_id ASC");
$allUsers = $usersStmt->fetchAll();

$auditLogsStmt = $pdo->query(
    "SELECT audit_logs.log_id, audit_logs.created_at, audit_logs.report_html, audit_logs.triggered_by_user_id, src_users.username
     FROM audit_logs
     INNER JOIN src_users ON src_users.user_id = audit_logs.triggered_by_user_id
     ORDER BY audit_logs.created_at DESC
     LIMIT 20"
);
$auditLogs = $auditLogsStmt->fetchAll();

$backupMessage = $_SESSION['backup_message'] ?? null;
$backupSuccess = $_SESSION['backup_success'] ?? false;
unset($_SESSION['backup_message'], $_SESSION['backup_success']);

$systemConfig = false;
try {
    $configStmt = $pdo->query('SELECT * FROM system_config ORDER BY config_id DESC LIMIT 1');
    $systemConfig = $configStmt->fetch();
} catch (PDOException $e) {
    $systemConfig = false;
}
$activeAcademicYear = $systemConfig !== false ? htmlspecialchars($systemConfig['active_academic_year'] ?? '2025/2026', ENT_QUOTES, 'UTF-8') : '2025/2026';
$maintenanceMode = $systemConfig !== false ? (bool) $systemConfig['maintenance_mode'] : false;
?>

<?php if ($sessionRole !== 'Admin'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);"><?= __('access_restricted') ?></div>
        <p class="bento-subtext"><?= __('no_permission_desc') ?></p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">settings</i> <?= __('system_settings') ?></div>
        <p class="bento-subtext"><?= __('manage_users_logs_tools') ?></p>

            <div class="action-button-row" style="margin-top: 1.5rem;">
                <a href="activity_logs.php" class="btn purple"><i class="material-icons left">history</i><?= __('view_activity_logs') ?></a>
                <a href="templates.php" class="btn teal"><i class="material-icons left">layers</i><?= __('manage_templates') ?></a>
            </div>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">people</i> <?= __('user_accounts') ?></div>
        <p class="bento-subtext"><?= __('manage_users_desc') ?></p>

            <form method="post" action="/dss/controllers/action_admin_manage_users.php" style="margin-top: 1.5rem;">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="create_user">
                <div class="row">
                    <div class="input-field col s12 m4">
                        <input type="text" id="new_username" name="username" required>
                        <label for="new_username">Username</label>
                    </div>
                    <div class="input-field col s12 m4">
                        <input type="password" id="new_password" name="password" required>
                        <label for="new_password">Password</label>
                    </div>
                    <div class="input-field col s12 m4">
                        <select id="new_role" name="user_role" required>
                            <option value="" disabled selected>Select Role</option>
                            <option value="Financial Secretary">Financial Secretary</option>
                            <option value="Projects Coordinator">Projects Coordinator</option>
                            <option value="Executive Board">Executive Board</option>
                            <option value="Admin">Admin</option>
                            <option value="Faculty Representative">Faculty Representative</option>
                            <option value="Student Representative">Student Representative</option>
                        </select>
                        <label for="new_role">Role</label>
                    </div>
                </div>
                <button type="submit" class="btn green"><i class="material-icons left">person_add</i>Add User</button>
            </form>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">list</i> Current Users</div>
            <?php if ($allUsers === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">people_outline</i>
                    <h5 class="grey-text">No Users Found</h5>
                    <p class="grey-text">There are currently no users in the system.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="role-badge role-<?= strtolower(str_replace(' ', '-', $user['user_role'])) ?>">
                                        <?= htmlspecialchars($user['user_role'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ((int) $user['user_id'] !== (int) $_SESSION['user_id']): ?>
                                        <form method="post" action="/dss/controllers/action_admin_manage_users.php" style="display: inline;">
                                            <?= $csrfField ?>
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                            <button type="submit" class="btn red" style="padding: 0 1rem; height: 32px; line-height: 32px; font-size: 0.8rem;">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="grey-text" style="font-size: 0.8rem;">(You)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">history</i> Activity Log Management</div>
        <p class="bento-subtext">View or remove old activity logs.</p>

            <div class="action-button-row" style="margin-top: 1.5rem;">
                <form method="post" action="/dss/controllers/action_admin_audit_logs.php">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="purge_old">
                    <button type="submit" class="btn red" onclick="return confirm('Are you sure you want to remove activity logs older than 6 months? This cannot be undone.');"><i class="material-icons left">delete_sweep</i>Remove Old Logs (6+ months)</button>
                </form>
                <form method="post" action="/dss/controllers/action_admin_audit_logs.php">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="purge_all">
                    <button type="submit" class="btn red darken-3" onclick="return confirm('Are you sure you want to remove ALL activity logs? This cannot be undone.');"><i class="material-icons left">delete_forever</i>Remove All Logs</button>
                </form>
            </div>

            <?php if ($auditLogs === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">history</i>
                    <h5 class="grey-text"><?= __('no_activity_logs_found') ?></h5>
                    <p class="grey-text"><?= __('no_activity_logs_to_display') ?></p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Created At</th>
                            <th>Triggered By</th>
                            <th>Report Preview</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= mb_substr(strip_tags($log['report_html'] ?? ''), 0, 100) ?>...</td>
                                <td>
                                    <a href="view_audit.php?log_id=<?= (int) $log['log_id'] ?>" target="_blank" class="btn blue" style="padding: 0 1rem; height: 32px; line-height: 32px; font-size: 0.8rem;">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">settings</i> <?= __('system_settings') ?></div>
        <p class="bento-subtext"><?= __('set_academic_year_status') ?></p>

            <form method="post" action="/dss/controllers/action_admin_config.php" style="margin-top: 1.5rem;">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12 m6">
                        <input type="text" id="active_academic_year" name="active_academic_year" value="<?= $activeAcademicYear ?>" required>
                        <label for="active_academic_year">Current Academic Year</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <select id="maintenance_mode" name="maintenance_mode" required>
                            <option value="0" <?= !$maintenanceMode ? 'selected' : '' ?>>Normal</option>
                            <option value="1" <?= $maintenanceMode ? 'selected' : '' ?>>Maintenance</option>
                        </select>
                        <label for="maintenance_mode">System Status</label>
                    </div>
                </div>
                <button type="submit" class="btn green"><i class="material-icons left">save</i>Save Settings</button>
            </form>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">storage</i> Database Tools</div>
        <p class="bento-subtext">Reset projects or clear the database for a new term.</p>

            <div class="action-button-row" style="margin-top: 1.5rem;">
                <form method="post" action="/dss/controllers/action_admin_db_utils.php">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="reset_projects">
                    <button type="submit" class="btn orange" onclick="return confirm('Are you sure you want to reset all project statuses to Pending? This will clear all Approved/Rejected statuses.');">Reset All Projects to Pending</button>
                </form>
                <form method="post" action="/dss/controllers/action_admin_db_utils.php">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="truncate_projects">
                    <button type="submit" class="btn red darken-3" onclick="return confirm('Are you sure you want to DELETE ALL projects? This cannot be undone.');">Delete All Projects</button>
                </form>
            </div>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">shield</i> User Permissions</div>
        <p class="bento-subtext">Manage page-level permissions for each user.</p>
        <?php
        $pages = ['optimization.php', 'proposal.php', 'feedback.php', 'milestones.php', 'constraints.php', 'budget_analytics.php', 'admin_dashboard.php', 'admin_config.php', 'activity_logs.php', 'templates.php'];
        $permissions = [];
        try {
            $permStmt = $pdo->query('SELECT user_id, page, can_view, can_edit, can_delete FROM user_permissions');
            $permRows = $permStmt->fetchAll();
            foreach ($permRows as $row) {
                $permissions[$row['user_id']][$row['page']] = $row;
            }
        } catch (PDOException $e) {
            $permissions = [];
        }
        ?>
        <form method="post" action="/dss/controllers/action_admin_permissions.php" style="margin-top: 1.5rem;">
            <?= $csrfField ?>
            <div class="responsive-table-shell">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <?php foreach ($pages as $page): ?>
                                <th style="font-size: 0.7rem;"><?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($pages as $page): ?>
                                    <?php
                                    $userPerms = $permissions[(int) $user['user_id']][$page] ?? ['can_view' => 1, 'can_edit' => 0, 'can_delete' => 0];
                                    ?>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <input type="checkbox" name="can_view[<?= (int) $user['user_id'] ?>][<?= $page ?>]" id="view_<?= (int) $user['user_id'] ?>_<?= $page ?>" <?= $userPerms['can_view'] ? 'checked' : '' ?> style="margin-right: 0.25rem;">
                                        <input type="checkbox" name="can_edit[<?= (int) $user['user_id'] ?>][<?= $page ?>]" id="edit_<?= (int) $user['user_id'] ?>_<?= $page ?>" <?= $userPerms['can_edit'] ? 'checked' : '' ?> style="margin-right: 0.25rem;">
                                        <input type="checkbox" name="can_delete[<?= (int) $user['user_id'] ?>][<?= $page ?>]" id="del_<?= (int) $user['user_id'] ?>_<?= $page ?>" <?= $userPerms['can_delete'] ? 'checked' : '' ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn green" style="margin-top: 1rem;"><i class="material-icons left">save</i>Save Permissions</button>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">backup</i> Backup</div>
        <p class="bento-subtext">Create a backup of all data.</p>

            <form method="post" action="/dss/controllers/action_admin_backup.php" style="margin-top: 1.5rem;">
                <?= $csrfField ?>
                <button type="submit" class="btn blue">Create Backup</button>
            </form>

            <?php if (isset($backupMessage)): ?>
                <div class="card-panel <?= $backupSuccess ? 'green' : 'red' ?> lighten-4 <?= $backupSuccess ? 'green-text' : 'red-text' ?>" style="margin-top: 1rem;">
                    <?= htmlspecialchars($backupMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>