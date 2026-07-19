<?php

require_once '../includes/header.php';

$constraintsStmt = $pdo->query(
    'SELECT
        semester_constraints.constraint_id,
        semester_constraints.academic_term,
        semester_constraints.max_available_budget,
        semester_constraints.max_volunteer_hours,
        semester_constraints.reach_weight,
        semester_constraints.speed_weight,
        semester_constraints.set_by_user_id,
        src_users.username AS set_by_username
     FROM semester_constraints
     INNER JOIN src_users ON src_users.user_id = semester_constraints.set_by_user_id
     ORDER BY semester_constraints.constraint_id DESC'
);
$semesterConstraints = $constraintsStmt->fetchAll();

$currentCriteriaWeights = false;
try {
    $criteriaWeightsStmt = $pdo->query(
        'SELECT academic_term, academic_alignment, sustainability, health_safety, digital_infra,
                sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
         FROM criteria_weights
         ORDER BY criteria_id DESC
         LIMIT 1'
    );
    $currentCriteriaWeights = $criteriaWeightsStmt->fetch();
} catch (PDOException $e) {
    $currentCriteriaWeights = false;
}

$latestConstraintStmt = $pdo->query(
    'SELECT max_available_budget, academic_term
     FROM semester_constraints
     ORDER BY constraint_id DESC
     LIMIT 1'
);
$latestConstraint = $latestConstraintStmt->fetch();
$currentBudget = $latestConstraint !== false ? (float) $latestConstraint['max_available_budget'] : 0;

$budgetStatusStmt = $pdo->query(
    "SELECT dss_status, SUM(budget_required) as total_budget
     FROM projects
     GROUP BY dss_status"
);
$budgetStatusCounts = $budgetStatusStmt->fetchAll();
$budgetStatusMap = [];
foreach ($budgetStatusCounts as $row) {
    $budgetStatusMap[$row['dss_status']] = (float) $row['total_budget'];
}
$totalAcceptedBudget = (float) ($budgetStatusMap['Accepted'] ?? 0);
$remainingBudget = max(0, $currentBudget - $totalAcceptedBudget);
$budgetUtilization = $currentBudget > 0 ? ($totalAcceptedBudget / $currentBudget) * 100 : 0;

$gdssVotesStmt = $pdo->query(
    "SELECT gdss_votes.vote_id, gdss_votes.academic_term, gdss_votes.reach_weight, gdss_votes.speed_weight,
            gdss_votes.justification, gdss_votes.created_at, src_users.username
     FROM gdss_votes
     INNER JOIN src_users ON src_users.user_id = gdss_votes.user_id
     ORDER BY gdss_votes.created_at DESC
     LIMIT 20"
);
$gdssVotes = $gdssVotesStmt->fetchAll();

$criteriaHistoryStmt = $pdo->query(
    'SELECT criteria_id, academic_term, academic_alignment, sustainability, health_safety,
            digital_infra, sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency,
            created_at
     FROM criteria_weights
     ORDER BY created_at DESC
     LIMIT 10'
);
$criteriaHistory = $criteriaHistoryStmt->fetchAll();
?>

