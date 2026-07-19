<?php

require_once '../includes/header.php';

requireRole(['Executive Board']);

echo $csrfField;

if ($sessionRole !== 'Executive Board') {
    echo '<div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>';
    require_once '../includes/footer.php';
    exit();
}

try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN actual_budget DECIMAL(12,2) NULL DEFAULT NULL AFTER budget_required");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN budget_variance DECIMAL(12,2) NULL DEFAULT NULL AFTER actual_budget");
} catch (PDOException $e) { /* column already exists */ }
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN actual_volunteer_hours INT NULL DEFAULT NULL AFTER volunteer_hours");
} catch (PDOException $e) { /* column already exists */ }

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$termFilter = $_GET['term'] ?? '';
$budgetMin = $_GET['budget_min'] ?? '';
$budgetMax = $_GET['budget_max'] ?? '';

require_once __DIR__ . '/../services/model_management/AuditTrailService.php';
$auditService = new AuditTrailService($pdo);
$auditReport = $auditService->generateKnapsackReport();
$optimizationHistory = $auditService->renderOptimizationHistory();
$overrideHistory = $auditService->renderOverrideHistory();
$comparativeReport = $auditService->generateComparativeReport();

$baseQuery = "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
                     implementation_weeks, calculated_pis, dss_status, academic_term,
                     actual_budget, budget_variance, actual_volunteer_hours
               FROM projects
               WHERE dss_status IN ('Accepted', 'Rejected', 'Deferred')";

$params = [];
$where = [];

if ($search !== '') {
    $where[] = 'title LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $where[] = 'dss_status = :status';
    $params[':status'] = $statusFilter;
}
if ($termFilter !== '') {
    $where[] = 'academic_term = :term';
    $params[':term'] = $termFilter;
}
if ($budgetMin !== '' && is_numeric($budgetMin)) {
    $where[] = 'budget_required >= :budget_min';
    $params[':budget_min'] = (float) $budgetMin;
}
if ($budgetMax !== '' && is_numeric($budgetMax)) {
    $where[] = 'budget_required <= :budget_max';
    $params[':budget_max'] = (float) $budgetMax;
}

$whereClause = $where !== [] ? ' AND ' . implode(' AND ', $where) : '';
$orderClause = ' ORDER BY dss_status DESC, calculated_pis DESC, project_id ASC';

$allProjectsStmt = $pdo->prepare($baseQuery . $whereClause . $orderClause);
foreach ($params as $key => $value) {
    $allProjectsStmt->bindValue($key, $value);
}
$allProjectsStmt->execute();
$allProjects = $allProjectsStmt->fetchAll();

$termsStmt = $pdo->query("SELECT DISTINCT academic_term FROM projects ORDER BY academic_term DESC");
$terms = $termsStmt->fetchAll(PDO::FETCH_COLUMN);

