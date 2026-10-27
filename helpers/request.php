<?php
declare(strict_types=1);

require_once __DIR__ . '/response.php';

function jsonRequestBody(): array
{
    $maxBytes = 1024 * 1024;
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        sendResponse(413, 'Ukuran request terlalu besar.');
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    if (strlen($raw) > $maxBytes) {
        sendResponse(413, 'Ukuran request terlalu besar.');
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        sendResponse(400, 'Body JSON tidak valid.');
    }

    if (!is_array($data)) {
        sendResponse(400, 'Body JSON harus berupa object.');
    }

    return $data;
}
