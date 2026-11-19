<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/api/global/admin/AdminSession.php';
require_once dirname(__DIR__) . '/helpers/webPath.php';

AdminSession::start();
$docsBaseUrl = web_script_base();
if (!AdminSession::isAuthenticated()) {
    header('Location: ' . rtrim(dirname($docsBaseUrl), '/') . '/admin/overview');
    exit;
}

if (!AdminSession::hasPermission('docs.global')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Akun ini belum memiliki permission untuk mengunduh dokumentasi API.';
    exit;
}

$repositoryRoot = dirname(__DIR__);
$markdownFiles = [];
foreach (['docs', 'api', 'admin'] as $directory) {
    $scanRoot = realpath($repositoryRoot . DIRECTORY_SEPARATOR . $directory);
    if ($scanRoot === false || !is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->isLink() || strtolower($fileInfo->getExtension()) !== 'md') {
            continue;
        }

        $realPath = $fileInfo->getRealPath();
        if ($realPath === false || !str_starts_with($realPath, $scanRoot . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $markdownFiles[] = $realPath;
    }
}

sort($markdownFiles, SORT_NATURAL | SORT_FLAG_CASE);
$bundle = "# Dokumentasi Bank API BKK\n\nDokumentasi ini digabung dari file Markdown repository.\n";
foreach ($markdownFiles as $markdownFile) {
    $content = @file_get_contents($markdownFile);
    if (!is_string($content)) {
        error_log('[DOCS] File Markdown tidak dapat dibaca: ' . $markdownFile);
        continue;
    }

    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($markdownFile, strlen($repositoryRoot) + 1));
    $bundle .= "\n\n---\n\n## {$relativePath}\n\n" . rtrim($content) . "\n";
}

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="bank-api-documentation.md"');
header('Content-Length: ' . strlen($bundle));
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
echo $bundle;
