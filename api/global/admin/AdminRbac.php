<?php
declare(strict_types=1);

final class AdminRbac
{
    /** @return array<string, string> */
    public static function permissions(): array
    {
        return [
            'registry.view' => 'Melihat registry aplikasi',
            'registry.manage' => 'Membuat, mengubah, dan merotasi token',
            'audit.view' => 'Melihat audit hit API',
            'recap.view' => 'Melihat rekap penggunaan API',
            'docs.global' => 'Membuka Swagger API Global',
            'docs.simontok' => 'Membuka Swagger API Simontok',
            'docs.monbis' => 'Membuka Swagger API Monbis',
            'users.manage' => 'Mengelola user dan permission',
        ];
    }

    /** @param mixed $permissions @return array<int, string> */
    public static function normalize($permissions): array
    {
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($permissions)) {
            return [];
        }

        $allowed = self::permissions();
        $normalized = [];
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }
            $permission = strtolower(trim((string) $permission));
            if (isset($allowed[$permission])) {
                $normalized[$permission] = true;
            }
        }

        return array_keys($normalized);
    }

    /** @param array<int, string> $permissions */
    public static function has(array $permissions, string $permission): bool
    {
        return in_array($permission, $permissions, true);
    }
}
