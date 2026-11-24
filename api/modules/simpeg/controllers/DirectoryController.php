<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/DirectoryService.php';
require_once dirname(__DIR__, 4) . '/helpers/response.php';

final class DirectoryController
{
    public static function positions(): void
    {
        if (array_key_exists('updated_since', $_GET)) {
            sendResponse(400, 'Filter updated_since belum tersedia untuk master jabatan; sinkronkan seluruh halaman.');
        }

        self::respondWithPage(
            static fn(int $page, int $perPage, ?string $updatedSince): array => DirectoryService::positions($page, $perPage),
            false
        );
    }

    public static function employees(): void
    {
        self::respondWithPage(static fn(int $page, int $perPage, ?string $updatedSince): array =>
            DirectoryService::employees($page, $perPage, $updatedSince)
        );
    }

    public static function assignments(): void
    {
        self::respondWithPage(static fn(int $page, int $perPage, ?string $updatedSince): array =>
            DirectoryService::assignments($page, $perPage, $updatedSince)
        );
    }

    public static function units(): void
    {
        self::respondWithPage(static fn(int $page, int $perPage, ?string $updatedSince): array =>
            DirectoryService::units($page, $perPage, $updatedSince)
        );
    }

    /** @param callable(int, int, ?string): array $fetch */
    private static function respondWithPage(callable $fetch, bool $supportsUpdatedSince = true): void
    {
        $page = self::integerParameter('page', 1, 1000000);
        $perPage = self::integerParameter('per_page', 100, 200);
        $updatedSince = $supportsUpdatedSince ? self::updatedSince() : null;

        try {
            $result = $fetch($page, $perPage, $updatedSince);
        } catch (Throwable $exception) {
            error_log('[API] SIMPEG directory query failed: ' . $exception->getMessage());
            sendResponse(503, 'Data direktori SIMPEG sedang tidak tersedia.');
        }

        sendResponse(200, 'OK', $result['items'], $result['meta']);
    }

    private static function integerParameter(string $name, int $default, int $maximum): int
    {
        if (!array_key_exists($name, $_GET)) {
            return $default;
        }

        $value = $_GET[$name];
        if (!is_string($value) || !ctype_digit($value)) {
            sendResponse(400, "Parameter {$name} harus berupa bilangan bulat positif.");
        }

        $integer = (int) $value;
        if ($integer < 1 || $integer > $maximum) {
            sendResponse(400, "Parameter {$name} di luar batas yang diizinkan.");
        }

        return $integer;
    }

    private static function updatedSince(): ?string
    {
        if (!array_key_exists('updated_since', $_GET)) {
            return null;
        }

        $value = $_GET['updated_since'];
        if (
            !is_string($value)
            || !preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?)?$/D', trim($value))
        ) {
            sendResponse(400, 'updated_since harus berupa tanggal ISO 8601.');
        }

        try {
            $date = new DateTimeImmutable(trim($value));
        } catch (Throwable $exception) {
            sendResponse(400, 'updated_since harus berupa tanggal ISO 8601 yang valid.');
        }

        return $date
            ->setTimezone(new DateTimeZone('Asia/Jakarta'))
            ->format('Y-m-d H:i:s');
    }
}
