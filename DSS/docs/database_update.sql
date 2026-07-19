ALTER TABLE projects MODIFY COLUMN dss_status ENUM('Pending', 'Accepted', 'Rejected', 'Deferred') NOT NULL DEFAULT 'Pending';
