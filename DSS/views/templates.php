<?php

require_once '../includes/header.php';

requireRole(['Projects Coordinator', 'Admin']);

$templates = [];
try {
    $templatesStmt = $pdo->query('SELECT template_id, template_name, category, description, criteria_scores_json, default_budget, default_hours, default_reach, default_weeks, created_at FROM project_templates ORDER BY created_at DESC');
    $templates = $templatesStmt->fetchAll();
} catch (PDOException $e) {
    $templates = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    $name = trim($_POST['template_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $defaultBudget = $_POST['default_budget'] ?? 0;
    $defaultHours = $_POST['default_hours'] ?? 0;
    $defaultReach = $_POST['default_reach'] ?? 0;
    $defaultWeeks = $_POST['default_weeks'] ?? 0;

    if ($name !== '' && is_numeric($defaultBudget) && is_numeric($defaultHours) && is_numeric($defaultReach) && is_numeric($defaultWeeks)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO project_templates (template_name, category, description, default_budget, default_hours, default_reach, default_weeks)
                 VALUES (:name, :cat, :desc, :budget, :hours, :reach, :weeks)'
            );
            $stmt->execute([
                ':name' => $name,
                ':cat' => $category,
                ':desc' => $description,
                ':budget' => round((float) $defaultBudget, 2),
                ':hours' => (int) $defaultHours,
                ':reach' => (int) $defaultReach,
                ':weeks' => (int) $defaultWeeks,
            ]);
            $_SESSION['flash_message'] = 'Template created successfully.';
            header('Location: /dss/views/templates.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['flash_message'] = 'Error creating template: ' . $e->getMessage();
        }
    } else {
        $_SESSION['flash_message'] = 'Please fill all required fields correctly.';
    }
}
?>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">layers</i> <?= htmlspecialchars(__('templates'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext"><?= htmlspecialchars(__('create_template'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(__('projects'), ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">add_circle</i> Create Template</div>
    <form method="post" action="/dss/views/templates.php">
        <?= $csrfField ?>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="template_name" name="template_name" required maxlength="255">
                <label for="template_name">Template Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="text" id="category" name="category" required maxlength="100">
                <label for="category">Category</label>
            </div>
            <div class="input-field col s12 m4">
                <textarea id="description" name="description" class="materialize-textarea" maxlength="500"></textarea>
                <label for="description">Description</label>
            </div>
            <div class="input-field col s12 m3">
                <input type="number" id="default_budget" name="default_budget" min="0" step="0.01" required>
                <label for="default_budget">Default Budget (GHS)</label>
            </div>
            <div class="input-field col s12 m3">
                <input type="number" id="default_hours" name="default_hours" min="1" required>
                <label for="default_hours">Default Hours</label>
            </div>
            <div class="input-field col s12 m3">
                <input type="number" id="default_reach" name="default_reach" min="1" required>
                <label for="default_reach">Default Reach</label>
            </div>
            <div class="input-field col s12 m3">
                <input type="number" id="default_weeks" name="default_weeks" min="1" required>
                <label for="default_weeks">Default Weeks</label>
            </div>
            <div class="col s12" style="margin-top: 0.5rem;">
                <button type="submit" class="btn green"><i class="material-icons left">add</i>Create Template</button>
            </div>
        </div>
    </form>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">list</i> Existing Templates</div>
    <?php if ($templates === []): ?>
        <div class="center-align" style="padding: 2rem 1rem;">
            <i class="material-icons large grey-text text-lighten-2">layers</i>
            <h5 class="grey-text">No Templates</h5>
            <p class="grey-text">Create your first template above.</p>
        </div>
    <?php else: ?>
        <div class="row" style="margin-top: 1rem;">
            <?php foreach ($templates as $template): ?>
                <div class="col s12 m6 l4">
                    <div class="card-panel template-card">
                        <span class="template-category"><?= htmlspecialchars($template['category'], ENT_QUOTES, 'UTF-8') ?></span>
                        <h5 style="margin: 0.5rem 0 0.25rem; font-size: 1.1rem; color: #025928;"><?= htmlspecialchars($template['template_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <p style="margin: 0; font-size: 0.85rem; color: #6b7280;">
                            <?= htmlspecialchars($template['description'] ? mb_substr($template['description'], 0, 100) : 'No description', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p style="margin: 0.5rem 0 0; font-size: 0.85rem; color: #6b7280;">
                            <?= number_format((float) $template['default_budget'], 2) ?> GHS | <?= (int) $template['default_hours'] ?> hrs | <?= (int) $template['default_reach'] ?> students | <?= (int) $template['default_weeks'] ?> weeks
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
