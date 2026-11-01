<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/api/global/admin/AdminRbac.php';

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
        [$key, $value] = explode('=', substr($argument, 2), 2);
        $options[$key] = $value;
    }
}

$username = strtolower(trim((string) ($options['username'] ?? '')));
$employeeId = trim((string) ($options['employee-id'] ?? ''));
$fullName = trim((string) ($options['name'] ?? ''));
$password = (string) ($options['password'] ?? '');
$role = strtolower(trim((string) ($options['role'] ?? 'operator')));
$permissionsInput = $options['permissions'] ?? null;
$permissions = $permissionsInput === null
    ? array_keys(AdminRbac::permissions())
    : AdminRbac::normalize(explode(',', (string) $permissionsInput));

if ($employeeId !== '' && !preg_match('/^[A-Za-z0-9._-]{1,50}$/', $employeeId)) {
    fwrite(STDERR, "ID pegawai tidak valid.\n");
    exit(1);
}
if ($employeeId !== '') {
    $username = strtolower($employeeId);
}
if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,79}$/', $username)) {
    fwrite(STDERR, "Username tidak valid.\n");
    exit(1);
}
if ($fullName === '' || strlen($fullName) > 120 || strlen($password) < 12) {
    fwrite(STDERR, "Nama wajib diisi dan password minimal 12 karakter.\n");
    exit(1);
}
if (!preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $role) || $permissions === []) {
    fwrite(STDERR, "Role atau permission tidak valid.\n");
    exit(1);
}

global $pdo;
$statement = $pdo->prepare(
    'INSERT INTO user_sso
        (username, employee_id, full_name, password_hash, role_code, permissions_json, is_active)
     VALUES
        (:username, :employee_id, :full_name, :password_hash, :role_code, :permissions_json, 1)'
);

try {
    $statement->execute([
        'username' => $username,
        'employee_id' => $employeeId !== '' ? $employeeId : null,
        'full_name' => $fullName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role_code' => $role,
        'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
} catch (PDOException $exception) {
    fwrite(STDERR, ((string) ($exception->errorInfo[1] ?? '') === '1062')
        ? "Username sudah terdaftar.\n"
        : "User gagal dibuat.\n");
    exit(1);
}

fwrite(STDOUT, "User SSO berhasil dibuat: {$username}\n");
