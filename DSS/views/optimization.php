<?php

require_once '../includes/header.php';

echo $csrfField;

require_once __DIR__ . '/../services/model_management/AuditTrailService.php';
$auditService = new AuditTrailService($pdo);
$auditReport = $auditService->generateKnapsackReport();
$optimizationHistory = $auditService->renderOptimizationHistory();
$overrideHistory = $auditService->renderOverrideHistory();
$comparativeReport = $auditService->generateComparativeReport();

$allProjectsStmt = $pdo->query(
    "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
            implementation_weeks, calculated_pis, dss_status
     FROM projects
     WHERE dss_status IN ('Accepted', 'Rejected', 'Deferred')
     ORDER BY dss_status DESC, calculated_pis DESC, project_id ASC"
);
$allProjects = $allProjectsStmt->fetchAll();
?>

<?php if ($sessionRole !== 'Executive Board'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
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
        <form method="post" action="../controllers/model_management/action_run_engine.php">
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
        <p class="bento-subtext">Manually change a project's status with a reason. All changes are recorded with date and reason.</p>

            <?php if ($allProjects === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">gavel</i>
                    <h5 class="grey-text">No Projects to Change</h5>
                    <p class="grey-text">Run the project picker first to set project statuses.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Score</th>
                            <th>Current Status</th>
                            <th>Change To</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allProjects as $project): ?>
                            <tr>
                                <td><?= (int) $project['project_id'] ?></td>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">history</i> Change History</div>
        <p class="bento-subtext">Record of all manual changes with reasons.</p>
        <div class="responsive-html" style="margin-top: 1rem;">
            <?= $overrideHistory ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">description</i> Latest Selection Report</div>
        <div class="responsive-html">
            <?= $auditReport ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">history</i> Past Selection Reports</div>
        <div class="responsive-html">
            <?= $optimizationHistory ?>
        </div>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.override-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const projectId = this.getAttribute('data-project-id');
        const select = document.querySelector('.override-status-select[data-project-id="' + projectId + '"]');
        const reasonInput = document.querySelector('.override-reason-input[data-project-id="' + projectId + '"]');
        const newStatus = select.value;
        const reason = reasonInput.value.trim();

        if (!newStatus) {
            M.toast({html: 'Please select a new status.', classes: 'red'});
            return;
        }
        if (!reason) {
            M.toast({html: 'Please provide a reason for this change.', classes: 'red'});
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../controllers/action_override_project.php';

        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        const fields = [
            { name: 'csrf_token', value: csrfToken },
            { name: 'project_id', value: projectId },
            { name: 'new_status', value: newStatus },
            { name: 'override_reason', value: reason }
        ];

        fields.forEach(f => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = f.name;
            input.value = f.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
