-- Jalankan pada database BANK_API_DB_NAME (default: db_bank_api).
CREATE TABLE IF NOT EXISTS api_application_token_vault (
    registry_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    app_code VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_ciphertext VARBINARY(512) NOT NULL,
    token_nonce BINARY(12) NOT NULL,
    token_tag BINARY(16) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (registry_id, application_id),
    UNIQUE KEY uq_api_application_token_vault_code (registry_id, app_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
