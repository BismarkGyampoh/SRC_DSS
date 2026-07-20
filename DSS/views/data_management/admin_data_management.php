<?php

require_once '../../includes/header.php';

requireRole(['Admin']);

require_once __DIR__ . '/../../services/data_management/DataManagementService.php';
$dataService = new DataManagementService($pdo);
$importHistory = $dataService->getImportHistory();
$qualityChecks = $dataService->getQualityChecks();

$totalImports = count($importHistory);
$totalRecords = array_sum(array_column($importHistory, 'records_imported'));
$totalRejected = array_sum(array_column($importHistory, 'records_rejected'));
$activeIssues = count(array_filter($qualityChecks, function($c) { return strtolower($c['severity'] ?? '') === 'high' || strtolower($c['severity'] ?? '') === 'critical'; }));
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <div class="admin-page-header-icon">
            <i class="material-icons">storage</i>
        </div>
        <div class="admin-page-header-text">
            <h1>Data Management</h1>
            <p>Monitor data imports, sources, and quality checks across the system.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="javascript:void(0)" class="btn green" onclick="M.toast({html: 'Import feature coming soon', classes: 'green'})">
            <i class="material-icons left">cloud_upload</i>New Import
        </a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">upload_file</i></div>
        <div class="admin-stat-label">Total Imports</div>
        <div class="admin-stat-value"><?= (int) $totalImports ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">database</i></div>
        <div class="admin-stat-label">Records Imported</div>
        <div class="admin-stat-value"><?= number_format((int) $totalRecords) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">delete_outline</i></div>
        <div class="admin-stat-label">Records Rejected</div>
        <div class="admin-stat-value"><?= number_format((int) $totalRejected) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">warning</i></div>
        <div class="admin-stat-label">Active Issues</div>
        <div class="admin-stat-value"><?= (int) $activeIssues ?></div>
    </div>
</div>

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">cloud_download</i> Import History</div>
            <div class="admin-table-card-subtitle">Recent data imports from all sources.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Imported By</th>
                    <th>Academic Term</th>
                    <th>Records</th>
                    <th>Rejected</th>
                    <th>Status</th>
                    <th>Started At</th>
                    <th>Completed At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($importHistory === []): ?>
                    <tr>
                        <td colspan="8">
                            <div class="admin-empty-state">
                                <i class="material-icons">cloud_off</i>
                                <h4>No Imports Found</h4>
                                <p>No data imports have been recorded yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($importHistory as $import): ?>
                        <tr>
                            <td><?= htmlspecialchars($import['source_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($import['imported_by_username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($import['academic_term'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $import['records_imported'] ?></td>
                            <td><?= (int) $import['records_rejected'] ?></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= strtolower($import['status']) ?>">
                                    <?= htmlspecialchars($import['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($import['started_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $import['completed_at'] !== null ? htmlspecialchars($import['completed_at'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
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
            <div class="admin-table-card-title"><i class="material-icons">warning</i> Data Quality Checks</div>
            <div class="admin-table-card-subtitle">Recent quality issues detected during imports or data entry.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Import ID</th>
                    <th>Check Name</th>
                    <th>Severity</th>
                    <th>Violations</th>
                    <th>Details</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($qualityChecks === []): ?>
                    <tr>
                        <td colspan="6">
                            <div class="admin-empty-state">
                                <i class="material-icons">check_circle</i>
                                <h4>No Quality Issues</h4>
                                <p>All data checks passed. No issues detected.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($qualityChecks as $check): ?>
                        <tr>
                            <td><?= $check['import_id'] !== null ? (int) $check['import_id'] : '—' ?></td>
                            <td><?= htmlspecialchars($check['check_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= strtolower($check['severity']) ?>">
                                    <?= htmlspecialchars($check['severity'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= (int) $check['violation_count'] ?></td>
                            <td><?= htmlspecialchars($check['details'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($check['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
