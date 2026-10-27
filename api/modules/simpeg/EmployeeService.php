<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';

final class EmployeeService
{
    /**
     * Search active employee profiles by employee ID and/or name.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function searchActive(
        ?string $idPeg = null,
        ?string $name = null,
        bool $includeProfileFields = false,
        bool $includeBranchCode = false
    ): array
    {
        global $pdo;

        $columns = [
            'j.id_peg AS employee_id',
            'p.nama AS full_name',
        ];
        if ($includeProfileFields) {
            $columns = array_merge($columns, [
                'p.email',
                'p.telp',
                'k.nama_kantor AS branch_name',
                'mj.nama_unit_kerja AS unit_kerja',
                'mj.nama_jabatan AS job_position',
                'mj.level',
                'mj.group_jabatan',
            ]);
            if ($includeBranchCode) {
                array_unshift($columns, 'k.kode_cabang AS kode');
            }
        }

        $conditions = ["j.status_jab = 'Aktif'"];
        $params = [];

        if ($idPeg !== null && $idPeg !== '') {
            $conditions[] = 'p.id_peg = :id_peg';
            $params['id_peg'] = $idPeg;
        }

        if ($name !== null && $name !== '') {
            $conditions[] = 'p.nama LIKE :nama';
            $params['nama'] = '%' . $name . '%';
        }

        $stmt = $pdo->prepare('
            SELECT ' . implode(",\n                ", $columns) . '
            FROM tb_jabatan j
            INNER JOIN tb_pegawai p ON j.id_peg = p.id_peg
            INNER JOIN tb_master_jabatan mj
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
            LEFT JOIN tb_kantor k
                ON j.unit_kerja = k.kode_kantor_detail
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY p.nama ASC
            LIMIT 50
        ');
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return the active employee profile used by the whoami response.
     *
     * @return array<string, mixed>|false
     */
    public static function findActiveByIdPeg(string $idPeg): array|false
    {
        $results = self::searchActive($idPeg, null, true, true);

        return $results[0] ?? false;
    }
}
