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
        <div class="bento-card-header" style="color: var(--critical);"><?= __('access_restricted') ?></div>
        <p class="bento-subtext"><?= __('no_permission_desc') ?></p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">forward</i> <?= __('carry_forward_projects') ?></div>
        <p class="bento-subtext">Good projects that did not make it into the budget this term.</p>
    </div>

    <div class="bento-card bento-span-full">
            <?php if ($deferredProjects === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">assignment_late</i>
                    <h5 class="grey-text"><?= __('no_carry_forward_projects') ?></h5>
                    <p class="grey-text">No projects are waiting to be carried forward.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th><?= __('project') ?></th>
                            <th><?= __('term') ?></th>
                            <th class="text-right"><?= __('budget_gHS') ?></th>
                            <th class="text-right"><?= __('hours') ?></th>
                            <th class="text-right"><?= __('students') ?></th>
                            <th class="text-right"><?= __('implementation_weeks') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deferredProjects as $project): ?>
                            <tr>
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