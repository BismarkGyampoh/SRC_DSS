<?php

require_once '../../includes/header.php';

requireRole(['Admin']);

require_once __DIR__ . '/../../services/user_interface/DashboardService.php';
$dashboardService = new DashboardService($pdo);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$dashboards = $dashboardService->getUserDashboards($userId);
$activeDashboard = $dashboardService->loadDashboard($userId);
$widgets = $activeDashboard !== false ? $dashboardService->getWidgets((int) $activeDashboard['dashboard_id']) : [];
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <div class="admin-page-header-icon">
            <i class="material-icons">dashboard</i>
        </div>
        <div class="admin-page-header-text">
            <h1>Dashboard Manager</h1>
            <p>Save, load, and customize your personal dashboard layouts.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <button type="button" class="btn green" id="saveDashboardBtn">
            <i class="material-icons left">save</i>Save Dashboard
        </button>
    </div>
</div>

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">save</i> Save Dashboard Layout</div>
            <div class="admin-table-card-subtitle">Save the current dashboard configuration for your account.</div>
        </div>
    </div>
    <div class="row" style="margin-bottom: 0;">
        <div class="col s12 m6">
            <div class="input-field">
                <input type="text" id="dashboard_name" name="dashboard_name" value="<?= htmlspecialchars($activeDashboard !== false ? $activeDashboard['dashboard_name'] : 'My Dashboard', ENT_QUOTES, 'UTF-8') ?>" required>
                <label for="dashboard_name">Dashboard Name</label>
            </div>
        </div>
        <div class="col s12 m6">
            <label>
                <input type="checkbox" id="is_default" name="is_default" <?= $activeDashboard !== false && (int) $activeDashboard['is_default'] === 1 ? 'checked' : '' ?> />
                <span>Set as default dashboard</span>
            </label>
        </div>
    </div>
</div>

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">view_list</i> My Dashboards</div>
            <div class="admin-table-card-subtitle">Your saved dashboard configurations.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Default</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($dashboards === []): ?>
                    <tr>
                        <td colspan="4">
                            <div class="admin-empty-state">
                                <i class="material-icons">dashboard</i>
                                <h4>No Dashboards Saved</h4>
                                <p>Save your first dashboard layout using the form above.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dashboards as $dash): ?>
                        <tr>
                            <td><?= htmlspecialchars($dash['dashboard_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= (int) $dash['is_default'] === 1 ? 'success' : 'neutral' ?>">
                                    <?= (int) $dash['is_default'] === 1 ? 'Default' : 'Custom' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($dash['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($dash['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">widgets</i> Current Dashboard Widgets</div>
            <div class="admin-table-card-subtitle">Widgets on your active dashboard.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
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
                        <td colspan="4">
                            <div class="admin-empty-state">
                                <i class="material-icons">widgets</i>
                                <h4>No Widgets Configured</h4>
                                <p>Add widgets to your dashboard to see them here.</p>
                            </div>
                        </td>
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
document.getElementById('saveDashboardBtn').addEventListener('click', function(e) {
    e.preventDefault();
    const dashboardName = document.getElementById('dashboard_name').value;
    const isDefault = document.getElementById('is_default').hasAttribute('checked') ? 1 : 0;

    const payload = {
        dashboard_name: dashboardName,
        is_default: isDefault,
        layout_config: { saved: true },
        csrf_token: document.querySelector('input[name="csrf_token"]').value
    };

    fetch('/dss/controllers/action_save_dashboard.php', {
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

<?php require_once '../../includes/footer.php'; ?>
