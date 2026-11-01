ALTER TABLE api_admin_audit_logs
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_admin_audit_logs_user_time (user_id, created_at),
    ADD CONSTRAINT fk_admin_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES user_sso (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
