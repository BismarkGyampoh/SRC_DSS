<?php

require_once '../includes/header.php';

$templates = [];
try {
    $templatesStmt = $pdo->query(
        'SELECT template_id, template_name, category, default_budget, default_hours, default_reach, default_weeks
         FROM project_templates
         ORDER BY category, template_name'
    );
    $templates = $templatesStmt->fetchAll();
} catch (PDOException $e) {
    $templates = [];
}
?>

<?php if ($sessionRole !== 'Projects Coordinator'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">add_circle</i> Submit a Project</div>
        <p class="bento-subtext">Propose a new project for the Executive Board to review.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">layers</i> Quick-Start Templates</div>
        <p class="bento-subtext">Start quickly with ready-made project ideas. Click a template to fill in the form for you.</p>
            <?php if ($templates === []): ?>
                <p class="grey-text">No templates available right now.</p>
            <?php else: ?>
                <div class="row" style="margin-top: 1rem;">
                    <?php foreach ($templates as $template): ?>
                        <div class="col s12 m6 l4">
                            <div class="card-panel template-card" style="cursor: pointer; transition: all 0.2s;" data-template="<?= htmlspecialchars($template['template_name'], ENT_QUOTES, 'UTF-8') ?>" data-budget="<?= (float) $template['default_budget'] ?>" data-hours="<?= (int) $template['default_hours'] ?>" data-reach="<?= (int) $template['default_reach'] ?>" data-weeks="<?= (int) $template['default_weeks'] ?>">
                                <span class="template-category"><?= htmlspecialchars($template['category'], ENT_QUOTES, 'UTF-8') ?></span>
                                <h5 style="margin: 0.5rem 0 0.25rem; font-size: 1.1rem; color: #025928;"><?= htmlspecialchars($template['template_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                                <p style="margin: 0; font-size: 0.85rem; color: #6b7280;">
                                    <?= number_format((float) $template['default_budget'], 2) ?> GHS | <?= (int) $template['default_hours'] ?> hrs | <?= (int) $template['default_reach'] ?> students | <?= (int) $template['default_weeks'] ?> weeks
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">upload_file</i> Bulk Import Projects</div>
        <p class="bento-subtext">Upload a CSV file to create multiple projects at once. CSV columns: <strong>Title, Budget, Volunteer Hours, Reach, Weeks</strong></p>
        <form method="post" action="../controllers/data_management/action_bulk_import.php" enctype="multipart/form-data" style="margin-top: 1.25rem;">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m8">
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    <label for="csv_file">Select CSV File</label>
                </div>
                <div class="input-field col s12 m4" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn blue" style="width: 100%; margin-bottom: 1.5rem;">
                        <i class="material-icons left">cloud_upload</i>Import CSV
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">edit_note</i> New Project</div>
        <p class="bento-subtext">
            Projects you submit will be reviewed by the Executive Board.
            You can save as draft if you are not ready yet.
        </p>
            <form method="post" action="../controllers/action_add_project.php" enctype="multipart/form-data">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12">
                        <input type="text" id="title" name="title" required maxlength="255">
                        <label for="title">Project Title</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="budget_required"
                            name="budget_required"
                            min="0"
                            step="0.01"
                            required
                        >
                        <label for="budget_required">Budget Needed (GHS)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="volunteer_hours"
                            name="volunteer_hours"
                            min="1"
                            step="1"
                            required
                        >
                        <label for="volunteer_hours">Volunteer Hours Needed</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="student_reach"
                            name="student_reach"
                            min="1"
                            step="1"
                            required
                        >
                        <label for="student_reach">How Many Students Benefit</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="implementation_weeks"
                            name="implementation_weeks"
                            min="1"
                            step="1"
                            required
                        >
                        <label for="implementation_weeks">Weeks to Complete</label>
                    </div>
                </div>

                <h5 style="margin: 2rem 0 1rem; color: #025928;">Rate This Project (0-100)</h5>
                <p class="grey-text" style="margin-bottom: 1.5rem;">
                    Rate how relevant each category is to this project. 0 = not relevant, 100 = very relevant.
                </p>

                <div class="row">
                    <div class="input-field col s12 m6">
                        <input type="number" id="academic_alignment" name="academic_alignment" min="0" max="100" step="1" value="50">
                        <label for="academic_alignment">Academic Relevance</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="sustainability" name="sustainability" min="0" max="100" step="1" value="50">
                        <label for="sustainability">Sustainability</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="health_safety" name="health_safety" min="0" max="100" step="1" value="50">
                        <label for="health_safety">Health & Safety</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="digital_infra" name="digital_infra" min="0" max="100" step="1" value="50">
                        <label for="digital_infra">Digital Improvements</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="sports_recreation" name="sports_recreation" min="0" max="100" step="1" value="50">
                        <label for="sports_recreation">Sports & Recreation</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="hostel_welfare" name="hostel_welfare" min="0" max="100" step="1" value="50">
                        <label for="hostel_welfare">Hostel & Student Welfare</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="entrepreneurship" name="entrepreneurship" min="0" max="100" step="1" value="50">
                        <label for="entrepreneurship">Student Business & Skills</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input type="number" id="cost_efficiency" name="cost_efficiency" min="0" max="100" step="1" value="50">
                        <label for="cost_efficiency">Value for Money</label>
                    </div>
                </div>

                <div class="file-field input-field col s12" style="margin-top: 1.5rem;">
                    <div class="btn blue">
                        <span>Upload Support Document</span>
                        <input type="file" name="petition_path" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <div class="file-path-wrapper">
                        <input class="file-path validate" type="text" placeholder="Optional: Student petition or support letter">
                    </div>
                    <span class="helper-text">Optional: Add a petition or support letter to show there is demand</span>
                </div>

                <div class="action-button-row" style="margin-top: 2rem;">
                    <button type="submit" name="action" value="draft" class="btn grey"><i class="material-icons left">save</i>Save Draft</button>
                    <button type="submit" name="action" value="submit" class="btn green"><i class="material-icons left">send</i>Submit for Review</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.template-card').forEach(card => {
    card.addEventListener('click', function() {
        const title = this.getAttribute('data-template');
        const budget = this.getAttribute('data-budget');
        const hours = this.getAttribute('data-hours');
        const reach = this.getAttribute('data-reach');
        const weeks = this.getAttribute('data-weeks');

        document.getElementById('title').value = title;
        document.getElementById('budget_required').value = budget;
        document.getElementById('volunteer_hours').value = hours;
        document.getElementById('student_reach').value = reach;
        document.getElementById('implementation_weeks').value = weeks;

        document.querySelector('.bento-card-header').textContent = 'New Project — Template Loaded';
        M.toast({html: 'Template loaded: ' + title, classes: 'green'});
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
