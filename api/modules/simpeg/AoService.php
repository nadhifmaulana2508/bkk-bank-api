<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/db.php';

final class AoService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByTypeAndOffice(string $type, string $kodeKantor): array
    {
        global $pdo;

        $searchType = match ($type) {
            'dana' => 'AO Dana',
            'kredit' => 'AO Kredit',
            'remedial' => 'AO Remedial',
            default => null,
        };

        if ($searchType === null) {
            return [];
        }

        $params = ['search_tipe' => $searchType];
        if ($kodeKantor === '000') {
            $officeCondition = 'CAST(k.kode_cabang AS UNSIGNED) BETWEEN 1 AND 28';
        } else {
            $officeCondition = 'k.kode_cabang = :kode_kantor';
            $params['kode_kantor'] = $kodeKantor;
        }

        $stmt = $pdo->prepare('
            SELECT
                j.id_peg AS employee_id,
                p.nama AS full_name,
                k.kode_cabang,
                k.nama_kantor AS branch_name,
                mj.nama_jabatan AS job_position
            FROM tb_jabatan j
            INNER JOIN tb_pegawai p ON j.id_peg = p.id_peg
            INNER JOIN tb_master_jabatan mj
                ON CAST(j.kode_jabatan AS CHAR) = CAST(mj.kode_jabatan AS CHAR)
            LEFT JOIN tb_kantor k
                ON j.unit_kerja = k.kode_kantor_detail
            WHERE j.status_jab = \'Aktif\'
            AND ' . $officeCondition . '
            AND mj.nama_jabatan = :search_tipe
            ORDER BY CAST(k.kode_cabang AS UNSIGNED) ASC, p.nama ASC
        ');
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
