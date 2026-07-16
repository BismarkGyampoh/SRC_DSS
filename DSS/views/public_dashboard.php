<?php

require_once '../includes/public_header.php';

    $acceptedStmt = $pdo->query(
        "SELECT project_id, title, academic_term, budget_required, volunteer_hours, student_reach,
                implementation_weeks, calculated_pis, submitted_by,
                src_users.username AS submitted_by_username
         FROM projects
         INNER JOIN src_users ON src_users.user_id = projects.submitted_by
         WHERE dss_status = 'Accepted'
         ORDER BY calculated_pis DESC, projects.project_id ASC"
    );
$acceptedProjects = $acceptedStmt->fetchAll();

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

<div class="bento-card">
    <div class="bento-card-header"><i class="material-icons">public</i> SRC Public Dashboard</div>
    <p class="bento-subtext">See all approved SRC projects and student delivery reports.</p>
</div>

<div class="bento-grid">
    <div class="bento-card">
        <div class="bento-card-header"><i class="material-icons">check_circle</i> Approved Projects</div>
        <div class="bento-metric"><?= count($acceptedProjects) ?></div>
        <p class="bento-subtext">Projects currently funded</p>
    </div>
    <div class="bento-card">
        <div class="bento-card-header"><i class="material-icons">attach_money</i> Total Budget Used</div>
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
    <div class="bento-card-header"><i class="material-icons">check_circle</i> Approved Projects</div>
    <p class="bento-subtext">Projects approved by the Executive Board for this term.</p>
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
                        <th>ID</th>
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
                            <td><?= (int) $project['project_id'] ?></td>
                            <td><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($project['academic_term'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right"><?= number_format((float) $project['budget_required'], 2) ?></td>
                            <td class="text-right"><?= (int) $project['volunteer_hours'] ?></td>
                            <td class="text-right"><?= (int) $project['student_reach'] ?></td>
                            <td class="text-right"><?= (int) $project['implementation_weeks'] ?></td>
                            <td><?= $project['calculated_pis'] !== null ? number_format((float) $project['calculated_pis'], 4) : 'N/A' ?></td>
                            <td><?= htmlspecialchars($project['submitted_by_username'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">assignment</i> Project Delivery Reports</div>
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
    <div class="bento-card-header"><i class="material-icons">report</i> Report a Project</div>
    <p class="bento-subtext">Tell us if a project has been delivered.</p>
    <form method="post" action="../controllers/action_feedback.php" style="margin-top: 1.25rem;">
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