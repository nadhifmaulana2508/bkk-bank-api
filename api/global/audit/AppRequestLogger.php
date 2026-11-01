<?php
declare(strict_types=1);

final class AppRequestLogger
{
    public static function record(PDO $pdo, int $applicationId): void
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = substr($path, 0, 255);

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $method = substr($method, 0, 10);
        $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        try {
            $statement = $pdo->prepare(
                'INSERT INTO api_app_request_logs
                    (application_id, request_method, request_path, status_code, ip_address, user_agent)
                 VALUES
                    (:application_id, :request_method, :request_path, 200, :ip_address, :user_agent)'
            );
            $statement->execute([
                'application_id' => $applicationId,
                'request_method' => $method,
                'request_path' => $path,
                'ip_address' => $ipAddress !== '' ? $ipAddress : null,
                'user_agent' => $userAgent !== '' ? $userAgent : null,
            ]);

            $logId = (int) $pdo->lastInsertId();
            register_shutdown_function(static function () use ($pdo, $logId): void {
                $statusCode = (int) http_response_code();
                if ($statusCode < 100 || $statusCode > 599) {
                    $statusCode = 500;
                }

                try {
                    $update = $pdo->prepare(
                        'UPDATE api_app_request_logs
                         SET status_code = :status_code
                         WHERE id = :id'
                    );
                    $update->execute([
                        'status_code' => $statusCode,
                        'id' => $logId,
                    ]);
                } catch (Throwable $exception) {
                    error_log('[API] Request audit update failed: ' . $exception->getMessage());
                }
            });
        } catch (Throwable $exception) {
            // Audit logging must never turn a valid business request into a 5xx.
            error_log('[API] Request audit insert failed: ' . $exception->getMessage());
        }
    }
}
