<?php

require_once '../includes/header.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

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

$draftQuery = "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
                      implementation_weeks, dss_status
         FROM projects
         WHERE submitted_by = :uid";
$draftParams = [':uid' => (int) $_SESSION['user_id']];

if ($search !== '') {
    $draftQuery .= ' AND title LIKE :search';
    $draftParams[':search'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $draftQuery .= ' AND dss_status = :status';
    $draftParams[':status'] = $statusFilter;
}

$draftQuery .= ' ORDER BY project_id DESC';
$draftStmt = $pdo->prepare($draftQuery);
$draftStmt->execute($draftParams);
$userProjects = $draftStmt->fetchAll();

$userRatings = [];
if (isset($_SESSION['user_id'])) {
    try {
        $ratingsStmt = $pdo->prepare('SELECT project_id, rating FROM project_votes WHERE user_id = :uid');
        $ratingsStmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $userRatings = $ratingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) { }
}
?>

    <?php if ($sessionRole !== 'Projects Coordinator'): ?>
        <div class="bento-card bento-span-full">
            <div class="bento-card-header" style="color: var(--critical);"><?= __('access_restricted') ?></div>
            <p class="bento-subtext"><?= __('no_permission_desc') ?></p>
        </div>
    <?php else: ?>
        <div class="bento-card bento-span-full">
            <div class="bento-card-header"><i class="material-icons">add_circle</i> <?= __('submit_a_project') ?></div>
            <p class="bento-subtext"><?= __('propose_new_project_desc') ?></p>
        </div>

        <div class="bento-card bento-span-full">
            <div class="bento-card-header"><i class="material-icons">layers</i> <?= __('quick_start_templates') ?></div>
            <p class="bento-subtext"><?= __('start_quickly_desc') ?></p>
            <?php if ($templates === []): ?>
                <p class="grey-text"><?= __('no_templates_available') ?></p>
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
        <div class="bento-card-header"><i class="material-icons">upload_file</i> <?= __('bulk_import_projects') ?></div>
        <p class="bento-subtext"><?= __('upload_csv_desc') ?></p>
        <form method="post" action="/dss/controllers/data_management/action_bulk_import.php" enctype="multipart/form-data" style="margin-top: 1.25rem;">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m8">
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    <label for="csv_file"><?= __('select_csv_file') ?></label>
                </div>
                <div class="input-field col s12 m4" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn blue" style="width: 100%; margin-bottom: 1.5rem;">
                        <i class="material-icons left">cloud_upload</i><?= __('import_csv') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">edit_note</i> <?= __('new_project') ?></div>
        <p class="bento-subtext">
            <?= __('projects_review_desc') ?>
        </p>
            <form method="post" action="/dss/controllers/action_add_project.php" enctype="multipart/form-data">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12">
                        <input type="text" id="title" name="title" required maxlength="255">
                        <label for="title"><?= __('project_title') ?></label>
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
                        <label for="budget_required"><?= __('budget_needed') ?></label>
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
                        <label for="volunteer_hours"><?= __('volunteer_hours_needed') ?></label>
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
                        <label for="student_reach"><?= __('student_reach') ?></label>
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
                        <label for="implementation_weeks"><?= __('implementation_weeks') ?></label>
                    </div>
                </div>

                <h5 style="margin: 2rem 0 1rem; color: #025928;"><?= __('rate_this_project') ?></h5>
                <p class="grey-text" style="margin-bottom: 1.5rem;">
                    <?= __('rate_this_project_desc') ?>
                </p>

                <div class="row">
                    <?php
                    $ratingLevels = [
                        0   => __('rating_not_relevant'),
                        25  => __('rating_low'),
                        50  => __('rating_moderate'),
                        75  => __('rating_high'),
                        100 => __('rating_very_high'),
                    ];
                    $ratingFields = [
                        'academic_alignment' => __('rating_academic_relevance'),
                        'sustainability'     => __('rating_sustainability'),
                        'health_safety'      => __('rating_health_safety'),
                        'digital_infra'      => __('rating_digital_infra'),
                        'sports_recreation'  => __('rating_sports_recreation'),
                        'hostel_welfare'     => __('rating_hostel_welfare'),
                        'entrepreneurship'   => __('rating_entrepreneurship'),
                        'cost_efficiency'    => __('rating_cost_efficiency'),
                    ];
                    foreach ($ratingFields as $name => $label):
                    ?>
                        <div class="input-field col s12 m6">
                            <select name="<?= $name ?>" required>
                                <option value="" disabled><?= __('choose_rating') ?></option>
                                <?php foreach ($ratingLevels as $value => $word): ?>
                                    <option value="<?= $value ?>"<?= $value === 50 ? ' selected' : '' ?>><?= $word ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="file-field input-field col s12" style="margin-top: 1.5rem;">
                    <div class="btn blue">
                        <span><?= __('upload_support_document') ?></span>
                        <input type="file" name="petition_path" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                    <div class="file-path-wrapper">
                        <input class="file-path validate" type="text" placeholder="<?= __('optional_petition') ?>">
                    </div>
                    <span class="helper-text"><?= __('optional_petition_desc') ?></span>
                </div>

                <div class="action-button-row" style="margin-top: 2rem;">
                    <button type="submit" name="action" value="draft" class="btn grey"><i class="material-icons left">save</i><?= __('save_draft') ?></button>
                    <button type="submit" name="action" value="submit" class="btn green"><i class="material-icons left">send</i><?= __('submit_for_review') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">attach_file</i> <?= __('file_attachments') ?></div>
        <p class="bento-subtext"><?= __('upload_supporting_docs') ?></p>
        <form method="post" action="/dss/controllers/action_upload_attachment.php" enctype="multipart/form-data" style="margin-top: 1rem;">
            <?= $csrfField ?>
            <input type="hidden" name="attachment_type" value="project">
            <div class="row">
                <div class="input-field col s12 m6">
                    <select id="attach_project_id" name="project_id" required>
                        <option value="" disabled selected><?= __('choose_rating') ?></option>
                        <?php
                        $myProjectsStmt = $pdo->prepare("SELECT project_id, title, dss_status FROM projects WHERE submitted_by = :uid ORDER BY project_id DESC");
                        $myProjectsStmt->execute([':uid' => (int) $_SESSION['user_id']]);
                        $myProjects = $myProjectsStmt->fetchAll();
                        foreach ($myProjects as $p):
                        ?>
                            <option value="<?= (int) $p['project_id'] ?>">
                                #<?= (int) $p['project_id'] ?> — <?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($p['dss_status'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="attach_project_id">Project</label>
                </div>
                <div class="input-field col s12 m6">
                    <input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.txt,.zip" required>
                    <label for="attachment"><?= __('file') ?></label>
                </div>
                <div class="col s12" style="margin-top: 0.5rem;">
                    <button type="submit" class="btn blue"><i class="material-icons left">cloud_upload</i><?= __('upload_attachment') ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">link</i> <?= __('dependencies') ?></div>
        <p class="bento-subtext"><?= __('dependencies_desc') ?></p>
        <form method="post" action="/dss/controllers/action_add_dependency.php" style="margin-top: 1rem;">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m5">
                    <select id="dependency_project_id" name="project_id" required>
                        <option value="" disabled selected><?= __('select_project') ?></option>
                        <?php
                        $myProjectsStmt = $pdo->prepare("SELECT project_id, title FROM projects WHERE submitted_by = :uid ORDER BY project_id DESC");
                        $myProjectsStmt->execute([':uid' => (int) $_SESSION['user_id']]);
                        $myProjects = $myProjectsStmt->fetchAll();
                        foreach ($myProjects as $p):
                        ?>
                            <option value="<?= (int) $p['project_id'] ?>">
                                #<?= (int) $p['project_id'] ?> — <?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="dependency_project_id"><?= __('main_project') ?></label>
                </div>
                <div class="input-field col s12 m5">
                    <select id="depends_on_project_id" name="depends_on_project_id" required>
                        <option value="" disabled selected><?= __('select_dependent_project') ?></option>
                        <?php
                        $allProjectsStmt2 = $pdo->query("SELECT project_id, title FROM projects ORDER BY title ASC");
                        $allProjects2 = $allProjectsStmt2->fetchAll();
                        foreach ($allProjects2 as $p):
                        ?>
                            <option value="<?= (int) $p['project_id'] ?>">
                                #<?= (int) $p['project_id'] ?> — <?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="depends_on_project_id"><?= __('depends_on') ?></label>
                </div>
                <div class="input-field col s12 m2">
                    <select id="dependency_type" name="dependency_type" required>
                        <option value="Prerequisite" selected><?= __('prerequisite') ?></option>
                        <option value="Resource Conflict"><?= __('resource_conflict') ?></option>
                        <option value="Sequential"><?= __('sequential') ?></option>
                        <option value="Other"><?= __('other') ?></option>
                    </select>
                    <label for="dependency_type"><?= __('dependency_type') ?></label>
                </div>
                <div class="col s12" style="margin-top: 0.5rem;">
                    <button type="submit" class="btn green"><i class="material-icons left">link</i><?= __('link_projects') ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">star</i> <?= __('rate_projects') ?></div>
        <p class="bento-subtext"><?= __('rate_existing_desc') ?></p>
        <?php
        $ratingProjects = [];
        try {
            $ratingStmt = $pdo->query(
                "SELECT project_id, title, dss_status FROM projects WHERE dss_status IN ('Pending', 'Accepted') ORDER BY project_id DESC LIMIT 20"
            );
            $ratingProjects = $ratingStmt->fetchAll();
        } catch (PDOException $e) {
            $ratingProjects = [];
        }
        ?>
        <div style="margin-top: 1rem;">
            <?php if ($ratingProjects === []): ?>
                <p class="grey-text"><?= __('no_projects_to_rate') ?></p>
            <?php else: ?>
                <div class="responsive-html">
                    <table class="striped highlight">
                        <thead><tr><th><?= __('project') ?></th><th><?= __('status') ?></th><th><?= __('your_rating') ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($ratingProjects as $rp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($rp['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="status-badge status-<?= strtolower($rp['dss_status']) ?>"><?= htmlspecialchars($rp['dss_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <div class="star-rating" data-project-id="<?= (int) $rp['project_id'] ?>" data-rating="<?= $userRatings[$rp['project_id']] ?? 0 ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="material-icons star-btn" data-rating="<?= $i ?>" style="font-size: 1.2rem; color: #cbd5e1; cursor: pointer;">star</i>
                                            <?php endfor; ?>
                                            <span class="rating-text" data-project-id="<?= (int) $rp['project_id'] ?>"></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">drafts</i> <?= __('my_projects') ?></div>
        <p class="bento-subtext"><?= __('manage_projects_desc') ?></p>
        <form method="get" action="proposal.php" style="margin-top: 1rem;">
            <div class="row">
                <div class="input-field col s12 m5">
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= __('search_my_projects') ?>">
                    <label for="search"><?= __('keyword') ?></label>
                </div>
                <div class="input-field col s12 m4">
                    <select id="status" name="status">
                        <option value=""><?= __('all_statuses') ?></option>
                        <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Deferred" <?= $statusFilter === 'Deferred' ? 'selected' : '' ?>>Deferred</option>
                    </select>
                    <label for="status"><?= __('status') ?></label>
                </div>
                <div class="col s12 m3" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn blue"><i class="material-icons left">search</i><?= __('filter') ?></button>
                </div>
            </div>
        </form>
        <?php if ($userProjects === []): ?>
            <div class="center-align" style="padding: 2rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">inbox</i>
                <h5 class="grey-text"><?= __('no_projects_yet') ?></h5>
                <p class="grey-text"><?= __('no_projects_desc') ?></p>
            </div>
        <?php else: ?>
            <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th><?= __('title') ?></th>
                            <th><?= __('budget_gHS') ?></th>
                            <th><?= __('hours') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('submitted') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userProjects as $project): ?>
                            <tr>
                                <td><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= number_format((float) $project['budget_required'], 2) ?></td>
                                <td><?= (int) $project['volunteer_hours'] ?></td>
                                <td><span class="status-badge status-<?= strtolower($project['dss_status']) ?>"><?= htmlspecialchars($project['dss_status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>N/A</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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

document.querySelectorAll('.star-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const rating = parseInt(this.getAttribute('data-rating') || '0', 10);
        const container = this.closest('.star-rating');
        const projectId = container ? container.getAttribute('data-project-id') : '';
        if (!projectId || !rating) return;
        const currentRating = parseInt((container ? container.dataset.rating : '0') || '0', 10);
        const isUnrate = currentRating === rating;
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('project_id', projectId);
        fd.append('rating', rating);
        fd.append('action', isUnrate ? 'delete' : 'rate');
        fetch('/dss/controllers/action_vote.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const newRating = isUnrate ? 0 : rating;
                    container.dataset.rating = newRating;
                    M.toast({html: isUnrate ? 'Rating removed.' : 'Rated ' + rating + ' stars. Average: ' + data.average, classes: 'green'});
                    const stars = container.querySelectorAll('.star-btn');
                    stars.forEach(function(s, idx) {
                        s.style.color = idx < newRating ? 'var(--accent-gold)' : '#cbd5e1';
                    });
                    const text = container.querySelector('.rating-text');
                    if (text) text.textContent = isUnrate ? '' : '(' + data.count + ' votes)';
                } else {
                    M.toast({html: data.message || 'Could not save rating.', classes: 'red'});
                }
            })
            .catch(() => M.toast({html: 'Network error.', classes: 'red'}));
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
