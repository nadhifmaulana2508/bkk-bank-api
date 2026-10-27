<?php

function sendResponse(int $status, string $message, $data = null): void {
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $payload = [
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        $json = '{"status":500,"message":"Gagal membuat response JSON.","data":null}';
    }

    echo $json;
    exit;
}

?>
