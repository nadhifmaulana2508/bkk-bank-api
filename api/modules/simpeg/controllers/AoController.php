<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/AoService.php';
require_once dirname(__DIR__, 4) . '/helpers/response.php';

final class AoController
{
    public static function getAoByOffice(): void
    {
        $typeAo = $_GET['type_ao'] ?? null;
        $kodeKantor = $_GET['kode_kantor'] ?? null;
        if (!is_string($typeAo) || !is_string($kodeKantor)) {
            sendResponse(400, "Parameter 'type_ao' dan 'kode_kantor' tidak valid.");
        }
        $typeAo = strtolower(trim($typeAo));
        $kodeKantor = trim($kodeKantor);

        if ($typeAo === '' || $kodeKantor === '') {
            sendResponse(400, "Parameter 'type_ao' (dana/kredit/remedial) dan 'kode_kantor' wajib diisi.");
        }

        if (!in_array($typeAo, ['dana', 'kredit', 'remedial'], true)) {
            sendResponse(400, 'Tipe AO tidak valid. Pilih: dana, kredit, atau remedial.');
        }

        if (!preg_match('/^\d{3}$/', $kodeKantor)) {
            sendResponse(400, "Kode kantor harus 3 digit, misalnya 000 atau 001.");
        }

        $data = AoService::listByTypeAndOffice($typeAo, $kodeKantor);

        sendResponse(200, 'Berhasil mengambil data AO ' . strtoupper($typeAo), $data);
    }
}
