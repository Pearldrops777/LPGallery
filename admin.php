<?php

/*
 * LPGallery plugin admin page. Its presence makes CMSimple_XH list the plugin
 * under Plugins in the admin menu, with the settings page.
 */

if (!defined('CMSIMPLE_XH_VERSION')) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Register only Settings and Help in the plugin menu
if (function_exists('XH_registerPluginMenuItem')) {
    XH_registerPluginMenuItem('LPGallery', $tx['filetype']['config'], '?LPGallery&admin=plugin_config&action=plugin_edit');
    XH_registerPluginMenuItem('LPGallery', $tx['filetype']['help'], $pth['file']['plugin_help'], '_blank');
}

// Handles saving and displaying Settings
if (function_exists('XH_wantsPluginAdministration') && XH_wantsPluginAdministration('LPGallery')
    || isset($LPGallery) && $LPGallery === 'true'
) {
    $o .= print_plugin_admin('off');
    switch ($admin) {
        case '':
        case 'plugin_main':
            $o .= '<h1>LPGallery</h1>'
                . '<p>Use {{{LPGallery(\'userfiles/gallery/test\')}}} on a page to show a gallery folder. '
                . 'Change the grid, watermark and other settings under Plugins &gt; LPGallery &gt; Settings.</p>';
            break;
        default:
            $o .= plugin_admin_common($action, $admin, $plugin);
            break;
    }
}
