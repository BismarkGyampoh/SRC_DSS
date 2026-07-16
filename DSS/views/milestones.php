<?php

require_once '../includes/header.php';

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;

if ($projectId > 0) {
    $projectStmt = $pdo->prepare(
        'SELECT project_id, title, dss_status FROM projects WHERE project_id = :id'
    );
    $projectStmt->execute([':id' => $projectId]);
    $project = $projectStmt->fetch();

    if (!$project) {
        $project = null;
    }

    $milestonesStmt = $pdo->prepare(
        'SELECT milestone_id, milestone_name, target_date, status, created_at
         FROM project_milestones
         WHERE project_id = :id
         ORDER BY target_date ASC, milestone_id ASC'
    );
    $milestonesStmt->execute([':id' => $projectId]);
    $milestones = $milestonesStmt->fetchAll();
} else {
    $project = null;
    $milestones = [];
}
?>

<?php if ($sessionRole !== 'Projects Coordinator' && $sessionRole !== 'Executive Board' && $sessionRole !== 'Admin'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">flag</i> Project Milestones</div>
        <p class="bento-subtext">Track progress and milestones for SRC projects.</p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">search</i> Select Project</div>
        <p class="bento-subtext">Choose a project to view its milestones.</p>
        <form method="get" action="milestones.php" style="margin-top: 1rem;">
            <div class="row">
                <div class="input-field col s12 m8">
                    <select id="project_id" name="project_id" required onchange="this.form.submit()">
                        <option value="" disabled selected>Select a Project</option>
                        <?php
                        $allProjectsStmt = $pdo->query(
                            "SELECT project_id, title, dss_status FROM projects ORDER BY dss_status, project_id ASC"
                        );
                        $allProjects = $allProjectsStmt->fetchAll();
                        foreach ($allProjects as $p):
                        ?>
                            <option value="<?= (int) $p['project_id'] ?>" <?= $projectId === (int) $p['project_id'] ? 'selected' : '' ?>>
                                #<?= (int) $p['project_id'] ?> — <?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($p['dss_status'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="project_id">Project</label>
                </div>
            </div>
        </form>
    </div>

    <?php if ($project === null && $projectId > 0): ?>
        <div class="bento-card bento-span-full">
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">search_off</i>
                <h5 class="grey-text">Project Not Found</h5>
                <p class="grey-text">The selected project does not exist.</p>
            </div>
        </div>
    <?php elseif ($project !== null): ?>
        <div class="bento-card bento-span-full">
            <div class="bento-card-header"><i class="material-icons">assignment</i> <?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <p class="bento-subtext">Milestones for Project #<?= (int) $project['project_id'] ?> (<?= htmlspecialchars($project['dss_status'], ENT_QUOTES, 'UTF-8') ?>)</p>
            <?php if ($milestones === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">flag</i>
                    <h5 class="grey-text">No Milestones</h5>
                    <p class="grey-text">No milestones have been added for this project yet.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Milestone</th>
                            <th>Target Date</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($milestones as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['milestone_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($m['target_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $m['status'])) ?>">
                                        <?= htmlspecialchars($m['status'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($m['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
