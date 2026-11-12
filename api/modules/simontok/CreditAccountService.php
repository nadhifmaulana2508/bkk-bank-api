<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/bank_api_db.php';
require_once dirname(__DIR__) . '/simpeg/EmployeeService.php';
require_once dirname(__DIR__, 2) . '/global/auth/SsoAuthenticator.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';

final class CreditAccountService
{
    /**
     * @return array{branch_filter:?string, user_branch:string, is_central:bool}
     */
    public static function resolveAccess(?string $requestedBranch = null): array
    {
        $user = SsoAuthenticator::user();
        $employee = EmployeeService::findActiveByIdPeg((string) ($user['id_peg'] ?? ''));
        $userBranch = is_array($employee) ? trim((string) ($employee['kode'] ?? '')) : '';

        if (!preg_match('/^\d{3}$/', $userBranch)) {
            sendResponse(403, 'Kode cabang user tidak tersedia.');
        }

        $centralCodes = array_values(array_filter(array_map(
            static fn(string $code): string => trim($code),
            explode(',', (string) app_env_value('BANK_API_CENTRAL_BRANCH_CODES', '000'))
        ), static fn(string $code): bool => preg_match('/^\d{3}$/', $code) === 1));
        $isCentral = in_array($userBranch, $centralCodes, true);

        if (!$isCentral && $requestedBranch !== null && $requestedBranch !== $userBranch) {
            sendResponse(403, 'User hanya boleh mengakses rekening dari cabangnya.');
        }

        return [
            'branch_filter' => $isCentral ? $requestedBranch : $userBranch,
            'user_branch' => $userBranch,
            'is_central' => $isCentral,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $query, ?string $branchCode, int $limit): array
    {
        $access = self::resolveAccess($branchCode);
        $pdo = BankApiDatabase::bank();
        $conditions = [];
        $params = [
            'account_query' => $query . '%',
            'customer_query' => '%' . $query . '%',
        ];

        $conditions[] = '(k.no_rekening LIKE :account_query OR k.nama_nasabah LIKE :customer_query)';
        if ($access['branch_filter'] !== null) {
            $conditions[] = 'k.kode_cabang = :kode_cabang';
            $params['kode_cabang'] = $access['branch_filter'];
        }

        $stmt = $pdo->prepare('
            SELECT
                k.kode_cabang,
                k.no_rekening,
                k.nasabah_id,
                k.nama_nasabah,
                k.status_nikah,
                k.plafon,
                COALESCE((
                    SELECT COUNT(*)
                    FROM list_agunan a
                    WHERE a.kode_cabang = k.kode_cabang
                      AND a.no_rekening = k.no_rekening
                ), 0) AS collateral_count
            FROM list_kredit k
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY k.nama_nasabah ASC, k.no_rekening ASC
            LIMIT ' . $limit
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return self::mapAccounts($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function detail(string $accountId): ?array
    {
        $identity = self::decodeAccountId($accountId);
        if ($identity === null) {
            return null;
        }

        $access = self::resolveAccess($identity['branch_code']);
        $pdo = BankApiDatabase::bank();
        $stmt = $pdo->prepare('
            SELECT
                k.kode_cabang,
                k.no_rekening
            FROM list_kredit k
            WHERE k.kode_cabang = :kode_cabang
              AND k.no_rekening = :no_rekening
            LIMIT 1
        ');
        $stmt->execute([
            'kode_cabang' => $identity['branch_code'],
            'no_rekening' => $identity['account_number'],
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $collaterals = self::collateralsFor(
            (string) $row['kode_cabang'],
            (string) $row['no_rekening']
        );

        return [
            'account_id' => self::encodeAccountId((string) $row['kode_cabang'], (string) $row['no_rekening']),
            'account_number' => (string) $row['no_rekening'],
            'collateral_count' => count($collaterals),
            'collaterals' => $collaterals,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function collaterals(string $accountId): array
    {
        $identity = self::decodeAccountId($accountId);
        if ($identity === null) {
            return [];
        }

        self::resolveAccess($identity['branch_code']);

        return self::collateralsFor($identity['branch_code'], $identity['account_number']);
    }

    /**
     * @return array<int, array{code:string,name:string,is_active:bool}>
     */
    public static function branches(bool $activeOnly): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $stmt = BankApiDatabase::branch()->query(
            'SELECT kode_kantor, nama_kantor, is_active
             FROM kode_kantor ' . $where . '
             ORDER BY CAST(kode_kantor AS UNSIGNED), kode_kantor'
        );

        return array_map(static function (array $row): array {
            return [
                'code' => (string) $row['kode_kantor'],
                'name' => (string) $row['nama_kantor'],
                'is_active' => (bool) $row['is_active'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function mapAccounts(array $rows): array
    {
        $codes = array_values(array_unique(array_map(
            static fn(array $row): string => (string) $row['kode_cabang'],
            $rows
        )));
        $branches = self::branchMap($codes);

        return array_map(static function (array $row) use ($branches): array {
            $code = (string) $row['kode_cabang'];
            return [
                'account_id' => self::encodeAccountId($code, (string) $row['no_rekening']),
                'account_number' => (string) $row['no_rekening'],
                'customer_id' => (string) $row['nasabah_id'],
                'customer_name' => (string) $row['nama_nasabah'],
                'marital_status' => self::mapMaritalStatus($row['status_nikah'] ?? null),
                'collateral_count' => (int) $row['collateral_count'],
                'plafond' => $row['plafon'] !== null ? (string) $row['plafon'] : null,
                'branch' => $branches[$code] ?? [
                    'code' => $code,
                    'name' => null,
                    'is_active' => false,
                ],
                'selectable' => true,
            ];
        }, $rows);
    }

    /**
     * @param array<int, string> $codes
     * @return array<string, array{code:string,name:string,is_active:bool}>
     */
    private static function branchMap(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($codes) as $index => $code) {
            $key = 'branch_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $code;
        }

        $stmt = BankApiDatabase::branch()->prepare(
            'SELECT kode_kantor, nama_kantor, is_active
             FROM kode_kantor
             WHERE kode_kantor IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = (string) $row['kode_kantor'];
            $map[$code] = [
                'code' => $code,
                'name' => (string) $row['nama_kantor'],
                'is_active' => (bool) $row['is_active'],
            ];
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collateralsFor(string $branchCode, string $accountNumber): array
    {
        $stmt = BankApiDatabase::bank()->prepare('
            SELECT
                agunan_id,
                jenis_agunan,
                ikatan_agunan,
                nilai_agunan,
                deskripsi_ringkas,
                kode_cabang,
                no_rekening
            FROM list_agunan
            WHERE kode_cabang = :kode_cabang
              AND no_rekening = :no_rekening
            ORDER BY agunan_id ASC
        ');
        $stmt->execute([
            'kode_cabang' => $branchCode,
            'no_rekening' => $accountNumber,
        ]);

        return array_map(static function (array $row): array {
            $typeName = trim((string) ($row['jenis_agunan'] ?? ''));
            $typeCode = $typeName === ''
                ? null
                : strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $typeName));

            return [
                'collateral_id' => (string) $row['agunan_id'],
                'collateral_code' => (string) $row['agunan_id'],
                'type_code' => $typeCode,
                'type_name' => $typeName,
                'description' => $row['deskripsi_ringkas'] !== null ? (string) $row['deskripsi_ringkas'] : null,
                'legal_reference' => $row['ikatan_agunan'] !== null ? (string) $row['ikatan_agunan'] : null,
                'value' => $row['nilai_agunan'] !== null ? (string) $row['nilai_agunan'] : null,
                'account_id' => self::encodeAccountId((string) $row['kode_cabang'], (string) $row['no_rekening']),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function mapMaritalStatus(mixed $value): ?string
    {
        $status = trim((string) ($value ?? ''));
        if ($status === '') {
            return null;
        }

        $normalized = strtolower((string) preg_replace('/\\s+/', ' ', $status));

        return match ($normalized) {
            'menikah', 'married' => 'married',
            'single', 'belum menikah', 'belum kawin' => 'single',
            'cerai hidup', 'divorced' => 'divorced',
            'cerai mati', 'widowed' => 'widowed',
            default => $status,
        };
    }

    private static function encodeAccountId(string $branchCode, string $accountNumber): string
    {
        return rtrim(strtr(base64_encode($branchCode . "\0" . $accountNumber), '+/', '-_'), '=');
    }

    /**
     * @return array{branch_code:string,account_number:string}|null
     */
    private static function decodeAccountId(string $accountId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{8,120}$/', $accountId)) {
            return null;
        }

        $padding = strlen($accountId) % 4;
        if ($padding === 1) {
            return null;
        }
        if ($padding > 0) {
            $accountId .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($accountId, '-_', '+/'), true);
        if (!is_string($decoded)) {
            return null;
        }

        [$branchCode, $accountNumber] = array_pad(explode("\0", $decoded, 2), 2, null);
        if (
            !is_string($branchCode)
            || !preg_match('/^\d{3}$/', $branchCode)
            || !is_string($accountNumber)
            || $accountNumber === ''
            || strlen($accountNumber) > 32
        ) {
            return null;
        }

        return ['branch_code' => $branchCode, 'account_number' => $accountNumber];
    }
}
