<?php

require_once '../includes/header.php';
require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';
$modelService = new ModelManagementService($pdo);
$catalog = $modelService->getModelCatalog();
$executions = $modelService->getExecutionHistory();
?>

<?php if ($sessionRole !== 'Admin'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">model_training</i> Model Management</div>
        <p class="bento-subtext">View registered DSS models, parameters, and execution history.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">hub</i> Model Catalog</div>
        <p class="bento-subtext">Registered analytical engines in the DSS.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Model Name</th>
                        <th>Type</th>
                        <th>Version</th>
                        <th>Active</th>
                        <th>Run Count</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($catalog === []): ?>
                        <tr>
                            <td colspan="7" class="center-align grey-text">No models registered.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($catalog as $model): ?>
                            <tr>
                                <td><?= (int) $model['model_id'] ?></td>
                                <td><?= htmlspecialchars($model['model_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($model['model_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($model['version'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int) $model['is_active'] ?></td>
                                <td><?= (int) $model['run_count'] ?></td>
                                <td><?= htmlspecialchars($model['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">tune</i> Model Parameters</div>
        <p class="bento-subtext">Runtime parameters for registered models.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Param ID</th>
                        <th>Model</th>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Updated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $allParams = [];
                    foreach ($catalog as $model) {
                        $params = $modelService->getModelParameters((int) $model['model_id']);
                        foreach ($params as $param) {
                            $param['model_name'] = $model['model_name'];
                            $allParams[] = $param;
                        }
                    }

                    if ($allParams === []):
                    ?>
                        <tr>
                            <td colspan="7" class="center-align grey-text">No parameters configured.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allParams as $param): ?>
                            <tr>
                                <td><?= (int) $param['param_id'] ?></td>
                                <td><?= htmlspecialchars($param['model_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($param['param_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($param['param_value'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($param['param_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($param['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($param['updated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">history</i> Execution History</div>
        <p class="bento-subtext">Recent model executions across all DSS engines.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Execution ID</th>
                        <th>Model</th>
                        <th>Type</th>
                        <th>Triggered By</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th>Time (ms)</th>
                        <th>Created At</th>
                        <th>Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($executions === []): ?>
                        <tr>
                            <td colspan="9" class="center-align grey-text">No executions recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($executions as $exec): ?>
                            <tr>
                                <td><?= (int) $exec['execution_id'] ?></td>
                                <td><?= htmlspecialchars($exec['model_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($exec['model_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($exec['triggered_by_username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($exec['academic_term'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($exec['status']) ?>">
                                        <?= htmlspecialchars($exec['status'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= $exec['execution_time_ms'] !== null ? (int) $exec['execution_time_ms'] : '—' ?></td>
                                <td><?= htmlspecialchars($exec['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $exec['completed_at'] !== null ? htmlspecialchars($exec['completed_at'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
