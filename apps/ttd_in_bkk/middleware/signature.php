<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function require_signature_ready(PDO $pdo): array {
    $user = require_auth();
    $idPeg = (string)$user['employee_id'];
    $profile = get_signature_profile($pdo, $idPeg);
    $security = get_signature_security($pdo, $idPeg);

    if (!$profile || !$security || ($profile['status'] ?? '') !== 'ACTIVE') {
        flash('warning', 'TTD belum aktif. Silakan registrasi TTD dan buat PIN.');
        redirect_to('register-signature.php');
    }
    return [$user,$profile,$security];
}
