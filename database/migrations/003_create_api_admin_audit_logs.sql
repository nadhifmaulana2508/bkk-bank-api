CREATE TABLE IF NOT EXISTS api_admin_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_name VARCHAR(40) NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    metadata TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_audit_logs_application_time (application_id, created_at),
    KEY idx_admin_audit_logs_created_at (created_at),
    CONSTRAINT fk_admin_audit_logs_application
        FOREIGN KEY (application_id) REFERENCES api_applications (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
