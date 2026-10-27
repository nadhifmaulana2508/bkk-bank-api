<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/response.php';

class AoController {

    public static function getAoByCabang() {
        global $pdo;

        // // Wajib pakai token JWT biar aman
        // require_once __DIR__ . '/middlewares/authMiddleware.php';
        // $user = auth(); 

        // Tangkap parameter dari URL (?tipe=...&kode_cabang=...)
        $tipe = $_GET['tipe'] ?? ''; 
        $kode_cabang = $_GET['kode_cabang'] ?? '';

        if (!$tipe || !$kode_cabang) {
            sendResponse(400, "Parameter 'tipe' (dana/kredit/remedial) dan 'kode_cabang' wajib diisi.");
            return;
        }

        // 🔥 LOGIKA FILTER BERDASARKAN NAMA JABATAN 🔥
        // Sesuaikan kata kunci persen (%) ini dengan teks yang ada di kolom `nama_jabatan` database kamu.
        $searchTipe = '';
        if ($tipe === 'dana') {
            $searchTipe = 'AO Dana';       // Cocok untuk: "AO Dana", "Staf Dana", dll
        } elseif ($tipe === 'kredit') {
            $searchTipe = 'AO Kredit';     // Cocok untuk: "AO Kredit", "Analis Kredit", dll
        } elseif ($tipe === 'remedial') {
            $searchTipe = 'AO Remedial';   // Cocok untuk: "AO Remedial", "Staf Remedial"
        } else {
            sendResponse(400, "Tipe AO tidak valid. Pilih: dana, kredit, atau remedial.");
            return;
        }

        // Query join mirip seperti di whoami, tapi kita filter jabatannya
        $stmt = $pdo->prepare("
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
            WHERE j.status_jab = 'Aktif'
            AND k.kode_cabang = :kode_cabang
            AND mj.nama_jabatan = :search_tipe
            ORDER BY p.nama ASC
        ");

        $stmt->execute([
            'kode_cabang' => $kode_cabang,
            'search_tipe' => $searchTipe
        ]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(200, "Berhasil mengambil data AO " . strtoupper($tipe), $data);
    }
}