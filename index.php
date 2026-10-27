<?php
declare(strict_types=1);

// =========================================
// 🔥 PENGAMAN CORS (WAJIB DI PALING ATAS)
// =========================================
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Employee-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// =========================================

require_once __DIR__ . '/api/routes.php';
