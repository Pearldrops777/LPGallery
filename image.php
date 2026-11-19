<?php

/*
 * LPGallery plugin: serves a large picture, scaled and with the watermark.
 * Called from index.php as image.php?pic=<base64 of the file path>&w=<width>&h=<height>
 */

$plugin_cf = [];
include __DIR__ . '/config/config.php';

$rawPath = base64_decode((string) ($_GET['pic'] ?? ''), true);
if ($rawPath === false || $rawPath === '') {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Only JPEG files inside userfiles/gallery may be served
$cmsRoot = realpath(__DIR__ . '/../..');
$galleryRoot = $cmsRoot !== false ? realpath($cmsRoot . '/userfiles/gallery') : false;
$src = realpath($rawPath);
if (
    $galleryRoot === false ||
    $src === false ||
    strpos($src, $galleryRoot . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($src) ||
    !preg_match('/\.jpe?g$/i', $src)
) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$dimensions = @getimagesize($src);
if ($dimensions === false) {
    header('HTTP/1.1 415 Unsupported Media Type');
    exit;
}
$origW = max(1, (int) $dimensions[0]);
$origH = max(1, (int) $dimensions[1]);

// Scale down to fit the requested width and height, keeping the proportions
$reqW = (int) ($_GET['w'] ?? 0);
$reqH = (int) ($_GET['h'] ?? 0);
$scale = 1.0;
if ($reqW > 0) {
    $scale = min($scale, $reqW / $origW);
}
if ($reqH > 0) {
    $scale = min($scale, $reqH / $origH);
}
$width = max(1, (int) round($origW * $scale));
$height = max(1, (int) round($origH * $scale));

$image = @imagecreatefromjpeg($src);
if (!$image) {
    header('HTTP/1.1 500 Internal Server Error');
    exit;
}
$out = imagecreatetruecolor($width, $height);
imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

// Watermark: uses TrueType (full UTF-8 including ©) if available, with clean ASCII fallback
$text = (string) ($plugin_cf['LPGallery']['watermark_text'] ?? '');
if ($text !== '') {
    $font = max(1, min(5, (int) ($plugin_cf['LPGallery']['watermark_font'] ?? 5)));
    $margin = (int) ($plugin_cf['LPGallery']['watermark_margin'] ?? 10);
    $vAlign = (string) ($plugin_cf['LPGallery']['watermark_v_align'] ?? 'bottom');
    $hAlign = (string) ($plugin_cf['LPGallery']['watermark_h_align'] ?? 'center');

    $hex = str_pad(substr(ltrim((string) ($plugin_cf['LPGallery']['watermark_color'] ?? 'ffffff'), '#'), 0, 6), 6, '0');
    $color = imagecolorallocate($out, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));

    $ttfFont = null;
    if (function_exists('imagettftext') && function_exists('imagettfbbox')) {
        $candidates = [
            __DIR__ . '/font.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/liberation-sans/LiberationSans-Regular.ttf',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $ttfFont = $candidate;
                break;
            }
        }
    }

    if ($ttfFont !== null) {
        $ptSize = [1 => 8, 2 => 10, 3 => 12, 4 => 14, 5 => 16][$font];
        $bbox = imagettfbbox($ptSize, 0, $ttfFont, $text);
        $textW = abs($bbox[2] - $bbox[0]);
        $textH = abs($bbox[7] - $bbox[1]);

        $x = $margin;
        if ($hAlign === 'right') {
            $x = $width - $textW - $margin;
        } elseif ($hAlign === 'center') {
            $x = (int) (($width - $textW) / 2);
        }

        // In imagettftext(), y is the baseline of the text
        $y = $margin + $textH;
        if ($vAlign === 'bottom') {
            $y = $height - $margin;
        } elseif ($vAlign === 'center') {
            $y = (int) (($height + $textH) / 2);
        }

        imagettftext($out, $ptSize, 0, $x, $y, $color, $ttfFont, $text);
    } else {
        // Built-in GD bitmap fonts lack the © glyph (Latin-2), so replace © with (c)
        $fallbackText = str_replace('©', '(c)', $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-2//TRANSLIT//IGNORE', $fallbackText);
            if ($converted !== false) {
                $fallbackText = $converted;
            }
        }

        $textW = strlen($fallbackText) * imagefontwidth($font);
        $textH = imagefontheight($font);

        $y = $margin;
        if ($vAlign === 'bottom') {
            $y = $height - $textH - $margin;
        } elseif ($vAlign === 'center') {
            $y = (int) (($height - $textH) / 2);
        }

        $x = $margin;
        if ($hAlign === 'right') {
            $x = $width - $textW - $margin;
        } elseif ($hAlign === 'center') {
            $x = (int) (($width - $textW) / 2);
        }

        imagestring($out, $font, $x, $y, $fallbackText, $color);
    }
}

$quality = (int) ($plugin_cf['LPGallery']['thumb_quality'] ?? 75);
header('Content-Type: image/jpeg');
imagejpeg($out, null, $quality);
exit;
