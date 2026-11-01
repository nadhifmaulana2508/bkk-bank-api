<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once __DIR__ . '/AdminAuditLogger.php';

final class AdminApplicationService
{
    /**
     * @return array<string, mixed>
     */
    public static function dashboard(string $page = 'all'): array
    {
        return match ($page) {
            'registry' => [
                'summary' => self::summary(),
                'applications' => self::applications(),
            ],
            'audit' => ['recent_hits' => self::recentHits()],
            'recap' => ['summary' => ['daily_hits' => self::dailyHits()]],
            default => [
                'summary' => self::summary(),
                'applications' => self::applications(),
                'recent_hits' => self::recentHits(),
            ],
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function create(array $payload): array
    {
        $pdo = self::pdo();
        $appCode = self::appCode($payload['app_code'] ?? null);
        $appName = self::appName($payload['app_name'] ?? null);
        $expiresAt = self::expiry($payload['expires_at'] ?? null);
        $rawToken = 'app_' . bin2hex(random_bytes(32));

        try {
            $statement = $pdo->prepare(
                'INSERT INTO api_applications
                    (app_code, app_name, token_hash, is_active, expires_at)
                 VALUES
                    (:app_code, :app_name, :token_hash, 1, :expires_at)'
            );
            $statement->execute([
                'app_code' => $appCode,
                'app_name' => $appName,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => $expiresAt,
            ]);
        } catch (PDOException $exception) {
            if ((string) ($exception->errorInfo[1] ?? '') === '1062') {
                throw new InvalidArgumentException('Kode aplikasi sudah terdaftar.');
            }
            throw $exception;
        }

        $id = (int) $pdo->lastInsertId();
        AdminAuditLogger::record($pdo, 'application.created', $id, ['app_code' => $appCode]);

        return [
            'application' => self::find($id),
            'token' => $rawToken,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function update(array $payload): array
    {
        $pdo = self::pdo();
        $id = self::id($payload['id'] ?? null);
        $current = self::find($id);
        $appName = self::appName($payload['app_name'] ?? null);
        $expiresAt = self::expiry($payload['expires_at'] ?? null);
        $isActive = filter_var($payload['is_active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($isActive === null) {
            throw new InvalidArgumentException('Status aplikasi tidak valid.');
        }

        $statement = $pdo->prepare(
            'UPDATE api_applications
             SET app_name = :app_name, is_active = :is_active, expires_at = :expires_at
             WHERE id = :id'
        );
        $statement->execute([
            'app_name' => $appName,
            'is_active' => $isActive ? 1 : 0,
            'expires_at' => $expiresAt,
            'id' => $id,
        ]);

        AdminAuditLogger::record($pdo, 'application.updated', $id, [
            'app_code' => $current['app_code'],
            'is_active' => $isActive,
            'expires_at' => $expiresAt,
        ]);

        return ['application' => self::find($id)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rotateToken(int $id): array
    {
        $pdo = self::pdo();
        $current = self::find($id);
        $rawToken = 'app_' . bin2hex(random_bytes(32));

        $statement = $pdo->prepare(
            'UPDATE api_applications SET token_hash = :token_hash, is_active = 1 WHERE id = :id'
        );
        $statement->execute([
            'token_hash' => hash('sha256', $rawToken),
            'id' => $id,
        ]);

        AdminAuditLogger::record($pdo, 'application.token_rotated', $id, [
            'app_code' => $current['app_code'],
        ]);

        return [
            'application' => self::find($id),
            'token' => $rawToken,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(): array
    {
        $pdo = self::pdo();
        $statement = $pdo->query(
            'SELECT
                COUNT(*) AS total_applications,
                COALESCE(SUM(is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())), 0) AS active_applications,
                COALESCE(SUM(is_active = 0 OR (expires_at IS NOT NULL AND expires_at <= NOW())), 0) AS inactive_applications
             FROM api_applications'
        );
        $metrics = $statement->fetch() ?: [];

        $hitsToday = (int) $pdo->query(
            'SELECT COUNT(*) FROM api_app_request_logs WHERE created_at >= CURDATE()'
        )->fetchColumn();

        return [
            'total_applications' => (int) ($metrics['total_applications'] ?? 0),
            'active_applications' => (int) ($metrics['active_applications'] ?? 0),
            'inactive_applications' => (int) ($metrics['inactive_applications'] ?? 0),
            'hits_today' => $hitsToday,
            'daily_hits' => self::dailyHits(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function dailyHits(): array
    {
        $pdo = self::pdo();
        $dailyStatement = $pdo->query(
            'SELECT DATE(created_at) AS day,
                    COUNT(*) AS hits,
                    COALESCE(SUM(status_code >= 400), 0) AS failures
             FROM api_app_request_logs
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC'
        );

        $daily = [];
        foreach ($dailyStatement as $row) {
            $daily[] = [
                'day' => (string) $row['day'],
                'hits' => (int) $row['hits'],
                'failures' => (int) $row['failures'],
            ];
        }

        return $daily;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function applications(): array
    {
        $pdo = self::pdo();
        $statement = $pdo->query(
            'SELECT a.id, a.app_code, a.app_name, a.is_active, a.expires_at,
                    a.last_used_at, a.created_at,
                    COUNT(l.id) AS total_hits,
                    COALESCE(SUM(l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS hits_7d
             FROM api_applications a
             LEFT JOIN api_app_request_logs l ON l.application_id = a.id
             GROUP BY a.id, a.app_code, a.app_name, a.is_active, a.expires_at,
                      a.last_used_at, a.created_at
             ORDER BY a.app_code ASC'
        );

        $applications = [];
        foreach ($statement as $row) {
            $applications[] = self::serialize($row);
        }

        return $applications;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function recentHits(): array
    {
        $pdo = self::pdo();
        $statement = $pdo->query(
            'SELECT l.request_method, l.request_path, l.status_code,
                    l.ip_address, l.created_at, a.app_code
             FROM api_app_request_logs l
             JOIN api_applications a ON a.id = l.application_id
             ORDER BY l.created_at DESC
             LIMIT 50'
        );

        $hits = [];
        foreach ($statement as $row) {
            $hits[] = [
                'app_code' => (string) $row['app_code'],
                'request_method' => (string) $row['request_method'],
                'request_path' => (string) $row['request_path'],
                'status_code' => (int) $row['status_code'],
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $hits;
    }

    /**
     * @return array<string, mixed>
     */
    private static function find(int $id): array
    {
        $pdo = self::pdo();
        $statement = $pdo->prepare(
            'SELECT a.id, a.app_code, a.app_name, a.is_active, a.expires_at,
                    a.last_used_at, a.created_at,
                    COUNT(l.id) AS total_hits,
                    COALESCE(SUM(l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS hits_7d
             FROM api_applications a
             LEFT JOIN api_app_request_logs l ON l.application_id = a.id
             WHERE a.id = :id
             GROUP BY a.id, a.app_code, a.app_name, a.is_active, a.expires_at,
                      a.last_used_at, a.created_at'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new InvalidArgumentException('Aplikasi tidak ditemukan.');
        }

        return self::serialize($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function serialize(array $row): array
    {
        $expiresAt = $row['expires_at'] !== null ? (string) $row['expires_at'] : null;
        $expired = $expiresAt !== null && strtotime($expiresAt) <= time();
        $active = (int) ($row['is_active'] ?? 0) === 1 && !$expired;

        return [
            'id' => (int) $row['id'],
            'app_code' => (string) $row['app_code'],
            'app_name' => (string) $row['app_name'],
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'status' => $active ? 'active' : ($expired ? 'expired' : 'inactive'),
            'expires_at' => $expiresAt,
            'last_used_at' => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            'created_at' => (string) $row['created_at'],
            'total_hits' => (int) ($row['total_hits'] ?? 0),
            'hits_7d' => (int) ($row['hits_7d'] ?? 0),
        ];
    }

    private static function pdo(): PDO
    {
        global $pdo;

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Koneksi database tidak tersedia.');
        }

        return $pdo;
    }

    private static function id($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('ID aplikasi tidak valid.');
        }

        return (int) $id;
    }

    private static function appCode($value): string
    {
        $code = strtolower(trim((string) $value));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,49}$/', $code)) {
            throw new InvalidArgumentException('Kode aplikasi hanya boleh berisi huruf kecil, angka, titik, strip, atau underscore.');
        }

        return $code;
    }

    private static function appName($value): string
    {
        $name = trim((string) $value);
        if ($name === '' || strlen($name) > 100) {
            throw new InvalidArgumentException('Nama aplikasi wajib diisi dan maksimal 100 karakter.');
        }

        return $name;
    }

    private static function expiry($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= ' 23:59:59';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            $value .= ':00';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Format masa berlaku tidak valid.');
        }

        if ($date <= new DateTimeImmutable('now')) {
            throw new InvalidArgumentException('Masa berlaku harus berada di masa depan.');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
