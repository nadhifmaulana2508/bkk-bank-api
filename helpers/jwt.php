<?php

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    if (!is_string($data) || $data === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $data)) {
        return false;
    }

    $remainder = strlen($data) % 4;
    if ($remainder === 1) {
        return false;
    }

    if ($remainder > 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($data, '-_', '+/'), true);
}

function jwt_encode($payload, $secret) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];

    $header_enc = base64url_encode(json_encode($header));
    $payload_enc = base64url_encode(json_encode($payload));

    $signature = hash_hmac('sha256', "$header_enc.$payload_enc", $secret, true);
    $signature_enc = base64url_encode($signature);

    return "$header_enc.$payload_enc.$signature_enc";
}

function jwt_decode($jwt, $secret) {
    if (!is_string($jwt) || strlen($jwt) > 4096 || !is_string($secret) || $secret === '') {
        return false;
    }

    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;

    list($header_enc, $payload_enc, $signature_enc) = $parts;

    $header_json = base64url_decode($header_enc);
    $payload_json = base64url_decode($payload_enc);
    if ($header_json === false || $payload_json === false) {
        return false;
    }

    $header = json_decode($header_json, true);
    $payload = json_decode($payload_json, true);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? null) !== 'HS256') {
        return false;
    }

    $valid_signature = base64url_encode(
        hash_hmac('sha256', "$header_enc.$payload_enc", $secret, true)
    );

    if (!hash_equals($valid_signature, $signature_enc)) {
        return false;
    }

    $now = time();
    if (array_key_exists('exp', $payload) && (!is_numeric($payload['exp']) || (int) $payload['exp'] <= $now)) {
        return false;
    }

    if (array_key_exists('nbf', $payload) && (!is_numeric($payload['nbf']) || (int) $payload['nbf'] > $now)) {
        return false;
    }

    return $payload;
}
