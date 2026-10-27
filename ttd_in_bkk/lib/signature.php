<?php
declare(strict_types=1);

function get_signature_profile(PDO $pdo, string $idPeg): ?array {
    $st = $pdo->prepare('SELECT * FROM signature_profiles WHERE id_peg=:id LIMIT 1');
    $st->execute(['id'=>$idPeg]);
    return $st->fetch() ?: null;
}

function get_signature_security(PDO $pdo, string $idPeg): ?array {
    $st = $pdo->prepare('SELECT * FROM signature_security WHERE id_peg=:id LIMIT 1');
    $st->execute(['id'=>$idPeg]);
    return $st->fetch() ?: null;
}

function log_signature(PDO $pdo, string $idPeg, string $action, string $status, ?string $docId=null, string $application='TTD IN BKK'): void {
    $st = $pdo->prepare("INSERT INTO signature_logs(id_peg,application,document_id,action,status,ip_address,user_agent)
                        VALUES(:id,:app,:doc,:act,:status,:ip,:ua)");
    $st->execute([
        'id'=>$idPeg,'app'=>$application,'doc'=>$docId,'act'=>$action,'status'=>$status,
        'ip'=>client_ip(),'ua'=>user_agent()
    ]);
}

function store_signature_image(string $dataUrl, string $idPeg): string {
    if (!preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $dataUrl, $m)) {
        throw new RuntimeException('Format TTD tidak valid.');
    }
    $raw = base64_decode($m[2], true);
    if ($raw === false || strlen($raw) < 100 || strlen($raw) > 3*1024*1024) {
        throw new RuntimeException('Ukuran TTD tidak valid.');
    }

    $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
        throw new RuntimeException('File harus PNG atau JPG.');
    }

    $dir = dirname(__DIR__) . '/storage/signatures';
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    // Keep old signature files available so a signed document can retain its TTD snapshot.
    $name = hash('sha256', $idPeg . '|' . hash('sha256', $raw)) . '.png';
    $path = $dir . '/' . $name;

    if (!render_signature_png($raw, $path)) {
        file_put_contents($path, $raw);
    } else {
        // render_signature_png already saved the normalized PNG.
    }

    return 'storage/signatures/' . $name;
}

function render_signature_png(string $raw, string $path): bool {
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $src = @imagecreatefromstring($raw);
    if (!$src) {
        return false;
    }

    $width = imagesx($src);
    $height = imagesy($src);
    $dst = imagecreatetruecolor($width, $height);
    imagealphablending($dst, true);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $cornerPixels = [
        imagecolorsforindex($src, imagecolorat($src, 0, 0)),
        imagecolorsforindex($src, imagecolorat($src, $width - 1, 0)),
        imagecolorsforindex($src, imagecolorat($src, 0, $height - 1)),
        imagecolorsforindex($src, imagecolorat($src, $width - 1, $height - 1)),
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
            $pixel = imagecolorsforindex($src, imagecolorat($src, $x, $y));
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
            $ink = imagecolorallocatealpha($dst, 0, 0, 0, $alpha);
            imagesetpixel($dst, $x, $y, $ink);
        }
    }

    imagesavealpha($dst, false);
    $saved = imagepng($dst, $path, 6);
    imagedestroy($src);
    imagedestroy($dst);
    return $saved;
}

function normalize_signature_file(string $path): bool {
    if (!is_file($path)) {
        return false;
    }

    $raw = file_get_contents($path);
    return $raw !== false && render_signature_png($raw, $path);
}