$userRatings = [];
if (isset($_SESSION['user_id'])) {
    try {
        $ratingsStmt = $pdo->prepare('SELECT project_id, rating FROM project_votes WHERE user_id = :uid');
        $ratingsStmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $userRatings = $ratingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) { }
}
?>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">filter_list</i> Advanced Filters</div>
        <p class="bento-subtext">Filter projects by keyword, status, term, or budget range.</p>
        <form method="get" action="optimization.php" style="margin-top: 1rem;">
            <div class="row">
                <div class="input-field col s12 m3">
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search projects...">
                    <label for="search">Keyword</label>
                </div>
                <div class="input-field col s12 m2">
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Deferred" <?= $statusFilter === 'Deferred' ? 'selected' : '' ?>>Deferred</option>
                    </select>
                    <label for="status">Status</label>
                </div>
                <div class="input-field col s12 m2">
                    <select id="term" name="term">
                        <option value="">All Terms</option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?>" <?= $termFilter === $term ? 'selected' : '' ?>>
                                <?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="term">Academic Term</label>
                </div>
                <div class="input-field col s12 m2">
                    <input type="number" id="budget_min" name="budget_min" value="<?= htmlspecialchars($budgetMin, ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01">
                    <label for="budget_min">Min Budget (GHS)</label>
                </div>
                <div class="input-field col s12 m2">
                    <input type="number" id="budget_max" name="budget_max" value="<?= htmlspecialchars($budgetMax, ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01">
                    <label for="budget_max">Max Budget (GHS)</label>
                </div>
                <div class="col s12" style="margin-top: 0.5rem;">
                    <button type="submit" class="btn blue"><i class="material-icons left">search</i>Apply Filters</button>
                    <?php if ($search !== '' || $statusFilter !== '' || $termFilter !== '' || $budgetMin !== '' || $budgetMax !== ''): ?>
                        <a href="optimization.php" class="btn grey" style="margin-left: 0.5rem;"><i class="material-icons left">clear</i>Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">check_circle</i> Project Selection Tool</div>
        <p class="bento-subtext">Run the project picker and review current and past selection reports.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">shield</i> Smart Risk Checker</div>
        <p class="bento-subtext">Checks all pending projects for common risks and flags them before you pick projects.</p>

            <button type="button" class="btn purple" id="runAiBtn"><i class="material-icons left">auto_fix_high</i>Run Smart Checker</button>

            <div id="aiResult" style="margin-top: 1.5rem; display: none;"></div>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">play_circle</i> Run Project Picker</div>
        <p class="bento-subtext">
            Scores all projects and picks the best ones within your budget and volunteer limits.
        </p>
        <form method="post" action="/dss/controllers/model_management/action_run_engine.php">
            <?= $csrfField ?>
            <button type="submit" class="btn green"><i class="material-icons left">play_arrow</i>Run Project Picker</button>
        </form>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">compare</i> Compare System Pick vs Manual Pick</div>
        <p class="bento-subtext">See what the system picked compared to what remains.</p>
        <div class="responsive-html">
            <?= $comparativeReport ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">edit</i> Manual Change Tool</div>
        <p class="bento-subtext">Manually change projects' statuses with a reason. All changes are recorded with date and reason.</p>
        <div style="margin-top: 0.75rem;">
            <a href="/dss/controllers/action_export_csv.php?type=projects" class="btn grey"><i class="material-icons left">download</i>Export Projects CSV</a>
            <a href="/dss/controllers/action_export_csv.php?type=overrides" class="btn grey" style="margin-left: 0.5rem;"><i class="material-icons left">download</i>Export Overrides CSV</a>
        </div>

            <?php if ($allProjects === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">gavel</i>
                    <h5 class="grey-text">No Projects to Change</h5>
                    <p class="grey-text">Run the project picker first to set project statuses.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <form id="bulkOverrideForm">
                    <?= $csrfField ?>
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th style="width: 36px;"><input type="checkbox" id="selectAllProjects" title="Select All"></th>
                                <th>Title</th>
                                <th>Score</th>
                                <th>Current Status</th>
                                <th>Change To</th>
                                <th>Reason</th>
                                <th>Action</th>
                                <th>Comments</th>
                                <th>Rating</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allProjects as $project): ?>
                            <tr>
                                <td><input type="checkbox" class="project-checkbox" name="project_ids[]" value="<?= (int) $project['project_id'] ?>"></td>
                                <td><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $project['calculated_pis'] !== null ? number_format((float) $project['calculated_pis'], 4) : 'N/A' ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($project['dss_status']) ?>">
                                            <?= htmlspecialchars($project['dss_status'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <select class="override-status-select" data-project-id="<?= (int) $project['project_id'] ?>">
                                            <option value="">-- Select --</option>
                                            <option value="Accepted">Accepted</option>
                                            <option value="Rejected">Rejected</option>
                                            <option value="Deferred">Deferred</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="override-reason-input" data-project-id="<?= (int) $project['project_id'] ?>" placeholder="Reason required" style="width: 100%;">
                                    </td>
                                    <td>
                                        <button type="button" class="btn orange override-btn" data-project-id="<?= (int) $project['project_id'] ?>" style="padding: 0 0.75rem; height: 32px; line-height: 32px; font-size: 0.8rem;"><i class="material-icons left">edit</i>Change</button>
                                    </td>
                                    <td>
                                        <button type="button" class="btn grey comment-toggle-btn" data-project-id="<?= (int) $project['project_id'] ?>" style="padding: 0 0.5rem; height: 32px; line-height: 32px; font-size: 0.75rem;"><i class="material-icons left">comment</i>Comment</button>
                                    </td>
                                    <td>
                                        <div class="star-rating" data-project-id="<?= (int) $project['project_id'] ?>" data-rating="<?= $userRatings[$project['project_id']] ?? 0 ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="material-icons star-btn" data-rating="<?= $i ?>" style="font-size: 1.2rem; color: #cbd5e1; cursor: pointer;">star</i>
                                            <?php endfor; ?>
                                            <span class="rating-text" data-project-id="<?= (int) $project['project_id'] ?>"></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="button" class="btn red" id="bulkOverrideBtn"><i class="material-icons left">playlist_add_check</i>Bulk Change Selected</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">comment</i> Project Comments</div>
        <p class="bento-subtext">Discuss projects with other board members.</p>
        <form method="post" action="/dss/controllers/action_add_comment.php" style="margin-top: 1rem;" id="globalCommentForm">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m4">
                    <select id="comment_project_id" name="project_id" required>
                        <option value="" disabled selected>Select Project</option>
                        <?php foreach ($allProjects as $project): ?>
                            <option value="<?= (int) $project['project_id'] ?>">#<?= (int) $project['project_id'] ?> — <?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="comment_project_id">Project</label>
                </div>
                <div class="input-field col s12 m6">
                    <textarea id="comment_text" name="comment_text" class="materialize-textarea" required placeholder="Write a comment..."></textarea>
                    <label for="comment_text">Comment</label>
                </div>
                <div class="input-field col s12 m2" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn blue" style="width: 100%; margin-bottom: 1.5rem;"><i class="material-icons left">send</i>Post</button>
                </div>
            </div>
        </form>
        <?php
        try {
            $commentsStmt = $pdo->query(
                "SELECT c.comment_id, c.project_id, c.comment_text, c.parent_comment_id, c.created_at,
                        src_users.username, src_users.display_name, src_users.user_role
                 FROM project_comments c
                 INNER JOIN src_users ON src_users.user_id = c.user_id
                 ORDER BY c.created_at DESC
                 LIMIT 20"
            );
            $comments = $commentsStmt->fetchAll();
        } catch (PDOException $e) {
            $comments = [];
        }
        ?>
        <div style="margin-top: 1rem;">
            <?php if ($comments === []): ?>
                <p class="grey-text">No comments yet. Start the discussion!</p>
            <?php else: ?>
                <div class="responsive-html">
                    <table class="striped highlight">
                        <thead><tr><th>Project</th><th>User</th><th>Comment</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php foreach ($comments as $comment): ?>
                                <tr>
                                    <td>#<?= (int) $comment['project_id'] ?></td>
                                    <td><?= htmlspecialchars($comment['display_name'] ?? $comment['username'], ENT_QUOTES, 'UTF-8') ?> <small class="grey-text">(<?= htmlspecialchars($comment['user_role'], ENT_QUOTES, 'UTF-8') ?>)</small></td>
                                    <td><?= nl2br(htmlspecialchars($comment['comment_text'], ENT_QUOTES, 'UTF-8')) ?></td>
                                    <td><?= date('M j, g:i a', strtotime($comment['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">star</i> Project Ratings</div>
        <p class="bento-subtext">Rate projects to help prioritize the best ones.</p>
        <?php
        try {
            $votesStmt = $pdo->query(
                "SELECT v.project_id, p.title, AVG(v.rating) as avg_rating, COUNT(*) as vote_count
                 FROM project_votes v
                 INNER JOIN projects p ON p.project_id = v.project_id
                 GROUP BY v.project_id
                 ORDER BY avg_rating DESC, vote_count DESC
                 LIMIT 20"
            );
            $votes = $votesStmt->fetchAll();
        } catch (PDOException $e) {
            $votes = [];
        }
        ?>
        <div style="margin-top: 1rem;">
            <?php if ($votes === []): ?>
                <p class="grey-text">No ratings yet. Be the first to rate!</p>
            <?php else: ?>
                <div class="responsive-html">
                    <table class="striped highlight">
                        <thead><tr><th>Project</th><th>Average Rating</th><th>Votes</th></tr></thead>
                        <tbody>
                            <?php foreach ($votes as $vote): ?>
                                <tr>
                                    <td><?= htmlspecialchars($vote['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="material-icons" style="font-size: 1.1rem; color: <?= $i <= round((float) $vote['avg_rating']) ? 'var(--accent-gold)' : '#cbd5e1' ?>;">star</i>
                                        <?php endfor; ?>
                                        <span style="margin-left: 0.5rem; font-weight: 600;"><?= number_format((float) $vote['avg_rating'], 1) ?></span>
                                    </td>
                                    <td><?= (int) $vote['vote_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<script>
document.querySelectorAll('.override-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const projectId = this.getAttribute('data-project-id');
        const select = document.querySelector('.override-status-select[data-project-id="' + projectId + '"]');
        const reasonInput = document.querySelector('.override-reason-input[data-project-id="' + projectId + '"]');
        const newStatus = select ? select.value : '';
        const reason = reasonInput ? reasonInput.value.trim() : '';

        if (!newStatus) {
            M.toast({html: 'Please select a new status.', classes: 'red'});
            return;
        }
        if (!reason) {
            M.toast({html: 'Please provide a reason for this change.', classes: 'red'});
            return;
        }

        const btnEl = this;
        const originalText = btnEl.textContent;
        btnEl.textContent = 'Saving...';
        btnEl.disabled = true;

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('project_id', projectId);
        formData.append('new_status', newStatus);
        formData.append('override_reason', reason);

        fetch('/dss/controllers/action_override_project.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                M.toast({html: 'Project #' + projectId + ' updated to ' + newStatus + '.', classes: 'green'});
                setTimeout(() => location.reload(), 700);
            } else {
                M.toast({html: data.message || 'Could not save change.', classes: 'red'});
            }
        })
        .catch(err => {
            M.toast({html: 'Could not save change. Please try again.', classes: 'red'});
        })
        .finally(() => {
            btnEl.textContent = originalText;
            btnEl.disabled = false;
        });
    });
});

document.getElementById('selectAllProjects')?.addEventListener('change', function() {
    document.querySelectorAll('.project-checkbox').forEach(cb => cb.checked = this.checked);
});

document.getElementById('bulkOverrideBtn')?.addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('.project-checkbox:checked');
    if (checkboxes.length === 0) {
        M.toast({html: 'Select at least one project.', classes: 'red'});
        return;
    }
    const newStatus = prompt('Enter new status (Accepted, Rejected, or Deferred):');
    if (!newStatus) return;
    const validStatuses = ['Accepted', 'Rejected', 'Deferred'];
    if (!validStatuses.includes(newStatus)) {
        M.toast({html: 'Invalid status.', classes: 'red'});
        return;
    }
    const reason = prompt('Enter reason for bulk change:');
    if (!reason) return;

    const form = document.getElementById('bulkOverrideForm');
    const fd = new FormData(form);
    fd.append('new_status', newStatus);
    fd.append('override_reason', reason);

        fetch('/dss/controllers/action_bulk_override.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            M.toast({html: data.message, classes: 'green'});
            setTimeout(() => location.reload(), 700);
        } else {
            M.toast({html: data.message, classes: 'red'});
        }
    })
    .catch(() => M.toast({html: 'Network error.', classes: 'red'}));
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

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">attach_money</i> Budget & Volunteer Tracking</div>
        <p class="bento-subtext">Track actual spending and volunteer hours against plans.</p>
        <form method="post" action="/dss/controllers/action_update_project_tracking.php" style="margin-top: 1rem;">
            <?= $csrfField ?>
            <div class="responsive-table-shell">
                <table class="striped highlight">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Budget Required</th>
                            <th>Actual Budget</th>
                            <th>Budget Variance</th>
                            <th>Planned Hours</th>
                            <th>Actual Hours</th>
                            <th>Save</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProjects as $project): ?>
                            <?php
                                $actualBudget = $project['actual_budget'] ?? '';
                                $budgetVariance = $project['budget_variance'] ?? '';
                                $actualHours = $project['actual_volunteer_hours'] ?? '';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= number_format((float) $project['budget_required'], 2) ?> GHS</td>
                                <td><input type="number" class="tracking-input" name="actual_budget[<?= (int) $project['project_id'] ?>]" value="<?= $actualBudget === '' ? '' : number_format($actualBudget, 2, '.', '') ?>" step="0.01"></td>
                                <td><input type="number" class="tracking-input" name="budget_variance[<?= (int) $project['project_id'] ?>]" value="<?= $budgetVariance === '' ? '' : number_format($budgetVariance, 2, '.', '') ?>" step="0.01"></td>
                                <td><?= (int) $project['volunteer_hours'] ?> hrs</td>
                                <td><input type="number" class="tracking-input" name="actual_volunteer_hours[<?= (int) $project['project_id'] ?>]" value="<?= $actualHours === '' ? '' : $actualHours ?>"></td>
                                <td>
                                    <button type="submit" name="project_id" value="<?= (int) $project['project_id'] ?>" class="btn green" style="padding: 0 0.75rem; height: 32px; line-height: 32px; font-size: 0.8rem;"><i class="material-icons left">save</i>Save</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
<?php require_once '../includes/footer.php'; ?>
