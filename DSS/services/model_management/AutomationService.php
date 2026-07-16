<?php

class AutomationService
{
    private PDO $pdo;
    private array $triggeredExecutions = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function onProjectCreated(int $projectId, array $projectData): void
    {
        $this->autoCalculatePIS($projectId);
        $this->autoEvaluateProject($projectId, $projectData);
        $this->checkProjectConstraints($projectId, $projectData);
    }

    public function onConstraintUpdated(string $academicTerm): void
    {
        $this->autoRunOptimization($academicTerm, 'Constraint update trigger');
    }

    public function onCriteriaWeightsUpdated(string $academicTerm): void
    {
        $this->autoRecalculateAllPIS($academicTerm);
    }

    public function onImportCompleted(int $importId, array $importData): void
    {
        $this->autoValidateImportedProjects($importId, $importData);
    }

    private function autoCalculatePIS(int $projectId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM projects WHERE project_id = :project_id LIMIT 1'
            );
            $stmt->execute([':project_id' => $projectId]);
            $project = $stmt->fetch();

            if ($project === false) {
                return;
            }

            $criteriaStmt = $this->pdo->query(
                'SELECT academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM criteria_weights ORDER BY criteria_id DESC LIMIT 1'
            );
            $weights = $criteriaStmt->fetch();

            if ($weights === false) {
                return;
            }

            $pis = 0.0;
            $pis += (float) ($project['academic_alignment'] ?? 0) * (float) $weights['academic_alignment'];
            $pis += (float) ($project['sustainability'] ?? 0) * (float) $weights['sustainability'];
            $pis += (float) ($project['health_safety'] ?? 0) * (float) $weights['health_safety'];
            $pis += (float) ($project['digital_infra'] ?? 0) * (float) $weights['digital_infra'];
            $pis += (float) ($project['sports_recreation'] ?? 0) * (float) $weights['sports_recreation'];
            $pis += (float) ($project['hostel_welfare'] ?? 0) * (float) $weights['hostel_welfare'];
            $pis += (float) ($project['entrepreneurship'] ?? 0) * (float) $weights['entrepreneurship'];
            $pis += (float) ($project['cost_efficiency'] ?? 0) * (float) $weights['cost_efficiency'];

