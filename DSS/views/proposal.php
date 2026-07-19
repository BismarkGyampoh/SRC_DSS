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
        <form method="post" action="/dss/controllers/data_management/action_bulk_import.php" enctype="multipart/form-data" style="margin-top: 1.25rem;">
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
            <form method="post" action="/dss/controllers/action_add_project.php" enctype="multipart/form-data">
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

                <h5 style="margin: 2rem 0 1rem; color: #025928;">Rate This Project</h5>
                <p class="grey-text" style="margin-bottom: 1.5rem;">
                    For each category, choose how relevant it is to this project. Not Relevant = 0, Very High = 100.
                </p>

                <div class="row">
                    <?php
                    $ratingLevels = [
                        0   => 'Not Relevant',
                        25  => 'Low',
                        50  => 'Moderate',
                        75  => 'High',
                        100 => 'Very High',
                    ];
                    $ratingFields = [
                        'academic_alignment' => 'Academic Relevance',
                        'sustainability'     => 'Sustainability',
                        'health_safety'      => 'Health & Safety',
                        'digital_infra'      => 'Digital Improvements',
                        'sports_recreation'  => 'Sports & Recreation',
                        'hostel_welfare'     => 'Hostel & Student Welfare',
                        'entrepreneurship'   => 'Student Business & Skills',
                        'cost_efficiency'    => 'Value for Money',
                    ];
                    foreach ($ratingFields as $name => $label):
                    ?>
                        <div class="input-field col s12 m6">
                            <select name="<?= $name ?>" required>
                                <option value="" disabled>Choose rating</option>
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

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">attach_file</i> File Attachments</div>
        <p class="bento-subtext">Upload supporting documents for your projects.</p>
        <form method="post" action="/dss/controllers/action_upload_attachment.php" enctype="multipart/form-data" style="margin-top: 1rem;">
            <?= $csrfField ?>
            <input type="hidden" name="attachment_type" value="project">
            <div class="row">
                <div class="input-field col s12 m6">
                    <select id="attach_project_id" name="project_id" required>
                        <option value="" disabled selected>Select Your Project</option>
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
                    <label for="attachment">File</label>
                </div>
                <div class="col s12" style="margin-top: 0.5rem;">
                    <button type="submit" class="btn blue"><i class="material-icons left">cloud_upload</i>Upload Attachment</button>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">link</i> Project Dependencies</div>
        <p class="bento-subtext">Link projects that depend on each other.</p>
        <form method="post" action="/dss/controllers/action_add_dependency.php" style="margin-top: 1rem;">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m5">
                    <select id="dependency_project_id" name="project_id" required>
                        <option value="" disabled selected>Select Your Project</option>
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
                    <label for="dependency_project_id">Main Project</label>
                </div>
                <div class="input-field col s12 m5">
                    <select id="depends_on_project_id" name="depends_on_project_id" required>
                        <option value="" disabled selected>Select Dependent Project</option>
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
                    <label for="depends_on_project_id">Depends On</label>
                </div>
                <div class="input-field col s12 m2">
                    <select id="dependency_type" name="dependency_type" required>
                        <option value="Prerequisite" selected>Prerequisite</option>
                        <option value="Resource Conflict">Resource Conflict</option>
                        <option value="Sequential">Sequential</option>
                        <option value="Other">Other</option>
                    </select>
                    <label for="dependency_type">Type</label>
                </div>
                <div class="col s12" style="margin-top: 0.5rem;">
                    <button type="submit" class="btn green"><i class="material-icons left">link</i>Link Projects</button>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">star</i> Rate Projects</div>
        <p class="bento-subtext">Rate existing projects to help prioritize.</p>
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
                <p class="grey-text">No projects available to rate.</p>
            <?php else: ?>
                <div class="responsive-html">
                    <table class="striped highlight">
                        <thead><tr><th>Project</th><th>Status</th><th>Your Rating</th></tr></thead>
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
        <div class="bento-card-header"><i class="material-icons">drafts</i> My Projects</div>
        <p class="bento-subtext">View and manage your submitted projects and drafts.</p>
        <form method="get" action="proposal.php" style="margin-top: 1rem;">
            <div class="row">
                <div class="input-field col s12 m5">
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search my projects...">
                    <label for="search">Keyword</label>
                </div>
                <div class="input-field col s12 m4">
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Deferred" <?= $statusFilter === 'Deferred' ? 'selected' : '' ?>>Deferred</option>
                    </select>
                    <label for="status">Status</label>
                </div>
                <div class="col s12 m3" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn blue"><i class="material-icons left">search</i>Filter</button>
                </div>
            </div>
        </form>
        <?php if ($userProjects === []): ?>
            <div class="center-align" style="padding: 2rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">inbox</i>
                <h5 class="grey-text">No Projects Yet</h5>
                <p class="grey-text">You have not submitted any projects yet.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Budget (GHS)</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Submitted</th>
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
