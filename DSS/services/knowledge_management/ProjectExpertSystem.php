<?php

class ProjectExpertSystem
{
    private array $rules = [];
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->loadKnowledgeBase();
    }

    private function loadKnowledgeBase(): void
    {
        $dbLoaded = false;

        if ($this->pdo !== null) {
            try {
                $stmt = $this->pdo->query(
                    'SELECT rule_id, condition_json, recommendation, severity
                     FROM expert_rules
                     WHERE is_active = 1
                     ORDER BY rule_id ASC'
                );

                $dbRules = $stmt->fetchAll();

                if ($dbRules !== []) {
                    $dbLoaded = true;

                    foreach ($dbRules as $rule) {
                        $condition = json_decode($rule['condition_json'], true);
                        $recommendation = (string) $rule['recommendation'];
                        $severity = (string) $rule['severity'];
                        $ruleId = (int) $rule['rule_id'];

                        $this->rules[] = function (array $project, array $constraints) use ($condition, $recommendation, $severity, $ruleId): ?string {
                            if ($this->evaluateCondition($project, $constraints, $condition)) {
                                return $this->formatRecommendation($recommendation, $severity);
                            }
                            return null;
                        };
                    }
                }
            } catch (Exception $e) {
                $dbLoaded = false;
            }
        }

        if (!$dbLoaded) {
            $this->loadHardcodedRules();
        }
    }

    private function evaluateCondition(array $project, array $constraints, array $condition): bool
    {
        if (!isset($condition['type']) || !isset($condition['field']) || !isset($condition['operator']) || !isset($condition['value'])) {
            return false;
        }

        $field = (string) $condition['field'];
        $operator = (string) $condition['operator'];
        $expected = $condition['value'];

        $actual = match ($field) {
            'budget_required' => (float) ($project['budget_required'] ?? 0),
            'volunteer_hours' => (int) ($project['volunteer_hours'] ?? 0),
            'student_reach' => (int) ($project['student_reach'] ?? 0),
            'implementation_weeks' => (int) ($project['implementation_weeks'] ?? 0),
            'academic_alignment' => (int) ($project['academic_alignment'] ?? 0),
            'sustainability' => (int) ($project['sustainability'] ?? 0),
            'health_safety' => (int) ($project['health_safety'] ?? 0),
            'digital_infra' => (int) ($project['digital_infra'] ?? 0),
            'hostel_welfare' => (int) ($project['hostel_welfare'] ?? 0),
            'entrepreneurship' => (int) ($project['entrepreneurship'] ?? 0),
            'cost_efficiency' => (int) ($project['cost_efficiency'] ?? 0),
            'sports_recreation' => (int) ($project['sports_recreation'] ?? 0),
            'max_available_budget' => (float) ($constraints['max_available_budget'] ?? 0),
            'max_volunteer_hours' => (int) ($constraints['max_volunteer_hours'] ?? 0),
            default => null,
        };

        if ($actual === null) {
            return false;
        }

        return match ($operator) {
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            default => false,
        };
    }

    private function formatRecommendation(string $recommendation, string $severity): string
    {
        $prefix = match ($severity) {
            'Critical' => '🔴',
            'Warning' => '🟠',
            'Advisory' => '🔵',
            default => '⚪',
        };

        return $prefix . ' ' . $recommendation;
    }

    private function loadHardcodedRules(): void
    {
        $this->rules[] = static function (array $project, array $constraints): ?string {
            $maxBudget = (float) $constraints['max_available_budget'];
            if ($maxBudget > 0 && (float) $project['budget_required'] >= ($maxBudget * 0.40)) {
                return "🔴 CRITICAL RISK: Project consumes ≥40% of total semester budget. High monopoly risk.";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $weeks = (int) $project['implementation_weeks'];
            if ($weeks > 0 && ((int) $project['volunteer_hours'] / $weeks) > 30) {
                return "🟠 WARNING: Burnout risk. Requires >30 volunteer hours per week.";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            if ((int) $project['student_reach'] >= 2000 && (float) $project['budget_required'] <= 15000) {
                return "🟢 EXCELLENT ROI: High student reach (≥2000) for low capital (≤15,000 GHS).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            if ((int) $project['implementation_weeks'] >= 10) {
                return "🟠 LOGISTICAL DRAG: Implementation exceeds 10 weeks. Risk of overlapping into exam period.";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $alignment = (int) ($project['academic_alignment'] ?? 0);
            if ($alignment >= 80) {
                return "🟢 ACADEMIC EXCELLENCE: Strong alignment with UMaT academic mission (score ≥80/100).";
            } elseif ($alignment >= 50) {
                return "🔵 ACADEMIC ALIGNMENT: Moderate alignment with UMaT academic goals (score ≥50/100).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $sustainability = (int) ($project['sustainability'] ?? 0);
            if ($sustainability >= 80) {
                return "🟢 ENVIRONMENTAL LEADER: High sustainability score (≥80/100). Aligns with UMaT ecological responsibility.";
            } elseif ($sustainability >= 40) {
                return "🔵 SUSTAINABILITY NOTED: Moderate environmental consideration (≥40/100).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $healthSafety = (int) ($project['health_safety'] ?? 0);
            if ($healthSafety >= 80) {
                return "🟢 SAFETY PRIORITY: Critical health and safety impact (≥80/100). Urgent for mining/engineering campus.";
            } elseif ($healthSafety >= 50) {
                return "🔵 SAFETY CONSIDERATION: Moderate health and safety relevance (≥50/100).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $digitalInfra = (int) ($project['digital_infra'] ?? 0);
            if ($digitalInfra >= 80) {
                return "🟢 DIGITAL TRANSFORMATION: High digital infrastructure impact (≥80/100). Core to UMaT technology mission.";
            } elseif ($digitalInfra >= 50) {
                return "🔵 DIGITAL ENABLER: Moderate technology infrastructure benefit (≥50/100).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $hostelWelfare = (int) ($project['hostel_welfare'] ?? 0);
            if ($hostelWelfare >= 80) {
                return "🟢 WELFARE CRITICAL: High hostel and student welfare impact (≥80/100). Essential for residential campus life.";
            } elseif ($hostelWelfare >= 50) {
                return "🔵 WELFARE SUPPORT: Moderate residential welfare improvement (≥50/100).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $entrepreneurship = (int) ($project['entrepreneurship'] ?? 0);
            if ($entrepreneurship >= 80) {
                return "🟢 ENTREPRENEURSHIP BOOST: High student entrepreneurship impact (≥80/100). Builds industry-ready graduates.";
            } elseif ($entrepreneurship >= 50) {
                return "🔵 ENTREPRENEURSHIP ENABLER: Moderate business incubation value (≥50/100).";
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $costEfficiency = (int) ($project['cost_efficiency'] ?? 0);
            $budget = (float) $project['budget_required'];
            $reach = (int) $project['student_reach'];
            if ($costEfficiency >= 80 && $budget > 0 && $reach > 0) {
                $ceRatio = $reach / $budget;
                if ($ceRatio >= 100) {
                    return "🟢 COST EFFICIENCY EXCELLENCE: Exceptional value — reaches ≥100 students per GHS.";
                } elseif ($ceRatio >= 50) {
                    return "🔵 COST EFFICIENT: Strong budget-to-reach ratio (≥50 students per GHS).";
                }
            }
            return null;
        };

        $this->rules[] = static function (array $project, array $constraints): ?string {
            $sports = (int) ($project['sports_recreation'] ?? 0);
            $weeks = (int) ($project['implementation_weeks'] ?? 0);
            if ($sports >= 70 && $weeks <= 4) {
                return "🟢 QUICK WIN SPORTS: High sports/recreation impact (≥70/100) with fast implementation (≤4 weeks).";
            } elseif ($sports >= 50) {
                return "🔵 RECREATION VALUE: Moderate sports and recreation benefit (≥50/100).";
            }
            return null;
        };
    }

    public function evaluateProject(array $project, array $constraints): array
    {
        $advice = [];
        foreach ($this->rules as $rule) {
            $result = $rule($project, $constraints);
            if ($result !== null) {
                $advice[] = $result;
            }
        }

        if (empty($advice)) {
            $advice[] = "⚪ Standard Profile: No significant qualitative risks or standout opportunities detected.";
        }

        return $advice;
    }

    public function evaluateProjectWithLog(array $project, array $constraints, int $projectId): array
    {
        $advice = [];
        $ruleIndex = 0;

        foreach ($this->rules as $rule) {
            $result = $rule($project, $constraints);
            if ($result !== null) {
                $advice[] = $result;

                if ($this->pdo !== null) {
                    try {
                        $stmt = $this->pdo->prepare(
                            'INSERT INTO rule_trigger_log (rule_id, project_id, result)
                             VALUES (:rule_id, :project_id, :result)'
                        );

                        $stmt->execute([
                            ':rule_id' => $ruleIndex + 1,
                            ':project_id' => $projectId,
                            ':result' => $result,
                        ]);
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
            $ruleIndex++;
        }

        if (empty($advice)) {
            $advice[] = "⚪ Standard Profile: No significant qualitative risks or standout opportunities detected.";
        }

        return $advice;
    }

    public function getRuleCount(): int
    {
        return count($this->rules);
    }
}
