<?php

require_once '../includes/header.php';

requireRole(['Financial Secretary', 'Projects Coordinator', 'Executive Board', 'Faculty Representative', 'Student Representative']);

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
    <div class="bento-card-header"><i class="material-icons">report</i> <?= htmlspecialchars(__('proposal'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext">
        Tell us if an approved project has been delivered. Your report helps the SRC track what is happening on campus.
    </p>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">send</i> <?= htmlspecialchars(__('submit'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(__('feedback'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext">Your report helps keep SRC accountable to students.</p>

        <form method="post" action="/dss/controllers/action_feedback.php">
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

        <form method="post" action="/dss/controllers/action_upload_attachment.php" enctype="multipart/form-data" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
            <?= $csrfField ?>
            <input type="hidden" name="attachment_type" value="feedback">
            <div class="bento-card-header" style="margin-bottom: 1rem;"><i class="material-icons">attach_file</i> Attach Evidence</div>
            <div class="row">
                <div class="input-field col s12 m6">
                    <select id="feedback_id" name="feedback_id" required>
                        <option value="" disabled selected>Select Your Report</option>
                        <?php
                        $myFeedbackStmt = $pdo->prepare(
                            "SELECT feedback_id, feedback_text, created_at FROM project_feedback
                             WHERE student_id = :sid OR student_name = :sname
                             ORDER BY created_at DESC LIMIT 20"
                        );
                        $myFeedbackStmt->execute([':sid' => $_SESSION['user_id'], ':sname' => $profileName]);
                        $myFeedback = $myFeedbackStmt->fetchAll();
                        foreach ($myFeedback as $fb):
                        ?>
                            <option value="<?= (int) $fb['feedback_id'] ?>">
                                #<?= (int) $fb['feedback_id'] ?> — <?= htmlspecialchars(mb_substr($fb['feedback_text'], 0, 60), ENT_QUOTES, 'UTF-8') ?>...
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="feedback_id">Link to Report</label>
                </div>
                <div class="input-field col s12 m6">
                    <input type="file" id="feedback_attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.txt,.zip" required>
                    <label for="feedback_attachment">Evidence File</label>
                </div>
                <div class="col s12" style="margin-top: 0.5rem;">
                    <button type="submit" class="btn blue"><i class="material-icons left">cloud_upload</i>Upload Evidence</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>