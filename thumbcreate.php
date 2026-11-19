<?php

/*
 * LPGallery plugin: helper functions, thumbnails and comment files.
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

function gallery_is_true($value)
{
    return in_array(strtolower(trim((string) $value)), ['true', '1', 'yes', 'on'], true);
}

/* Reads a form value (POST first, then GET). */
function gallery_getvar($name)
{
    if (isset($_POST[$name])) {
        return (string) $_POST[$name];
    }
    return isset($_GET[$name]) ? (string) $_GET[$name] : '';
}

function gallery_esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* Removes line breaks and semicolons, which are used as separators in the .txt files. */
function gallery_clean($text)
{
    $text = str_replace(["\r", "\n", ';'], [' ', ' ', ','], (string) $text);
    return trim($text);
}

/*
 * Creates a folder with mode 0775. Inside userfiles/gallery (setgid 2775)
 * the new folder gets the group www-data automatically.
 */
function gallery_make_folder($path)
{
    if (is_dir($path)) {
        return true;
    }
    $old = umask(0);
    $ok = @mkdir($path, 0775, true);
    umask($old);
    return $ok || is_dir($path);
}

/* Creates a new file with mode 0664, so the group can write it as well. */
function gallery_create_file($path, $content)
{
    $old = umask(0);
    $ok = @file_put_contents($path, $content);
    if ($ok !== false) {
        @chmod($path, 0664);
    }
    umask($old);
    return $ok !== false;
}

/* Lists the JPEG files in a folder, sorted by name. */
function gallery_list_pictures($fsDir)
{
    $list = [];
    if (is_dir($fsDir) && ($handle = @opendir($fsDir))) {
        while (($file = readdir($handle)) !== false) {
            if (preg_match('/\.jpe?g$/i', $file) && is_file($fsDir . $file)) {
                $list[] = $file;
            }
        }
        closedir($handle);
    }
    sort($list);
    return $list;
}

/* photo.jpg -> photo.txt */
function gallery_comment_file($file)
{
    return preg_replace('/\.jpe?g$/i', '.txt', $file);
}

/* Creates the .txt file of a picture if it does not exist yet. */
function gallery_ensure_comment_file($fsDir, $file)
{
    $path = $fsDir . gallery_comment_file($file);
    if (!file_exists($path)) {
        gallery_create_file($path, $file . ';');
    }
    return $path;
}

/* Creates thumb/tn_<file> if it does not exist yet. */
function gallery_ensure_thumb($fsDir, $file, $size, $quality, $crop)
{
    $thumb = $fsDir . 'thumb/tn_' . $file;
    if (file_exists($thumb)) {
        return true;
    }
    $src = @imagecreatefromjpeg($fsDir . $file);
    if (!$src) {
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $sx = 0;
    $sy = 0;
    $sw = $w;
    $sh = $h;
    if ($crop) {
        if ($w > $h) {
            $sx = (int) (($w - $h) / 2);
            $sw = $h;
        } elseif ($h > $w) {
            $sy = (int) (($h - $w) / 2);
            $sh = $w;
        }
    }

    if ($sw >= $sh) {
        $tw = $size;
        $th = max(1, (int) round($sh / $sw * $size));
    } else {
        $th = $size;
        $tw = max(1, (int) round($sw / $sh * $size));
    }

    $out = imagecreatetruecolor($tw, $th);
    imagecopyresampled($out, $src, 0, 0, $sx, $sy, $tw, $th, $sw, $sh);

    $old = umask(0);
    $ok = imagejpeg($out, $thumb, $quality);
    if ($ok) {
        @chmod($thumb, 0664);
    }
    umask($old);

    return $ok;
}

/* Scales width and height so the longest side is at most $max. */
function gallery_fit($w, $h, $max)
{
    if ($w <= 0 || $h <= 0) {
        return [$max, $max];
    }
    if ($max <= 0 || ($w <= $max && $h <= $max)) {
        return [$w, $h];
    }
    if ($w >= $h) {
        return [$max, max(1, (int) round($h / $w * $max))];
    }
    return [max(1, (int) round($w / $h * $max)), $max];
}

/*
 * Reads a .txt file.
 * Line 1: title;extra1;extra2;...
 * Lines without ';': description (shown with the title)
 * Lines with ';': comments in the form time;name;text;
 */
function gallery_read_comments($path)
{
    $data = ['title' => '', 'extra' => [], 'description' => [], 'comments' => []];
    if (!is_file($path)) {
        return $data;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $first = array_shift($lines);
    if ($first === null) {
        return $data;
    }

    $parts = explode(';', rtrim((string) $first, "\r\n"));
    $data['title'] = trim($parts[0]);
    $data['extra'] = array_map('trim', array_slice($parts, 1));

    foreach ($lines as $line) {
        $line = rtrim((string) $line, "\r\n");
        if (trim($line) === '') {
            continue;
        }
        if (strpos($line, ';') === false) {
            $data['description'][] = $line;
            continue;
        }
        $p = explode(';', $line);
        $data['comments'][] = [
            'time' => trim($p[0]),
            'name' => trim($p[1] ?? ''),
            'text' => trim($p[2] ?? ''),
        ];
    }
    return $data;
}

/* The title is the first field; underscores are shown as spaces. */
function gallery_display_title($title, $file)
{
    $title = trim($title) !== '' ? $title : preg_replace('/\.jpe?g$/i', '', $file);
    return str_replace('_', ' ', $title);
}
