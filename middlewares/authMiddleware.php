<?php
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';

$SECRET_KEY = "SSO_BKK_SECRET_2026";

function getBearerToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) return null;

    if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    return null;
}

function auth() {
    global $SECRET_KEY;

    $token = getBearerToken();
    if (!$token) {
        sendResponse(401, "Unauthorized");
        exit;
    }

    $decoded = jwt_decode($token, $SECRET_KEY);
    if (!$decoded) {
        sendResponse(401, "Invalid token");
        exit;
    }

    return $decoded;
}
