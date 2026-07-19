<?php

require_once '../includes/header.php';

$totalUsersStmt = $pdo->query("SELECT COUNT(*) as count FROM src_users");
$totalUsersRow = $totalUsersStmt->fetch();
$totalUsers = (int) ($totalUsersRow['count'] ?? 0);

$totalAuditLogsStmt = $pdo->query("SELECT COUNT(*) as count FROM audit_logs");
$totalAuditLogsRow = $totalAuditLogsStmt->fetch();
$totalAuditLogs = (int) ($totalAuditLogsRow['count'] ?? 0);

$projectStatusStmt = $pdo->query("SELECT dss_status, COUNT(*) as count FROM projects GROUP BY dss_status");
$projectStatusCounts = $projectStatusStmt->fetchAll();
$projectStatusMap = [];
foreach ($projectStatusCounts as $row) {
    $projectStatusMap[(string) $row['dss_status']] = (int) $row['count'];
}

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
$dbStatus = [
    'connection' => 'Connected',
    'database' => $dbName ?: 'src_dss_db'
];
?>

<?php if ($sessionRole !== 'Admin'): ?>
    <div class="card">
        <div class="card-content">
            <span class="card-title" style="color: var(--critical);">Access Restricted</span>
            <p class="grey-text">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
        </div>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">dashboard</i> Admin Dashboard</div>
        <p class="bento-subtext">See system stats and info at a glance.</p>
    </div>

    <div class="bento-grid">
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">people</i> Total Users</div>
            <div class="bento-metric"><?= (int) $totalUsers ?></div>
            <p class="bento-subtext">People with access</p>
        </div>
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">pending</i> Projects Waiting</div>
            <div class="bento-metric bento-metric-gold"><?= (int) ($projectStatusMap['Pending'] ?? 0) ?></div>
            <p class="bento-subtext">Waiting for review</p>
        </div>
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">check_circle</i> Total Selections Made</div>
            <div class="bento-metric"><?= (int) $totalAuditLogs ?></div>
            <p class="bento-subtext">Past project selection runs</p>
        </div>
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">analytics</i> Budget Utilized</div>
            <div class="bento-metric"><?php
                $budgetStmt = $pdo->query("SELECT SUM(budget_required) FROM projects WHERE dss_status = 'Accepted'");
                $totalBudget = (float) ($budgetStmt->fetchColumn() ?: 0);
                echo number_format($totalBudget, 0);
            ?></div>
            <p class="bento-subtext">GHS total allocated</p>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">bar_chart</i> Project Status Distribution</div>
        <div class="chart-shell chart-shell-sm" style="margin-top: 1rem;">
            <canvas id="statusChart" width="400" height="200"></canvas>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">show_chart</i> Budget Utilization</div>
        <div class="chart-shell chart-shell-sm" style="margin-top: 1rem;">
            <canvas id="budgetChart" width="400" height="200"></canvas>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">info</i> System Info</div>
        <p class="bento-subtext">Basic system information.</p>
        <div style="margin-top: 1.5rem;">
            <div class="card-panel green lighten-4 green-text">
                <strong>Database:</strong> <?= htmlspecialchars($dbStatus['connection'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="card-panel grey lighten-4" style="margin-top: 0.5rem;">
                <strong>Database name:</strong> <?= htmlspecialchars($dbStatus['database'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">download</i> Download Data</div>
        <p class="bento-subtext">Download data as CSV.</p>
        <div class="action-button-row" style="margin-top: 1.5rem;">
            <a href="/dss/controllers/action_export_csv.php?type=projects" class="btn blue"><i class="material-icons left">file_download</i>Export Projects</a>
            <a href="/dss/controllers/action_export_csv.php?type=overrides" class="btn orange"><i class="material-icons left">file_download</i>Export Overrides</a>
            <a href="/dss/controllers/action_export_csv.php?type=activity" class="btn green"><i class="material-icons left">file_download</i>Export Activity Logs</a>
            <a href="/dss/controllers/action_admin_export.php?type=users" class="btn grey"><i class="material-icons left">file_download</i>Export Users</a>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">layers</i> System Architecture Management</div>
        <p class="bento-subtext">Access the five DSS architecture layers: Data, Knowledge, Model, and Interface management.</p>
        <div class="action-button-row" style="margin-top: 1.5rem;">
            <a href="data_management/admin_data_management.php" class="btn teal"><i class="material-icons left">storage</i>Data Management</a>
            <a href="knowledge_management/admin_knowledge_management.php" class="btn purple"><i class="material-icons left">psychology</i>Knowledge Management</a>
            <a href="model_management/admin_model_management.php" class="btn orange"><i class="material-icons left">model_training</i>Model Management</a>
            <a href="user_interface/admin_dashboard_manager.php" class="btn cyan"><i class="material-icons left">dashboard</i>Dashboard Manager</a>
            <a href="/dss/views/templates.php" class="btn grey"><i class="material-icons left">layers</i>Templates</a>
            <a href="/dss/views/activity_feed.php" class="btn teal"><i class="material-icons left">history</i>Activity Feed</a>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">dashboard</i> Dashboard Personalization</div>
        <p class="bento-subtext">Toggle which widgets appear on your dashboard.</p>
        <div style="margin-top: 1rem;">
            <label>
                <input type="checkbox" id="widgetStats" checked>
                <span>Statistics Cards</span>
            </label>
            <label style="margin-left: 1.5rem;">
                <input type="checkbox" id="widgetCharts" checked>
                <span>Charts</span>
            </label>
            <label style="margin-left: 1.5rem;">
                <input type="checkbox" id="widgetTables" checked>
                <span>Data Tables</span>
            </label>
            <label style="margin-left: 1.5rem;">
                <input type="checkbox" id="widgetSystem" checked>
                <span>System Info</span>
            </label>
            <button type="button" class="btn blue" id="saveDashboardConfigBtn" style="margin-left: 1.5rem; margin-top: 0.5rem;"><i class="material-icons left">save</i>Save Layout</button>
            <span id="dashboardConfigMsg" style="margin-left: 1rem; font-size: 0.85rem;"></span>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusData = <?= json_encode($projectStatusMap) ?>;
        const statusLabels = Object.keys(statusData);
        const statusValues = Object.values(statusData);
        const statusColors = ['#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#64748b'];

        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: statusColors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { family: 'Outfit' } } }
                    }
                }
            });
        }

        const budgetCtx = document.getElementById('budgetChart');
        if (budgetCtx) {
            const acceptedBudget = <?= (float) ($projectStatusMap['Accepted'] ?? 0) > 0 ? json_encode($totalBudget) : 0 ?>;
            const otherBudget = Math.max(0, (function() {
                const stmt = <?= json_encode($projectStatusMap) ?>;
                let total = 0;
                Object.keys(stmt).forEach(function(k) { if (k !== 'Accepted') total += parseFloat(stmt[k] || 0); });
                return total * 5000;
            })());
            new Chart(budgetCtx, {
                type: 'bar',
                data: {
                    labels: ['Accepted', 'Other Statuses'],
                    datasets: [{
                        label: 'Budget (GHS)',
                        data: [acceptedBudget, otherBudget],
                        backgroundColor: ['#10b981', '#94a3b8'],
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    });
    </script>

    <script>
    document.getElementById('saveDashboardConfigBtn')?.addEventListener('click', function() {
        const config = {
            widgets: {
                stats: document.getElementById('widgetStats')?.checked ?? true,
                charts: document.getElementById('widgetCharts')?.checked ?? true,
                tables: document.getElementById('widgetTables')?.checked ?? true,
                system: document.getElementById('widgetSystem')?.checked ?? true,
            }
        };
        fetch('/dss/controllers/action_save_dashboard_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        })
        .then(r => r.json())
        .then(data => {
            const msg = document.getElementById('dashboardConfigMsg');
            if (data.status === 'success') {
                msg.textContent = 'Saved!';
                msg.style.color = 'var(--success)';
            } else {
                msg.textContent = 'Error: ' + (data.message || 'Unknown');
                msg.style.color = 'var(--critical)';
            }
            setTimeout(() => { msg.textContent = ''; }, 3000);
        })
        .catch(() => {
            const msg = document.getElementById('dashboardConfigMsg');
            msg.textContent = 'Network error';
            msg.style.color = 'var(--critical)';
        });
    });
    </script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>