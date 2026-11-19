<?php

declare(strict_types=1);

/*
 * LPGallery – Core filesystem, thumbnail, and JSON sidecar helpers
 * Copyright (C) 2026 Lambert Peeters (GPL-3.0)
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function lpg_bool(mixed $val): bool
{
    return in_array(strtolower(trim((string) $val)), ['true', '1', 'yes', 'on'], true);
}

function lpg_param(string $key): string
{
    if (isset($_POST[$key])) {
        return (string) $_POST[$key];
    }
    return isset($_GET[$key]) ? (string) $_GET[$key] : '';
}

function lpg_esc(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function lpg_notice(string $text): string
{
    return '<p class="lpg-notice"><strong>LPGallery:</strong> ' . lpg_esc($text) . '</p>';
}

function lpg_resolve_folder(string $input): ?string
{
    $clean = trim(str_replace('\\', '/', $input), '/');
    if ($clean === '' || str_contains($clean, '..')) {
        return null;
    }
    if (!str_starts_with($clean, 'userfiles/gallery/')) {
        $clean = 'userfiles/gallery/' . $clean;
    }
    $clean = rtrim($clean, '/');
    if ($clean === 'userfiles/gallery') {
        return null;
    }
    return $clean . '/';
}

function lpg_site_base(): string
{
    global $sn;

    $base = dirname((string) $sn);
    return ($base === '.' || $base === '/' || $base === '\\') ? '' : $base;
}

function lpg_public_url(string $relPath, string $file): string
{
    $segments = array_map('rawurlencode', explode('/', $relPath . $file));
    return lpg_site_base() . '/' . implode('/', $segments);
}

function lpg_stream_url(string $absFile, int $w, int $h): string
{
    $real = realpath($absFile);
    $token = base64_encode($real !== false ? $real : $absFile);
    return lpg_site_base() . '/plugins/LPGallery/stream.php?src=' . rawurlencode($token)
        . '&w=' . $w . '&h=' . $h;
}

function lpg_ensure_dir(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }
    $prev = umask(0);
    $created = @mkdir($dir, 0775, true);
    umask($prev);
    return $created || is_dir($dir);
}

function lpg_write_file(string $path, string $content): bool
{
    $prev = umask(0);
    $bytes = @file_put_contents($path, $content, LOCK_EX);
    if ($bytes !== false) {
        @chmod($path, 0664);
    }
    umask($prev);
    return $bytes !== false;
}

function lpg_scan_images(string $absDir): array
{
    $items = [];
    if (!is_dir($absDir)) {
        return $items;
    }
    $entries = @scandir($absDir);
    if ($entries === false) {
        return $items;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (preg_match('/\.jpe?g$/i', $entry) === 1 && is_file($absDir . $entry)) {
            $items[] = $entry;
        }
    }
    natcasesort($items);
    return array_values($items);
}

function lpg_sidecar_name(string $imageFile): string
{
    return (string) preg_replace('/\.jpe?g$/i', '.json', $imageFile);
}

function lpg_ensure_sidecar(string $absDir, string $imageFile): void
{
    global $plugin_cf;

    $jsonPath = $absDir . lpg_sidecar_name($imageFile);
    if (is_file($jsonPath)) {
        return;
    }

    $defaultKeysRaw = (string) ($plugin_cf['LPGallery']['meta_default_keys'] ?? '');
    $metaFields = [];
    foreach (explode(',', $defaultKeysRaw) as $key) {
        $k = trim($key);
        if ($k !== '') {
            $metaFields[$k] = '';
        }
    }

    $baseTitle = str_replace(['_', '-'], ' ', (string) preg_replace('/\.jpe?g$/i', '', $imageFile));
    $payload = [
        'title' => trim($baseTitle),
        'description' => '',
        'meta' => $metaFields,
        'comments' => [],
    ];

    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
        lpg_write_file($jsonPath, $encoded . "\n");
    }
}

function lpg_read_sidecar(string $jsonPath): array
{
    $fallback = [
        'title' => '',
        'description' => '',
        'meta' => [],
        'comments' => [],
    ];

    if (!is_file($jsonPath)) {
        return $fallback;
    }

    $raw = @file_get_contents($jsonPath);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $fallback;
    }

    return [
        'title' => (string) ($decoded['title'] ?? ''),
        'description' => (string) ($decoded['description'] ?? ''),
        'meta' => is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [],
        'comments' => is_array($decoded['comments'] ?? null) ? $decoded['comments'] : [],
    ];
}

function lpg_append_comment(string $jsonPath, string $author, string $message): void
{
    $data = lpg_read_sidecar($jsonPath);
    $data['comments'][] = [
        'date' => date('Y-m-d H:i'),
        'author' => $author,
        'message' => $message,
    ];

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded !== false) {
        lpg_write_file($jsonPath, $encoded . "\n");
    }
}

function lpg_format_title(string $title, string $imageFile): string
{
    $t = trim($title);
    if ($t !== '') {
        return $t;
    }
    return trim(str_replace(['_', '-'], ' ', (string) preg_replace('/\.jpe?g$/i', '', $imageFile)));
}

function lpg_scale_dimensions(int $w, int $h, int $maxSide): array
{
    if ($w <= 0 || $h <= 0) {
        return [$maxSide, $maxSide];
    }
    if ($w <= $maxSide && $h <= $maxSide) {
        return [$w, $h];
    }
    if ($w >= $h) {
        return [$maxSide, max(1, (int) round(($h / $w) * $maxSide))];
    }
    return [max(1, (int) round(($w / $h) * $maxSide)), $maxSide];
}

function lpg_build_thumbnail(string $absDir, string $imageFile, int $maxPx, int $quality, bool $squareCrop): bool
{
    $dest = $absDir . '_thumbs/' . $imageFile;
    if (is_file($dest)) {
        return true;
    }

    $src = @imagecreatefromjpeg($absDir . $imageFile);
    if ($src === false) {
        return false;
    }

    $origW = imagesx($src);
    $origH = imagesy($src);
    $cropX = 0;
    $cropY = 0;
    $cropW = $origW;
    $cropH = $origH;

    if ($squareCrop) {
        if ($origW > $origH) {
            $cropX = (int) (($origW - $origH) / 2);
            $cropW = $origH;
        } elseif ($origH > $origW) {
            $cropY = (int) (($origH - $origW) / 2);
            $cropH = $origW;
        }
    }

    if ($cropW >= $cropH) {
        $dstW = $maxPx;
        $dstH = max(1, (int) round(($cropH / $cropW) * $maxPx));
    } else {
        $dstH = $maxPx;
        $dstW = max(1, (int) round(($cropW / $cropH) * $maxPx));
    }

    $canvas = imagecreatetruecolor($dstW, $dstH);
    imagecopyresampled($canvas, $src, 0, 0, $cropX, $cropY, $dstW, $dstH, $cropW, $cropH);

    $prev = umask(0);
    $saved = imagejpeg($canvas, $dest, $quality);
    if ($saved) {
        @chmod($dest, 0664);
    }
    umask($prev);

    return $saved;
}
