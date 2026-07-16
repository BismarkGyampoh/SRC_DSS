<?php

require_once '../includes/header.php';

$deferredStmt = $pdo->query(
    "SELECT project_id, title, academic_term, budget_required, volunteer_hours, student_reach, implementation_weeks
     FROM projects
     WHERE dss_status = 'Deferred'
     ORDER BY project_id ASC"
);
$deferredProjects = $deferredStmt->fetchAll();
?>

<?php if ($sessionRole !== 'Projects Coordinator'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">forward</i> Carry-Forward Projects</div>
        <p class="bento-subtext">Good projects that did not make it into the budget this term.</p>
    </div>

    <div class="bento-card bento-span-full">
            <?php if ($deferredProjects === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">assignment_late</i>
                    <h5 class="grey-text">No Carry-Forward Projects</h5>
                    <p class="grey-text">No projects are waiting to be carried forward.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deferredProjects as $project): ?>
                            <tr>
                                <td><?= (int) $project['project_id'] ?></td>
                                <td><?= htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($project['academic_term'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-right"><?= number_format((float) $project['budget_required'], 2) ?></td>
                                <td class="text-right"><?= (int) $project['volunteer_hours'] ?></td>
                                <td class="text-right"><?= (int) $project['student_reach'] ?></td>
                                <td class="text-right"><?= (int) $project['implementation_weeks'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>