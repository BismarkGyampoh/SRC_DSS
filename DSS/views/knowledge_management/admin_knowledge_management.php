<?php

require_once '../../includes/header.php';

requireRole(['Admin']);

$stmt = $pdo->query('SELECT * FROM knowledge_categories ORDER BY category_name ASC');
$categories = $stmt->fetchAll();

$stmt = $pdo->query(
    'SELECT expert_rules.*, knowledge_categories.category_name
     FROM expert_rules
     INNER JOIN knowledge_categories ON knowledge_categories.category_id = expert_rules.category_id
     ORDER BY expert_rules.rule_id ASC'
);
$rules = $stmt->fetchAll();

$stmt = $pdo->query(
    'SELECT rule_trigger_log.*, projects.title AS project_title
     FROM rule_trigger_log
     INNER JOIN projects ON projects.project_id = rule_trigger_log.project_id
     ORDER BY rule_trigger_log.triggered_at DESC
     LIMIT 100'
);
$triggers = $stmt->fetchAll();

$activeRules = count(array_filter($rules, function($r) { return (int) $r['is_active'] === 1; }));
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <div class="admin-page-header-icon">
            <i class="material-icons">psychology</i>
        </div>
        <div class="admin-page-header-text">
            <h1>Knowledge Management</h1>
            <p>Manage expert system rules and knowledge base categories.</p>
        </div>
    </div>
    <div class="admin-page-actions">
        <a href="javascript:void(0)" class="btn green" onclick="M.toast({html: 'Create rule feature coming soon', classes: 'green'})">
            <i class="material-icons left">add_circle</i>New Rule
        </a>
    </div>
</div>

<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">category</i></div>
        <div class="admin-stat-label">Categories</div>
        <div class="admin-stat-value"><?= (int) count($categories) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">rule</i></div>
        <div class="admin-stat-label">Total Rules</div>
        <div class="admin-stat-value"><?= (int) count($rules) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">check_circle</i></div>
        <div class="admin-stat-label">Active Rules</div>
        <div class="admin-stat-value"><?= (int) $activeRules ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><i class="material-icons">history</i></div>
        <div class="admin-stat-label">Rule Triggers</div>
        <div class="admin-stat-value"><?= (int) count($triggers) ?></div>
    </div>
</div>

<div class="admin-table-card">
    <div class="admin-table-card-header">
        <div>
            <div class="admin-table-card-title"><i class="material-icons">category</i> Knowledge Categories</div>
            <div class="admin-table-card-subtitle">Categories used to group expert system rules.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categories === []): ?>
                    <tr>
                        <td colspan="3">
                            <div class="admin-empty-state">
                                <i class="material-icons">folder_open</i>
                                <h4>No Categories Found</h4>
                                <p>Create knowledge categories to organize expert rules.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($cat['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
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
            <div class="admin-table-card-title"><i class="material-icons">rule</i> Expert Rules</div>
            <div class="admin-table-card-subtitle">Rule-based qualitative risk and ROI assessments for project evaluation.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Rule Name</th>
                    <th>Category</th>
                    <th>Severity</th>
                    <th>Active</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rules === []): ?>
                    <tr>
                        <td colspan="5">
                            <div class="admin-empty-state">
                                <i class="material-icons">gavel</i>
                                <h4>No Rules Found</h4>
                                <p>Expert rules will appear here once migrated from the knowledge base.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td><?= htmlspecialchars($rule['rule_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($rule['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= strtolower($rule['severity']) ?>">
                                    <?= htmlspecialchars($rule['severity'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-<?= (int) $rule['is_active'] === 1 ? 'success' : 'neutral' ?>">
                                    <?= (int) $rule['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($rule['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
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
            <div class="admin-table-card-title"><i class="material-icons">history</i> Rule Trigger Log</div>
            <div class="admin-table-card-subtitle">Record of when expert system rules fired during project evaluations.</div>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Rule ID</th>
                    <th>Project</th>
                    <th>Result</th>
                    <th>Triggered At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($triggers === []): ?>
                    <tr>
                        <td colspan="4">
                            <div class="admin-empty-state">
                                <i class="material-icons">event_available</i>
                                <h4>No Triggers Recorded</h4>
                                <p>Rule triggers will appear here when projects are evaluated.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($triggers as $trigger): ?>
                        <tr>
                            <td><?= (int) $trigger['rule_id'] ?></td>
                            <td><?= htmlspecialchars($trigger['project_title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($trigger['result'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($trigger['triggered_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
