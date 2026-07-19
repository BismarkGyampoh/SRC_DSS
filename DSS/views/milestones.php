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
        <div class="bento-card-header"><i class="material-icons">flag</i> <?= htmlspecialchars(__('milestones'), ENT_QUOTES, 'UTF-8') ?></div>
        <p class="bento-subtext"><?= htmlspecialchars(__('tracking'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(__('projects'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">search</i> Select Project</div>
        <p class="bento-subtext">Choose a project to view its milestones.</p>
        <form method="get" action="/dss/views/milestones.php" style="margin-top: 1rem;">
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

                <div style="margin-top: 2rem;">
                    <h5 style="color: #025928; margin-bottom: 1rem;"><i class="material-icons left">calendar_today</i>Calendar View</h5>
                    <?php
                    $month = $_GET['cal_month'] ?? date('Y-m');
                    $monthStart = new DateTime($month . '-01');
                    $monthEnd = clone $monthStart;
                    $monthEnd->modify('last day of this month');
                    $daysInMonth = (int) $monthEnd->format('t');
                    $firstDayOfWeek = (int) $monthStart->format('w');
                    $prevMonth = clone $monthStart;
                    $prevMonth->modify('first day of previous month');
                    $nextMonth = clone $monthStart;
                    $nextMonth->modify('first day of next month');
                    $milestoneDates = [];
                    foreach ($milestones as $m) {
                        if ($m['target_date']) {
                            $d = new DateTime($m['target_date']);
                            if ($d->format('Y-m') === $month) {
                                $milestoneDates[(int) $d->format('j')][] = $m;
                            }
                        }
                    }
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                        <a href="?project_id=<?= (int) $projectId ?>&cal_month=<?= $prevMonth->format('Y-m') ?>" class="btn grey" style="padding: 0 0.75rem; height: 32px; line-height: 32px; font-size: 0.8rem;"><i class="material-icons left">chevron_left</i>Prev</a>
                        <strong style="font-size: 1.1rem;"><?= $monthStart->format('F Y') ?></strong>
                        <a href="?project_id=<?= (int) $projectId ?>&cal_month=<?= $nextMonth->format('Y-m') ?>" class="btn grey" style="padding: 0 0.75rem; height: 32px; line-height: 32px; font-size: 0.8rem;">Next<i class="material-icons right">chevron_right</i></a>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; background: var(--border-color); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color);">
                        <?php
                        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        foreach ($dayNames as $dayName):
                        ?>
                            <div style="background: var(--bg-surface); padding: 0.5rem; text-align: center; font-weight: 700; font-size: 0.8rem; color: var(--text-secondary);"><?= $dayName ?></div>
                        <?php endforeach; ?>
                        <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                            <div style="background: var(--bg-surface); padding: 0.75rem; min-height: 60px;"></div>
                        <?php endfor; ?>
                        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                            <?php $hasMilestones = isset($milestoneDates[$day]); ?>
                            <div style="background: var(--bg-surface); padding: 0.5rem; min-height: 60px; position: relative; cursor: <?= $hasMilestones ? 'pointer' : 'default' ?>; <?= $hasMilestones ? 'border-left: 3px solid var(--accent-gold);' : '' ?>">
                                <span style="font-weight: <?= date('j') === $day ? '700' : '400' ?>; font-size: 0.85rem; color: <?= date('j') === $day ? 'var(--accent-gold)' : 'var(--text-primary)' ?>;"><?= $day ?></span>
                                <?php if ($hasMilestones): ?>
                                    <?php foreach ($milestoneDates[$day] as $ms): ?>
                                        <div style="font-size: 0.65rem; color: var(--primary-light); margin-top: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($ms['milestone_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($ms['milestone_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                        <?php $remaining = (7 - (($firstDayOfWeek + $daysInMonth) % 7)) % 7; ?>
                        <?php for ($i = 0; $i < $remaining; $i++): ?>
                            <div style="background: var(--bg-surface); padding: 0.75rem; min-height: 60px;"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
