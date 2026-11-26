<?php

declare(strict_types=1);

/*
 * LPGallery – On-the-fly image scaler & UTF-8 watermark endpoint
 * Copyright (C) 2026 Lambert Peeters (GPL-3.0)
 */

$plugin_cf = [];
include __DIR__ . '/config/config.php';

$encoded = (string) ($_GET['src'] ?? '');
$decoded = base64_decode($encoded, true);
if ($decoded === false || $decoded === '') {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$cmsRoot = realpath(__DIR__ . '/../..');
$galleryBase = $cmsRoot !== false ? realpath($cmsRoot . '/userfiles/gallery') : false;
$realSrc = realpath($decoded);

if (
    $galleryBase === false ||
    $realSrc === false ||
    !str_starts_with($realSrc, $galleryBase . DIRECTORY_SEPARATOR) ||
    !is_file($realSrc) ||
    preg_match('/\.jpe?g$/i', $realSrc) !== 1
) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$info = @getimagesize($realSrc);
if ($info === false) {
    header('HTTP/1.1 415 Unsupported Media Type');
    exit;
}

$origW = max(1, (int) $info[0]);
$origH = max(1, (int) $info[1]);
$reqW = (int) ($_GET['w'] ?? 0);
$reqH = (int) ($_GET['h'] ?? 0);

$ratio = 1.0;
if ($reqW > 0) {
    $ratio = min($ratio, $reqW / $origW);
}
if ($reqH > 0) {
    $ratio = min($ratio, $reqH / $origH);
}

$outW = max(1, (int) round($origW * $ratio));
$outH = max(1, (int) round($origH * $ratio));

$srcImg = @imagecreatefromjpeg($realSrc);
if ($srcImg === false) {
    header('HTTP/1.1 500 Internal Server Error');
    exit;
}

$canvas = imagecreatetruecolor($outW, $outH);
imagecopyresampled($canvas, $srcImg, 0, 0, 0, 0, $outW, $outH, imagesx($srcImg), imagesy($srcImg));

$cfg = $plugin_cf['LPGallery'] ?? [];
$wmText = trim((string) ($cfg['wm_text'] ?? ''));

if ($wmText !== '') {
    // wm_size is a point size (6-200). Without a TrueType font, the bitmap
    // fallback can only use GD font 1-5, so the size is mapped to the nearest one.
    $pt = max(6, min(200, (int) ($cfg['wm_size'] ?? 15)));
    $sizeIdx = $pt <= 9 ? 1 : ($pt <= 11 ? 2 : ($pt <= 13 ? 3 : ($pt <= 15 ? 4 : 5)));
    $padding = max(0, (int) ($cfg['wm_padding'] ?? 16));
    $posY = (string) ($cfg['wm_vertical'] ?? 'bottom');
    $posX = (string) ($cfg['wm_horizontal'] ?? 'center');

    $hex = str_pad(substr(ltrim((string) ($cfg['wm_hex_color'] ?? 'ffffff'), '#'), 0, 6), 6, '0');
    $rgb = imagecolorallocate(
        $canvas,
        (int) hexdec(substr($hex, 0, 2)),
        (int) hexdec(substr($hex, 2, 2)),
        (int) hexdec(substr($hex, 4, 2))
    );

    $fontFile = null;
    if (function_exists('imagettftext') && function_exists('imagettfbbox')) {
        $fontPaths = [
            __DIR__ . '/font.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ];
        foreach ($fontPaths as $candidate) {
            if (is_file($candidate)) {
                $fontFile = $candidate;
                break;
            }
        }
    }

    if ($fontFile !== null) {
        $box = imagettfbbox($pt, 0, $fontFile, $wmText);
        $boxW = abs((int) $box[2] - (int) $box[0]);
        $boxH = abs((int) $box[7] - (int) $box[1]);

        $x = $padding;
        if ($posX === 'right') {
            $x = $outW - $boxW - $padding;
        } elseif ($posX === 'center') {
            $x = (int) (($outW - $boxW) / 2);
        }

        $y = $padding + $boxH;
        if ($posY === 'bottom') {
            $y = $outH - $padding;
        } elseif ($posY === 'middle' || $posY === 'center') {
            $y = (int) (($outH + $boxH) / 2);
        }

        imagettftext($canvas, $pt, 0, $x, $y, $rgb, $fontFile, $wmText);
    } else {
        $ascii = str_replace('©', '(c)', $wmText);
        $boxW = strlen($ascii) * imagefontwidth($sizeIdx);
        $boxH = imagefontheight($sizeIdx);

        $x = $padding;
        if ($posX === 'right') {
            $x = $outW - $boxW - $padding;
        } elseif ($posX === 'center') {
            $x = (int) (($outW - $boxW) / 2);
        }

        $y = $padding;
        if ($posY === 'bottom') {
            $y = $outH - $boxH - $padding;
        } elseif ($posY === 'middle' || $posY === 'center') {
            $y = (int) (($outH - $boxH) / 2);
        }

        imagestring($canvas, $sizeIdx, $x, $y, $ascii, $rgb);
    }
}

$quality = max(10, min(100, (int) ($cfg['viewer_jpeg_quality'] ?? 85)));
header('Content-Type: image/jpeg');
imagejpeg($canvas, null, $quality);
exit;
