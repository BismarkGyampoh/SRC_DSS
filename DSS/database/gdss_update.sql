CREATE TABLE IF NOT EXISTS gdss_votes (
    vote_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_term VARCHAR(50) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reach_weight DECIMAL(5, 4) NOT NULL,
    speed_weight DECIMAL(5, 4) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_term (academic_term, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
