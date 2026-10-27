<?php
declare(strict_types=1);

function require_auth(): array {
    $user = current_user();
    if (!$user || !current_token()) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect_to('index.php');
    }
    return $user;
}
