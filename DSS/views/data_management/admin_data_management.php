<?php

require_once '../includes/header.php';
require_once __DIR__ . '/../../services/data_management/DataManagementService.php';
$dataService = new DataManagementService($pdo);
$importHistory = $dataService->getImportHistory();
$qualityChecks = $dataService->getQualityChecks();
?>

<?php if ($sessionRole !== 'Admin'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">storage</i> Data Management</div>
        <p class="bento-subtext">Monitor data imports, sources, and quality checks across the system.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">cloud_download</i> Import History</div>
        <p class="bento-subtext">Recent data imports from all sources.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
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
                            <td colspan="9" class="center-align grey-text">No imports found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($importHistory as $import): ?>
                            <tr>
                                <td><?= (int) $import['import_id'] ?></td>
                                <td><?= htmlspecialchars($import['source_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($import['imported_by_username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($import['academic_term'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) $import['records_imported'] ?></td>
                                <td><?= (int) $import['records_rejected'] ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($import['status']) ?>">
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

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">warning</i> Data Quality Checks</div>
        <p class="bento-subtext">Recent quality issues detected during imports or data entry.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
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
                            <td colspan="7" class="center-align grey-text">No quality checks found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($qualityChecks as $check): ?>
                            <tr>
                                <td><?= (int) $check['check_id'] ?></td>
                                <td><?= $check['import_id'] !== null ? (int) $check['import_id'] : '—' ?></td>
                                <td><?= htmlspecialchars($check['check_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($check['severity']) ?>">
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
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
