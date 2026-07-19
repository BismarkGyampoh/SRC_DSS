<?php

require_once '../includes/header.php';

// Fetch latest semester constraints
$constraintStmt = $pdo->query(
    'SELECT max_available_budget, academic_term
     FROM semester_constraints
     ORDER BY constraint_id DESC
     LIMIT 1'
);
$constraint = $constraintStmt->fetch();

// Fetch budget sums by project status
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

$totalSemesterBudget = $constraint !== false ? (float) $constraint['max_available_budget'] : 0;
$totalAcceptedBudget = (float) ($budgetStatusMap['Accepted'] ?? 0);
$remainingBudget = max(0, $totalSemesterBudget - $totalAcceptedBudget);

$academicTerm = $constraint !== false
    ? htmlspecialchars($constraint['academic_term'], ENT_QUOTES, 'UTF-8')
    : 'N/A';
?>

<?php if (!in_array($sessionRole, ['Financial Secretary', 'Executive Board'])): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);">Access Restricted</div>
        <p class="bento-subtext">You do not have permission to access this page. Please contact the SRC Executive Board if you believe this is an error.</p>
    </div>
<?php else: ?>
<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">account_balance_wallet</i> <?= htmlspecialchars(__('budget_required'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext"><?= htmlspecialchars(__('budget_required'), ENT_QUOTES, 'UTF-8') ?> <?= $academicTerm ?>.</p>
</div>

    <div class="bento-grid">
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">account_balance</i> Total Budget</div>
            <div class="bento-metric"><?= number_format((float) $totalSemesterBudget, 2) ?></div>
            <p class="bento-subtext">GHS total budget for this term</p>
        </div>
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">lock</i> Budget Reserved</div>
            <div class="bento-metric"><?= number_format((float) $totalAcceptedBudget, 2) ?></div>
            <p class="bento-subtext">GHS for approved projects</p>
        </div>
        <div class="bento-card">
            <div class="bento-card-header"><i class="material-icons">account_balance_wallet</i> Budget Left</div>
            <div class="bento-metric bento-metric-gold"><?= number_format((float) $remainingBudget, 2) ?></div>
            <p class="bento-subtext">GHS still available</p>
        </div>
    </div>

    <?php
    $trendTerms = [];
    $trendAllocated = [];
    $trendCommitted = [];
    try {
        $termsStmt = $pdo->query(
            'SELECT DISTINCT academic_term, max_available_budget
             FROM semester_constraints
             ORDER BY constraint_id DESC
             LIMIT 3'
        );
        $terms = $termsStmt->fetchAll();
        foreach ($terms as $t) {
            $term = $t['academic_term'];
            $allocated = (float) $t['max_available_budget'];
            $committedStmt = $pdo->prepare(
                'SELECT SUM(budget_required) as total FROM projects WHERE academic_term = :term AND dss_status = "Accepted"'
            );
            $committedStmt->execute([':term' => $term]);
            $committed = (float) ($committedStmt->fetchColumn() ?: 0);
            $trendTerms[] = $term;
            $trendAllocated[] = $allocated;
            $trendCommitted[] = $committed;
        }
    } catch (PDOException $e) {
        $trendTerms = [];
    }
    ?>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">show_chart</i> Historical Budget Utilization Trend</div>
        <p class="bento-subtext">Allocated vs actual committed budget across the last 3 academic terms.</p>
        <?php if (empty($trendTerms)): ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">show_chart</i>
                <h5 class="grey-text">No Trend Data</h5>
                <p class="grey-text">No historical budget data available yet.</p>
            </div>
        <?php else: ?>
            <div class="chart-shell chart-shell-md">
                <canvas id="trendChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">list</i> Budget by Status</div>
        <p class="bento-subtext">See budget totals for each project status.</p>
            <?php if ($budgetStatusCounts === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">account_balance_wallet</i>
                    <h5 class="grey-text">No Budget Data</h5>
                    <p class="grey-text">There are currently no projects with budget data to display.</p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="text-right">Total Budget (GHS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budgetStatusCounts as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['dss_status'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-right"><?= number_format((float) $row['total_budget'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">bar_chart</i> Budget Chart</div>
        <p class="bento-subtext">See how budget is split across statuses.</p>
        <?php if (array_sum($budgetStatusMap) === 0): ?>
            <div class="center-align" style="padding: 3rem 1rem;">
                <i class="material-icons large grey-text text-lighten-2">account_balance_wallet</i>
                <h5 class="grey-text">Not Enough Data for Chart</h5>
                <p class="grey-text">There are currently no projects with budget data to display.</p>
            </div>
        <?php else: ?>
            <div class="chart-shell chart-shell-md">
                <canvas id="budgetChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <?php if (array_sum($budgetStatusMap) > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('budgetChart').getContext('2d');
            const budgetData = {
                labels: ['Accepted', 'Pending', 'Rejected', 'Deferred'],
                datasets: [{
                    label: 'Budget (GHS)',
                    data: [
                        <?= number_format((float) ($budgetStatusMap['Accepted'] ?? 0), 2, '.', '') ?>,
                        <?= number_format((float) ($budgetStatusMap['Pending'] ?? 0), 2, '.', '') ?>,
                        <?= number_format((float) ($budgetStatusMap['Rejected'] ?? 0), 2, '.', '') ?>,
                        <?= number_format((float) ($budgetStatusMap['Deferred'] ?? 0), 2, '.', '') ?>
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#6b7280'
                    ],
                    borderWidth: 2
                }]
            };
            new Chart(ctx, {
                type: 'bar',
                data: budgetData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Budget (GHS)'
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>

    <?php if (!empty($trendTerms)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('trendChart').getContext('2d');
            const trendData = {
                labels: <?= json_encode($trendTerms) ?>,
                datasets: [
                    {
                        label: 'Allocated Budget',
                        data: <?= json_encode(array_map('floatval', $trendAllocated)) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Actual Committed Budget',
                        data: <?= json_encode(array_map('floatval', $trendCommitted)) ?>,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            };
            new Chart(ctx, {
                type: 'line',
                data: trendData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Budget (GHS)'
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>