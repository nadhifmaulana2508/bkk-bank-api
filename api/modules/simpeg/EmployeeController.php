<?php
declare(strict_types=1);

require_once __DIR__ . '/EmployeeService.php';
require_once dirname(__DIR__, 2) . '/global/auth/SsoAuthenticator.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';

final class EmployeeController
{
    public static function search(): void
    {
        SsoAuthenticator::user();

        $idPeg = trim((string)($_GET['id_peg'] ?? ''));
        if ($idPeg === '') {
            sendResponse(400, "Parameter 'id_peg' wajib diisi.");
        }

        $data = EmployeeService::findActiveByIdPeg($idPeg);
        if ($data === false) {
            sendResponse(404, 'Pegawai tidak ditemukan');
        }

        sendResponse(200, 'OK', $data);
    }
}