            $updateStmt = $this->pdo->prepare(
                'UPDATE projects SET calculated_pis = :pis WHERE project_id = :project_id'
            );
            $updateStmt->execute([
                ':pis' => round($pis, 4),
                ':project_id' => $projectId,
            ]);
        } catch (Exception $e) {
            // Silent fail for autonomous calculation
        }
    }

    private function autoEvaluateProject(int $projectId, array $projectData): void
    {
        try {
            $constraintStmt = $this->pdo->query(
                'SELECT max_available_budget, max_volunteer_hours FROM semester_constraints ORDER BY constraint_id DESC LIMIT 1'
            );
            $constraints = $constraintStmt->fetch();

            if (!$constraints) {
                return;
            }

            $projectStmt = $this->pdo->prepare(
                'SELECT project_id, title, budget_required, volunteer_hours, student_reach, implementation_weeks,
                        academic_alignment, sustainability, health_safety, digital_infra,
                        sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency
                 FROM projects WHERE project_id = :project_id LIMIT 1'
            );
            $projectStmt->execute([':project_id' => $projectId]);
            $project = $projectStmt->fetch();

            if ($project === false) {
                return;
            }

            require_once __DIR__ . '/../services/knowledge_management/ProjectExpertSystem.php';
            $expertSystem = new ProjectExpertSystem($this->pdo);
            $expertSystem->evaluateProjectWithLog($project, $constraints, $projectId);
        } catch (Exception $e) {
            // Silent fail for autonomous evaluation
        }
    }

    private function checkProjectConstraints(int $projectId, array $projectData): void
    {
        try {
            $budget = (float) ($projectData['budget_required'] ?? 0);
            $hours = (int) ($projectData['volunteer_hours'] ?? 0);

            $constraintStmt = $this->pdo->query(
                'SELECT max_available_budget, max_volunteer_hours, academic_term FROM semester_constraints ORDER BY constraint_id DESC LIMIT 1'
            );
            $constraints = $constraintStmt->fetch();

            if (!$constraints) {
                return;
            }

            $maxBudget = (float) $constraints['max_available_budget'];
            $maxHours = (int) $constraints['max_volunteer_hours'];

            if ($budget >= ($maxBudget * 0.40) && $maxBudget > 0) {
                require_once __DIR__ . '/../services/ActivityLogger.php';
                ActivityLogger::log(
                    $this->pdo,
                    'AUTONOMOUS_ALERT',
                    'Auto-detected: Project ID ' . $projectId . ' consumes >=40% of semester budget (' . number_format($budget, 2) . ' GHS / ' . number_format($maxBudget, 2) . ' GHS)',
                    'project',
                    $projectId,
                    null,
                    ['budget_required' => $budget, 'max_budget' => $maxBudget, 'percentage' => round(($budget / $maxBudget) * 100, 2)]
                );
            }

            if ($hours > $maxHours) {
                require_once __DIR__ . '/../services/ActivityLogger.php';
                ActivityLogger::log(
                    $this->pdo,
                    'AUTONOMOUS_ALERT',
                    'Auto-detected: Project ID ' . $projectId . ' exceeds semester volunteer-hour limit (' . $hours . ' hrs / ' . $maxHours . ' hrs)',
                    'project',
                    $projectId,
                    null,
                    ['volunteer_hours' => $hours, 'max_hours' => $maxHours]
                );
            }
        } catch (Exception $e) {
            // Silent fail for autonomous constraint check
        }
    }

    private function autoRunOptimization(string $academicTerm, string $triggerReason): void
    {
        try {
            $modelStmt = $this->pdo->query(
                'SELECT model_id FROM dss_models WHERE model_name = \'Knapsack Optimizer\' AND is_active = 1 LIMIT 1'
            );
            $model = $modelStmt->fetch();

            if ($model === false) {
                return;
            }

            $modelId = (int) $model['model_id'];

            require_once __DIR__ . '/../services/model_management/ModelManagementService.php';
            $modelService = new ModelManagementService($this->pdo);

            $executionId = $modelService->registerExecution([
                'model_id' => $modelId,
                'triggered_by' => 0,
                'academic_term' => $academicTerm,
                'input_snapshot' => [
                    'trigger' => 'autonomous',
                    'reason' => $triggerReason,
                    'timestamp' => date('c'),
                ],
                'status' => 'Running',
            ]);

            require_once __DIR__ . '/../services/KnapsackOptimizationService.php';
            $engine = new KnapsackOptimizationService($this->pdo);
            $engine->calculatePIS();
            $result = $engine->runKnapsack(0);

            $modelService->completeExecution($executionId, [
                'output_snapshot' => [
                    'accepted_count' => count($result['accepted']),
                    'rejected_count' => count($result['rejected']),
                    'budget_used' => $result['budget_used'],
                    'hours_used' => $result['hours_used'],
                    'trigger' => 'autonomous',
                ],
                'execution_time_ms' => 0,
                'status' => 'Completed',
            ]);

            $this->triggeredExecutions[] = $executionId;
        } catch (Exception $e) {
            // Silent fail for autonomous optimization
        }
    }

    private function autoRecalculateAllPIS(string $academicTerm): void
    {
        try {
            $projectStmt = $this->pdo->prepare(
                'SELECT project_id FROM projects WHERE academic_term = :term AND dss_status IN (\'Pending\', \'Accepted\', \'Deferred\')'
            );
            $projectStmt->execute([':term' => $academicTerm]);
            $projects = $projectStmt->fetchAll();

            foreach ($projects as $project) {
                $this->autoCalculatePIS((int) $project['project_id']);
            }
        } catch (Exception $e) {
            // Silent fail
        }
    }

    private function autoValidateImportedProjects(int $importId, array $importData): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT project_id, budget_required, volunteer_hours, student_reach, implementation_weeks
                 FROM projects WHERE academic_term = :term AND created_at >= :start'
            );
            $stmt->execute([
                ':term' => $importData['academic_term'] ?? '',
                ':start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            ]);
            $projects = $stmt->fetchAll();

            foreach ($projects as $project) {
                $this->checkProjectConstraints((int) $project['project_id'], $project);
            }
        } catch (Exception $e) {
            // Silent fail
        }
    }

    public function getTriggeredExecutions(): array
    {
        return $this->triggeredExecutions;
    }
}
