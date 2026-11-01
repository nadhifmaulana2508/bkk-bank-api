CREATE TABLE IF NOT EXISTS api_app_request_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    request_method VARCHAR(10) NOT NULL,
    request_path VARCHAR(255) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_app_request_logs_application_time (application_id, created_at),
    KEY idx_app_request_logs_created_at (created_at),
    CONSTRAINT fk_app_request_logs_application
        FOREIGN KEY (application_id) REFERENCES api_applications (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
