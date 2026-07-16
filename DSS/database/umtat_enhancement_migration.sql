-- Migration: UMaT Enhanced Features (Schema v2.0)
-- Run this on an existing database to upgrade from v1.0 to v2.0
-- WARNING: Backup your database before running this script

-- Add new user roles
ALTER TABLE src_users MODIFY COLUMN user_role ENUM('Financial Secretary','Projects Coordinator','Executive Board','Admin','Faculty Representative','Student Representative') NOT NULL;

-- Add UMaT-specific criteria scores to projects
ALTER TABLE projects ADD COLUMN academic_alignment TINYINT UNSIGNED NULL DEFAULT NULL AFTER cost_efficiency;
ALTER TABLE projects ADD COLUMN sustainability TINYINT UNSIGNED NULL DEFAULT NULL AFTER academic_alignment;
ALTER TABLE projects ADD COLUMN health_safety TINYINT UNSIGNED NULL DEFAULT NULL AFTER sustainability;
ALTER TABLE projects ADD COLUMN digital_infra TINYINT UNSIGNED NULL DEFAULT NULL AFTER health_safety;
ALTER TABLE projects ADD COLUMN sports_recreation TINYINT UNSIGNED NULL DEFAULT NULL AFTER digital_infra;
ALTER TABLE projects ADD COLUMN hostel_welfare TINYINT UNSIGNED NULL DEFAULT NULL AFTER sports_recreation;
ALTER TABLE projects ADD COLUMN entrepreneurship TINYINT UNSIGNED NULL DEFAULT NULL AFTER hostel_welfare;
ALTER TABLE projects ADD COLUMN cost_efficiency TINYINT UNSIGNED NULL DEFAULT NULL AFTER entrepreneurship;
ALTER TABLE projects ADD COLUMN receipt_path VARCHAR(500) NULL DEFAULT NULL AFTER cost_efficiency;
ALTER TABLE projects ADD COLUMN petition_path VARCHAR(500) NULL DEFAULT NULL AFTER receipt_path;

-- Add justification to GDSS votes
ALTER TABLE gdss_votes ADD COLUMN justification TEXT NULL DEFAULT NULL AFTER speed_weight;

-- Create criteria_weights table
CREATE TABLE IF NOT EXISTS criteria_weights (
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

-- Create project_templates table
CREATE TABLE IF NOT EXISTS project_templates (
    template_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_name  VARCHAR(255) NOT NULL,
    category       VARCHAR(100) NOT NULL,
    default_budget DECIMAL(12, 2) NOT NULL,
    default_hours  INT UNSIGNED NOT NULL,
    default_reach  INT UNSIGNED NOT NULL,
    default_weeks  INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_criteria_scores table
CREATE TABLE IF NOT EXISTS project_criteria_scores (
    score_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    criteria   ENUM('academic_alignment','sustainability','health_safety','digital_infra','sports_recreation','hostel_welfare','entrepreneurship','cost_efficiency') NOT NULL,
    score      TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_criteria_scores_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_project_criteria (project_id, criteria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_overrides table
CREATE TABLE IF NOT EXISTS project_overrides (
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

-- Create project_milestones table
CREATE TABLE IF NOT EXISTS project_milestones (
    milestone_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id     INT UNSIGNED NOT NULL,
    milestone_name VARCHAR(255) NOT NULL,
    target_date    DATE NULL DEFAULT NULL,
    status         ENUM('Pending','In Progress','Completed','Overdue') NOT NULL DEFAULT 'Pending',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_milestones_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create project_feedback table
CREATE TABLE IF NOT EXISTS project_feedback (
    feedback_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id     INT UNSIGNED NOT NULL,
    student_name   VARCHAR(255) NULL DEFAULT NULL,
    student_id     VARCHAR(50) NULL DEFAULT NULL,
    feedback_text  TEXT NOT NULL,
    delivery_status ENUM('Delivered','Partially Delivered','Not Delivered') NOT NULL DEFAULT 'Delivered',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_project FOREIGN KEY (project_id)
        REFERENCES projects (project_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default criteria weights for existing terms
INSERT IGNORE INTO criteria_weights (academic_term, academic_alignment, sustainability, health_safety, digital_infra, sports_recreation, hostel_welfare, entrepreneurship, cost_efficiency, set_by_user_id)
SELECT academic_term, 0.1500, 0.1250, 0.1250, 0.1250, 0.1000, 0.1250, 0.1250, 0.1250, 1 FROM semester_constraints;

-- Seed project templates
INSERT IGNORE INTO project_templates (template_name, category, default_budget, default_hours, default_reach, default_weeks) VALUES
('Hostel Water System Upgrade', 'Hostel & Welfare', 25000.00, 150, 800, 6),
('Inter-Faculty Sports Tournament', 'Sports & Recreation', 18000.00, 120, 1500, 4),
('Mental Health Awareness Campaign', 'Health & Safety', 12000.00, 80, 2000, 3),
('Campus WiFi Hotspot Expansion', 'Digital Infrastructure', 35000.00, 200, 3000, 8),
('Student Entrepreneurship Bootcamp', 'Student Entrepreneurship', 15000.00, 100, 500, 5),
('Solar Lighting Installation', 'Environmental / Sustainability', 28000.00, 160, 1200, 7),
('Academic Tutorial Center', 'Academic Enhancement', 20000.00, 140, 1000, 5);

-- Create system_config table if not exists
CREATE TABLE IF NOT EXISTS system_config (
    config_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    active_academic_year VARCHAR(50) NOT NULL DEFAULT '2025/2026',
    maintenance_mode   TINYINT(1) NOT NULL DEFAULT 0,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_config (active_academic_year, maintenance_mode) VALUES ('2025/2026', 0);

-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL DEFAULT NULL,
    user_role     VARCHAR(100) NULL DEFAULT NULL,
    action_type   VARCHAR(50) NOT NULL,
    entity_type   VARCHAR(50) NULL DEFAULT NULL,
    entity_id     INT UNSIGNED NULL DEFAULT NULL,
    description   TEXT NOT NULL,
    ip_address    VARCHAR(45) NULL DEFAULT NULL,
    user_agent    VARCHAR(255) NULL DEFAULT NULL,
    old_values    TEXT NULL DEFAULT NULL,
    new_values    TEXT NULL DEFAULT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
