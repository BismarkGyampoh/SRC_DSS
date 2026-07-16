<?php

class KnapsackOptimizationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function calculatePIS(): int
    {
        $weights = $this->fetchCurrentWeights();
        $criteriaWeights = $this->fetchCriteriaWeights();

        $projects = [];
        try {
            $stmt = $this->pdo->query(
                "SELECT project_id, student_reach, implementation_weeks,
                        academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM projects
                 WHERE dss_status = 'Pending'"
            );
            $projects = $stmt->fetchAll();
        } catch (PDOException $e) {
            $stmt = $this->pdo->query(
                "SELECT project_id, student_reach, implementation_weeks
                 FROM projects
                 WHERE dss_status = 'Pending'"
            );
            $projects = $stmt->fetchAll();
        }

        if ($projects === []) {
            return 0;
        }

        $maxReach = max(array_column($projects, 'student_reach'));
        $weeks = array_column($projects, 'implementation_weeks');
        $minWeeks = min($weeks);
        $maxWeeks = max($weeks);
        $weekRange = $maxWeeks - $minWeeks;

        $maxCostEfficiency = 0.0;
        foreach ($projects as $project) {
            $budget = (float) ($project['budget_required'] ?? 0);
            $reach = (int) ($project['student_reach'] ?? 0);
            if ($budget > 0 && $reach > 0) {
                $ce = $reach / $budget;
                if ($ce > $maxCostEfficiency) {
                    $maxCostEfficiency = $ce;
                }
            }
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE projects SET calculated_pis = :pis WHERE project_id = :project_id'
        );

        $updated = 0;

        foreach ($projects as $project) {
            $reachScore = $maxReach > 0
                ? (float) $project['student_reach'] / $maxReach
                : 0.0;

            if ($weekRange === 0) {
                $speedScore = 1.0;
            } else {
                $speedScore = ($maxWeeks - (int) $project['implementation_weeks'] + 1)
                    / ($weekRange + 1);
            }

            $academicAlignmentScore = $this->normalizeScore($project['academic_alignment']);
            $sustainabilityScore = $this->normalizeScore($project['sustainability']);
            $healthSafetyScore = $this->normalizeScore($project['health_safety']);
            $digitalInfraScore = $this->normalizeScore($project['digital_infra']);
            $sportsScore = $this->normalizeScore($project['sports_recreation']);
            $hostelWelfareScore = $this->normalizeScore($project['hostel_welfare']);
            $entrepreneurshipScore = $this->normalizeScore($project['entrepreneurship']);

            $projectCE = 0.0;
            $budget = (float) ($project['budget_required'] ?? 0);
            $reach = (int) ($project['student_reach'] ?? 0);
            if ($budget > 0 && $reach > 0) {
                $projectCE = $reach / $budget;
            }
            $costEfficiencyScore = $maxCostEfficiency > 0 ? $projectCE / $maxCostEfficiency : 0.0;

            $pis = round(
                ($weights['reach_weight'] * $reachScore)
                + ($weights['speed_weight'] * $speedScore)
                + ($criteriaWeights['academic_alignment'] * $academicAlignmentScore)
                + ($criteriaWeights['sustainability'] * $sustainabilityScore)
                + ($criteriaWeights['health_safety'] * $healthSafetyScore)
                + ($criteriaWeights['digital_infra'] * $digitalInfraScore)
                + ($criteriaWeights['sports_recreation'] * $sportsScore)
                + ($criteriaWeights['hostel_welfare'] * $hostelWelfareScore)
                + ($criteriaWeights['entrepreneurship'] * $entrepreneurshipScore)
                + ($criteriaWeights['cost_efficiency'] * $costEfficiencyScore),
                4
            );

            $updateStmt->execute([
                ':pis'        => $pis,
                ':project_id' => $project['project_id'],
            ]);
            $updated++;
        }

        return $updated;
    }

    public function runKnapsack(int $triggeredByUserId): array
    {
        $constraintStmt = $this->pdo->query(
            'SELECT max_available_budget, max_volunteer_hours
             FROM semester_constraints
             ORDER BY constraint_id DESC
             LIMIT 1'
        );
        $constraint = $constraintStmt->fetch();

        if ($constraint === false) {
            throw new RuntimeException('No semester constraints found.');
        }

        $capacityBudget = (int) round((float) $constraint['max_available_budget']);
        $capacityHours = (int) $constraint['max_volunteer_hours'];

        $projectStmt = $this->pdo->query(
            "SELECT project_id, budget_required, volunteer_hours, calculated_pis, student_reach
             FROM projects
             WHERE dss_status = 'Pending'
             ORDER BY calculated_pis DESC, student_reach DESC, budget_required ASC, project_id ASC"
        );
        $projects = $projectStmt->fetchAll();

        if ($projects === []) {
            $result = [
                'accepted'    => [],
                'rejected'    => [],
                'total_pis'   => 0.0,
                'budget_used' => 0.0,
                'hours_used'  => 0,
            ];

            require_once __DIR__ . '/AuditTrailService.php';
            $auditService = new AuditTrailService($this->pdo);
            $auditService->persistOptimizationLog(
                $triggeredByUserId,
                $auditService->generateKnapsackReport()
            );

            return $result;
        }

        $items = [];
        foreach ($projects as $project) {
            $items[] = [
                'project_id' => (int) $project['project_id'],
                'weight'     => (int) round((float) $project['budget_required']),
                'hours'      => (int) $project['volunteer_hours'],
                'value'      => (float) $project['calculated_pis'],
            ];
        }

        $selectedIds = $this->solveKnapsack($items, $capacityBudget, $capacityHours);

        $acceptedIds = array_map('intval', $selectedIds);
        $remainingIds = array_values(array_diff(
            array_column($items, 'project_id'),
            $acceptedIds
        ));

        $deferredIds = [];
        $rejectedIds = [];
        foreach ($items as $item) {
            if (in_array($item['project_id'], $remainingIds, true)) {
                if ($item['value'] >= 0.5000) {
                    $deferredIds[] = $item['project_id'];
                } else {
                    $rejectedIds[] = $item['project_id'];
                }
            }
        }

        $this->updateProjectStatuses($acceptedIds, $rejectedIds, $deferredIds);

        $budgetUsed = 0.0;
        $hoursUsed = 0;
        $totalPis = 0.0;
        foreach ($items as $item) {
            if (in_array($item['project_id'], $acceptedIds, true)) {
                $budgetUsed += $item['weight'];
                $hoursUsed += $item['hours'];
                $totalPis += $item['value'];
            }
        }

        $result = [
            'accepted'    => $acceptedIds,
            'rejected'    => $rejectedIds,
            'total_pis'   => round($totalPis, 4),
            'budget_used' => round($budgetUsed, 2),
            'hours_used'  => $hoursUsed,
        ];

        require_once __DIR__ . '/AuditTrailService.php';
        $auditService = new AuditTrailService($this->pdo);
        $auditService->persistOptimizationLog($triggeredByUserId, $auditService->generateKnapsackReport());

        return $result;
    }

    private function fetchCurrentWeights(): array
    {
        $stmt = $this->pdo->query(
            'SELECT reach_weight, speed_weight
             FROM semester_constraints
             ORDER BY constraint_id DESC
             LIMIT 1'
        );
        $constraint = $stmt->fetch();

        if ($constraint === false) {
            throw new RuntimeException('No semester constraints found.');
        }

        return [
            'reach_weight' => (float) $constraint['reach_weight'],
            'speed_weight' => (float) $constraint['speed_weight'],
        ];
    }

    private function fetchCriteriaWeights(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM criteria_weights
                 ORDER BY criteria_id DESC
                 LIMIT 1'
            );
            $row = $stmt->fetch();

            if ($row !== false) {
                return [
                    'academic_alignment' => (float) $row['academic_alignment'],
                    'sustainability'     => (float) $row['sustainability'],
                    'health_safety'      => (float) $row['health_safety'],
                    'digital_infra'      => (float) $row['digital_infra'],
                    'sports_recreation'  => (float) $row['sports_recreation'],
                    'hostel_welfare'     => (float) $row['hostel_welfare'],
                    'entrepreneurship'   => (float) $row['entrepreneurship'],
                    'cost_efficiency'    => (float) $row['cost_efficiency'],
                ];
            }
        } catch (PDOException $e) {
            // Table does not exist yet — use defaults
        }

        return [
            'academic_alignment' => 0.1500,
            'sustainability'     => 0.1250,
            'health_safety'      => 0.1250,
            'digital_infra'      => 0.1250,
            'sports_recreation'  => 0.1000,
            'hostel_welfare'     => 0.1250,
            'entrepreneurship'   => 0.1250,
            'cost_efficiency'    => 0.1250,
        ];
    }

    private function normalizeScore(?int $score): float
    {
        if ($score === null || $score < 0) {
            return 0.0;
        }
        return (float) $score / 100.0;
    }

    private function solveKnapsack(array $items, int $capacityBudget, int $capacityHours): array
    {
        $n = count($items);

        if ($n === 0 || $capacityBudget <= 0 || $capacityHours <= 0) {
            return [];
        }

        $hoursCap = $capacityHours + 1;
        $gridSize = ($capacityBudget + 1) * $hoursCap;
        $keepBytes = (int) ceil($gridSize / 8);

        $dp = new SplFixedArray($gridSize);
        $keep = [];
        for ($i = 0; $i < $n; $i++) {
            $keep[$i] = str_repeat("\0", $keepBytes);
        }

        for ($i = 0; $i < $n; $i++) {
            $weight = $items[$i]['weight'];
            $hours = $items[$i]['hours'];
            $value = $items[$i]['value'];

            for ($budget = $capacityBudget; $budget >= $weight; $budget--) {
                for ($hoursUsed = $capacityHours; $hoursUsed >= $hours; $hoursUsed--) {
                    $idx = ($budget * $hoursCap) + $hoursUsed;
                    $prevIdx = (($budget - $weight) * $hoursCap) + ($hoursUsed - $hours);
                    $candidate = ($dp[$prevIdx] ?? 0.0) + $value;

                    if ($candidate > ($dp[$idx] ?? 0.0)) {
                        $dp[$idx] = $candidate;
                        $this->keepSet($keep[$i], $idx, true);
                    }
                }
            }
        }

        $bestBudget = 0;
        $bestHours = 0;
        $bestValue = 0.0;

        for ($budget = 0; $budget <= $capacityBudget; $budget++) {
            for ($hoursUsed = 0; $hoursUsed <= $capacityHours; $hoursUsed++) {
                $idx = ($budget * $hoursCap) + $hoursUsed;

                if (($dp[$idx] ?? 0.0) > $bestValue) {
                    $bestValue = $dp[$idx] ?? 0.0;
                    $bestBudget = $budget;
                    $bestHours = $hoursUsed;
                }
            }
        }

        $selected = [];
        $budget = $bestBudget;
        $hoursUsed = $bestHours;

        for ($i = $n - 1; $i >= 0; $i--) {
            $idx = ($budget * $hoursCap) + $hoursUsed;

            if ($this->keepGet($keep[$i], $idx)) {
                $selected[] = $items[$i]['project_id'];
                $budget -= $items[$i]['weight'];
                $hoursUsed -= $items[$i]['hours'];
            }
        }

        return $selected;
    }

    private function keepSet(string &$bits, int $index, bool $value): void
    {
        $byteIndex = intdiv($index, 8);
        $bitIndex = $index % 8;
        $byte = ord($bits[$byteIndex]);

        if ($value) {
            $byte |= (1 << $bitIndex);
        } else {
            $byte &= ~(1 << $bitIndex);
        }

        $bits[$byteIndex] = chr($byte);
    }

    private function keepGet(string $bits, int $index): bool
    {
        $byteIndex = intdiv($index, 8);
        $bitIndex = $index % 8;

        return ((ord($bits[$byteIndex]) >> $bitIndex) & 1) === 1;
    }

    private function updateProjectStatuses(array $acceptedIds, array $rejectedIds, array $deferredIds = []): void
    {
        if ($acceptedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($acceptedIds), '?'));
            $stmt = $this->pdo->prepare(
                "UPDATE projects SET dss_status = 'Accepted' WHERE project_id IN ($placeholders)"
            );
            $stmt->execute($acceptedIds);
        }

        if ($rejectedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($rejectedIds), '?'));
            $stmt = $this->pdo->prepare(
                "UPDATE projects SET dss_status = 'Rejected' WHERE project_id IN ($placeholders)"
            );
            $stmt->execute($rejectedIds);
        }

        if ($deferredIds !== []) {
            $placeholders = implode(',', array_fill(0, count($deferredIds), '?'));
            $stmt = $this->pdo->prepare(
                "UPDATE projects SET dss_status = 'Deferred' WHERE project_id IN ($placeholders)"
            );
            $stmt->execute($deferredIds);
        }
    }

    public function runSandboxKnapsack(float $simBudget, int $simHours, float $simReachWt, float $simSpeedWt): array
    {
        $projects = [];
        try {
            $stmt = $this->pdo->query(
                "SELECT project_id, title, budget_required, volunteer_hours, student_reach, implementation_weeks,
                        academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM projects
                 WHERE dss_status = 'Pending'
                 ORDER BY project_id ASC"
            );
            $projects = $stmt->fetchAll();
        } catch (PDOException $e) {
            $stmt = $this->pdo->query(
                "SELECT project_id, title, budget_required, volunteer_hours, student_reach, implementation_weeks
                 FROM projects
                 WHERE dss_status = 'Pending'
                 ORDER BY project_id ASC"
            );
            $projects = $stmt->fetchAll();
        }

        if ($projects === []) {
            return [
                'accepted'    => [],
                'rejected'    => [],
                'deferred'    => [],
                'total_pis'   => 0.0,
                'budget_used' => 0.0,
                'hours_used'  => 0,
            ];
        }

        $maxReach = max(array_column($projects, 'student_reach'));
        $weeks = array_column($projects, 'implementation_weeks');
        $minWeeks = min($weeks);
        $maxWeeks = max($weeks);
        $weekRange = $maxWeeks - $minWeeks;

        $maxCostEfficiency = 0.0;
        foreach ($projects as $project) {
            $budget = (float) ($project['budget_required'] ?? 0);
            $reach = (int) ($project['student_reach'] ?? 0);
            if ($budget > 0 && $reach > 0) {
                $ce = $reach / $budget;
                if ($ce > $maxCostEfficiency) {
                    $maxCostEfficiency = $ce;
                }
            }
        }

        $criteriaWeights = [
            'academic_alignment' => 0.1500,
            'sustainability'     => 0.1250,
            'health_safety'      => 0.1250,
            'digital_infra'      => 0.1250,
            'sports_recreation'  => 0.1000,
            'hostel_welfare'     => 0.1250,
            'entrepreneurship'   => 0.1250,
            'cost_efficiency'    => 0.1250,
        ];

        $items = [];
        foreach ($projects as $project) {
            $reachScore = $maxReach > 0
                ? (float) $project['student_reach'] / $maxReach
                : 0.0;

            if ($weekRange === 0) {
                $speedScore = 1.0;
            } else {
                $speedScore = ($maxWeeks - (int) $project['implementation_weeks'] + 1)
                    / ($weekRange + 1);
            }

            $academicAlignmentScore = $this->normalizeScore($project['academic_alignment']);
            $sustainabilityScore = $this->normalizeScore($project['sustainability']);
            $healthSafetyScore = $this->normalizeScore($project['health_safety']);
            $digitalInfraScore = $this->normalizeScore($project['digital_infra']);
            $sportsScore = $this->normalizeScore($project['sports_recreation']);
            $hostelWelfareScore = $this->normalizeScore($project['hostel_welfare']);
            $entrepreneurshipScore = $this->normalizeScore($project['entrepreneurship']);

            $projectCE = 0.0;
            $budget = (float) ($project['budget_required'] ?? 0);
            $reach = (int) ($project['student_reach'] ?? 0);
            if ($budget > 0 && $reach > 0) {
                $projectCE = $reach / $budget;
            }
            $costEfficiencyScore = $maxCostEfficiency > 0 ? $projectCE / $maxCostEfficiency : 0.0;

            $simPis = round(
                ($simReachWt * $reachScore)
                + ($simSpeedWt * $speedScore)
                + ($criteriaWeights['academic_alignment'] * $academicAlignmentScore)
                + ($criteriaWeights['sustainability'] * $sustainabilityScore)
                + ($criteriaWeights['health_safety'] * $healthSafetyScore)
                + ($criteriaWeights['digital_infra'] * $digitalInfraScore)
                + ($criteriaWeights['sports_recreation'] * $sportsScore)
                + ($criteriaWeights['hostel_welfare'] * $hostelWelfareScore)
                + ($criteriaWeights['entrepreneurship'] * $entrepreneurshipScore)
                + ($criteriaWeights['cost_efficiency'] * $costEfficiencyScore),
                4
            );

            $items[] = [
                'project_id' => (int) $project['project_id'],
                'title' => $project['title'],
                'budget_required' => (float) $project['budget_required'],
                'volunteer_hours' => (int) $project['volunteer_hours'],
                'student_reach' => (int) $project['student_reach'],
                'implementation_weeks' => (int) $project['implementation_weeks'],
                'weight' => (int) round((float) $project['budget_required']),
                'hours' => (int) $project['volunteer_hours'],
                'value' => $simPis,
                'simulated_pis' => $simPis,
            ];
        }

        usort($items, static function ($a, $b) {
            if ($a['value'] !== $b['value']) {
                return $b['value'] <=> $a['value'];
            }
            if ($a['student_reach'] !== $b['student_reach']) {
                return $b['student_reach'] <=> $a['student_reach'];
            }
            if ($a['budget_required'] !== $b['budget_required']) {
                return $a['budget_required'] <=> $b['budget_required'];
            }
            return $a['project_id'] <=> $b['project_id'];
        });

        $capacityBudget = (int) round($simBudget);
        $capacityHours = $simHours;

        $selectedIds = $this->solveKnapsack($items, $capacityBudget, $capacityHours);

        $acceptedIds = array_map('intval', $selectedIds);
        $remainingIds = array_values(array_diff(
            array_column($items, 'project_id'),
            $acceptedIds
        ));

        $deferredIds = [];
        $rejectedIds = [];
        foreach ($items as $item) {
            if (in_array($item['project_id'], $remainingIds, true)) {
                if ($item['value'] >= 0.5000) {
                    $deferredIds[] = $item['project_id'];
                } else {
                    $rejectedIds[] = $item['project_id'];
                }
            }
        }

        $accepted = [];
        $rejected = [];
        $deferred = [];
        $budgetUsed = 0.0;
        $hoursUsed = 0;
        $totalPis = 0.0;

        foreach ($items as $item) {
            if (in_array($item['project_id'], $acceptedIds, true)) {
                $accepted[] = [
                    'project_id' => $item['project_id'],
                    'title' => $item['title'],
                    'budget_required' => $item['budget_required'],
                    'volunteer_hours' => $item['volunteer_hours'],
                    'student_reach' => $item['student_reach'],
                    'implementation_weeks' => $item['implementation_weeks'],
                    'simulated_pis' => $item['simulated_pis'],
                ];
                $budgetUsed += $item['weight'];
                $hoursUsed += $item['hours'];
                $totalPis += $item['value'];
            } elseif (in_array($item['project_id'], $deferredIds, true)) {
                $deferred[] = [
                    'project_id' => $item['project_id'],
                    'title' => $item['title'],
                    'budget_required' => $item['budget_required'],
                    'volunteer_hours' => $item['volunteer_hours'],
                    'student_reach' => $item['student_reach'],
                    'implementation_weeks' => $item['implementation_weeks'],
                    'simulated_pis' => $item['simulated_pis'],
                ];
            } else {
                $rejected[] = [
                    'project_id' => $item['project_id'],
                    'title' => $item['title'],
                    'budget_required' => $item['budget_required'],
                    'volunteer_hours' => $item['volunteer_hours'],
                    'student_reach' => $item['student_reach'],
                    'implementation_weeks' => $item['implementation_weeks'],
                    'simulated_pis' => $item['simulated_pis'],
                ];
            }
        }

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'deferred' => $deferred,
            'total_pis' => round($totalPis, 4),
            'budget_used' => round($budgetUsed, 2),
            'hours_used' => $hoursUsed,
        ];
    }

    public function runGoalSeeking(float $targetPis): array
    {
        $stmt = $this->pdo->query(
            "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
                    implementation_weeks, calculated_pis
             FROM projects
             WHERE dss_status = 'Pending' AND calculated_pis IS NOT NULL
             ORDER BY calculated_pis DESC, student_reach DESC, budget_required ASC, project_id ASC"
        );
        $projects = $stmt->fetchAll();

        $accumulatedPis = 0.0;
        $requiredBudget = 0.0;
        $requiredProjects = [];

        foreach ($projects as $project) {
            if ($accumulatedPis >= $targetPis) {
                break;
            }
            $accumulatedPis += (float) $project['calculated_pis'];
            $requiredBudget += (float) $project['budget_required'];
            $requiredProjects[] = $project;
        }

        return [
            'success' => $accumulatedPis >= $targetPis,
            'target_pis' => $targetPis,
            'achieved_pis' => round($accumulatedPis, 4),
            'required_budget' => round($requiredBudget, 2),
            'projects' => $requiredProjects
        ];
    }

    public function getComparativeAnalysis(): array
    {
        $stmt = $this->pdo->query(
            "SELECT project_id, title, budget_required, volunteer_hours, student_reach,
                    implementation_weeks, calculated_pis, dss_status, submitted_by,
                    src_users.username AS submitted_by_username
             FROM projects
             INNER JOIN src_users ON src_users.user_id = projects.submitted_by
             ORDER BY calculated_pis DESC, projects.project_id ASC"
        );
        $projects = $stmt->fetchAll();

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

        $algorithmPis = array_reduce(
            $accepted,
            static fn(float $carry, array $project): float => $carry + (float) ($project['calculated_pis'] ?? 0),
            0.0
        );

        $constraintStmt = $this->pdo->query(
            'SELECT max_available_budget, max_volunteer_hours
             FROM semester_constraints
             ORDER BY constraint_id DESC
             LIMIT 1'
        );
        $constraint = $constraintStmt->fetch();
        $maxBudget = $constraint !== false ? (float) $constraint['max_available_budget'] : 0;

        $manualBudget = 0.0;
        $manualHours = 0;
        $manualPis = 0.0;
        $manualCount = 0;

        foreach ($projects as $project) {
            if ($project['dss_status'] === 'Accepted') {
                $manualBudget += (float) $project['budget_required'];
                $manualHours += (int) $project['volunteer_hours'];
                $manualPis += (float) ($project['calculated_pis'] ?? 0);
                $manualCount++;
            }
        }

        return [
            'total_projects' => count($projects),
            'accepted_count' => count($accepted),
            'rejected_count' => count($rejected),
            'deferred_count' => count($deferred),
            'algorithm_total_pis' => round($algorithmPis, 4),
            'algorithm_budget_used' => round($manualBudget, 2),
            'algorithm_hours_used' => $manualHours,
            'manual_selection_count' => $manualCount,
            'max_budget' => $maxBudget,
            'remaining_budget' => round(max(0, $maxBudget - $manualBudget), 2),
            'projects' => $projects,
        ];
    }
}
