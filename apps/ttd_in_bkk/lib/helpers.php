<?php
declare(strict_types=1);

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string {
    $base = rtrim((string)envv('APP_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect_to(string $path): never {
    header('Location: ' . base_url($path));
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = compact('type', 'message');
}

function pull_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function current_token(): ?string {
    return $_SESSION['sso_token'] ?? null;
}

function json_out(int $status, array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): ?string {
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function user_agent(): string {
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}
