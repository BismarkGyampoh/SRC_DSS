<?php

class DashboardService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function saveDashboard(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_dashboards (user_id, dashboard_name, layout_config, is_default, created_at, updated_at)
             VALUES (:user_id, :dashboard_name, :layout_config, :is_default, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE dashboard_name = :dashboard_name, layout_config = :layout_config, updated_at = :updated_at'
        );

        $stmt->execute([
            ':user_id' => (int) $data['user_id'],
            ':dashboard_name' => (string) $data['dashboard_name'],
            ':layout_config' => json_encode($data['layout_config'] ?? []),
            ':is_default' => (int) ($data['is_default'] ?? 0),
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function loadDashboard(int $userId, int $dashboardId = null): array|false
    {
        if ($dashboardId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM user_dashboards WHERE dashboard_id = :dashboard_id AND user_id = :user_id LIMIT 1'
            );

            $stmt->execute([
                ':dashboard_id' => $dashboardId,
                ':user_id' => $userId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM user_dashboards WHERE user_id = :user_id AND is_default = 1 LIMIT 1'
            );

            $stmt->execute([':user_id' => $userId]);
        }

        $dashboard = $stmt->fetch();

        if ($dashboard === false) {
            return false;
        }

        $dashboard['layout_config'] = json_decode($dashboard['layout_config'], true) ?? [];

        return $dashboard;
    }

    public function getUserDashboards(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT dashboard_id, dashboard_name, is_default, created_at, updated_at
             FROM user_dashboards
             WHERE user_id = :user_id
             ORDER BY is_default DESC, updated_at DESC'
        );

        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function saveWidget(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dashboard_widgets (dashboard_id, widget_type, position_config, widget_config, created_at, updated_at)
             VALUES (:dashboard_id, :widget_type, :position_config, :widget_config, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE position_config = :position_config, widget_config = :widget_config, updated_at = :updated_at'
        );

        $stmt->execute([
            ':dashboard_id' => (int) $data['dashboard_id'],
            ':widget_type' => (string) $data['widget_type'],
            ':position_config' => json_encode($data['position_config'] ?? []),
            ':widget_config' => json_encode($data['widget_config'] ?? []),
            ':created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getWidgets(int $dashboardId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM dashboard_widgets WHERE dashboard_id = :dashboard_id ORDER BY widget_id ASC'
        );

        $stmt->execute([':dashboard_id' => $dashboardId]);

        $widgets = $stmt->fetchAll();

        foreach ($widgets as &$widget) {
            $widget['position_config'] = json_decode($widget['position_config'], true) ?? [];
            $widget['widget_config'] = json_decode($widget['widget_config'], true) ?? [];
        }

        return $widgets;
    }

    public function deleteWidget(int $widgetId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM dashboard_widgets WHERE widget_id = :widget_id');
        $stmt->execute([':widget_id' => $widgetId]);
    }

    public function deleteDashboard(int $dashboardId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_dashboards WHERE dashboard_id = :dashboard_id');
        $stmt->execute([':dashboard_id' => $dashboardId]);
    }
}
