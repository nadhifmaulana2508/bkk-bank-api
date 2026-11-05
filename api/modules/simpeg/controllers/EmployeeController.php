<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeService.php';
require_once dirname(__DIR__, 4) . '/helpers/response.php';

final class EmployeeController
{
    public static function search(): void
    {
        $idPeg = $_GET['id_peg'] ?? null;
        $name = $_GET['nama'] ?? null;
        if (($idPeg !== null && !is_string($idPeg)) || ($name !== null && !is_string($name))) {
            sendResponse(400, "Parameter 'id_peg' atau 'nama' tidak valid.");
        }
        $idPeg = trim((string) $idPeg);
        $name = trim((string) $name);
        if (strlen($idPeg) > 80 || strlen($name) > 80) {
            sendResponse(400, "Parameter pencarian maksimal 80 karakter.");
        }
        if ($idPeg !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $idPeg)) {
            sendResponse(400, "Format 'id_peg' tidak valid.");
        }
        if ($idPeg === '' && $name === '') {
            sendResponse(400, "Parameter 'id_peg' atau 'nama' wajib diisi.");
        }

        $data = EmployeeService::searchDirectory(
            $idPeg !== '' ? $idPeg : null,
            $name !== '' ? $name : null
        );

        sendResponse(200, 'OK', $data);
    }
}
