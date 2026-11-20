<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/env.php';

final class AppTokenVaultConfigurationException extends RuntimeException
{
}

final class AppTokenVaultUnavailableException extends RuntimeException
{
}

final class AppTokenVault
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    public static function isConfigured(): bool
    {
        try {
            self::encryptionKey();
            return true;
        } catch (AppTokenVaultConfigurationException $exception) {
            return false;
        }
    }

    public static function store(int $applicationId, string $appCode, string $rawToken): void
    {
        $encrypted = self::encrypt($applicationId, $appCode, $rawToken);
        $statement = self::pdo()->prepare(
            'INSERT INTO api_application_token_vault
                (registry_id, application_id, app_code, token_hash, token_ciphertext, token_nonce, token_tag)
             VALUES (:registry_id, :application_id, :app_code, :token_hash, :token_ciphertext, :token_nonce, :token_tag)
             ON DUPLICATE KEY UPDATE
                application_id = VALUES(application_id),
                app_code = VALUES(app_code),
                token_hash = VALUES(token_hash),
                token_ciphertext = VALUES(token_ciphertext),
                token_nonce = VALUES(token_nonce),
                token_tag = VALUES(token_tag)'
        );
        $statement->execute([
            'registry_id' => self::registryId(),
            'application_id' => $applicationId,
            'app_code' => $appCode,
            'token_hash' => hash('sha256', $rawToken),
            'token_ciphertext' => $encrypted['ciphertext'],
            'token_nonce' => $encrypted['nonce'],
            'token_tag' => $encrypted['tag'],
        ]);
    }

    public static function storeIfMissing(int $applicationId, string $appCode, string $rawToken): bool
    {
        $pdo = self::pdo();
        $statement = $pdo->prepare(
            'SELECT token_hash
             FROM api_application_token_vault
             WHERE registry_id = :registry_id AND application_id = :application_id
             LIMIT 1'
        );
        $statement->execute([
            'registry_id' => self::registryId(),
            'application_id' => $applicationId,
        ]);
        $storedHash = $statement->fetchColumn();
        if (is_string($storedHash) && hash_equals($storedHash, hash('sha256', $rawToken))) {
            return false;
        }

        self::store($applicationId, $appCode, $rawToken);
        return true;
    }

    public static function has(int $applicationId, string $appCode): bool
    {
        if (!self::isConfigured()) {
            return false;
        }

        $statement = self::pdo()->prepare(
            'SELECT 1 FROM api_application_token_vault
             WHERE registry_id = :registry_id AND application_id = :application_id AND app_code = :app_code
             LIMIT 1'
        );
        $statement->execute([
            'registry_id' => self::registryId(),
            'application_id' => $applicationId,
            'app_code' => $appCode,
        ]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array<int, bool> */
    public static function availableApplicationIds(): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        $statement = self::pdo()->prepare(
            'SELECT application_id
             FROM api_application_token_vault
             WHERE registry_id = :registry_id'
        );
        $statement->execute(['registry_id' => self::registryId()]);
        $ids = [];
        foreach ($statement as $row) {
            $ids[(int) $row['application_id']] = true;
        }
        return $ids;
    }

    public static function reveal(int $applicationId, string $appCode, string $expectedHash): ?string
    {
        $statement = self::pdo()->prepare(
            'SELECT token_hash, token_ciphertext, token_nonce, token_tag
             FROM api_application_token_vault
             WHERE registry_id = :registry_id AND application_id = :application_id AND app_code = :app_code
             LIMIT 1'
        );
        $statement->execute([
            'registry_id' => self::registryId(),
            'application_id' => $applicationId,
            'app_code' => $appCode,
        ]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        if (!hash_equals($expectedHash, (string) $row['token_hash'])) {
            return null;
        }

        $token = openssl_decrypt(
            (string) $row['token_ciphertext'],
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            (string) $row['token_nonce'],
            (string) $row['token_tag'],
            self::associatedData($applicationId, $appCode)
        );
        if (!is_string($token) || $token === '' || !hash_equals($expectedHash, hash('sha256', $token))) {
            throw new RuntimeException('Token aplikasi tidak dapat didekripsi. Periksa kunci vault di server.');
        }

        return $token;
    }

    /** @return array{ciphertext: string, nonce: string, tag: string} */
    private static function encrypt(int $applicationId, string $appCode, string $rawToken): array
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $rawToken,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::associatedData($applicationId, $appCode),
            self::TAG_BYTES
        );
        if (!is_string($ciphertext) || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Token aplikasi gagal dienkripsi.');
        }

        return ['ciphertext' => $ciphertext, 'nonce' => $nonce, 'tag' => $tag];
    }

    private static function encryptionKey(): string
    {
        $encodedKey = trim((string) app_env_value('APP_TOKEN_ENCRYPTION_KEY', ''));
        $key = $encodedKey !== '' ? base64_decode($encodedKey, true) : false;
        if (!is_string($key) || strlen($key) !== 32) {
            throw new AppTokenVaultConfigurationException(
                'APP_TOKEN_ENCRYPTION_KEY harus berupa Base64 dari 32 byte acak.'
            );
        }

        return $key;
    }

    private static function associatedData(int $applicationId, string $appCode): string
    {
        return 'bank-api-app-token-v1:' . self::registryId() . ':' . $applicationId . ':' . $appCode;
    }

    private static function registryId(): string
    {
        $environment = trim((string) app_env_value('APP_ENV', 'local'));
        $database = trim((string) app_env_value('DB_NAME', ''));
        return hash('sha256', strtolower($environment) . "\0" . $database);
    }

    private static function pdo(): PDO
    {
        try {
            require_once dirname(__DIR__, 3) . '/config/bank_api_db.php';
            return BankApiDatabase::bank();
        } catch (Throwable $exception) {
            throw new AppTokenVaultUnavailableException('Database vault Bank API tidak tersedia.', 0, $exception);
        }
    }
}
