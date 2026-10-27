<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/helpers/response.php';

final class AoController
{
    public static function getAoByCabang(): void
    {
        global $pdo;

        $tipe = $_GET['tipe'] ?? '';
        $kodeCabang = $_GET['kode_cabang'] ?? '';

        if (!$tipe || !$kodeCabang) {
            sendResponse(400, "Parameter 'tipe' (dana/kredit/remedial) dan 'kode_cabang' wajib diisi.");
        }

        $searchTipe = match ($tipe) {
            'dana' => 'AO Dana',
            'kredit' => 'AO Kredit',
            'remedial' => 'AO Remedial',
            default => null,
        };

        if ($searchTipe === null) {
            sendResponse(400, 'Tipe AO tidak valid. Pilih: dana, kredit, atau remedial.');
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
            AND k.kode_cabang = :kode_cabang
            AND mj.nama_jabatan = :search_tipe
            ORDER BY p.nama ASC
        ');
        $stmt->execute([
            'kode_cabang' => $kodeCabang,
            'search_tipe' => $searchTipe,
        ]);

        sendResponse(200, 'Berhasil mengambil data AO ' . strtoupper($tipe), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
