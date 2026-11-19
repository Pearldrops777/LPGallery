<?php

/*
 * LPGallery plugin for CMSimple_XH
 * Use on a page: {{{LPGallery('userfiles/gallery/test')}}}
 * Replace test with the gallery name of your choice
 * Folders, thumbnails and comment files are created automatically inside userfiles/gallery/.
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

include_once __DIR__ . '/thumbcreate.php';

/* Entry point: shows the thumbnail grid, one picture, or the comment form. */
function LPGallery($dir = '')
{
    global $plugin_cf;

    // Fallback in case CMSimple has not loaded config/config.php for this plugin
    if (!isset($plugin_cf['LPGallery'])) {
        include_once __DIR__ . '/config/config.php';
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $cfg = $plugin_cf['LPGallery'] ?? [];
    $rows = max(1, (int) ($cfg['rows'] ?? 3));
    $cols = max(1, (int) ($cfg['columns'] ?? 3));
    $perPage = $rows * $cols;

    $rel = gallery_folder_name($dir);
    if ($rel === false) {
        return gallery_message('The gallery folder must be a name inside userfiles/gallery, without "..".');
    }

    $fsDir = dirname(__DIR__, 2) . '/' . $rel;
    $fsThumb = $fsDir . 'thumb/';
    if (!gallery_make_folder($fsThumb) || !is_writable($fsDir) || !is_writable($fsThumb)) {
        return gallery_message('The folder ' . $rel . ' is not writable by the web server. Check the permissions of userfiles/gallery (see help/help.htm).');
    }

    $files = gallery_list_pictures($fsDir);
    foreach ($files as $file) {
        gallery_ensure_comment_file($fsDir, $file);
    }

    $pic = gallery_getvar('pic');
    $page = gallery_getvar('page');
    $action = gallery_getvar('action');
    $picIdx = $pic !== '' ? (int) $pic : -1;

    if ($action === 'writecomment' && isset($files[$picIdx])) {
        return gallery_save_comment($rel, $fsDir, $files, $picIdx, $perPage, $cfg);
    }
    if ($action === 'addcomment' && isset($files[$picIdx])) {
        return gallery_comment_form($rel, $fsDir, $files, $picIdx, $perPage, $cfg);
    }
    if ($action === '' && isset($files[$picIdx])) {
        return gallery_single($rel, $fsDir, $files, $picIdx, $perPage, $cfg);
    }
    return gallery_thumbnails($rel, $fsDir, $files, $page, $perPage, $cfg);
}

/* Turns the name given to LPGallery() into a folder inside userfiles/gallery/. */
function gallery_folder_name($dir)
{
    $name = trim(str_replace('\\', '/', (string) $dir), '/');
    if (strpos($name, '..') !== false) {
        return false;
    }
    if (strpos($name, 'userfiles/gallery/') !== 0) {
        $name = 'userfiles/gallery/' . $name;
    }
    $name = rtrim($name, '/');
    if ($name === 'userfiles/gallery') {
        return false;
    }
    return $name . '/';
}

/* The site folder as seen in the browser, e.g. '' or '/cms'. */
function gallery_root()
{
    global $sn;

    $root = dirname((string) $sn);
    return ($root === '.' || $root === '/' || $root === '\\') ? '' : $root;
}

/* URL of a file inside the gallery, e.g. /userfiles/gallery/test/thumb/tn_photo.jpg */
function gallery_url($rel, $path)
{
    $parts = array_map('rawurlencode', explode('/', $rel . $path));
    return gallery_root() . '/' . implode('/', $parts);
}

function gallery_image_url($fsPath, $w, $h)
{
    $real = realpath($fsPath);
    $encoded = base64_encode($real !== false ? $real : $fsPath);
    return gallery_root() . '/plugins/LPGallery/image.php?pic=' . rawurlencode($encoded)
        . '&w=' . $w . '&h=' . $h;
}

function gallery_message($text)
{
    return '<p class="gallery_message"><b>LPGallery:</b> ' . gallery_esc($text) . '</p>';
}

/* Title and description together, inheriting the website's template style. */
function gallery_title_block($title, $description)
{
    // The title is a block element so the thumbnail below it does not sit on the same line
    $html = '<span class="gallery_title" style="display:block;">' . gallery_esc($title) . '</span>';
    if (!empty($description)) {
        $lines = array_map('gallery_esc', $description);
        $html .= '<span class="gallery_description" style="display:block;">'
            . implode('<br>', $lines) . '</span>';
    }
    return $html;
}

/* Thumbnail grid, with pages of rows x columns pictures. */
function gallery_thumbnails($rel, $fsDir, $files, $page, $perPage, $cfg)
{
    global $sn, $su;

    $total = count($files);
    $t = '<fieldset class="gallery_fieldset"><legend class="gallery_legend">'
        . $total . ($total === 1 ? ' picture' : ' pictures') . ' in this folder</legend>';
    if ($total === 0) {
        return $t . '<p>No pictures yet. Copy JPEG files into ' . gallery_esc($rel) . ' (for example with FTP) and reload this page.</p></fieldset>';
    }

    $pages = (int) ceil($total / $perPage);
    $pageIdx = min(max(0, (int) $page), $pages - 1);

    $size = max(20, (int) ($cfg['thumb_size'] ?? 125));
    $quality = (int) ($cfg['thumb_quality'] ?? 75);
    $crop = gallery_is_true($cfg['thumb_crop'] ?? 'true');
    $cols = max(1, (int) ($cfg['columns'] ?? 3));
    $showTitle = gallery_is_true($cfg['show_title'] ?? 'true');
    $titleTop = strcasecmp($cfg['title_placement'] ?? 'top', 'top') === 0;
    $showComments = gallery_is_true($cfg['show_comments'] ?? 'true');
    $base = $sn . '?' . $su;

    $cells = [];
    foreach (array_slice($files, $pageIdx * $perPage, $perPage, true) as $idx => $file) {
        gallery_ensure_thumb($fsDir, $file, $size, $quality, $crop);
        $info = gallery_read_comments($fsDir . gallery_comment_file($file));
        $title = gallery_display_title($info['title'], $file);
        $titleHtml = $showTitle ? gallery_title_block($title, $info['description']) : '';
        $thumb = @getimagesize($fsDir . 'thumb/tn_' . $file);
        $tw = $thumb ? $thumb[0] : $size;
        $th = $thumb ? $thumb[1] : $size;
        $count = count($info['comments']);

        $cell = '';
        if ($titleTop) {
            $cell .= $titleHtml;
        }
        $cell .= '<a href="' . gallery_esc($base . '&pic=' . $idx . '&page=' . $pageIdx) . '" class="thumb">'
            . '<img src="' . gallery_esc(gallery_url($rel, 'thumb/tn_' . $file)) . '" width="' . $tw
            . '" height="' . $th . '" alt="' . gallery_esc($title) . '"></a>';
        if (!$titleTop) {
            $cell .= $titleHtml;
        }
        if ($showComments) {
            $cell .= '<br><sup>' . ($count === 0 ? 'No comments' : ($count === 1 ? '1 comment' : $count . ' comments')) . '</sup>';
            $cell .= '<br><sup><a href="' . gallery_esc($base . '&pic=' . $idx . '&page=' . $pageIdx . '&action=addcomment') . '">Add comment</a></sup>';
        }
        $cells[] = $cell;
    }

    // Responsive grid: the configured columns on wide screens, fewer on narrow screens
    $t .= '<style>'
        . '.gallery_grid{display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));gap:1.5em 1em;}'
        . '.gallery_grid .thumbnailitem{min-width:0;vertical-align:top;}'
        . '@media (max-width:700px){.gallery_grid{grid-template-columns:repeat(2,minmax(0,1fr));}}'
        . '@media (max-width:360px){.gallery_grid{grid-template-columns:minmax(0,1fr);}}'
        . '.gallery_grid img{max-width:100%;height:auto;}'
        . '</style>';
    $t .= '<div class="gallery_grid">';
    foreach ($cells as $cell) {
        $t .= '<div class="thumbnailitem">' . $cell . '</div>';
    }
    $t .= '</div>';

    if ($pages > 1) {
        $t .= '<p class="gallery_pages">';
        if ($pageIdx > 0) {
            $t .= '<a href="' . gallery_esc($base . '&page=' . ($pageIdx - 1)) . '">Prev</a>&nbsp;&nbsp;';
        }
        $t .= 'Page ' . ($pageIdx + 1) . ' of ' . $pages;
        if ($pageIdx < $pages - 1) {
            $t .= '&nbsp;&nbsp;<a href="' . gallery_esc($base . '&page=' . ($pageIdx + 1)) . '">Next</a>';
        }
        $t .= '</p>';
    }

    return $t . '</fieldset>';
}

/* One picture with title, description, extra fields and comments. */
function gallery_single($rel, $fsDir, $files, $picIdx, $perPage, $cfg)
{
    global $sn, $su;

    $total = count($files);
    $file = $files[$picIdx];
    $prev = ($picIdx - 1 + $total) % $total;
    $next = ($picIdx + 1) % $total;
    $pageIdx = (int) floor($picIdx / $perPage);
    $info = gallery_read_comments($fsDir . gallery_comment_file($file));
    $title = gallery_display_title($info['title'], $file);
    $base = $sn . '?' . $su;

    $t = '<div class="gallery_legend">'
        . '<a href="' . gallery_esc($base . '&page=' . $pageIdx) . '">Return</a><br>'
        . '<a href="' . gallery_esc($base . '&pic=' . $prev . '&page=' . $pageIdx) . '">Prev</a>&nbsp;&nbsp;'
        . 'Image ' . ($picIdx + 1) . ' of ' . $total . '&nbsp;&nbsp;'
        . '<a href="' . gallery_esc($base . '&pic=' . $next . '&page=' . $pageIdx) . '">Next</a>'
        . '</div>';

    $t .= '<fieldset class="gallery_fieldset">';

    $titleTop = strcasecmp($cfg['title_placement'] ?? 'top', 'top') === 0;
    $titleHtml = gallery_is_true($cfg['show_title'] ?? 'true')
        ? '<p class="gallery_title">' . gallery_title_block($title, $info['description']) . '</p>'
        : '';
    if ($titleTop) {
        $t .= $titleHtml;
    }

    $size = @getimagesize($fsDir . $file);
    $w = $size ? $size[0] : 500;
    $h = $size ? $size[1] : 375;
    [$w, $h] = gallery_fit($w, $h, (int) ($cfg['main_max_size'] ?? 500));
    $t .= '<div class="gallery_pic"><img src="' . gallery_esc(gallery_image_url($fsDir . $file, $w, $h)) . '" width="' . $w
        . '" height="' . $h . '" alt="' . gallery_esc($title) . '" class="picture"></div>';

    if (!$titleTop) {
        $t .= $titleHtml;
    }

    if (gallery_is_true($cfg['show_extrainfo'] ?? 'true')) {
        $labels = array_map('trim', explode(',', $cfg['extra_info_labels'] ?? ''));
        $rows = '';
        foreach ($info['extra'] as $i => $value) {
            if ($value === '') {
                continue;
            }
            $label = $labels[$i] ?? '';
            $rows .= '<b>' . gallery_esc($label !== '' ? $label . ': ' : '') . '</b>' . gallery_esc($value) . '<br>';
        }
        if ($rows !== '') {
            $t .= '<div class="gallery_extrainfo">' . $rows . '</div>';
        }
    }

    if (gallery_is_true($cfg['show_comments'] ?? 'true')) {
        $t .= '<div class="gallery_comments">';
        $t .= '<a href="' . gallery_esc($base . '&pic=' . $picIdx . '&page=' . $pageIdx . '&action=addcomment') . '">Add comment</a><br><br>';
        foreach ($info['comments'] as $c) {
            $meta = implode(' | ', array_filter([$c['time'], $c['name']], static fn($v) => $v !== ''));
            $t .= '<b>' . gallery_esc($c['text']) . '</b><br>' . gallery_esc($meta) . '<hr>';
        }
        $t .= '</div>';
    }

    return $t . '</fieldset>';
}

/* The comment form, with a simple sum as security check. */
function gallery_comment_form($rel, $fsDir, $files, $picIdx, $perPage, $cfg, $error = '', $name = '', $text = '')
{
    global $sn, $su;

    $file = $files[$picIdx];
    $pageIdx = (int) floor($picIdx / $perPage);

    $useCaptcha = gallery_is_true($cfg['comment_captcha'] ?? 'true');
    $question = '';
    if ($useCaptcha) {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $_SESSION['gallery_captcha'] = (string) ($a + $b);
        $question = $a . ' + ' . $b . ' = ?';
    }

    $t = '<fieldset class="gallery_fieldset"><legend>Add a comment</legend>';
    if ($error !== '') {
        $t .= '<p class="gallery_error"><b>' . gallery_esc($error) . '</b></p>';
    }
    $t .= '<p><img src="' . gallery_esc(gallery_url($rel, 'thumb/tn_' . $file)) . '" alt="' . gallery_esc($file) . '"></p>';
    $t .= '<form method="post" action="' . gallery_esc($sn . '?' . $su) . '">';
    $t .= '<input type="hidden" name="action" value="writecomment">';
    $t .= '<input type="hidden" name="pic" value="' . $picIdx . '">';
    $t .= '<input type="hidden" name="page" value="' . $pageIdx . '">';
    $t .= '<p>Name<br><input type="text" name="comment_name" size="26" value="' . gallery_esc($name) . '"></p>';
    $t .= '<p>Comment<br><textarea name="comment_text" rows="3" cols="30">' . gallery_esc($text) . '</textarea></p>';
    if ($useCaptcha) {
        $t .= '<p>Security check (<b>' . gallery_esc($question) . '</b>)<br>'
            . '<input type="text" name="captcha_answer" size="6" autocomplete="off" required></p>';
    }
    $t .= '<p><input type="submit" value="Add comment"></p>';
    $t .= '</form></fieldset>';
    return $t;
}

/* Saves a comment to the .txt file of the picture, then reloads the page. */
function gallery_save_comment($rel, $fsDir, $files, $picIdx, $perPage, $cfg)
{
    global $sn, $su;

    $expected = (string) ($_SESSION['gallery_captcha'] ?? '');
    unset($_SESSION['gallery_captcha']);
    $name = gallery_getvar('comment_name');
    $text = gallery_getvar('comment_text');

    if (gallery_is_true($cfg['comment_captcha'] ?? 'true')
        && ($expected === '' || trim(gallery_getvar('captcha_answer')) !== $expected)) {
        return gallery_comment_form($rel, $fsDir, $files, $picIdx, $perPage, $cfg,
            'Wrong answer to the security question. Please try again.', $name, $text);
    }

    $cleanText = gallery_clean($text);
    if ($cleanText !== '') {
        $line = "\r\n" . date('d-m-Y H:i') . ';' . gallery_clean($name) . ';' . $cleanText . ';';
        file_put_contents($fsDir . gallery_comment_file($files[$picIdx]), $line, FILE_APPEND);
    }

    $pageIdx = (int) floor($picIdx / $perPage);
    header('Location: ' . $sn . '?' . $su . '&pic=' . $picIdx . '&page=' . $pageIdx);
    exit;
}
