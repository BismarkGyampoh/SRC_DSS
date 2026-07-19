<?php

require_once '../includes/public_header.php';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$termFilter = $_GET['term'] ?? '';
$budgetMin = $_GET['budget_min'] ?? '';
$budgetMax = $_GET['budget_max'] ?? '';

$baseQuery = "SELECT project_id, title, academic_term, budget_required, volunteer_hours, student_reach,
                     implementation_weeks, calculated_pis, submitted_by,
                     src_users.username AS submitted_by_username
              FROM projects
              INNER JOIN src_users ON src_users.user_id = projects.submitted_by
              WHERE dss_status = 'Accepted'";

$params = [];
$where = [];

if ($search !== '') {
    $where[] = '(projects.title LIKE :search OR src_users.username LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $where[] = 'projects.dss_status = :status';
    $params[':status'] = $statusFilter;
}
if ($budgetMin !== '' && is_numeric($budgetMin)) {
    $where[] = 'projects.budget_required >= :budget_min';
    $params[':budget_min'] = (float) $budgetMin;
}
if ($budgetMax !== '' && is_numeric($budgetMax)) {
    $where[] = 'projects.budget_required <= :budget_max';
    $params[':budget_max'] = (float) $budgetMax;
}
if ($termFilter !== '') {
    $where[] = 'projects.academic_term = :term';
    $params[':term'] = $termFilter;
}

$whereClause = $where !== [] ? ' AND ' . implode(' AND ', $where) : '';
$orderClause = ' ORDER BY calculated_pis DESC, projects.project_id ASC';

$acceptedStmt = $pdo->prepare($baseQuery . $whereClause . $orderClause);
foreach ($params as $key => $value) {
    $acceptedStmt->bindValue($key, $value);
}
$acceptedStmt->execute();
$acceptedProjects = $acceptedStmt->fetchAll();

$userRatings = [];
if (isset($_SESSION['user_id'])) {
    try {
        $ratingsStmt = $pdo->prepare('SELECT project_id, rating FROM project_votes WHERE user_id = :uid');
        $ratingsStmt->execute([':uid' => (int) $_SESSION['user_id']]);
        $userRatings = $ratingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) { }
}

$totalAcceptedBudget = array_reduce(
    $acceptedProjects,
    static fn(float $carry, array $project): float => $carry + (float) ($project['budget_required'] ?? 0),
    0.0
);

    $feedbackItems = [];
    try {
        $feedbackStmt = $pdo->query(
            "SELECT project_feedback.feedback_id, project_feedback.delivery_status, project_feedback.created_at,
                    projects.title, project_feedback.student_name, project_feedback.feedback_text,
                    project_feedback.student_id
             FROM project_feedback
             INNER JOIN projects ON projects.project_id = project_feedback.project_id
             WHERE projects.dss_status = 'Accepted'
             ORDER BY project_feedback.created_at DESC
             LIMIT 20"
        );
        $feedbackItems = $feedbackStmt->fetchAll();
    } catch (PDOException $e) {
        $feedbackItems = [];
    }

