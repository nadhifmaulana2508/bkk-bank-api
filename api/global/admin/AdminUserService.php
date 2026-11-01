<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/modules/simpeg/EmployeeService.php';
require_once __DIR__ . '/AdminRbac.php';
require_once __DIR__ . '/AdminAuditLogger.php';
require_once __DIR__ . '/AdminSession.php';

final class AdminUserService
{
    /** @return array<string, mixed> */
    public static function dashboard(): array
    {
        return [
            'users' => self::users(),
            'permissions' => AdminRbac::permissions(),
            'csrf_token' => AdminSession::csrfToken(),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function create(array $payload): array
    {
        $employeeId = self::employeeId($payload['employee_id'] ?? null);
        self::assertEmployee($employeeId);
        $username = self::username($employeeId ?? ($payload['username'] ?? null));
        $fullName = self::fullName($payload['full_name'] ?? null);
        $email = self::email($payload['email'] ?? null);
        $roleCode = self::roleCode($payload['role_code'] ?? null);
        $permissions = self::permissions($payload['permissions'] ?? []);
        $password = self::password($payload['password'] ?? null, true);

        try {
            $statement = self::pdo()->prepare(
                'INSERT INTO user_sso
                    (username, employee_id, full_name, email, password_hash, role_code, permissions_json, is_active)
                 VALUES
                    (:username, :employee_id, :full_name, :email, :password_hash, :role_code, :permissions_json, 1)'
            );
            $statement->execute([
                'username' => $username,
                'employee_id' => $employeeId,
                'full_name' => $fullName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role_code' => $roleCode,
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (PDOException $exception) {
            if ((string) ($exception->errorInfo[1] ?? '') === '1062') {
                throw new InvalidArgumentException('Username atau email sudah terdaftar.');
            }
            throw $exception;
        }

        $id = (int) self::pdo()->lastInsertId();
        AdminAuditLogger::record(self::pdo(), 'user.created', null, ['username' => $username, 'role_code' => $roleCode]);
        return ['user' => self::find($id)];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function update(array $payload): array
    {
        $id = self::id($payload['id'] ?? null);
        $current = self::find($id);
        $employeeId = self::employeeId(
            array_key_exists('employee_id', $payload) ? $payload['employee_id'] : ($current['employee_id'] ?? null)
        );
        self::assertEmployee($employeeId);
        $isActive = filter_var($payload['is_active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            throw new InvalidArgumentException('Status user tidak valid.');
        }
        if (!$isActive && $id === AdminSession::user()['id']) {
            throw new InvalidArgumentException('User yang sedang login tidak dapat dinonaktifkan.');
        }

        $fullName = self::fullName($payload['full_name'] ?? null);
        $email = self::email($payload['email'] ?? null);
        $roleCode = self::roleCode($payload['role_code'] ?? null);
        $permissions = self::permissions($payload['permissions'] ?? []);
        $password = trim((string) ($payload['password'] ?? ''));

        $fields = [
            'employee_id = :employee_id',
            'full_name = :full_name',
            'email = :email',
            'role_code = :role_code',
            'permissions_json = :permissions_json',
            'is_active = :is_active',
        ];
        $params = [
            'employee_id' => $employeeId,
            'full_name' => $fullName,
            'email' => $email,
            'role_code' => $roleCode,
            'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => $isActive ? 1 : 0,
            'id' => $id,
        ];
        if ($password !== '') {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash(self::password($password, true), PASSWORD_DEFAULT);
        }

        try {
            $statement = self::pdo()->prepare('UPDATE user_sso SET ' . implode(', ', $fields) . ' WHERE id = :id');
            $statement->execute($params);
        } catch (PDOException $exception) {
            if ((string) ($exception->errorInfo[1] ?? '') === '1062') {
                throw new InvalidArgumentException('Username, email, atau pegawai sudah terdaftar.');
            }
            throw $exception;
        }
        AdminAuditLogger::record(self::pdo(), 'user.updated', null, [
            'username' => $current['username'],
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);

        return ['user' => self::find($id)];
    }

    /** @return array<int, array<string, mixed>> */
    private static function users(): array
    {
        $statement = self::pdo()->query(
            'SELECT id, username, employee_id, full_name, email, role_code, permissions_json,
                    is_active, last_login_at, created_at
             FROM user_sso
             ORDER BY username ASC'
        );
        $users = [];
        foreach ($statement as $row) {
            $users[] = self::serialize($row);
        }
        return $users;
    }

    /** @return array<string, mixed> */
    private static function find(int $id): array
    {
        $statement = self::pdo()->prepare(
            'SELECT id, username, employee_id, full_name, email, role_code, permissions_json,
                    is_active, last_login_at, created_at
             FROM user_sso WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException('User tidak ditemukan.');
        }
        return self::serialize($row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function serialize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'employee_id' => $row['employee_id'] !== null ? (string) $row['employee_id'] : null,
            'full_name' => (string) $row['full_name'],
            'email' => (string) ($row['email'] ?? ''),
            'role_code' => (string) $row['role_code'],
            'permissions' => AdminRbac::normalize($row['permissions_json'] ?? []),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'last_login_at' => $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /** @param mixed $value */
    private static function id($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('ID user tidak valid.');
        }
        return (int) $id;
    }

    /** @param mixed $value */
    private static function username($value): string
    {
        $username = strtolower(trim((string) $value));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $username)) {
            throw new InvalidArgumentException('Username hanya boleh berisi huruf kecil, angka, titik, strip, atau underscore; minimal 3 karakter.');
        }
        return $username;
    }

    /** @param mixed $value */
    private static function employeeId($value): ?string
    {
        $employeeId = trim((string) $value);
        if ($employeeId === '') {
            return null;
        }
        if (strlen($employeeId) > 50 || !preg_match('/^[A-Za-z0-9._-]+$/', $employeeId)) {
            throw new InvalidArgumentException('ID pegawai tidak valid.');
        }
        return $employeeId;
    }

    private static function assertEmployee(?string $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }
        if (EmployeeService::searchDirectory($employeeId, null) === []) {
            throw new InvalidArgumentException('Pegawai tidak ditemukan atau sudah tidak aktif.');
        }
    }

    /** @param mixed $value */
    private static function fullName($value): string
    {
        $name = trim((string) $value);
        if ($name === '' || strlen($name) > 120) {
            throw new InvalidArgumentException('Nama lengkap wajib diisi dan maksimal 120 karakter.');
        }
        return $name;
    }

    /** @param mixed $value */
    private static function email($value): ?string
    {
        $email = trim((string) $value);
        if ($email === '') {
            return null;
        }
        if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        return $email;
    }

    /** @param mixed $value */
    private static function roleCode($value): string
    {
        $role = strtolower(trim((string) $value));
        if (!preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $role)) {
            throw new InvalidArgumentException('Role user tidak valid.');
        }
        return $role;
    }

    /** @param mixed $value @return array<int, string> */
    private static function permissions($value): array
    {
        $permissions = AdminRbac::normalize($value);
        if ($permissions === []) {
            throw new InvalidArgumentException('Minimal satu permission harus dipilih.');
        }
        return $permissions;
    }

    /** @param mixed $value */
    private static function password($value, bool $required): string
    {
        $password = (string) $value;
        if ($required && strlen($password) < 12) {
            throw new InvalidArgumentException('Password minimal 12 karakter.');
        }
        if ($password !== '' && strlen($password) > 200) {
            throw new InvalidArgumentException('Password terlalu panjang.');
        }
        return $password;
    }

    private static function pdo(): PDO
    {
        global $pdo;
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Koneksi database tidak tersedia.');
        }
        return $pdo;
    }
}
