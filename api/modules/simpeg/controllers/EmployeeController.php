<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeService.php';
require_once dirname(__DIR__, 4) . '/helpers/request.php';
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

    public static function updateLoginStatus(): void
    {
        $input = jsonRequestBody();
        try {
            $data = EmployeeService::updateLoginStatus($input['id_peg'] ?? null, $input['status'] ?? null);
        } catch (InvalidArgumentException $exception) {
            sendResponse(422, $exception->getMessage());
        }

        $name = trim((string) ($data['nama'] ?? '')) ?: $data['id_peg'];
        if ($data['status'] === 'non job') {
            $message = $name . ' ditandai non job dan tidak dapat login ke aplikasi yang menggunakan SSO.';
        } elseif ($data['status'] === '0') {
            $message = $name . ' dinonaktifkan dan tidak dapat login ke aplikasi yang menggunakan SSO.';
        } else {
            $message = 'Status login ' . $name . ' diaktifkan kembali.';
        }

        sendResponse(200, $message, $data);
    }
}