$deliveredCount = 0;
$partialCount = 0;
$notDeliveredCount = 0;
foreach ($feedbackItems as $item) {
    if ($item['delivery_status'] === 'Delivered') $deliveredCount++;
    elseif ($item['delivery_status'] === 'Partially Delivered') $partialCount++;
    else $notDeliveredCount++;
}
?>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">public</i> <?= htmlspecialchars(__('site_title'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext"><?= htmlspecialchars(__('dashboard'), ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">filter_list</i> Filter Projects</div>
    <form method="get" action="public_dashboard.php" style="margin-top: 1rem;">
        <div class="row">
            <div class="input-field col s12 m3">
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by title or submitter...">
                <label for="search">Keyword</label>
            </div>
            <div class="input-field col s12 m2">
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="Accepted" <?= $statusFilter === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                </select>
                <label for="status">Status</label>
            </div>
            <?php
            $termsStmt = $pdo->query("SELECT DISTINCT academic_term FROM projects WHERE dss_status = 'Accepted' ORDER BY academic_term DESC");
            $publicTerms = $termsStmt->fetchAll(PDO::FETCH_COLUMN);
            $termFilter = $_GET['term'] ?? '';
            ?>
            <div class="input-field col s12 m2">
                <select id="term" name="term">
                    <option value="">All Terms</option>
                    <?php foreach ($publicTerms as $term): ?>
                        <option value="<?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?>" <?= $termFilter === $term ? 'selected' : '' ?>><?= htmlspecialchars($term, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="term">Term</label>
            </div>
            <div class="input-field col s12 m2">
                <input type="number" id="budget_min" name="budget_min" value="<?= htmlspecialchars($budgetMin, ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01">
                <label for="budget_min">Min Budget (GHS)</label>
            </div>
            <div class="input-field col s12 m2">
                <input type="number" id="budget_max" name="budget_max" value="<?= htmlspecialchars($budgetMax, ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01">
                <label for="budget_max">Max Budget (GHS)</label>
            </div>
            <div class="col s12 m1" style="margin-top: 1.5rem;">
                <button type="submit" class="btn blue"><i class="material-icons left">search</i>Filter</button>
            </div>
        </div>
    </form>
</div>

<div class="bento-grid">
    <div class="bento-card">
        <div class="bento-card-header"><i class="material-icons">check_circle</i> Approved Projects</div>
        <div class="bento-metric"><?= count($acceptedProjects) ?></div>
        <p class="bento-subtext">Projects currently funded</p>
    </div>
    <div class="bento-card">
        <div class="bento-card-header"><i class="material-icons">payments</i> Total Budget Used (₵)</div>
        <div class="bento-metric"><?= number_format($totalAcceptedBudget, 2) ?></div>
        <p class="bento-subtext">GHS total budget allocated</p>
    </div>
    <div class="bento-card">
        <div class="bento-card-header"><i class="material-icons">feedback</i> Student Reports</div>
        <div class="bento-metric bento-metric-gold"><?= count($feedbackItems) ?></div>
        <p class="bento-subtext">Student delivery reports</p>
    </div>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">check_circle</i> <?= htmlspecialchars(__('status_accepted'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(__('projects'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext">Projects approved by the Executive Board for this term.</p>
        <div class="action-button-row" style="margin-top: 1rem;">
            <a href="/dss/controllers/action_export_csv.php?type=projects" class="btn blue"><i class="material-icons left">file_download</i>Export Projects CSV</a>
        </div>
        <?php if ($acceptedProjects === []): ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">folder_open</i>
                <h5 class="grey-text">No Approved Projects</h5>
                <p class="grey-text">No projects have been approved yet for this term.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                        <tr>
                            <th>Title</th>
                        <th>Term</th>
                        <th class="text-right">Budget (GHS)</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Students</th>
                        <th class="text-right">Weeks</th>
                        <th>Score</th>
                        <th>Submitted By</th>
                    </tr>
                </thead>
                <tbody>
                     <?php foreach ($acceptedProjects as $project): ?>
                             <tr>
                                 <td><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></td>
                             <td><?= htmlspecialchars($project['academic_term'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                             <td class="text-right"><?= number_format((float) $project['budget_required'], 2) ?></td>
                             <td class="text-right"><?= (int) $project['volunteer_hours'] ?></td>
                             <td class="text-right"><?= (int) $project['student_reach'] ?></td>
                             <td class="text-right"><?= (int) $project['implementation_weeks'] ?></td>
                             <td><?= $project['calculated_pis'] !== null ? number_format((float) $project['calculated_pis'], 4) : 'N/A' ?></td>
                             <td><?= htmlspecialchars($project['submitted_by_username'], ENT_QUOTES, 'UTF-8') ?></td>
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
            </div>
        <?php endif; ?>
    </div>

    <script>
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
    <div class="bento-card-header"><i class="material-icons">assignment</i> <?= htmlspecialchars(__('feedback'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(__('comments'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext">See what students report about project delivery.</p>
    <?php if ($feedbackItems === []): ?>
        <div class="center-align" style="padding: 3rem 1rem;">
            <i class="material-icons large grey-text text-lighten-2">feedback</i>
            <h5 class="grey-text">No Reports Yet</h5>
            <p class="grey-text">Students have not submitted delivery reports yet.</p>
        </div>
    <?php else: ?>
        <div class="bento-grid" style="margin-bottom: 1.5rem;">
            <div class="bento-card" style="text-align: center;">
                <div class="bento-metric" style="font-size: 2rem;"><?= $deliveredCount ?></div>
                <p class="bento-subtext">Delivered</p>
            </div>
            <div class="bento-card" style="text-align: center;">
                <div class="bento-metric bento-metric-gold" style="font-size: 2rem;"><?= $partialCount ?></div>
                <p class="bento-subtext">Partially Delivered</p>
            </div>
            <div class="bento-card" style="text-align: center;">
                <div class="bento-metric" style="font-size: 2rem; background: linear-gradient(135deg, #dc2626, #ef4444); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= $notDeliveredCount ?></div>
                <p class="bento-subtext">Not Delivered</p>
            </div>
        </div>
        <div class="responsive-table-shell">
        <table class="striped highlight responsive-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Student</th>
                    <th>Student ID</th>
                    <th>Comments</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedbackItems as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['delivery_status'])) ?>">
                                <?= htmlspecialchars($item['delivery_status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($item['student_name'] ?? 'Anonymous', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($item['student_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($item['feedback_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($item['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">report</i> <?= htmlspecialchars(__('feedback'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext">Tell us if a project has been delivered.</p>
    <form method="post" action="/dss/controllers/action_feedback.php" style="margin-top: 1.25rem;">
        <?= $csrfField ?>
        <div class="row">
            <div class="input-field col s12 m6">
                <select id="project_id" name="project_id" required>
                    <option value="" disabled selected>Select Approved Project</option>
                    <?php foreach ($acceptedProjects as $project): ?>
                        <option value="<?= (int) $project['project_id'] ?>">
                            #<?= (int) $project['project_id'] ?> — <?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="project_id">Project</label>
            </div>
            <div class="input-field col s12 m6">
                <select id="delivery_status" name="delivery_status" required>
                    <option value="" disabled selected>Was it delivered?</option>
                    <option value="Delivered">Delivered</option>
                    <option value="Partially Delivered">Partially Delivered</option>
                    <option value="Not Delivered">Not Delivered</option>
                </select>
                <label for="delivery_status">Delivery Status</label>
            </div>
            <div class="input-field col s12 m6">
                <input type="text" id="student_name" name="student_name">
                <label for="student_name">Your Name (Optional)</label>
            </div>
            <div class="input-field col s12 m6">
                <input type="text" id="student_id" name="student_id">
                <label for="student_id">Student ID (Optional)</label>
            </div>
            <div class="input-field col s12">
                <textarea id="feedback_text" name="feedback_text" class="materialize-textarea" required placeholder="Describe what you saw..."></textarea>
                <label for="feedback_text">Your Comments</label>
            </div>
        </div>
        <button type="submit" class="btn green"><i class="material-icons left">send</i>Submit Report</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>