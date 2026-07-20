<?php

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lang/index.php';

requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Admin', 'Faculty Representative', 'Student Representative']);

$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$sessionRole = $_SESSION['user_role'] ?? '';
$userRole = htmlspecialchars($sessionRole, ENT_QUOTES, 'UTF-8');

$profileName = $userRole;
$profilePic = '';
$unreadNotifications = 0;
if (isset($pdo) && $pdo instanceof PDO && isset($_SESSION['user_id'])) {
    try {
        $pStmt = $pdo->prepare('SELECT display_name, profile_picture, theme_preference FROM src_users WHERE user_id = :id LIMIT 1');
        $pStmt->execute([':id' => (int) $_SESSION['user_id']]);
        $pRow = $pStmt->fetch();
        if ($pRow !== false) {
            if (!empty($pRow['display_name'])) {
                $profileName = htmlspecialchars($pRow['display_name'], ENT_QUOTES, 'UTF-8');
            }
            if (!empty($pRow['profile_picture'])) {
                $profilePic = 'public/uploads/avatars/' . htmlspecialchars($pRow['profile_picture'], ENT_QUOTES, 'UTF-8');
            }
            if (isset($pRow['theme_preference'])) {
                $_SESSION['theme_preference'] = $pRow['theme_preference'];
            }
        }
    } catch (PDOException $e) { /* columns may not exist yet */ }
    try {
        $nStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $nStmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $unreadNotifications = (int) ($nStmt->fetchColumn() ?: 0);
    } catch (PDOException $e) { /* table may not exist yet */ }
}
$avatarInitial = mb_strtoupper(mb_substr($profileName, 0, 1));
$csrfField = '<input type="hidden" name="csrf_token" value="'
    . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';

