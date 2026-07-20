<?php

class AuditTrailService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function persistOptimizationLog(int $triggeredByUserId, string $reportHtml): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (triggered_by_user_id, report_html)
             VALUES (:triggered_by_user_id, :report_html)'
        );

        $stmt->execute([
            ':triggered_by_user_id' => $triggeredByUserId,
            ':report_html'          => $reportHtml,
        ]);
    }

    public function persistOverrideLog(int $triggeredByUserId, int $projectId, string $originalStatus, string $newStatus, string $reason): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO project_overrides (project_id, original_status, new_status, override_reason, override_by)
             VALUES (:project_id, :original_status, :new_status, :override_reason, :override_by)'
        );

        $stmt->execute([
            ':project_id'     => $projectId,
            ':original_status'=> $originalStatus,
            ':new_status'     => $newStatus,
            ':override_reason'=> $reason,
            ':override_by'    => $triggeredByUserId,
        ]);
    }

    public function getOptimizationHistory(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                audit_logs.log_id,
                audit_logs.created_at,
                audit_logs.report_html,
                src_users.username
             FROM audit_logs
             INNER JOIN src_users ON src_users.user_id = audit_logs.triggered_by_user_id
             ORDER BY audit_logs.created_at DESC, audit_logs.log_id DESC'
        );

        return $stmt->fetchAll();
    }

    public function getOverrideHistory(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT
                    project_overrides.override_id,
                    project_overrides.created_at,
                    project_overrides.original_status,
                    project_overrides.new_status,
                    project_overrides.override_reason,
                    projects.title,
                    src_users.username AS override_by_username
                 FROM project_overrides
                 INNER JOIN projects ON projects.project_id = project_overrides.project_id
                 INNER JOIN src_users ON src_users.user_id = project_overrides.override_by
                 ORDER BY project_overrides.created_at DESC, project_overrides.override_id DESC'
            );

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function renderOptimizationHistory(): string
    {
        $logs = $this->getOptimizationHistory();

        if ($logs === []) {
            return '<div class="center-align" style="padding: 3rem 1rem;">'
                . '<i class="material-icons large grey-text text-lighten-2">history</i>'
                . '<h5 class="grey-text">No Optimization Runs</h5>'
                . '<p class="grey-text">No optimization runs have been recorded yet.</p>'
                . '</div>';
        }

        $html = '<div class="optimization-history">';

        foreach ($logs as $log) {
            $timestamp = htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8');
            $username = htmlspecialchars((string) $log['username'], ENT_QUOTES, 'UTF-8');
            $logId = (int) $log['log_id'];

            $verifyUrl = 'http://localhost/dss/views/view_audit.php?log_id=' . $logId;
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verifyUrl);

            $html .= '<details style="margin-bottom:1rem;border:1px solid #e5e7eb;border-radius:8px;padding:0.75rem 1rem;">';
            $html .= '<summary style="cursor:pointer;font-weight:600;color:#025928; display: flex; align-items: center; justify-content: space-between;">';
            $html .= '<span>Run #' . $logId . ' — ' . $timestamp . ' — Triggered by ' . $username . '</span>';
            $html .= '</summary>';

            $html .= '<div style="margin-top:1.5rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">';

            $html .= '<div style="flex-shrink: 0; text-align: center; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px dashed #cbd5e1;">';
            $html .= '<img src="' . $qrCodeUrl . '" alt="Audit Verification QR Code" style="border-radius: 4px;">';
            $html .= '<p style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; max-width: 120px;">Scan to verify official audit record</p>';
            $html .= '</div>';

            $html .= '<div style="flex: 1; min-width: 300px;">' . $log['report_html'] . '</div>';

            $html .= '</div>';
            $html .= '</details>';
        }

        $html .= '</div>';

        return $html;
    }

    public function renderOverrideHistory(): string
    {
        $overrides = $this->getOverrideHistory();

        if ($overrides === []) {
            return '<div class="center-align" style="padding: 3rem 1rem;">'
                . '<i class="material-icons large grey-text text-lighten-2">gavel</i>'
                . '<h5 class="grey-text">No Manual Overrides</h5>'
                . '<p class="grey-text">No executive overrides have been recorded yet.</p>'
                . '</div>';
        }

        $html = '<div class="responsive-table-shell"><table class="striped highlight responsive-table">';
        $html .= '<thead><tr>'
            . '<th>ID</th><th>Date</th><th>Project</th>'
            . '<th>Original</th><th>New Status</th><th>Reason</th><th>By</th>'
            . '</tr></thead><tbody>';

        foreach ($overrides as $override) {
            $html .= '<tr>';
            $html .= '<td>' . (int) $override['override_id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($override['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($override['title'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($override['original_status'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($override['new_status'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($override['override_reason'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($override['override_by_username'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    public function generateKnapsackReport(): string
    {
        $constraintStmt = $this->pdo->query(
            'SELECT academic_term, max_available_budget, max_volunteer_hours, reach_weight, speed_weight
             FROM semester_constraints
             ORDER BY constraint_id DESC
             LIMIT 1'
        );
        $constraint = $constraintStmt->fetch();

        $criteriaRow = false;
        try {
            $criteriaWeightsStmt = $this->pdo->query(
                'SELECT academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM criteria_weights
                 ORDER BY criteria_id DESC
                 LIMIT 1'
            );
            $criteriaRow = $criteriaWeightsStmt->fetch();
        } catch (PDOException $e) {
            $criteriaRow = false;
        }

        $projects = [];
        try {
            $projectStmt = $this->pdo->query(
                'SELECT
                    projects.project_id,
                    projects.title,
                    projects.budget_required,
                    projects.volunteer_hours,
                    projects.student_reach,
                    projects.implementation_weeks,
                    projects.calculated_pis,
                    projects.dss_status,
                    projects.submitted_by,
                    projects.academic_alignment,
                    projects.sustainability,
                    projects.health_safety,
                    projects.digital_infra,
                    projects.sports_recreation,
                    projects.hostel_welfare,
                    projects.entrepreneurship,
                    projects.cost_efficiency,
                    src_users.username AS submitted_by_username
                 FROM projects
                 INNER JOIN src_users ON src_users.user_id = projects.submitted_by
                 ORDER BY projects.calculated_pis DESC, projects.project_id ASC'
            );
            $projects = $projectStmt->fetchAll();
        } catch (PDOException $e) {
            $projectStmt = $this->pdo->query(
                'SELECT
                    projects.project_id,
                    projects.title,
                    projects.budget_required,
                    projects.volunteer_hours,
                    projects.student_reach,
                    projects.implementation_weeks,
                    projects.calculated_pis,
                    projects.dss_status,
                    projects.submitted_by,
                    src_users.username AS submitted_by_username
                 FROM projects
                 INNER JOIN src_users ON src_users.user_id = projects.submitted_by
                 ORDER BY projects.calculated_pis DESC, projects.project_id ASC'
            );
            $projects = $projectStmt->fetchAll();
        }

        $pisValues = array_filter(array_column($projects, 'calculated_pis'), fn($val) => $val !== null);
        $hasTie = count($pisValues) !== count(array_unique($pisValues));

        $accepted = array_values(array_filter(
            $projects,
            static fn(array $project): bool => $project['dss_status'] === 'Accepted'
        ));
        $rejected = array_values(array_filter(
            $projects,
            static fn(array $project): bool => $project['dss_status'] === 'Rejected'
        ));
        $deferred = array_values(array_filter(
            $projects,
            static fn(array $project): bool => $project['dss_status'] === 'Deferred'
        ));
        $pending = array_values(array_filter(
            $projects,
            static fn(array $project): bool => $project['dss_status'] === 'Pending'
        ));

        $totalBudgetUsed = array_reduce(
            $accepted,
            static fn(float $carry, array $project): float => $carry + (float) ($project['budget_required'] ?? 0),
            0.0
        );
        $totalHoursUsed = array_reduce(
            $accepted,
            static fn(int $carry, array $project): int => $carry + (int) ($project['volunteer_hours'] ?? 0),
            0
        );
        $totalPis = array_reduce(
            $accepted,
            static fn(float $carry, array $project): float => $carry + (float) ($project['calculated_pis'] ?? 0),
            0.0
        );

        $maxBudgetValue = $constraint !== false ? (float) $constraint['max_available_budget'] : 0;
        $remainingBudget = max(0, $maxBudgetValue - $totalBudgetUsed);
        $rejectedPis = array_reduce(
            $rejected,
            static fn(float $carry, array $project): float => $carry + (float) ($project['calculated_pis'] ?? 0),
            0.0
        );
        $deferredPis = array_reduce(
            $deferred,
            static fn(float $carry, array $project): float => $carry + (float) ($project['calculated_pis'] ?? 0),
            0.0
        );

        $budgetChartUsed = round((float) $totalBudgetUsed, 2);
        $budgetChartRemaining = round((float) $remainingBudget, 2);
        $acceptedPisChart = round((float) $totalPis, 4);
        $rejectedPisChart = round((float) $rejectedPis, 4);
        $deferredPisChart = round((float) $deferredPis, 4);

        $term = $constraint !== false
            ? htmlspecialchars($constraint['academic_term'], ENT_QUOTES, 'UTF-8')
            : 'N/A';
        $maxBudget = $constraint !== false
            ? number_format((float) $constraint['max_available_budget'], 2)
            : 'N/A';
        $maxHours = $constraint !== false
            ? (int) $constraint['max_volunteer_hours']
            : 0;
        $reachWeight = $constraint !== false
            ? number_format((float) $constraint['reach_weight'], 4)
            : 'N/A';
        $speedWeight = $constraint !== false
            ? number_format((float) $constraint['speed_weight'], 4)
            : 'N/A';

        $html = '<div class="audit-report">';
        $html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        $html .= '<h2>Knapsack Optimization Audit Report</h2>';

        if ($hasTie) {
            $html .= '<div style="background: linear-gradient(135deg, #fff7ed, #ffedd5); border-left: 4px solid #f59e0b; padding: 1rem; margin: 1rem 0; border-radius: 4px; display: flex; align-items: center; gap: 0.75rem;">';
            $html .= '<span style="font-size: 1.25rem; color: #f59e0b;">⚠</span>';
            $html .= '<div style="flex: 1;">';
            $html .= '<strong style="color: #92400e; display: block; margin-bottom: 0.25rem;">Algorithmic Notice:</strong>';
            $html .= '<span style="color: #78350f; font-size: 0.9rem;">Identical Project Impact Scores were detected. The optimization engine automatically applied a secondary deterministic tie-breaker, prioritizing projects with higher Student Reach and lower Budget Requirements.</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<p><strong>Academic Term:</strong> ' . $term . '</p>';

        $html .= '<div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin-bottom:1.5rem;">';
        $html .= '<div style="flex:1;min-width:280px;overflow-x:auto;"><canvas id="budgetChart"></canvas></div>';
        $html .= '<div style="flex:1;min-width:280px;overflow-x:auto;"><canvas id="pisChart"></canvas></div>';
        $html .= '</div>';

        $html .= '<div style="width:100%;overflow-x:auto;">';
        $html .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;min-width:560px;max-width:720px;margin-bottom:1.5rem;">';
        $html .= '<thead><tr><th colspan="2">Resource Utilization Summary</th></tr></thead>';
        $html .= '<tbody>';
        $html .= '<tr><td>PIS Reach Weight</td><td>' . $reachWeight . '</td></tr>';
        $html .= '<tr><td>PIS Speed Weight</td><td>' . $speedWeight . '</td></tr>';
        $html .= '<tr><td>Total Budget Used</td><td>'
            . number_format((float) $totalBudgetUsed, 2) . ' GHS / ' . $maxBudget . ' GHS</td></tr>';
        $html .= '<tr><td>Total Volunteer Hours Used</td><td>'
            . (int) $totalHoursUsed . ' hrs / ' . $maxHours . ' hrs</td></tr>';
        $html .= '<tr><td>Combined Accepted PIS</td><td>'
            . number_format((float) $totalPis, 4) . '</td></tr>';
        $html .= '</tbody></table>';
        $html .= '</div>';

        $html .= $this->buildCriteriaWeightsTable($criteriaRow);
        $html .= $this->buildProjectTable('Accepted Projects', $accepted, '#ecfdf5');
        $html .= $this->buildProjectTable('Rejected Projects', $rejected, '#fef2f2');
        $html .= $this->buildProjectTable('Deferred Projects (Rollover Queue)', $deferred, '#eff6ff');
        $html .= $this->buildProjectTable('Pending Projects', $pending, '#fffbeb');

        $html .= '<p style="font-size:0.875rem;color:#4b5563;margin-top:1rem;">'
            . 'This report documents the DSS knapsack outcome for executive review and transparent justification.'
            . '</p>';

        $html .= '<script>';
        $html .= 'const budgetData = {';
        $html .= '  labels: ["Budget Used", "Remaining Budget"],';
        $html .= '  datasets: [{';
        $html .= '    data: [' . json_encode((float) $budgetChartUsed) . ', ' . json_encode((float) $budgetChartRemaining) . '],';
        $html .= '    backgroundColor: ["#16324f", "#a7f3d0"],';
        $html .= '    borderWidth: 1';
        $html .= '  }]';
        $html .= '};';
        $html .= 'const budgetChart = new Chart(document.getElementById("budgetChart"), {';
        $html .= '  type: "doughnut",';
        $html .= '  data: budgetData,';
        $html .= '  options: { responsive: true, plugins: { legend: { position: "bottom" } } }';
        $html .= '});';
        $html .= 'const pisData = {';
        $html .= '  labels: ["Accepted PIS", "Rejected PIS", "Deferred PIS"],';
        $html .= '  datasets: [{';
        $html .= '    label: "PIS Comparison",';
        $html .= '    data: [' . json_encode((float) $acceptedPisChart) . ', ' . json_encode((float) $rejectedPisChart) . ', ' . json_encode((float) $deferredPisChart) . '],';
        $html .= '    backgroundColor: ["#16324f", "#dc2626", "#2563eb"],';
        $html .= '    borderWidth: 1';
        $html .= '  }]';
        $html .= '};';
        $html .= 'const pisChart = new Chart(document.getElementById("pisChart"), {';
        $html .= '  type: "bar",';
        $html .= '  data: pisData,';
        $html .= '  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }';
        $html .= '});';
        $html .= '</script>';

        $html .= '</div>';

        return $html;
    }

    public function generateComparativeReport(): string
    {
        $constraintStmt = $this->pdo->query(
            'SELECT academic_term, max_available_budget, max_volunteer_hours, reach_weight, speed_weight
             FROM semester_constraints
             ORDER BY constraint_id DESC
             LIMIT 1'
        );
        $constraint = $constraintStmt->fetch();
        $term = $constraint !== false ? htmlspecialchars($constraint['academic_term'], ENT_QUOTES, 'UTF-8') : 'N/A';
        $maxBudget = $constraint !== false ? (float) $constraint['max_available_budget'] : 0;

        $acceptedStmt = $this->pdo->query(
            "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
                    implementation_weeks, calculated_pis, submitted_by,
                    src_users.username AS submitted_by_username
             FROM projects
             INNER JOIN src_users ON src_users.user_id = projects.submitted_by
             WHERE dss_status = 'Accepted'
             ORDER BY calculated_pis DESC, projects.project_id ASC"
        );
        $accepted = $acceptedStmt->fetchAll();

        $allPendingStmt = $this->pdo->query(
            "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
                    implementation_weeks, calculated_pis
             FROM projects
             WHERE dss_status = 'Pending'
             ORDER BY calculated_pis DESC, project_id ASC"
        );
        $allPending = $allPendingStmt->fetchAll();

        $acceptedBudget = array_reduce(
            $accepted,
            static fn(float $carry, array $project): float => $carry + (float) ($project['budget_required'] ?? 0),
            0.0
        );
        $acceptedHours = array_reduce(
            $accepted,
            static fn(int $carry, array $project): int => $carry + (int) ($project['volunteer_hours'] ?? 0),
            0
        );
        $acceptedPis = array_reduce(
            $accepted,
            static fn(float $carry, array $project): float => $carry + (float) ($project['calculated_pis'] ?? 0),
            0.0
        );

        $html = '<div class="comparative-report" style="border: 2px solid #2563eb; border-radius: 12px; padding: 1.5rem; background: #eff6ff;">';
        $html .= '<div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">';
        $html .= '<span style="background: #2563eb; color: #ffffff; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">COMPARATIVE ANALYSIS</span>';
        $html .= '<h2 style="margin: 0; font-size: 1.125rem; color: #1e40af;">Algorithm Output vs Manual Selection</h2>';
        $html .= '</div>';

        $html .= '<p><strong>Academic Term:</strong> ' . $term . '</p>';

        $html .= '<div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin:1.5rem 0;">';
        $html .= '<div style="flex:1;min-width:240px;background:#ffffff;padding:1rem;border-radius:8px;border-left:4px solid #10b981;">';
        $html .= '<h4 style="margin:0 0 0.5rem;color:#065f46;">Algorithm Selection</h4>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Projects Accepted:</strong> ' . count($accepted) . '</p>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Budget Used:</strong> ' . number_format($acceptedBudget, 2) . ' GHS</p>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Hours Used:</strong> ' . $acceptedHours . ' hrs</p>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Total PIS:</strong> ' . number_format($acceptedPis, 4) . '</p>';
        $html .= '</div>';

        $html .= '<div style="flex:1;min-width:240px;background:#ffffff;padding:1rem;border-radius:8px;border-left:4px solid #f59e0b;">';
        $html .= '<h4 style="margin:0 0 0.5rem;color:#92400e;">Remaining Capacity</h4>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Budget Remaining:</strong> ' . number_format(max(0, $maxBudget - $acceptedBudget), 2) . ' GHS</p>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Pending Projects:</strong> ' . count($allPending) . '</p>';
        $html .= '<p style="margin:0.25rem 0;"><strong>Budget Limit:</strong> ' . number_format($maxBudget, 2) . ' GHS</p>';
        $html .= '</div>';
        $html .= '</div>';

        if ($accepted !== []) {
            $html .= '<h4 style="margin:1.5rem 0 0.5rem;color:#1e40af;">Accepted Projects Detail</h4>';
            $html .= '<div style="width:100%;overflow-x:auto;"><table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;min-width:760px;background:#ffffff;">';
            $html .= '<thead style="background:#dbeafe;"><tr>'
                . '<th>ID</th><th>Title</th><th>Budget (GHS)</th><th>Hours</th>'
                . '<th>Student Reach</th><th>Weeks</th><th>PIS</th><th>Submitted By</th>'
                . '</tr></thead><tbody>';

            foreach ($accepted as $project) {
                $html .= '<tr>';
                $html .= '<td>' . (int) $project['project_id'] . '</td>';
                $html .= '<td>' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . number_format((float) $project['budget_required'], 2) . '</td>';
                $html .= '<td>' . (int) $project['volunteer_hours'] . '</td>';
                $html .= '<td>' . (int) $project['student_reach'] . '</td>';
                $html .= '<td>' . (int) $project['implementation_weeks'] . '</td>';
                $html .= '<td>' . number_format((float) $project['calculated_pis'], 4) . '</td>';
                $html .= '<td>' . htmlspecialchars($project['submitted_by_username'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= '<p style="font-size:0.8125rem;color:#6b7280;margin-top:1rem;font-style:italic;">'
            . 'Comparative analysis shows algorithm-selected projects. Manual overrides can be applied with justification via the Executive Board override tool.'
            . '</p>';
        $html .= '</div>';

        return $html;
    }

    public function renderComparativeAnalysis(): string
    {
        $overrides = $this->getOverrideHistory();
        $html = '<div class="override-history-section" style="margin-top: 2rem;">';
        $html .= '<h3 style="color: #1e40af; margin-bottom: 1rem;">Executive Override History</h3>';
        $html .= $this->renderOverrideHistory();
        $html .= '</div>';

        return $html;
    }

    private function buildCriteriaWeightsTable(mixed $criteriaRow): string
    {
        if ($criteriaRow === false || $criteriaRow === null) {
            return '';
        }

        $weights = [
            'Academic Alignment'    => (float) $criteriaRow['academic_alignment'],
            'Sustainability'        => (float) $criteriaRow['sustainability'],
            'Health & Safety'       => (float) $criteriaRow['health_safety'],
            'Digital Infrastructure'=> (float) $criteriaRow['digital_infra'],
            'Sports & Recreation'   => (float) $criteriaRow['sports_recreation'],
            'Hostel & Welfare'      => (float) $criteriaRow['hostel_welfare'],
            'Entrepreneurship'      => (float) $criteriaRow['entrepreneurship'],
            'Cost Efficiency'       => (float) $criteriaRow['cost_efficiency'],
        ];

        $html = '<div style="width:100%;overflow-x:auto;margin-bottom:1.5rem;">';
        $html .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;min-width:560px;max-width:720px;">';
        $html .= '<thead><tr><th colspan="2">UMaT Multi-Criteria Weights</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($weights as $label => $weight) {
            $html .= '<tr><td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right;">' . number_format($weight, 4) . '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function buildProjectTable(string $title, array $projects, string $headerColor): string
    {
        $html = '<h3 style="margin:1.25rem 0 0.5rem;">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>';

        if ($projects === []) {
            $html .= '<div class="center-align" style="padding: 3rem 1rem;">';
            $html .= '<i class="material-icons large grey-text text-lighten-2">folder_open</i>';
            $html .= '<h5 class="grey-text">No Projects</h5>';
            $html .= '<p class="grey-text">There are currently no projects in this category.</p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<div style="width:100%;overflow-x:auto;">';
        $html .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;min-width:760px;margin-bottom:1rem;">';
        $html .= '<thead style="background:' . $headerColor . ';">';
        $html .= '<tr>';
        $html .= '<th>ID</th><th>Title</th><th>Budget (GHS)</th><th>Hours</th>'
            . '<th>Student Reach</th><th>Weeks</th><th>PIS</th><th>Status</th><th>Submitted By</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($projects as $project) {
            $html .= '<tr>';
            $html .= '<td>' . (int) $project['project_id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . number_format((float) $project['budget_required'], 2) . '</td>';
            $html .= '<td>' . (int) $project['volunteer_hours'] . '</td>';
            $html .= '<td>' . (int) $project['student_reach'] . '</td>';
            $html .= '<td>' . (int) $project['implementation_weeks'] . '</td>';
            $html .= '<td>' . ($project['calculated_pis'] !== null
                ? number_format((float) $project['calculated_pis'], 4)
                : 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($project['dss_status'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars((string) ($project['submitted_by_username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        return $html;
    }
}
