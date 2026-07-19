<?php

require_once '../includes/header.php';

$draftsStmt = $pdo->query(
    "SELECT project_id, title, academic_term, budget_required, volunteer_hours, student_reach, implementation_weeks
     FROM projects
     WHERE dss_status = 'Draft'
     ORDER BY project_id ASC"
);
$drafts = $draftsStmt->fetchAll();
?>

<?php if ($sessionRole !== 'Projects Coordinator'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">drafts</i> My Drafts</div>
        <p class="bento-subtext">Review and submit your saved project drafts.</p>
    </div>

    <div class="bento-card bento-span-full">
            <?php if ($drafts === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">note_add</i>
                    <h5 class="grey-text">No Drafts</h5>
                    <p class="grey-text">You have no saved drafts.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Term</th>
                            <th class="text-right">Budget (GHS)</th>
                            <th class="text-right">Hours</th>
                            <th class="text-right">Students</th>
                            <th class="text-right">Weeks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drafts as $draft): ?>
                            <tr>
                                <td><?= htmlspecialchars($draft['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($draft['academic_term'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-right"><?= number_format((float) $draft['budget_required'], 2) ?></td>
                                <td class="text-right"><?= (int) $draft['volunteer_hours'] ?></td>
                                <td class="text-right"><?= (int) $draft['student_reach'] ?></td>
                                <td class="text-right"><?= (int) $draft['implementation_weeks'] ?></td>
                                <td>
                                    <form method="post" action="/dss/controllers/action_submit_draft.php" style="display: inline;">
                                        <?= $csrfField ?>
                                        <input type="hidden" name="project_id" value="<?= (int) $draft['project_id'] ?>">
                                        <button type="submit" class="btn green" style="padding: 0 1rem; height: 32px; line-height: 32px; font-size: 0.8rem;"><i class="material-icons left">send</i>Submit for Review</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>