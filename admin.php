<?php

declare(strict_types=1);

/*
 * LPGallery – CMSimple_XH Admin Integration
 * Copyright (C) 2026 Lambert Peeters (GPL-3.0)
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (function_exists('XH_registerPluginMenuItem')) {
    XH_registerPluginMenuItem('LPGallery', $tx['filetype']['config'], '?LPGallery&admin=plugin_config&action=plugin_edit');
    XH_registerPluginMenuItem('LPGallery', $tx['filetype']['help'], $pth['file']['plugin_help'], '_blank');
}

if (
    (function_exists('XH_wantsPluginAdministration') && XH_wantsPluginAdministration('LPGallery'))
    || (isset($LPGallery) && $LPGallery === 'true')
) {
    $o .= print_plugin_admin('off');
    switch ($admin) {
        case '':
        case 'plugin_main':
            $o .= '<h1>LPGallery</h1>'
                . '<p>Embed a gallery on any page with <code>&#123;&#123;&#123;LPGallery(\'userfiles/gallery/test\')&#125;&#125;&#125;</code>.</p>'
                . '<p>Configure grid dimensions, thumbnails, JSON metadata keys, comments, and watermarks under '
                . '<strong>Plugins &gt; LPGallery &gt; Settings</strong>.</p>';
            break;
        default:
            $o .= plugin_admin_common($action, $admin, $plugin);
            break;
    }
}