$currentPage = strtolower(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$pageTitle = ucwords(str_replace(['_', '.php'], [' ', ''], basename($currentPage)));
$themePreference = $_SESSION['theme_preference'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('site_title'), ENT_QUOTES, 'UTF-8') ?> — Executive Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link rel="stylesheet" href="/dss/public/css/app.css?v=18">
</head>
<body>
    <header class="app-header">
        <div class="app-header-inner">
            <a href="#" class="app-logo">
                <img src="/dss/public/images/logo.png" alt="UMaT SRC" class="app-logo-img">
            </a>

            <nav class="app-nav" id="appNav">
                <div class="app-nav-primary" id="appNavPrimary">
                    <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board', 'Faculty Representative'])): ?>
                        <a class="app-nav-link <?= $currentPage === '/dss/views/constraints.php' ? 'active' : '' ?>" data-collapse href="/dss/views/constraints.php">
                            <i class="material-icons">settings</i>
                            <span><?= __('term_budget') ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($sessionRole === 'Executive Board'): ?>
                        <a class="app-nav-link <?= $currentPage === '/dss/views/optimization.php' ? 'active' : '' ?>" data-collapse href="/dss/views/optimization.php">
                            <i class="material-icons">check_circle</i>
                            <span><?= __('project_selection') ?></span>
                        </a>
                        <a class="app-nav-link <?= $currentPage === '/dss/views/sandbox.php' ? 'active' : '' ?>" data-collapse href="/dss/views/sandbox.php">
                            <i class="material-icons">science</i>
                            <span><?= __('try_out_tool') ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($sessionRole === 'Projects Coordinator'): ?>
                        <a class="app-nav-link <?= $currentPage === '/dss/views/proposal.php' ? 'active' : '' ?>" data-collapse href="/dss/views/proposal.php">
                            <i class="material-icons">add_circle</i>
                            <span><?= __('submit_project') ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array($sessionRole, ['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Faculty Representative', 'Student Representative'])): ?>
                        <a class="app-nav-link <?= $currentPage === '/dss/views/feedback.php' ? 'active' : '' ?>" data-collapse href="/dss/views/feedback.php">
                            <i class="material-icons">feedback</i>
                            <span><?= __('feedback') ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board', 'Projects Coordinator', 'Faculty Representative', 'Student Representative'])): ?>
                        <a class="app-nav-link <?= $currentPage === '/dss/views/activity_feed.php' ? 'active' : '' ?>" data-collapse href="/dss/views/activity_feed.php">
                            <i class="material-icons">history</i>
                            <span><?= __('activity_nav') ?></span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="app-nav-more" id="appNavMore" hidden>
                    <button class="app-nav-link app-nav-more-trigger" id="appNavMoreTrigger" aria-expanded="false" aria-haspopup="true" type="button">
                        <i class="material-icons">more_horiz</i>
                        <span><?= __('more') ?></span>
                        <i class="material-icons app-nav-more-arrow">expand_more</i>
                    </button>
                    <div class="app-nav-more-menu" id="appNavMoreMenu">
                        <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board'])): ?>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/budget_analytics.php' ? 'active' : '' ?>" href="/dss/views/budget_analytics.php">
                                <i class="material-icons">bar_chart</i>
                                <span><?= __('budget_reports') ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($sessionRole === 'Projects Coordinator'): ?>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/drafts.php' ? 'active' : '' ?>" href="/dss/views/drafts.php">
                                <i class="material-icons">drafts</i>
                                <span><?= __('my_drafts') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/rollover.php' ? 'active' : '' ?>" href="/dss/views/rollover.php">
                                <i class="material-icons">forward</i>
                                <span><?= __('carry_forward') ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (in_array($sessionRole, ['Faculty Representative', 'Student Representative'])): ?>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/public_dashboard.php' ? 'active' : '' ?>" href="/dss/views/public_dashboard.php">
                                <i class="material-icons">public</i>
                                <span><?= __('public_dashboard') ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (in_array($sessionRole, ['Executive Board', 'Projects Coordinator', 'Admin'])): ?>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/milestones.php' ? 'active' : '' ?>" href="/dss/views/milestones.php">
                                <i class="material-icons">flag</i>
                                <span><?= __('milestones') ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (in_array($sessionRole, ['Projects Coordinator', 'Admin'])): ?>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/templates.php' ? 'active' : '' ?>" href="/dss/views/templates.php">
                                <i class="material-icons">layers</i>
                                <span><?= __('templates') ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($sessionRole === 'Admin'): ?>
                            <div class="app-nav-more-divider"></div>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/admin_dashboard.php' ? 'active' : '' ?>" href="/dss/views/admin_dashboard.php">
                                <i class="material-icons">dashboard</i>
                                <span><?= __('system_dashboard') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/admin_config.php' ? 'active' : '' ?>" href="/dss/views/admin_config.php">
                                <i class="material-icons">settings</i>
                                <span><?= __('system_settings') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/activity_logs.php' ? 'active' : '' ?>" href="/dss/views/activity_logs.php">
                                <i class="material-icons">history</i>
                                <span><?= __('activity_logs') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/data_management/admin_data_management.php' ? 'active' : '' ?>" href="/dss/views/data_management/admin_data_management.php">
                                <i class="material-icons">storage</i>
                                <span><?= __('data_management') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/knowledge_management/admin_knowledge_management.php' ? 'active' : '' ?>" href="/dss/views/knowledge_management/admin_knowledge_management.php">
                                <i class="material-icons">psychology</i>
                                <span><?= __('knowledge_management') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/model_management/admin_model_management.php' ? 'active' : '' ?>" href="/dss/views/model_management/admin_model_management.php">
                                <i class="material-icons">model_training</i>
                                <span><?= __('model_management') ?></span>
                            </a>
                            <a class="app-nav-more-item <?= $currentPage === '/dss/views/user_interface/admin_dashboard_manager.php' ? 'active' : '' ?>" href="/dss/views/user_interface/admin_dashboard_manager.php">
                                <i class="material-icons">dashboard</i>
                                <span><?= __('ui_manager') ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <div class="app-header-actions">
                <form method="post" action="/dss/controllers/action_change_language.php" class="app-lang-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="language" id="languageInput" value="<?= htmlspecialchars($_SESSION['language'] ?? 'en', ENT_QUOTES, 'UTF-8') ?>">
                    <select id="languageSwitcher" class="app-lang-select" onchange="document.getElementById('languageInput').value=this.value;this.form.submit();">
                        <option value="en" <?= ($_SESSION['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>EN</option>
                        <option value="gh" <?= ($_SESSION['language'] ?? 'en') === 'gh' ? 'selected' : '' ?>>GH</option>
                    </select>
                </form>

                <button class="app-icon-btn" id="notificationBell" title="<?= __('notifications') ?>" aria-label="<?= __('notifications') ?>">
                    <i class="material-icons">notifications</i>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="app-badge" id="notificationBadge"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
                    <?php endif; ?>
                </button>
                <div class="app-notification-dropdown" id="notificationDropdown">
                    <div class="app-notification-header">
                        <span class="app-notification-title"><?= __('notifications') ?></span>
                        <div class="app-notification-actions">
                            <button type="button" class="app-notification-action-btn" id="markAllReadBtn"><?= __('mark_all_read') ?></button>
                            <button type="button" class="app-notification-action-btn" id="deleteReadBtn"><?= __('delete_read') ?></button>
                        </div>
                    </div>
                    <div class="app-notification-body" id="notificationList">
                        <?php
                        try {
                            $notifStmt = $pdo->prepare('SELECT notification_id, title, message, type, is_read, created_at FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 20');
                            $notifStmt->execute([':uid' => (int) $_SESSION['user_id']]);
                            $notifications = $notifStmt->fetchAll();
                            if ($notifications === []) {
                                echo '<div class="app-notification-empty">' . __('no_notifications') . '</div>';
                            } else {
                                foreach ($notifications as $notif):
                        ?>
                        <div class="app-notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>" data-id="<?= (int) $notif['notification_id'] ?>">
                            <div class="app-notification-item-header">
                                <span class="app-notification-item-title"><?= htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="app-notification-item-time"><?= date('M j, g:i a', strtotime($notif['created_at'])) ?></span>
                            </div>
                            <div class="app-notification-item-message"><?= htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="app-notification-item-footer">
                                <span class="app-notification-type type-<?= htmlspecialchars($notif['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($notif['type']), ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="app-notification-item-actions">
                                    <?php if (!$notif['is_read']): ?>
                                        <button type="button" class="app-notification-item-btn mark-read-btn" data-id="<?= (int) $notif['notification_id'] ?>">Mark read</button>
                                    <?php endif; ?>
                                    <button type="button" class="app-notification-item-btn delete-btn" data-id="<?= (int) $notif['notification_id'] ?>">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php
                                endforeach;
                            }
                        } catch (PDOException $e) {
                            echo '<div class="app-notification-empty">' . __('no_notifications') . '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="app-user-menu" id="appUserMenu">
                    <button class="app-user-trigger" id="appUserTrigger" aria-expanded="false" aria-haspopup="true">
                        <span class="app-user-avatar">
                            <?php if ($profilePic !== ''): ?>
                                <img src="/dss/<?= $profilePic ?>" alt="Avatar" class="app-user-avatar-img">
                            <?php else: ?>
                                <?= $avatarInitial ?>
                            <?php endif; ?>
                        </span>
                        <span class="app-user-name"><?= htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8') ?></span>
                        <i class="material-icons app-user-arrow">expand_more</i>
                    </button>
                    <div class="app-user-dropdown" id="appUserDropdown">
                        <div class="app-user-dropdown-header">
                            <span class="app-user-avatar app-user-avatar-lg">
                                <?php if ($profilePic !== ''): ?>
                                    <img src="/dss/<?= $profilePic ?>" alt="Avatar" class="app-user-avatar-img">
                                <?php else: ?>
                                    <?= $avatarInitial ?>
                                <?php endif; ?>
                            </span>
                            <div class="app-user-info">
                                <div class="app-user-dropdown-name"><?= htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="app-user-dropdown-role"><?= $userRole ?></div>
                            </div>
                        </div>
                        <div class="app-user-dropdown-divider"></div>
                        <a class="app-user-dropdown-item" href="/dss/views/profile.php">
                            <i class="material-icons">edit</i>
                            <span><?= __('edit_profile') ?></span>
                        </a>
                        <a class="app-user-dropdown-item" href="/dss/views/activity_feed.php">
                            <i class="material-icons">history</i>
                            <span><?= __('activity_feed') ?></span>
                        </a>
                        <div class="app-user-dropdown-divider"></div>
                        <button type="button" class="app-user-dropdown-item" id="themeToggleBtn">
                            <i class="material-icons" id="themeIcon"><?= __('dark_mode') ?></i>
                            <span id="themeLabel"><?= __('dark_mode') ?></span>
                        </button>
                        <div class="app-user-dropdown-divider"></div>
                        <a class="app-user-dropdown-item app-user-dropdown-item-danger" href="/dss/logout.php">
                            <i class="material-icons">exit_to_app</i>
                            <span><?= __('logout') ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="app-main-inner">
            <?php if ($flashMessage !== null): ?>
                <div class="app-alert" role="status">
                    <i class="material-icons app-alert-icon">check_circle</i>
                    <span class="app-alert-msg"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    <button type="button" class="app-alert-close" aria-label="Dismiss" onclick="this.parentElement.remove()"><i class="material-icons">close</i></button>
                </div>
            <?php endif; ?>

    <script>
    (function() {
        const trigger = document.getElementById('appUserTrigger');
        const menu = trigger ? trigger.closest('.app-user-menu') : null;
        if (!trigger || !menu) return;

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const open = menu.classList.toggle('open');
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target)) {
                menu.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    })();

    (function() {
        const bell = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        if (!bell || !dropdown) return;

        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.app-notification-dropdown').forEach(function(el) {
                if (el !== dropdown) el.classList.remove('open');
            });
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });

        function postNotification(action, id) {
            const fd = new FormData();
            fd.append('action', action);
            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            if (id) fd.append('notification_id', id);
                    fetch('/dss/controllers/action_notifications.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status === 'success') {
                        if (action === 'mark_read' || action === 'delete') {
                            const item = dropdown.querySelector('[data-id="' + id + '"]');
                            if (item) item.remove();
                        }
                        if (action === 'mark_all_read') {
                            dropdown.querySelectorAll('.app-notification-item.unread').forEach(function(el) { el.classList.remove('unread'); el.classList.add('read'); });
                        }
                        if (action === 'delete_read') {
                            dropdown.querySelectorAll('.app-notification-item.read').forEach(function(el) { el.remove(); });
                        }
                        updateBadge();
                    }
                })
                .catch(function() {});
        }

        function updateBadge() {
            const badge = document.getElementById('notificationBadge');
            const remaining = dropdown.querySelectorAll('.app-notification-item.unread').length;
            if (remaining > 0) {
                if (!badge) {
                    const b = document.createElement('span');
                    b.id = 'notificationBadge';
                    b.className = 'app-badge';
                    b.textContent = remaining > 99 ? '99+' : remaining;
                    bell.appendChild(b);
                } else {
                    badge.textContent = remaining > 99 ? '99+' : remaining;
                }
            } else if (badge) {
                badge.remove();
            }
            const empty = dropdown.querySelector('.app-notification-empty');
            if (empty && dropdown.querySelectorAll('.app-notification-item').length === 0) {
                empty.style.display = 'block';
            } else if (empty) {
                empty.style.display = 'none';
            }
        }

        dropdown.addEventListener('click', function(e) {
            const markReadBtn = e.target.closest('.mark-read-btn');
            const deleteBtn = e.target.closest('.delete-btn');
            if (markReadBtn) {
                e.preventDefault();
                postNotification('mark_read', markReadBtn.dataset.id);
            }
            if (deleteBtn) {
                e.preventDefault();
                postNotification('delete', deleteBtn.dataset.id);
            }
        });

        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function() {
                postNotification('mark_all_read');
            });
        }

        const deleteReadBtn = document.getElementById('deleteReadBtn');
        if (deleteReadBtn) {
            deleteReadBtn.addEventListener('click', function() {
                postNotification('delete_read');
            });
        }
    })();

    (function() {
        const themeBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        if (!themeBtn) return;

        const saved = localStorage.getItem('theme') || '<?= htmlspecialchars($themePreference, ENT_QUOTES, 'UTF-8') ?>';
        applyTheme(saved);

        themeBtn.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
                    fetch('/dss/controllers/action_update_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + encodeURIComponent(next) + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)
            }).catch(function() {});
        });

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            if (themeIcon) themeIcon.textContent = theme === 'dark' ? 'light_mode' : 'dark_mode';
            if (themeLabel) themeLabel.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }
    })();

    (function() {
        const moreTrigger = document.getElementById('appNavMoreTrigger');
        const moreMenu = document.getElementById('appNavMoreMenu');
        if (!moreTrigger || !moreMenu) return;
        const moreContainer = moreTrigger.closest('.app-nav-more');
        const primary = document.getElementById('appNavPrimary');

        moreTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const open = moreContainer.classList.toggle('open');
            moreTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function(e) {
            if (!moreContainer.contains(e.target)) {
                moreContainer.classList.remove('open');
                moreTrigger.setAttribute('aria-expanded', 'false');
            }
        });

        function collapseNav() {
            if (!primary) return;
            const collapsedClass = 'app-nav-collapsed';
            const nav = primary.closest('.app-nav');

            let primaryItems = Array.from(primary.querySelectorAll('a.app-nav-link:not(.' + collapsedClass + ')'));
            if (primaryItems.length === 0) {
                moreContainer.hidden = (moreMenu.children.length === 0);
                return;
            }

            const moreTriggerWidth = moreContainer.hidden ? 0 : moreContainer.offsetWidth;

            while (primaryItems.length > 0) {
                const totalNeeded = primary.scrollWidth + moreTriggerWidth + 2;
                if (totalNeeded <= nav.clientWidth) break;
                const el = primaryItems.pop();
                if (!el) break;
                el.classList.add(collapsedClass);
                moreMenu.insertBefore(el, moreMenu.firstChild);
                primaryItems = Array.from(primary.querySelectorAll('a.app-nav-link:not(.' + collapsedClass + ')'));
            }

            moreContainer.hidden = (moreMenu.children.length === 0);
            if (moreContainer.hidden) {
                moreContainer.classList.remove('open');
                moreTrigger.setAttribute('aria-expanded', 'false');
            }
        }

        function expandNav() {
            const collapsed = Array.from(moreMenu.querySelectorAll('a.app-nav-link.app-nav-collapsed'));
            collapsed.slice().reverse().forEach(function(el) {
                el.classList.remove('app-nav-collapsed');
                primary.appendChild(el);
            });
            if (collapsed.length > 0) collapseNav();
            moreContainer.hidden = (moreMenu.children.length === 0);
            if (moreContainer.hidden) {
                moreContainer.classList.remove('open');
                moreTrigger.setAttribute('aria-expanded', 'false');
            }
        }

        let rafId = null;
        function reflow() {
            if (rafId) cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(function() {
                expandNav();
                collapseNav();
            });
        }

        window.addEventListener('resize', reflow);
        window.addEventListener('load', reflow);
        reflow();
    })();
    </script>
