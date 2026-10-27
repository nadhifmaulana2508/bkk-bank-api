<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/AoService.php';
require_once dirname(__DIR__, 3) . '/global/auth/SsoAuthenticator.php';
require_once dirname(__DIR__, 4) . '/helpers/response.php';

final class AoController
{
    public static function getAoByOffice(): void
    {
        SsoAuthenticator::user();

        $typeAo = (string)($_GET['type_ao'] ?? '');
        $kodeKantor = trim((string)($_GET['kode_kantor'] ?? ''));

        if (!$typeAo || !$kodeKantor) {
            sendResponse(400, "Parameter 'type_ao' (dana/kredit/remedial) dan 'kode_kantor' wajib diisi.");
        }

        if (!in_array($typeAo, ['dana', 'kredit', 'remedial'], true)) {
            sendResponse(400, 'Tipe AO tidak valid. Pilih: dana, kredit, atau remedial.');
        }

        $data = AoService::listByTypeAndOffice($typeAo, $kodeKantor);

        sendResponse(200, 'Berhasil mengambil data AO ' . strtoupper($typeAo), $data);
    }
}
