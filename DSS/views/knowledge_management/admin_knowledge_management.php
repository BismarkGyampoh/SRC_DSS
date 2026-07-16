<?php

require_once '../includes/header.php';
?>

<?php if ($sessionRole !== 'Admin'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">psychology</i> Knowledge Management</div>
        <p class="bento-subtext">Manage expert system rules and knowledge base categories.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">category</i> Knowledge Categories</div>
        <p class="bento-subtext">Categories used to group expert system rules.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query('SELECT * FROM knowledge_categories ORDER BY category_name ASC');
                    $categories = $stmt->fetchAll();
                    if ($categories === []):
                    ?>
                        <tr>
                            <td colspan="4" class="center-align grey-text">No categories found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= (int) $cat['category_id'] ?></td>
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

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">rule</i> Expert Rules</div>
        <p class="bento-subtext">Rule-based qualitative risk and ROI assessments for project evaluation.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rule Name</th>
                        <th>Category</th>
                        <th>Severity</th>
                        <th>Active</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query(
                        'SELECT expert_rules.*, knowledge_categories.category_name
                         FROM expert_rules
                         INNER JOIN knowledge_categories ON knowledge_categories.category_id = expert_rules.category_id
                         ORDER BY expert_rules.rule_id ASC'
                    );
                    $rules = $stmt->fetchAll();
                    if ($rules === []):
                    ?>
                        <tr>
                            <td colspan="6" class="center-align grey-text">No rules found. Seed data has been added but rules are loaded from hardcoded closures until migration is complete.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><?= (int) $rule['rule_id'] ?></td>
                                <td><?= htmlspecialchars($rule['rule_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($rule['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($rule['severity']) ?>">
                                        <?= htmlspecialchars($rule['severity'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= (int) $rule['is_active'] ?></td>
                                <td><?= htmlspecialchars($rule['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">history</i> Rule Trigger Log</div>
        <p class="bento-subtext">Record of when expert system rules fired during project evaluations.</p>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Trigger ID</th>
                        <th>Rule ID</th>
                        <th>Project ID</th>
                        <th>Result</th>
                        <th>Triggered At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query(
                        'SELECT rule_trigger_log.*, projects.title AS project_title
                         FROM rule_trigger_log
                         INNER JOIN projects ON projects.project_id = rule_trigger_log.project_id
                         ORDER BY rule_trigger_log.triggered_at DESC
                         LIMIT 100'
                    );
                    $triggers = $stmt->fetchAll();
                    if ($triggers === []):
                    ?>
                        <tr>
                            <td colspan="5" class="center-align grey-text">No rule triggers recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($triggers as $trigger): ?>
                            <tr>
                                <td><?= (int) $trigger['trigger_id'] ?></td>
                                <td><?= (int) $trigger['rule_id'] ?></td>
                                <td><?= (int) $trigger['project_id'] ?> — <?= htmlspecialchars($trigger['project_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($trigger['result'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($trigger['triggered_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
