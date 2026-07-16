<?php

require_once '../includes/header.php';

$projectsStmt = $pdo->query(
    "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
            implementation_weeks, calculated_pis, dss_status,
            src_users.username AS submitted_by_username
     FROM projects
     INNER JOIN src_users ON src_users.user_id = projects.submitted_by
     WHERE dss_status = 'Accepted'
     ORDER BY title ASC"
);
$approvedProjects = $projectsStmt->fetchAll();
?>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">report</i> Report a Project</div>
    <p class="bento-subtext">
        Tell us if an approved project has been delivered. Your report helps the SRC track what is happening on campus.
    </p>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">send</i> Submit Delivery Report</div>
    <p class="bento-subtext">Your report helps keep SRC accountable to students.</p>

        <form method="post" action="../controllers/action_feedback.php">
            <?= $csrfField ?>
            <div class="row">
                <div class="input-field col s12 m6">
                    <select id="project_id" name="project_id" required>
                        <option value="" disabled selected>Select Approved Project</option>
                        <?php foreach ($approvedProjects as $project): ?>
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
</div>

<?php require_once '../includes/footer.php'; ?>