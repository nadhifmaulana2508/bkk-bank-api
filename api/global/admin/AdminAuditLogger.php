<?php
declare(strict_types=1);

require_once __DIR__ . '/AdminSession.php';

final class AdminAuditLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public static function record(PDO $pdo, string $action, ?int $applicationId, array $metadata = []): void
    {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO api_admin_audit_logs
                    (user_id, action_name, application_id, metadata, ip_address, user_agent)
                 VALUES
                    (:user_id, :action_name, :application_id, :metadata, :ip_address, :user_agent)'
            );
            $statement->execute([
                'user_id' => AdminSession::user()['id'] ?: null,
                'action_name' => substr($action, 0, 40),
                'application_id' => $applicationId,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
        } catch (Throwable $exception) {
            error_log('[API] Admin audit insert failed: ' . $exception->getMessage());
        }
    }
}
