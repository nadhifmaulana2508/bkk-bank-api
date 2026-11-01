ALTER TABLE user_sso
    ADD COLUMN employee_id VARCHAR(50) NULL AFTER username,
    ADD UNIQUE KEY uq_user_sso_employee_id (employee_id);
