-- SRC Project Selection DSS - UMaT Enhanced Database Schema & Seed Data
-- Version: 2.0 (UMaT-Specific Multi-Criteria Decision Support)

CREATE DATABASE IF NOT EXISTS src_dss_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE src_dss_db;

DROP TABLE IF EXISTS project_overrides;
DROP TABLE IF EXISTS project_milestones;
DROP TABLE IF EXISTS project_feedback;
DROP TABLE IF EXISTS project_templates;
DROP TABLE IF EXISTS project_criteria_scores;
DROP TABLE IF EXISTS criteria_weights;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS gdss_votes;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS semester_constraints;
DROP TABLE IF EXISTS src_users;

CREATE TABLE src_users (
    user_id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username             VARCHAR(100) NOT NULL UNIQUE,
    password_hash        VARCHAR(255) NOT NULL,
    user_role            ENUM('Financial Secretary','Projects Coordinator','Executive Board','Admin','Faculty Representative','Student Representative') NOT NULL,
    display_name         VARCHAR(120) NULL DEFAULT NULL,
    profile_picture      VARCHAR(255) NULL DEFAULT NULL,
    email                VARCHAR(255) NULL DEFAULT NULL,
    phone                VARCHAR(50) NULL DEFAULT NULL,
    bio                  TEXT NULL DEFAULT NULL,
    theme_preference     VARCHAR(20) NOT NULL DEFAULT 'light',
    two_factor_secret    VARCHAR(255) NULL DEFAULT NULL,
    two_factor_enabled   TINYINT(1) NOT NULL DEFAULT 0,
    email_notifications  TINYINT(1) NOT NULL DEFAULT 1,
    language             VARCHAR(10) NOT NULL DEFAULT 'en',
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semester_constraints (
    constraint_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_term        VARCHAR(50) NOT NULL,
    max_available_budget DECIMAL(12, 2) NOT NULL,
    max_volunteer_hours  INT UNSIGNED NOT NULL,
    reach_weight         DECIMAL(5, 4) NOT NULL,
    speed_weight         DECIMAL(5, 4) NOT NULL,
    set_by_user_id       INT UNSIGNED NOT NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_semester_constraints_set_by FOREIGN KEY (set_by_user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE criteria_weights (
    criteria_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_term        VARCHAR(50) NOT NULL,
    academic_alignment   DECIMAL(5, 4) NOT NULL DEFAULT 0.1500,
    sustainability       DECIMAL(5, 4) NOT NULL DEFAULT 0.1250,
    health_safety        DECIMAL(5, 4) NOT NULL DEFAULT 0.1250,
    digital_infra        DECIMAL(5, 4) NOT NULL DEFAULT 0.1250,
    sports_recreation    DECIMAL(5, 4) NOT NULL DEFAULT 0.1000,
    hostel_welfare       DECIMAL(5, 4) NOT NULL DEFAULT 0.1250,
    entrepreneurship     DECIMAL(5, 4) NOT NULL DEFAULT 0.1250,
    cost_efficiency      DECIMAL(5, 4) NOT NULL DEFAULT 0.1250,
    set_by_user_id       INT UNSIGNED NOT NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_criteria_weights_set_by FOREIGN KEY (set_by_user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_term_criteria (academic_term)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    log_id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    triggered_by_user_id INT UNSIGNED NOT NULL,
    report_html          LONGTEXT NOT NULL,
    created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_logs_triggered_by FOREIGN KEY (triggered_by_user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    project_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                VARCHAR(255) NOT NULL,
    academic_term        VARCHAR(50) NOT NULL,
    submitted_by         INT UNSIGNED NOT NULL,
    budget_required      DECIMAL(12, 2) NOT NULL,
    volunteer_hours      INT UNSIGNED NOT NULL,
    student_reach        INT UNSIGNED NOT NULL,
    implementation_weeks INT UNSIGNED NOT NULL,
    calculated_pis       DECIMAL(10, 4) NULL DEFAULT NULL,
    dss_status           ENUM('Draft','Pending','Accepted','Rejected','Deferred') NOT NULL DEFAULT 'Pending',
    actual_budget        DECIMAL(12, 2) NULL DEFAULT NULL,
    budget_variance      DECIMAL(12, 2) NULL DEFAULT NULL,
    actual_volunteer_hours INT UNSIGNED NULL DEFAULT NULL,
    academic_alignment   TINYINT UNSIGNED NULL DEFAULT NULL,
    sustainability       TINYINT UNSIGNED NULL DEFAULT NULL,
    health_safety        TINYINT UNSIGNED NULL DEFAULT NULL,
    digital_infra        TINYINT UNSIGNED NULL DEFAULT NULL,
    sports_recreation    TINYINT UNSIGNED NULL DEFAULT NULL,
    hostel_welfare       TINYINT UNSIGNED NULL DEFAULT NULL,
    entrepreneurship     TINYINT UNSIGNED NULL DEFAULT NULL,
    cost_efficiency      TINYINT UNSIGNED NULL DEFAULT NULL,
    receipt_path         VARCHAR(500) NULL DEFAULT NULL,
    petition_path        VARCHAR(500) NULL DEFAULT NULL,
    CONSTRAINT fk_projects_submitted_by FOREIGN KEY (submitted_by)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_projects_term_status (academic_term, dss_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_templates (
    template_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_name      VARCHAR(255) NOT NULL,
    category           VARCHAR(100) NOT NULL,
    description        TEXT NULL DEFAULT NULL,
    criteria_scores_json JSON NULL DEFAULT NULL,
    default_budget     DECIMAL(12, 2) NOT NULL,
    default_hours      INT UNSIGNED NOT NULL,
    default_reach      INT UNSIGNED NOT NULL,
    default_weeks      INT UNSIGNED NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_criteria_scores (
    score_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    criteria   ENUM('academic_alignment','sustainability','health_safety','digital_infra','sports_recreation','hostel_welfare','entrepreneurship','cost_efficiency') NOT NULL,
    score      TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_criteria_scores_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_project_criteria (project_id, criteria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_overrides (
    override_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id       INT UNSIGNED NOT NULL,
    original_status  ENUM('Draft','Pending','Accepted','Rejected','Deferred') NOT NULL,
    new_status       ENUM('Draft','Pending','Accepted','Rejected','Deferred') NOT NULL,
    override_reason  TEXT NOT NULL,
    override_by      INT UNSIGNED NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_overrides_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_overrides_user FOREIGN KEY (override_by)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_milestones (
    milestone_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id     INT UNSIGNED NOT NULL,
    milestone_name VARCHAR(255) NOT NULL,
    target_date    DATE NULL DEFAULT NULL,
    status         ENUM('Pending','In Progress','Completed','Overdue') NOT NULL DEFAULT 'Pending',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_milestones_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_feedback (
    feedback_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id    INT UNSIGNED NOT NULL,
    student_name  VARCHAR(255) NULL DEFAULT NULL,
    student_id    VARCHAR(50) NULL DEFAULT NULL,
    feedback_text TEXT NOT NULL,
    delivery_status ENUM('Delivered','Partially Delivered','Not Delivered') NOT NULL DEFAULT 'Delivered',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gdss_votes (
    vote_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_term VARCHAR(50) NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    reach_weight DECIMAL(5, 4) NOT NULL,
    speed_weight DECIMAL(5, 4) NOT NULL,
    justification TEXT NULL DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_term (academic_term, user_id),
    CONSTRAINT fk_gdss_votes_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    log_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NULL DEFAULT NULL,
    user_role      VARCHAR(100) NULL DEFAULT NULL,
    action_type    VARCHAR(50) NOT NULL,
    entity_type    VARCHAR(50) NULL DEFAULT NULL,
    entity_id      INT UNSIGNED NULL DEFAULT NULL,
    description    TEXT NOT NULL,
    ip_address     VARCHAR(45) NULL DEFAULT NULL,
    user_agent     VARCHAR(255) NULL DEFAULT NULL,
    old_values     TEXT NULL DEFAULT NULL,
    new_values     TEXT NULL DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    type            VARCHAR(50) NOT NULL DEFAULT 'info',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_user_unread (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_config (
    config_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    active_academic_year VARCHAR(50) NOT NULL DEFAULT '2025/2026',
    maintenance_mode   TINYINT(1) NOT NULL DEFAULT 0,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Management Layer
CREATE TABLE data_sources (
    source_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_name    VARCHAR(255) NOT NULL,
    source_type    ENUM('Manual Entry','CSV Import','API Feed','Legacy Database') NOT NULL,
    description    TEXT NULL DEFAULT NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_imports (
    import_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id          INT UNSIGNED NOT NULL,
    imported_by        INT UNSIGNED NOT NULL,
    academic_term      VARCHAR(50) NOT NULL,
    file_path          VARCHAR(500) NULL DEFAULT NULL,
    records_imported   INT UNSIGNED NOT NULL DEFAULT 0,
    records_rejected   INT UNSIGNED NOT NULL DEFAULT 0,
    status             ENUM('Processing','Completed','Failed','Partial') NOT NULL DEFAULT 'Processing',
    started_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at       TIMESTAMP NULL DEFAULT NULL,
    error_log          TEXT NULL DEFAULT NULL,
    CONSTRAINT fk_data_imports_source FOREIGN KEY (source_id)
        REFERENCES data_sources (source_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_data_imports_user FOREIGN KEY (imported_by)
        REFERENCES src_users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_quality_checks (
    check_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id       INT UNSIGNED NULL DEFAULT NULL,
    project_id      INT UNSIGNED NULL DEFAULT NULL,
    check_name      VARCHAR(255) NOT NULL,
    severity        ENUM('Info','Warning','Error','Critical') NOT NULL DEFAULT 'Warning',
    violation_count INT UNSIGNED NOT NULL DEFAULT 0,
    details         TEXT NULL DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quality_checks_import FOREIGN KEY (import_id)
        REFERENCES data_imports (import_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quality_checks_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Management Layer
CREATE TABLE knowledge_categories (
    category_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name      VARCHAR(100) NOT NULL UNIQUE,
    description        TEXT NULL DEFAULT NULL,
    parent_category_id INT UNSIGNED NULL DEFAULT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_knowledge_categories_parent FOREIGN KEY (parent_category_id)
        REFERENCES knowledge_categories (category_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expert_rules (
    rule_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id    INT UNSIGNED NOT NULL,
    rule_name      VARCHAR(255) NOT NULL,
    condition_json JSON NOT NULL,
    recommendation TEXT NOT NULL,
    severity       ENUM('Critical','Warning','Advisory') NOT NULL DEFAULT 'Warning',
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_expert_rules_category FOREIGN KEY (category_id)
        REFERENCES knowledge_categories (category_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rule_trigger_log (
    trigger_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id      INT UNSIGNED NOT NULL,
    project_id   INT UNSIGNED NOT NULL,
    triggered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    result       TEXT NULL DEFAULT NULL,
    CONSTRAINT fk_rule_trigger_log_rule FOREIGN KEY (rule_id)
        REFERENCES expert_rules (rule_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rule_trigger_log_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Model Management Layer
CREATE TABLE dss_models (
    model_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_name    VARCHAR(255) NOT NULL,
    model_type    ENUM('Optimizer','Scoring','Risk Assessment','GDSS','Simulation') NOT NULL,
    description   TEXT NULL DEFAULT NULL,
    version       VARCHAR(50) NOT NULL DEFAULT '1.0.0',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE model_parameters (
    param_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id      INT UNSIGNED NOT NULL,
    param_name    VARCHAR(255) NOT NULL,
    param_value   TEXT NOT NULL,
    param_type    ENUM('Number','String','Boolean','JSON') NOT NULL DEFAULT 'String',
    description   TEXT NULL DEFAULT NULL,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_model_parameters_model FOREIGN KEY (model_id)
        REFERENCES dss_models (model_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_model_param (model_id, param_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE model_executions (
    execution_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id          INT UNSIGNED NOT NULL,
    triggered_by      INT UNSIGNED NOT NULL,
    academic_term     VARCHAR(50) NOT NULL,
    input_snapshot    JSON NULL DEFAULT NULL,
    output_snapshot   JSON NULL DEFAULT NULL,
    execution_time_ms INT UNSIGNED NULL DEFAULT NULL,
    status            ENUM('Running','Completed','Failed','Cancelled') NOT NULL DEFAULT 'Running',
    error_message     TEXT NULL DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at      TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_model_executions_model FOREIGN KEY (model_id)
        REFERENCES dss_models (model_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_model_executions_user FOREIGN KEY (triggered_by)
        REFERENCES src_users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Interface Layer
CREATE TABLE user_dashboards (
    dashboard_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    dashboard_name  VARCHAR(255) NOT NULL,
    layout_config   JSON NOT NULL,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_dashboards_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_user_default_dashboard (user_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dashboard_widgets (
    widget_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dashboard_id    INT UNSIGNED NOT NULL,
    widget_type     VARCHAR(100) NOT NULL,
    position_config JSON NOT NULL,
    widget_config   JSON NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dashboard_widgets_dashboard FOREIGN KEY (dashboard_id)
        REFERENCES user_dashboards (dashboard_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_attachments (
    attachment_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id     INT UNSIGNED NOT NULL,
    uploaded_by    INT UNSIGNED NOT NULL,
    file_name      VARCHAR(255) NOT NULL,
    file_path      VARCHAR(500) NOT NULL,
    file_size      INT UNSIGNED NOT NULL,
    file_type      VARCHAR(100) NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_attachments_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_attachments_user FOREIGN KEY (uploaded_by)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback_attachments (
    attachment_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feedback_id    INT UNSIGNED NOT NULL,
    uploaded_by    INT UNSIGNED NOT NULL,
    file_name      VARCHAR(255) NOT NULL,
    file_path      VARCHAR(500) NOT NULL,
    file_size      INT UNSIGNED NOT NULL,
    file_type      VARCHAR(100) NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_attachments_feedback FOREIGN KEY (feedback_id)
        REFERENCES project_feedback (feedback_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_attachments_user FOREIGN KEY (uploaded_by)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_dependencies (
    dependency_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id           INT UNSIGNED NOT NULL,
    depends_on_project_id INT UNSIGNED NOT NULL,
    dependency_type      ENUM('Prerequisite','Resource Conflict','Sequential','Other') NOT NULL DEFAULT 'Prerequisite',
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_dependencies_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_dependencies_depends_on FOREIGN KEY (depends_on_project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_project_dependency (project_id, depends_on_project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_comments (
    comment_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id       INT UNSIGNED NOT NULL,
    user_id          INT UNSIGNED NOT NULL,
    comment_text     TEXT NOT NULL,
    parent_comment_id INT UNSIGNED NULL DEFAULT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_comments_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_comments_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_comments_parent FOREIGN KEY (parent_comment_id)
        REFERENCES project_comments (comment_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_project_comments (project_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_votes (
    vote_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    rating       TINYINT UNSIGNED NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_project_votes_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_votes_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_user_project_vote (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permissions (
    permission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    page          VARCHAR(100) NOT NULL,
    can_view      TINYINT(1) NOT NULL DEFAULT 1,
    can_edit      TINYINT(1) NOT NULL DEFAULT 0,
    can_delete    TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_user_page (user_id, page)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_dashboard_config (
    config_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    config_json JSON NOT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_dashboard_config_user FOREIGN KEY (user_id)
        REFERENCES src_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_user_dashboard_config (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_config (active_academic_year, maintenance_mode) VALUES ('2025/2026', 0);

-- Seed Data Management sources
INSERT INTO data_sources (source_name, source_type, description) VALUES
('Manual Entry', 'Manual Entry', 'Projects entered directly through the proposal form'),
('CSV Bulk Import', 'CSV Import', 'Batched project imports via CSV upload'),
('Legacy SRC Records', 'Legacy Database', 'Historical project data migrated from previous SRC systems');

-- Seed Knowledge Management categories
INSERT INTO knowledge_categories (category_name, description) VALUES
('Budget Risk', 'Rules related to project budget concentration and financial risk'),
('Volunteer Burnout', 'Rules detecting excessive volunteer-hour demands'),
('ROI Excellence', 'Rules identifying exceptional cost-to-reach value'),
('Logistics', 'Rules about implementation timeline and scheduling'),
('Academic Alignment', 'Rules evaluating alignment with UMaT academic mission'),
('Sustainability', 'Rules about environmental and long-term viability'),
('Health & Safety', 'Rules about health and safety impact'),
('Digital Infrastructure', 'Rules about technology and digital enablement'),
('Hostel & Welfare', 'Rules about residential student welfare'),
('Entrepreneurship', 'Rules about business incubation and graduate readiness'),
('Cost Efficiency', 'Rules about budget-to-reach ratio'),
('Sports & Recreation', 'Rules about sports and recreation value');

-- Seed Model Management registry
INSERT INTO dss_models (model_name, model_type, description, version) VALUES
('Knapsack Optimizer', 'Optimizer', 'Primary project selection engine using budget and volunteer-hour constraints', '1.0.0'),
('PIS Scorer', 'Scoring', 'Calculates Project Impact Scores from multi-criteria weights', '1.0.0'),
('Expert System', 'Risk Assessment', 'Rule-based qualitative risk and ROI advisor for pending projects', '1.0.0'),
('GDSS Aggregator', 'GDSS', 'Group Decision Support System vote aggregation engine', '1.0.0'),
('Sandbox Simulator', 'Simulation', 'What-if scenario testing for budget, hours, and weight changes', '1.0.0');

INSERT INTO model_parameters (model_id, param_name, param_value, param_type, description) VALUES
(1, 'default_budget', '50000', 'Number', 'Default semester budget constraint'),
(1, 'default_hours', '500', 'Number', 'Default semester volunteer-hour constraint'),
(2, 'criteria_count', '8', 'Number', 'Number of multi-criteria dimensions'),
(3, 'rule_count', '13', 'Number', 'Number of active expert-system rules'),
(4, 'vote_weight_precision', '4', 'Number', 'Decimal places for GDSS weight votes'),
(5, 'simulation_iterations', '1', 'Number', 'Default number of sandbox runs');

-- Seed users (password123 for all — change before real deployment)
INSERT INTO src_users (username, password_hash, user_role) VALUES
('fin_sec', '$2y$10$zOK4QD.6RTnnfJ/PWDzzB.SaHf2RBEr.3ur/h4XnqE8KqpHeSjVAq', 'Financial Secretary'),
('proj_coord', '$2y$10$zOK4QD.6RTnnfJ/PWDzzB.SaHf2RBEr.3ur/h4XnqE8KqpHeSjVAq', 'Projects Coordinator'),
('exec_board', '$2y$10$zOK4QD.6RTnnfJ/PWDzzB.SaHf2RBEr.3ur/h4XnqE8KqpHeSjVAq', 'Executive Board'),
('admin', '$2y$10$zOK4QD.6RTnnfJ/PWDzzB.SaHf2RBEr.3ur/h4XnqE8KqpHeSjVAq', 'Admin'),
('faculty_rep', '$2y$10$zOK4QD.6RTnnfJ/PWDzzB.SaHf2RBEr.3ur/h4XnqE8KqpHeSjVAq', 'Faculty Representative'),
('student_rep', '$2y$10$zOK4QD.6RTnnfJ/PWDzzB.SaHf2RBEr.3ur/h4XnqE8KqpHeSjVAq', 'Student Representative');

INSERT INTO semester_constraints (academic_term, max_available_budget, max_volunteer_hours, reach_weight, speed_weight, set_by_user_id) VALUES
('2025/2026 Semester 1', 50000.00, 500, 0.6000, 0.4000, 1);

INSERT INTO criteria_weights (academic_term, academic_alignment, sustainability, health_safety, digital_infra, sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency, set_by_user_id) VALUES
('2025/2026 Semester 1', 0.1500, 0.1250, 0.1250, 0.1250, 0.1000, 0.1250, 0.1250, 0.1250, 1);

INSERT INTO project_templates (template_name, category, default_budget, default_hours, default_reach, default_weeks) VALUES
('Hostel Water System Upgrade', 'Hostel & Welfare', 25000.00, 150, 800, 6),
('Inter-Faculty Sports Tournament', 'Sports & Recreation', 18000.00, 120, 1500, 4),
('Mental Health Awareness Campaign', 'Health & Safety', 12000.00, 80, 2000, 3),
('Campus WiFi Hotspot Expansion', 'Digital Infrastructure', 35000.00, 200, 3000, 8),
('Student Entrepreneurship Bootcamp', 'Student Entrepreneurship', 15000.00, 100, 500, 5),
('Solar Lighting Installation', 'Environmental / Sustainability', 28000.00, 160, 1200, 7),
('Academic Tutorial Center', 'Academic Enhancement', 20000.00, 140, 1000, 5);

INSERT INTO projects (title, academic_term, submitted_by, budget_required, volunteer_hours, student_reach, implementation_weeks, calculated_pis, dss_status) VALUES
('Campus WiFi Upgrade', '2025/2026 Semester 1', 2, 28000.00, 120, 3500, 8, NULL, 'Pending'),
('Annual Career Fair', '2025/2026 Semester 1', 2, 18500.00, 80, 1200, 4, NULL, 'Pending'),
('Mental Health Awareness Week', '2025/2026 Semester 1', 2, 9500.00, 60, 2000, 3, NULL, 'Pending'),
('Inter-Faculty Sports Tournament', '2025/2026 Semester 1', 2, 14000.00, 100, 800, 6, NULL, 'Pending'),
('Library Digital Resources', '2025/2026 Semester 1', 2, 22000.00, 90, 1500, 5, NULL, 'Pending');
 

 INSERT INTO system_config (config_key, config_value, updated_at) VALUES
('smtp_enabled', '0', NOW()),
('smtp_host', '', NOW()),
('smtp_port', '587', NOW()),
('smtp_username', '', NOW()),
('smtp_password', '', NOW()),
('smtp_from_email', '', NOW()),
('smtp_from_name', 'UMaT SRC DSS', NOW())
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW();