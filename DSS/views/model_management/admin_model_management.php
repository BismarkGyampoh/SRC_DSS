<?php

require_once '../../includes/header.php';

requireRole(['Admin']);

require_once __DIR__ . '/../../services/model_management/ModelManagementService.php';
$modelService = new ModelManagementService($pdo);
$catalog = $modelService->getModelCatalog();
$executions = $modelService->getExecutionHistory();

$activeModels = count(array_filter($catalog, function($m) { return (int) $m['is_active'] === 1; }));
$totalExecutions = count($executions);
$successfulExecutions = count(array_filter($executions, function($e) { return strtolower($e['status'] ?? '') === 'completed'; }));
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <div class="admin-page-header-icon">
            <i class="material-icons">model_training</i>
        </div>
        <div class="admin-page-header-text">
            <h1>Model Management</h1>
            <p>View registered DSS models, parameters, and execution history.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="javascript:void(0)" class="btn green" onclick="M.toast({html: 'Register model feature coming soon', classes: 'green'})">
            <i class="material-icons left">add_circle</i>Register Model
        </a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">hub</i></div>
        <div class="admin-stat-label">Registered Models</div>
        <div class="admin-stat-value"><?= (int) count($catalog) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">check_circle</i></div>
        <div class="admin-stat-label">Active Models</div>
        <div class="admin-stat-value"><?= (int) $activeModels ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">play_arrow</i></div>
        <div class="admin-stat-label">Total Executions</div>
        <div class="admin-stat-value"><?= (int) $totalExecutions ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">verified</i></div>
        <div class="admin-stat-label">Successful</div>
        <div class="admin-stat-value"><?= (int) $successfulExecutions ?></div>
    </div>
</div>

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">hub</i> Model Catalog</div>
            <div class="admin-table-card-subtitle">Registered analytical engines in the DSS.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
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
                        <td colspan="6">
                            <div class="admin-empty-state">
                                <i class="material-icons">memory</i>
                                <h4>No Models Registered</h4>
                                <p>Register DSS models to see them here.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($catalog as $model): ?>
                        <tr>
                            <td><?= htmlspecialchars($model['model_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($model['model_type'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($model['version'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= (int) $model['is_active'] === 1 ? 'success' : 'neutral' ?>">
                                    <?= (int) $model['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= (int) $model['run_count'] ?></td>
                            <td><?= htmlspecialchars($model['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
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
            <div class="admin-table-card-title"><i class="material-icons">tune</i> Model Parameters</div>
            <div class="admin-table-card-subtitle">Runtime parameters for registered models.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
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
                        <td colspan="7">
                            <div class="admin-empty-state">
                                <i class="material-icons">settings</i>
                                <h4>No Parameters Configured</h4>
                                <p>Model parameters will appear here once configured.</p>
                            </div>
                        </td>
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

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">history</i> Execution History</div>
            <div class="admin-table-card-subtitle">Recent model executions across all DSS engines.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
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
                        <td colspan="9">
                            <div class="admin-empty-state">
                                <i class="material-icons">play_circle_outline</i>
                                <h4>No Executions Recorded</h4>
                                <p>Model executions will appear here when DSS models are run.</p>
                            </div>
                        </td>
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
                                <span class="admin-badge admin-badge-<?= strtolower($exec['status']) ?>">
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

<?php require_once '../../includes/footer.php'; ?>
