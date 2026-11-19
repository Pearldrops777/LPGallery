<?php

declare(strict_types=1);

/*
 * LPGallery – Folder-based JPEG gallery plugin for CMSimple_XH
 * Copyright (C) 2026 Lambert Peeters (GPL-3.0)
 * Page call: {{{LPGallery('userfiles/gallery/test')}}}
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

require_once __DIR__ . '/includes/engine.php';

function LPGallery(string $folder = ''): string
{
    global $plugin_cf;

    if (!isset($plugin_cf['LPGallery'])) {
        include_once __DIR__ . '/config/config.php';
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $cfg = $plugin_cf['LPGallery'] ?? [];
    $cols = max(1, (int) ($cfg['grid_columns'] ?? 3));
    $rows = max(1, (int) ($cfg['grid_rows'] ?? 3));
    $perPage = $cols * $rows;

    $relPath = lpg_resolve_folder($folder);
    if ($relPath === null) {
        return lpg_notice('Invalid gallery path. Use a folder inside userfiles/gallery/ without "..".');
    }

    $absDir = dirname(__DIR__, 2) . '/' . $relPath;
    $absThumbs = $absDir . '_thumbs/';

    if (!lpg_ensure_dir($absThumbs)) {
        return lpg_notice('Cannot create or write to ' . $relPath . '. Check permissions on userfiles/gallery/ (see help/help.htm).');
    }

    clearstatcache(true, $absDir);
    clearstatcache(true, $absThumbs);

    if (!is_writable($absDir) || !is_writable($absThumbs)) {
        return lpg_notice('Folder ' . $relPath . ' is not writable by the web server (see help/help.htm).');
    }

    $images = lpg_scan_images($absDir);
    foreach ($images as $img) {
        lpg_ensure_sidecar($absDir, $img);
    }

    $view = lpg_param('lpg_view');
    $item = lpg_param('lpg_item');
    $page = max(0, (int) lpg_param('lpg_page'));
    $idx = $item !== '' ? (int) $item : -1;

    if ($view === 'save_comment' && isset($images[$idx])) {
        return lpg_handle_comment_post($relPath, $absDir, $images, $idx, $perPage, $cfg);
    }
    if ($view === 'comment' && isset($images[$idx])) {
        return lpg_render_comment_form($relPath, $images, $idx, $perPage, $cfg);
    }
    if ($view === 'photo' && isset($images[$idx])) {
        return lpg_render_viewer($relPath, $absDir, $images, $idx, $perPage, $cfg);
    }

    return lpg_render_grid($relPath, $absDir, $images, $page, $perPage, $cfg);
}

function lpg_render_grid(string $relPath, string $absDir, array $images, int $page, int $perPage, array $cfg): string
{
    global $sn, $su;

    $total = count($images);
    $baseUrl = $sn . '?' . $su;

    $out = '<section class="lpg-gallery">';
    $out .= '<header class="lpg-header"><span class="lpg-count">'
        . $total . ($total === 1 ? ' photo' : ' photos')
        . '</span></header>';

    if ($total === 0) {
        $out .= '<p class="lpg-empty">No JPEG photos found in <code>' . lpg_esc($relPath) . '</code>. Upload <code>.jpg</code> files and reload.</p>';
        return $out . '</section>';
    }

    $totalPages = (int) ceil($total / $perPage);
    $currentPage = min($page, $totalPages - 1);

    $cols = max(1, (int) ($cfg['grid_columns'] ?? 3));
    $thumbMax = max(32, (int) ($cfg['thumb_max_px'] ?? 160));
    $thumbQuality = max(10, min(100, (int) ($cfg['thumb_jpeg_quality'] ?? 80)));
    $squareCrop = lpg_bool($cfg['thumb_square_crop'] ?? 'true');
    $showCaptions = lpg_bool($cfg['grid_show_captions'] ?? 'true');
    $captionPos = strtolower((string) ($cfg['grid_caption_position'] ?? 'below'));
    $showComments = lpg_bool($cfg['comments_enabled'] ?? 'true');

    $gridId = 'lpg-grid-' . substr(md5($relPath), 0, 8);
    $out .= '<style>'
        . '#' . $gridId . '{display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));gap:1rem;align-items:start;}'
        . '#' . $gridId . ' .lpg-card{margin:0;padding:0;text-align:center;}'
        . '#' . $gridId . ' .lpg-card img{max-width:100%;height:auto;display:inline-block;}'
        . '@media(max-width:768px){#' . $gridId . '{grid-template-columns:repeat(' . min(2, $cols) . ',minmax(0,1fr));}}'
        . '@media(max-width:480px){#' . $gridId . '{grid-template-columns:1fr;}}'
        . '</style>';

    $out .= '<div id="' . $gridId . '" class="lpg-grid">';

    $slice = array_slice($images, $currentPage * $perPage, $perPage, true);
    foreach ($slice as $idx => $file) {
        lpg_build_thumbnail($absDir, $file, $thumbMax, $thumbQuality, $squareCrop);
        $meta = lpg_read_sidecar($absDir . lpg_sidecar_name($file));
        $title = lpg_format_title($meta['title'], $file);
        $thumbFile = '_thumbs/' . $file;
        $dim = @getimagesize($absDir . $thumbFile);
        $tw = $dim !== false ? (int) $dim[0] : $thumbMax;
        $th = $dim !== false ? (int) $dim[1] : $thumbMax;

        $captionHtml = '';
        if ($showCaptions) {
            $captionHtml .= '<figcaption class="lpg-caption">';
            $captionHtml .= '<span class="lpg-title">' . lpg_esc($title) . '</span>';
            if ($meta['description'] !== '') {
                $captionHtml .= '<span class="lpg-desc">' . nl2br(lpg_esc($meta['description'])) . '</span>';
            }
            $captionHtml .= '</figcaption>';
        }

        $photoUrl = $baseUrl . '&lpg_view=photo&lpg_item=' . $idx . '&lpg_page=' . $currentPage;

        $out .= '<figure class="lpg-card">';
        if ($captionPos === 'above') {
            $out .= $captionHtml;
        }
        $out .= '<a class="lpg-thumb-link" href="' . lpg_esc($photoUrl) . '">'
            . '<img class="lpg-thumb" src="' . lpg_esc(lpg_public_url($relPath, $thumbFile)) . '" width="' . $tw . '" height="' . $th . '" alt="' . lpg_esc($title) . '">'
            . '</a>';
        if ($captionPos !== 'above') {
            $out .= $captionHtml;
        }
        if ($showComments) {
            $cCount = count($meta['comments']);
            $commentUrl = $baseUrl . '&lpg_view=comment&lpg_item=' . $idx . '&lpg_page=' . $currentPage;
            $out .= '<div class="lpg-card-meta">'
                . '<span class="lpg-comment-count">' . ($cCount === 1 ? '1 comment' : $cCount . ' comments') . '</span>'
                . ' &middot; <a class="lpg-comment-add" href="' . lpg_esc($commentUrl) . '">Comment</a>'
                . '</div>';
        }
        $out .= '</figure>';
    }

    $out .= '</div>';

    if ($totalPages > 1) {
        $out .= '<nav class="lpg-pagination" aria-label="Gallery pages">';
        if ($currentPage > 0) {
            $out .= '<a class="lpg-page-prev" href="' . lpg_esc($baseUrl . '&lpg_page=' . ($currentPage - 1)) . '">&laquo; Previous</a> ';
        }
        $out .= '<span class="lpg-page-info">Page ' . ($currentPage + 1) . ' of ' . $totalPages . '</span>';
        if ($currentPage < $totalPages - 1) {
            $out .= ' <a class="lpg-page-next" href="' . lpg_esc($baseUrl . '&lpg_page=' . ($currentPage + 1)) . '">Next &raquo;</a>';
        }
        $out .= '</nav>';
    }

    return $out . '</section>';
}

function lpg_render_viewer(string $relPath, string $absDir, array $images, int $idx, int $perPage, array $cfg): string
{
    global $sn, $su;

    $total = count($images);
    $file = $images[$idx];
    $prevIdx = ($idx - 1 + $total) % $total;
    $nextIdx = ($idx + 1) % $total;
    $page = (int) floor($idx / $perPage);
    $baseUrl = $sn . '?' . $su;

    $meta = lpg_read_sidecar($absDir . lpg_sidecar_name($file));
    $title = lpg_format_title($meta['title'], $file);

    $out = '<section class="lpg-viewer">';
    $out .= '<nav class="lpg-viewer-nav">'
        . '<a class="lpg-back" href="' . lpg_esc($baseUrl . '&lpg_page=' . $page) . '">&larr; Back to gallery</a>'
        . ' <span class="lpg-sep">|</span> '
        . '<a class="lpg-prev" href="' . lpg_esc($baseUrl . '&lpg_view=photo&lpg_item=' . $prevIdx . '&lpg_page=' . $page) . '">&laquo; Prev</a> '
        . '<span class="lpg-pos">' . ($idx + 1) . ' / ' . $total . '</span> '
        . '<a class="lpg-next" href="' . lpg_esc($baseUrl . '&lpg_view=photo&lpg_item=' . $nextIdx . '&lpg_page=' . $page) . '">Next &raquo;</a>'
        . '</nav>';

    $showCaptions = lpg_bool($cfg['grid_show_captions'] ?? 'true');
    $captionPos = strtolower((string) ($cfg['grid_caption_position'] ?? 'below'));

    $headerHtml = '';
    if ($showCaptions) {
        $headerHtml .= '<div class="lpg-viewer-caption">';
        $headerHtml .= '<div class="lpg-title">' . lpg_esc($title) . '</div>';
        if ($meta['description'] !== '') {
            $headerHtml .= '<div class="lpg-desc">' . nl2br(lpg_esc($meta['description'])) . '</div>';
        }
        $headerHtml .= '</div>';
    }

    if ($captionPos === 'above') {
        $out .= $headerHtml;
    }

    $dim = @getimagesize($absDir . $file);
    $origW = $dim !== false ? (int) $dim[0] : 800;
    $origH = $dim !== false ? (int) $dim[1] : 600;
    $maxSide = max(100, (int) ($cfg['viewer_max_px'] ?? 800));
    [$w, $h] = lpg_scale_dimensions($origW, $origH, $maxSide);

    $out .= '<div class="lpg-stage">'
        . '<img class="lpg-photo" src="' . lpg_esc(lpg_stream_url($absDir . $file, $w, $h)) . '" width="' . $w . '" height="' . $h . '" alt="' . lpg_esc($title) . '">'
        . '</div>';

    if ($captionPos !== 'above') {
        $out .= $headerHtml;
    }

    if (lpg_bool($cfg['meta_enabled'] ?? 'true') && !empty($meta['meta'])) {
        $rows = '';
        foreach ($meta['meta'] as $k => $v) {
            if ((string) $v === '') {
                continue;
            }
            $rows .= '<dt>' . lpg_esc((string) $k) . '</dt><dd>' . lpg_esc((string) $v) . '</dd>';
        }
        if ($rows !== '') {
            $out .= '<dl class="lpg-meta">' . $rows . '</dl>';
        }
    }

    if (lpg_bool($cfg['comments_enabled'] ?? 'true')) {
        $commentUrl = $baseUrl . '&lpg_view=comment&lpg_item=' . $idx . '&lpg_page=' . $page;
        $out .= '<div class="lpg-comments">';
        $out .= '<p><a class="lpg-comment-add" href="' . lpg_esc($commentUrl) . '">Add a comment</a></p>';
        foreach ($meta['comments'] as $c) {
            $author = trim((string) ($c['author'] ?? ''));
            $date = trim((string) ($c['date'] ?? ''));
            $msg = trim((string) ($c['message'] ?? ''));
            if ($msg === '') {
                continue;
            }
            $header = implode(' &middot; ', array_filter([$author !== '' ? lpg_esc($author) : 'Anonymous', lpg_esc($date)]));
            $out .= '<article class="lpg-comment">'
                . '<div class="lpg-comment-head">' . $header . '</div>'
                . '<div class="lpg-comment-body">' . nl2br(lpg_esc($msg)) . '</div>'
                . '</article>';
        }
        $out .= '</div>';
    }

    return $out . '</section>';
}

function lpg_render_comment_form(string $relPath, array $images, int $idx, int $perPage, array $cfg, string $error = '', string $author = '', string $message = ''): string
{
    global $sn, $su;

    $file = $images[$idx];
    $page = (int) floor($idx / $perPage);
    $baseUrl = $sn . '?' . $su;

    $useCaptcha = lpg_bool($cfg['comments_captcha'] ?? 'true');
    $challenge = '';
    if ($useCaptcha) {
        $n1 = random_int(2, 9);
        $n2 = random_int(1, 9);
        $_SESSION['lpg_captcha_answer'] = (string) ($n1 + $n2);
        $challenge = $n1 . ' + ' . $n2;
    }

    $out = '<section class="lpg-comment-form">';
    $out .= '<p><a href="' . lpg_esc($baseUrl . '&lpg_view=photo&lpg_item=' . $idx . '&lpg_page=' . $page) . '">&larr; Back to photo</a></p>';
    if ($error !== '') {
        $out .= '<p class="lpg-error"><strong>' . lpg_esc($error) . '</strong></p>';
    }
    $out .= '<p><img src="' . lpg_esc(lpg_public_url($relPath, '_thumbs/' . $file)) . '" alt="' . lpg_esc($file) . '"></p>';
    $out .= '<form method="post" action="' . lpg_esc($baseUrl) . '">';
    $out .= '<input type="hidden" name="lpg_view" value="save_comment">';
    $out .= '<input type="hidden" name="lpg_item" value="' . $idx . '">';
    $out .= '<input type="hidden" name="lpg_page" value="' . $page . '">';
    $out .= '<p><label>Your name<br><input type="text" name="lpg_author" maxlength="60" value="' . lpg_esc($author) . '"></label></p>';
    $out .= '<p><label>Comment<br><textarea name="lpg_message" rows="4" cols="36" maxlength="1000" required>' . lpg_esc($message) . '</textarea></label></p>';
    if ($useCaptcha) {
        $out .= '<p><label>Spam check: what is <strong>' . lpg_esc($challenge) . '</strong>?<br>'
            . '<input type="text" name="lpg_captcha" size="6" autocomplete="off" required></label></p>';
    }
    $out .= '<p><button type="submit">Post comment</button></p>';
    $out .= '</form></section>';

    return $out;
}

function lpg_handle_comment_post(string $relPath, string $absDir, array $images, int $idx, int $perPage, array $cfg): string
{
    global $sn, $su;

    $expected = (string) ($_SESSION['lpg_captcha_answer'] ?? '');
    unset($_SESSION['lpg_captcha_answer']);

    $author = trim(lpg_param('lpg_author'));
    $message = trim(lpg_param('lpg_message'));

    if (lpg_bool($cfg['comments_captcha'] ?? 'true')) {
        $given = trim(lpg_param('lpg_captcha'));
        if ($expected === '' || $given !== $expected) {
            return lpg_render_comment_form($relPath, $images, $idx, $perPage, $cfg, 'Incorrect spam check answer. Please try again.', $author, $message);
        }
    }

    if ($message !== '') {
        lpg_append_comment($absDir . lpg_sidecar_name($images[$idx]), $author, $message);
    }

    $page = (int) floor($idx / $perPage);
    header('Location: ' . $sn . '?' . $su . '&lpg_view=photo&lpg_item=' . $idx . '&lpg_page=' . $page);
    exit;
}
