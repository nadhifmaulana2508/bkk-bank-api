<?php
declare(strict_types=1);

function get_signature_profile(PDO $pdo, string $idPeg): ?array
{
    $statement = $pdo->prepare('SELECT * FROM signature_profiles WHERE id_peg = :id LIMIT 1');
    $statement->execute(['id' => $idPeg]);
    return $statement->fetch() ?: null;
}

function get_signature_security(PDO $pdo, string $idPeg): ?array
{
    $statement = $pdo->prepare('SELECT * FROM signature_security WHERE id_peg = :id LIMIT 1');
    $statement->execute(['id' => $idPeg]);
    return $statement->fetch() ?: null;
}

function log_signature(
    PDO $pdo,
    string $idPeg,
    string $action,
    string $status,
    ?string $documentId = null,
    string $application = 'TTD IN BKK'
): void {
    $statement = $pdo->prepare(
        'INSERT INTO signature_logs
            (id_peg, application, document_id, action, status, ip_address, user_agent)
         VALUES (:id, :app, :doc, :action, :status, :ip, :user_agent)'
    );
    $statement->execute([
        'id' => $idPeg,
        'app' => $application,
        'doc' => $documentId,
        'action' => $action,
        'status' => $status,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

function store_signature_image(string $dataUrl, string $idPeg): string
{
    if (!preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $dataUrl, $matches)) {
        throw new RuntimeException('Format TTD tidak valid.');
    }

    $raw = base64_decode($matches[2], true);
    if ($raw === false || strlen($raw) < 100 || strlen($raw) > 3 * 1024 * 1024) {
        throw new RuntimeException('Ukuran TTD tidak valid.');
    }

    $imageInfo = @getimagesizefromstring($raw);
    if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
        throw new RuntimeException('File harus PNG atau JPG.');
    }

    $directory = dirname(__DIR__, 3) . '/storage/signatures';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Storage TTD tidak tersedia.');
    }

    $name = hash('sha256', $idPeg . '|' . hash('sha256', $raw)) . '.png';
    $path = $directory . '/' . $name;

    if (!render_signature_png($raw, $path) && file_put_contents($path, $raw) === false) {
        throw new RuntimeException('Gagal menyimpan TTD.');
    }

    return 'storage/signatures/' . $name;
}

function render_signature_png(string $raw, string $path): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $source = @imagecreatefromstring($raw);
    if (!$source) {
        return false;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $target = imagecreatetruecolor($width, $height);
    imagealphablending($target, true);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);

    $cornerPixels = [
        imagecolorsforindex($source, imagecolorat($source, 0, 0)),
        imagecolorsforindex($source, imagecolorat($source, $width - 1, 0)),
        imagecolorsforindex($source, imagecolorat($source, 0, $height - 1)),
        imagecolorsforindex($source, imagecolorat($source, $width - 1, $height - 1)),
    ];
    $blackCornerCount = 0;
    foreach ($cornerPixels as $corner) {
        if ($corner['alpha'] < 10 && $corner['red'] < 20 && $corner['green'] < 20 && $corner['blue'] < 20) {
            $blackCornerCount++;
        }
    }
    $legacyBlackBackground = $blackCornerCount >= 3;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $pixel = imagecolorsforindex($source, imagecolorat($source, $x, $y));
            if ($legacyBlackBackground && $pixel['alpha'] < 10 && $pixel['red'] < 20 && $pixel['green'] < 20 && $pixel['blue'] < 20) {
                continue;
            }

            $sourceOpacity = 1 - ($pixel['alpha'] / 127);
            $brightness = ($pixel['red'] + $pixel['green'] + $pixel['blue']) / 3;
            $inkStrength = ((255 - $brightness) / 255) * $sourceOpacity;
            if ($inkStrength < .06) {
                continue;
            }

            $alpha = (int)round(127 * (1 - min(1, $inkStrength)));
            $ink = imagecolorallocatealpha($target, 0, 0, 0, $alpha);
            imagesetpixel($target, $x, $y, $ink);
        }
    }

    imagesavealpha($target, false);
    $saved = imagepng($target, $path, 6);
    imagedestroy($source);
    imagedestroy($target);
    return $saved;
}

function normalize_signature_file(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }

    $raw = file_get_contents($path);
    return $raw !== false && render_signature_png($raw, $path);
}
