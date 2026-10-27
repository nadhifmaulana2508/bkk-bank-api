<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeService.php';
require_once dirname(__DIR__, 4) . '/helpers/response.php';

final class EmployeeController
{
    public static function search(): void
    {
        $idPeg = trim((string)($_GET['id_peg'] ?? ''));
        $name = trim((string)($_GET['nama'] ?? ''));
        if ($idPeg === '' && $name === '') {
            sendResponse(400, "Parameter 'id_peg' atau 'nama' wajib diisi.");
        }

        $data = EmployeeService::searchActive(
            $idPeg !== '' ? $idPeg : null,
            $name !== '' ? $name : null
        );

        sendResponse(200, 'OK', $data);
    }
}
