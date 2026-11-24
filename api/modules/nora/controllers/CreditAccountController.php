<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/CreditAccountService.php';
require_once dirname(__DIR__, 3) . '/Core/Http/ApiRequest.php';
require_once dirname(__DIR__, 4) . '/helpers/response.php';

final class CreditAccountController
{
    public static function search(): void
    {
        $query = self::queryString('query');
        $branchCode = self::optionalBranchCode('branch_code');
        $limit = self::limit();

        if (strlen($query) < 3) {
            sendResponse(400, "Parameter 'query' minimal 3 karakter.");
        }
        if (preg_match('/^[\p{L}\p{N} .,_\/-]+$/u', $query) !== 1) {
            sendResponse(400, "Parameter 'query' mengandung karakter yang tidak didukung.");
        }

        $data = CreditAccountService::search($query, $branchCode, $limit);
        sendResponse(200, 'OK', $data, [
            'limit' => $limit,
            'count' => count($data),
        ]);
    }

    public static function detail(): void
    {
        $accountId = ApiRequest::route('account_id');
        if (!is_string($accountId) || $accountId === '') {
            sendResponse(404, 'Rekening tidak ditemukan.');
        }

        $data = CreditAccountService::detail($accountId);
        if ($data === null) {
            sendResponse(404, 'Rekening tidak ditemukan.');
        }

        sendResponse(200, 'OK', $data);
    }

    public static function collaterals(): void
    {
        $accountId = ApiRequest::route('account_id');
        if (!is_string($accountId) || $accountId === '') {
            sendResponse(404, 'Rekening tidak ditemukan.');
        }

        $data = CreditAccountService::collaterals($accountId);
        if ($data === [] && CreditAccountService::detail($accountId) === null) {
            sendResponse(404, 'Rekening tidak ditemukan.');
        }

        sendResponse(200, 'OK', $data, ['count' => count($data)]);
    }

    public static function branches(): void
    {
        $active = $_GET['active'] ?? 'true';
        if (!is_string($active) || !in_array(strtolower($active), ['true', 'false'], true)) {
            sendResponse(400, "Parameter 'active' harus true atau false.");
        }

        $data = CreditAccountService::branches(strtolower($active) === 'true');
        sendResponse(200, 'OK', $data, ['count' => count($data)]);
    }

    private static function queryString(string $name): string
    {
        $value = $_GET[$name] ?? null;
        if (!is_string($value)) {
            sendResponse(400, "Parameter '{$name}' wajib diisi.");
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > 100) {
            sendResponse(400, "Parameter '{$name}' wajib diisi dan maksimal 100 karakter.");
        }

        return $value;
    }

    private static function optionalBranchCode(string $name): ?string
    {
        $value = $_GET[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{3}$/', $value)) {
            sendResponse(400, "Parameter '{$name}' harus berupa kode 3 digit.");
        }

        return $value;
    }

    private static function limit(): int
    {
        $value = $_GET['limit'] ?? '20';
        if (!is_string($value) || !preg_match('/^\d+$/', $value)) {
            sendResponse(400, "Parameter 'limit' harus berupa angka.");
        }

        $limit = (int) $value;
        if ($limit < 1 || $limit > 50) {
            sendResponse(400, "Parameter 'limit' harus berada di antara 1 sampai 50.");
        }

        return $limit;
    }
}
