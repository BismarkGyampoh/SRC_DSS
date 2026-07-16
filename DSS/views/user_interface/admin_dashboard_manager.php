<?php

require_once '../includes/header.php';

echo $csrfField;

require_once __DIR__ . '/../../services/user_interface/DashboardService.php';
$dashboardService = new DashboardService($pdo);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$dashboards = $dashboardService->getUserDashboards($userId);
$activeDashboard = $dashboardService->loadDashboard($userId);
$widgets = $activeDashboard !== false ? $dashboardService->getWidgets((int) $activeDashboard['dashboard_id']) : [];
?>

<?php if ($sessionRole === ''): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">Please sign in to manage dashboards.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">dashboard</i> Dashboard Manager</div>
        <p class="bento-subtext">Save, load, and customize your personal dashboard layouts.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">save</i> Save Dashboard Layout</div>
        <p class="bento-subtext">Save the current dashboard configuration for your account.</p>
        <form id="saveDashboardForm" style="margin-top: 1rem;">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m6">
                    <input type="text" id="dashboard_name" name="dashboard_name" value="<?= htmlspecialchars($activeDashboard !== false ? $activeDashboard['dashboard_name'] : 'My Dashboard', ENT_QUOTES, 'UTF-8') ?>" required>
                    <label for="dashboard_name">Dashboard Name</label>
                </div>
                <div class="input-field col s12 m6">
                    <label>
                        <input type="checkbox" id="is_default" name="is_default" <?= $activeDashboard !== false && (int) $activeDashboard['is_default'] === 1 ? 'checked' : '' ?> />
                        <span>Set as default dashboard</span>
                    </label>
                </div>
            </div>
            <button type="submit" class="btn green"><i class="material-icons left">save</i>Save Dashboard</button>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">view_list</i> My Dashboards</div>
        <p class="bento-subtext">Your saved dashboard configurations.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Default</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dashboards === []): ?>
                        <tr>
                            <td colspan="5" class="center-align grey-text">No dashboards saved yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dashboards as $dash): ?>
                            <tr>
                                <td><?= (int) $dash['dashboard_id'] ?></td>
                                <td><?= htmlspecialchars($dash['dashboard_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) $dash['is_default'] ?></td>
                                <td><?= htmlspecialchars($dash['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($dash['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">widgets</i> Current Dashboard Widgets</div>
        <p class="bento-subtext">Widgets on your active dashboard.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Widget ID</th>
                        <th>Type</th>
                        <th>Position Config</th>
                        <th>Widget Config</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($widgets === []): ?>
                        <tr>
                            <td colspan="4" class="center-align grey-text">No widgets configured.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($widgets as $widget): ?>
                            <tr>
                                <td><?= (int) $widget['widget_id'] ?></td>
                                <td><?= htmlspecialchars($widget['widget_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><code><?= htmlspecialchars(json_encode($widget['position_config']), ENT_QUOTES, 'UTF-8') ?></code></td>
                                <td><code><?= htmlspecialchars(json_encode($widget['widget_config']), ENT_QUOTES, 'UTF-8') ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.getElementById('saveDashboardForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const dashboardName = formData.get('dashboard_name');
        const isDefault = formData.has('is_default') ? 1 : 0;

        const payload = {
            dashboard_name: dashboardName,
            is_default: isDefault,
            layout_config: { saved: true },
            csrf_token: document.querySelector('input[name="csrf_token"]').value
        };

        fetch('../controllers/action_save_dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                M.toast({html: 'Dashboard saved successfully.', classes: 'green'});
            } else {
                M.toast({html: data.message || 'Failed to save dashboard.', classes: 'red'});
            }
        })
        .catch(() => {
            M.toast({html: 'Network error while saving dashboard.', classes: 'red'});
        });
    });
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
