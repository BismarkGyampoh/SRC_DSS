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
    <div class="bento-card">
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
            <a href="../controllers/action_admin_export.php?type=projects" class="btn blue"><i class="material-icons left">file_download</i>Export Projects</a>
            <a href="../controllers/action_admin_export.php?type=users" class="btn green"><i class="material-icons left">file_download</i>Export Users</a>
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
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">pie_chart</i> Projects by Status</div>
        <p class="bento-subtext">How many projects are in each status.</p>
        <?php if ($projectStatusCounts === []): ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">folder_open</i>
                <h5 class="grey-text">No Projects Found</h5>
                <p class="grey-text">There are currently no projects in the system.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th class="text-right">Project Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projectStatusCounts as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['dss_status'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right"><?= (int) $row['count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">insert_chart</i> Project Status Chart</div>
        <p class="bento-subtext">See project statuses in a chart.</p>
        <?php
        $totalProjects = (int) ($projectStatusMap['Pending'] ?? 0) + (int) ($projectStatusMap['Accepted'] ?? 0) + (int) ($projectStatusMap['Rejected'] ?? 0) + (int) ($projectStatusMap['Deferred'] ?? 0);
        if ($totalProjects === 0):
        ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">insert_chart</i>
                <h5 class="grey-text">No Projects to Show</h5>
                <p class="grey-text">Add projects to see the chart.</p>
            </div>
        <?php else: ?>
            <div class="chart-shell chart-shell-sm">
                <canvas id="projectChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalProjects > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('projectChart').getContext('2d');
            const projectData = {
                labels: ['Pending', 'Accepted', 'Rejected', 'Deferred'],
                datasets: [{
                    data: [
                        <?= (int) ($projectStatusMap['Pending'] ?? 0) ?>,
                        <?= (int) ($projectStatusMap['Accepted'] ?? 0) ?>,
                        <?= (int) ($projectStatusMap['Rejected'] ?? 0) ?>,
                        <?= (int) ($projectStatusMap['Deferred'] ?? 0) ?>
                    ],
                    backgroundColor: [
                        '#f59e0b',
                        '#10b981',
                        '#ef4444',
                        '#6b7280'
                    ],
                    borderWidth: 2
                }]
            };
            new Chart(ctx, {
                type: 'doughnut',
                data: projectData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>