<?php if (!in_array($sessionRole, ['Financial Secretary', 'Executive Board', 'Faculty Representative'])): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">settings</i> Term Budget Settings</div>
        <p class="bento-subtext">
            Set the total budget, volunteer hour limit, and project scoring priorities for the term.
        </p>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">history</i> Previous Term Settings</div>
        <p class="bento-subtext">History of budget limits and project scoring settings from past terms.</p>
            <?php if ($semesterConstraints === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">event_note</i>
                    <h5 class="grey-text">No Settings Yet</h5>
                    <p class="grey-text">No term settings have been configured yet. Use the form below to set up your first term.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Term</th>
                            <th class="text-right">Budget (GHS)</th>
                            <th class="text-right">Volunteer Hours</th>
                            <th class="text-right">Reach Priority</th>
                            <th class="text-right">Speed Priority</th>
                            <th>Set By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($semesterConstraints as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['academic_term'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-right"><?= number_format((float) $row['max_available_budget'], 2) ?></td>
                                <td class="text-right"><?= (int) $row['max_volunteer_hours'] ?></td>
                                <td class="text-right"><?= number_format((float) $row['reach_weight'], 4) ?></td>
                                <td class="text-right"><?= number_format((float) $row['speed_weight'], 4) ?></td>
                                <td><?= htmlspecialchars($row['set_by_username'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">account_balance_wallet</i> Budget Watch</div>
        <p class="bento-subtext">Track how much of the budget has been reserved for approved projects.</p>
        <div class="bento-grid" style="margin-top: 1.5rem;">
            <div class="bento-card" style="text-align: center;">
                <div class="bento-card-header">Total Budget</div>
                <div class="bento-metric"><?= number_format($currentBudget, 2) ?></div>
                <p class="bento-subtext">GHS total budget</p>
            </div>
            <div class="bento-card" style="text-align: center;">
                <div class="bento-card-header">Already Reserved</div>
                <div class="bento-metric"><?= number_format($totalAcceptedBudget, 2) ?></div>
                <p class="bento-subtext">GHS reserved</p>
            </div>
            <div class="bento-card" style="text-align: center;">
                <div class="bento-card-header">Remaining</div>
                <div class="bento-metric bento-metric-gold"><?= number_format($remainingBudget, 2) ?></div>
                <p class="bento-subtext">GHS remaining</p>
            </div>
        </div>
        <div class="progress" style="height: 24px; border-radius: 12px; background: #e5e7eb; overflow: hidden; margin-top: 1.5rem;">
            <div class="determinate" style="width: <?= min(100, $budgetUtilization) ?>%; background: <?= $budgetUtilization > 90 ? '#dc2626' : ($budgetUtilization > 70 ? '#f59e0b' : '#10b981') ?>; height: 100%; transition: width 0.3s ease;"></div>
        </div>
        <p style="margin-top: 0.5rem; font-size: 0.875rem; color: <?= $budgetUtilization > 90 ? '#dc2626' : '#6b7280' ?>;">
            <?= number_format($budgetUtilization, 1) ?>% used
            <?= $budgetUtilization > 90 ? ' — WARNING: Budget almost finished!' : '' ?>
            <?= $budgetUtilization > 70 && $budgetUtilization <= 90 ? ' — CAUTION: Budget running low' : '' ?>
        </p>
    </div>

    <?php if (in_array($sessionRole, ['Financial Secretary', 'Executive Board'])): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">tune</i> Set Term Budget & Project Scoring</div>
        <p class="bento-subtext">
            Choose how important each factor is in plain words. The system converts your choices into weights automatically — you never have to type decimals or make them add up to 100%.
        </p>
            <form method="post" action="/dss/controllers/action_set_constraints.php">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12">
                        <input
                            type="text"
                            id="academic_term"
                            name="academic_term"
                            placeholder="e.g. 2025/2026 Semester 2"
                            required
                        >
                        <label for="academic_term">Academic Term</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="max_available_budget"
                            name="max_available_budget"
                            min="0"
                            step="0.01"
                            required
                        >
                        <label for="max_available_budget">Total Budget (GHS)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="max_volunteer_hours"
                            name="max_volunteer_hours"
                            min="1"
                            step="1"
                            required
                        >
                        <label for="max_volunteer_hours">Volunteer Hour Limit</label>
                    </div>
                    <div class="importance-card col s12 m6">
                        <div class="importance-group-label">Reach Priority</div>
                        <div class="importance-options">
                            <label class="importance-pill"><input type="radio" name="reach_weight" value="1" required><span>Slightly</span></label>
                            <label class="importance-pill"><input type="radio" name="reach_weight" value="2" checked><span>Moderately</span></label>
                            <label class="importance-pill"><input type="radio" name="reach_weight" value="3"><span>Very</span></label>
                            <label class="importance-pill"><input type="radio" name="reach_weight" value="4"><span>Extremely</span></label>
                        </div>
                        <span class="helper-text">How much we care about how many students benefit</span>
                    </div>
                    <div class="importance-card col s12 m6">
                        <div class="importance-group-label">Speed Priority</div>
                        <div class="importance-options">
                            <label class="importance-pill"><input type="radio" name="speed_weight" value="1" required><span>Slightly</span></label>
                            <label class="importance-pill"><input type="radio" name="speed_weight" value="2" checked><span>Moderately</span></label>
                            <label class="importance-pill"><input type="radio" name="speed_weight" value="3"><span>Very</span></label>
                            <label class="importance-pill"><input type="radio" name="speed_weight" value="4"><span>Extremely</span></label>
                        </div>
                        <span class="helper-text">How much we care about finishing fast</span>
                    </div>
                </div>

                <h5 style="margin: 2rem 0 1rem; color: #025928;">UMaT Project Importance Ratings</h5>
                <p class="grey-text" style="margin-bottom: 1.5rem;">
                    For each project type, pick how important it is to UMaT. The system turns these into percentages automatically.
                </p>

                <div class="row">
                    <?php
                    $importanceLevels = [
                        1 => 'Slightly',
                        2 => 'Moderately',
                        3 => 'Very',
                        4 => 'Extremely',
                    ];
                    $ratingFields = [
                        'academic_alignment' => ['Academic Relevance', 'Tutorial centers, library upgrades, lab equipment'],
                        'sustainability'     => ['Sustainability', 'Waste management, solar power, tree planting'],
                        'health_safety'      => ['Health & Safety', 'Clinic upgrades, emergency services, fire safety'],
                        'digital_infra'      => ['Digital Improvements', 'WiFi zones, online learning, coding workshops'],
                        'sports_recreation'  => ['Sports & Recreation', 'Sports facilities, gym equipment, sports kits'],
                        'hostel_welfare'     => ['Hostel & Student Welfare', 'Water systems, furniture, common rooms'],
                        'entrepreneurship'   => ['Student Business & Skills', 'Business support, seed funding, skills training'],
                        'cost_efficiency'    => ['Value for Money', 'How much impact we get for every cedi spent'],
                    ];
                    foreach ($ratingFields as $name => $info):
                        [$label, $helper] = $info;
                    ?>
                        <div class="importance-card col s12 m6">
                            <div class="importance-group-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="importance-options">
                                <?php foreach ($importanceLevels as $value => $word): ?>
                                    <label class="importance-pill"><input type="radio" name="<?= $name ?>" value="<?= $value ?>"<?= $value === 2 ? ' checked' : '' ?>><span><?= $word ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <span class="helper-text"><?= htmlspecialchars($helper, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                

                <div class="card-panel blue lighten-4" style="margin-top: 1rem; padding: 1rem;">
                    <strong><i class="material-icons" style="font-size: 1.1rem; vertical-align: middle; margin-right: 0.3rem;">check_circle</i>Calculated Weight Split:</strong>
                    <span id="weightSumDisplay">Adjust the selections above to see the resulting percentages.</span>
                </div>

                <button type="submit" class="btn green"><i class="material-icons left">save</i>Save Term Settings</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">how_to_vote</i> Team Voting — Share Your Preferences</div>
        <p class="bento-subtext">Vote on how much each priority should matter. The system will use the team average so no single person decides.</p>

            <form method="post" action="/dss/controllers/model_management/action_gdss_vote.php" style="margin-bottom: 1.5rem;">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12">
                        <input type="text" name="academic_term" value="2025/2026 Semester 1" required>
                        <label>Target Term</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <select name="reach_weight" required>
                            <option value="" disabled selected>Choose importance</option>
                            <option value="1">Slightly important</option>
                            <option value="2" selected>Moderately important</option>
                            <option value="3">Very important</option>
                            <option value="4">Extremely important</option>
                        </select>
                        <label>Your Reach Preference</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <select name="speed_weight" required>
                            <option value="" disabled selected>Choose importance</option>
                            <option value="1">Slightly important</option>
                            <option value="2" selected>Moderately important</option>
                            <option value="3">Very important</option>
                            <option value="4">Extremely important</option>
                        </select>
                        <label>Your Speed Preference</label>
                    </div>
                    <div class="input-field col s12">
                        <textarea id="gdss_justification" name="justification" class="materialize-textarea" placeholder="Briefly explain why you chose these preferences (optional)"></textarea>
                        <label for="gdss_justification">Reason (optional)</label>
                    </div>
                </div>
                <button type="submit" class="btn blue"><i class="material-icons left">how_to_vote</i>Submit My Vote</button>
            </form>

            <?php if ($sessionRole === 'Executive Board'): ?>
                <div class="card-panel grey lighten-4">
                    <h5 style="margin-bottom: 0.5rem;"><i class="material-icons" style="font-size: 1.3rem; vertical-align: middle; margin-right: 0.3rem;">calculate</i>Use Team Average</h5>
                    <p class="grey-text" style="font-size: 0.85rem; margin-bottom: 1rem;">Takes the average of all votes and updates the official settings for this term.</p>
                    <form method="post" action="/dss/controllers/action_gdss_finalize.php">
                        <?= $csrfField ?>
                        <input type="hidden" name="academic_term" value="2025/2026 Semester 1">
                        <button type="submit" class="btn grey"><i class="material-icons left">check</i>Apply Team Average</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">how_to_vote</i> Team Votes</div>
        <p class="bento-subtext">See all votes cast by the executive team.</p>
        <?php if ($gdssVotes === []): ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">how_to_vote</i>
                <h5 class="grey-text">No Votes Yet</h5>
                <p class="grey-text">No team votes have been cast yet.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Term</th>
                        <th>User</th>
                        <th class="text-right">Reach</th>
                        <th class="text-right">Speed</th>
                        <th>Justification</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gdssVotes as $vote): ?>
                        <tr>
                            <td><?= htmlspecialchars($vote['academic_term'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($vote['username'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right"><?= number_format((float) $vote['reach_weight'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $vote['speed_weight'], 4) ?></td>
                            <td><?= htmlspecialchars($vote['justification'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($vote['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">tune</i> Criteria Weights History</div>
        <p class="bento-subtext">History of project scoring settings from past terms.</p>
        <?php if ($criteriaHistory === []): ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">tune</i>
                <h5 class="grey-text">No Criteria History</h5>
                <p class="grey-text">No criteria weights have been saved yet.</p>
            </div>
        <?php else: ?>
            <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Term</th>
                        <th class="text-right">Academic</th>
                        <th class="text-right">Sustainability</th>
                        <th class="text-right">Health</th>
                        <th class="text-right">Digital</th>
                        <th class="text-right">Sports</th>
                        <th class="text-right">Hostel</th>
                        <th class="text-right">Business</th>
                        <th class="text-right">Value</th>
                        <th>Saved At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($criteriaHistory as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['academic_term'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-right"><?= number_format((float) $row['academic_alignment'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['sustainability'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['health_safety'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['digital_infra'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['sports_recreation'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['hostel_welfare'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['entrepreneurship'], 4) ?></td>
                            <td class="text-right"><?= number_format((float) $row['cost_efficiency'], 4) ?></td>
                            <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.getElementById('academic_alignment')?.addEventListener('input', updateWeightPreview);
const weightFields = ['reach_weight', 'speed_weight', 'academic_alignment', 'sustainability', 'health_safety', 'digital_infra', 'sports_recreation', 'hostel_welfare', 'entrepreneurship', 'cost_efficiency'];
const weightLabels = {
    reach_weight: 'Reach',
    speed_weight: 'Speed',
    academic_alignment: 'Academic',
    sustainability: 'Sustainability',
    health_safety: 'Health & Safety',
    digital_infra: 'Digital',
    sports_recreation: 'Sports',
    hostel_welfare: 'Hostel',
    entrepreneurship: 'Business',
    cost_efficiency: 'Value'
};

weightFields.forEach(id => {
    document.getElementById(id)?.addEventListener('change', updateWeightPreview);
});

function updateWeightPreview() {
    const display = document.getElementById('weightSumDisplay');
    if (!display) return;

    const raw = {};
    let total = 0;
    weightFields.forEach(id => {
        const val = parseInt(document.getElementById(id)?.value) || 0;
        raw[id] = val;
        total += val;
    });

    if (total === 0) {
        display.textContent = 'Adjust the selections above to see the resulting percentages.';
        display.style.color = '#0f5132';
        return;
    }

    const parts = weightFields.map(id => {
        const pct = (raw[id] / total) * 100;
        return weightLabels[id] + ' ' + pct.toFixed(1) + '%';
    });
    display.textContent = parts.join('  •  ');
    display.style.color = '#065f46';
}
updateWeightPreview();
</script>

<?php require_once '../includes/footer.php'; ?>
