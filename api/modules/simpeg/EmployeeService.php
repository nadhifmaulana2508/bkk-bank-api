<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';

final class EmployeeService
{
    /**
     * Return the active employee profile used by the whoami response.
     *
     * @return array<string, mixed>|false
     */
    public static function findActiveByIdPeg(string $idPeg): array|false
    {
        global $pdo;

        $stmt = $pdo->prepare('
            SELECT
                k.kode_cabang AS kode,
                j.id_peg AS employee_id,
                p.nama AS full_name,
                p.email,
                p.telp,
                k.nama_kantor AS branch_name,
                mj.nama_unit_kerja AS unit_kerja,
                mj.nama_jabatan AS job_position,
                mj.level,
                mj.group_jabatan
            FROM tb_jabatan j
            INNER JOIN tb_pegawai p ON j.id_peg = p.id_peg
            INNER JOIN tb_master_jabatan mj
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
            LEFT JOIN tb_kantor k
                ON j.unit_kerja = k.kode_kantor_detail
            WHERE j.status_jab = \'Aktif\' AND p.id_peg = :id_peg
            LIMIT 1
        ');
        $stmt->execute(['id_peg' => $idPeg]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
