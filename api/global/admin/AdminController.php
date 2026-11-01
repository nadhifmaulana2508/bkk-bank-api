<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/helpers/request.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once __DIR__ . '/AdminApplicationService.php';
require_once __DIR__ . '/AdminUserService.php';
require_once __DIR__ . '/AdminSession.php';
require_once dirname(__DIR__, 2) . '/modules/simpeg/EmployeeService.php';

final class AdminController
{
    public static function dashboard(string $page = 'registry'): void
    {
        self::respond(static function () use ($page): array {
            $data = AdminApplicationService::dashboard($page);
            $data['csrf_token'] = AdminSession::csrfToken();
            return $data;
        });
    }

    public static function create(): void
    {
        self::respond(static fn(): array => AdminApplicationService::create(jsonRequestBody()), 'Aplikasi berhasil dibuat. Simpan token yang ditampilkan sekarang.');
    }

    public static function update(): void
    {
        self::respond(static fn(): array => AdminApplicationService::update(jsonRequestBody()), 'Aplikasi berhasil diperbarui.');
    }

    public static function rotate(): void
    {
        $payload = jsonRequestBody();
        $id = $payload['id'] ?? null;
        self::respond(static fn(): array => AdminApplicationService::rotateToken((int) $id), 'Token aplikasi berhasil dirotasi. Simpan token yang ditampilkan sekarang.');
    }

    public static function users(): void
    {
        self::respond(static fn(): array => AdminUserService::dashboard());
    }

    public static function searchEmployees(string $query): void
    {
        self::respond(static function () use ($query): array {
            $query = trim($query);
            if (strlen($query) < 2 || strlen($query) > 80) {
                throw new InvalidArgumentException('Isi minimal 2 karakter untuk mencari pegawai.');
            }

            $isEmployeeId = preg_match('/^\d+(?:-\d+)?$/', $query) === 1;
            $employees = EmployeeService::searchDirectory(
                $isEmployeeId ? $query : null,
                $isEmployeeId ? null : $query
            );

            return ['employees' => $employees];
        });
    }

    public static function createUser(): void
    {
        self::respond(static fn(): array => AdminUserService::create(jsonRequestBody()), 'User berhasil dibuat.');
    }

    public static function updateUser(): void
    {
        self::respond(static fn(): array => AdminUserService::update(jsonRequestBody()), 'User berhasil diperbarui.');
    }

    /**
     * @param callable(): array<string, mixed> $operation
     */
    private static function respond(callable $operation, string $successMessage = 'OK'): void
    {
        try {
            sendResponse(200, $successMessage, $operation());
        } catch (InvalidArgumentException $exception) {
            sendResponse(422, $exception->getMessage());
        } catch (PDOException $exception) {
            error_log('[API] Admin database error: ' . $exception->getMessage());
            sendResponse(503, 'Layanan admin sedang tidak tersedia.');
        } catch (Throwable $exception) {
            error_log('[API] Admin error: ' . $exception->getMessage());
            sendResponse(500, 'Terjadi kesalahan pada layanan admin.');
        }
    }
}
