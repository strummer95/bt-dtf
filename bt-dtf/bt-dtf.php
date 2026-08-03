<?php
/*
Plugin Name: BT DTF Studio
Plugin URI: https://boomerts.com
Description: Boomer T's DTF gang sheet builder — settings, pricing tiers, sheet upload/AJAX, production files on the order screen and admin email, browser-side PDF generation, and the Awaiting Items tracking flag. Ported from the DTF Studio WPCode snippets.
Version: 0.1.0
Author: Duck and Rabbit Co.
*/

if (!defined('ABSPATH')) exit;

define('BTDTF_VERSION', '0.1.0');
define('BTDTF_DIR',  plugin_dir_path(__FILE__));
define('BTDTF_URL',  plugin_dir_url(__FILE__));
define('BTDTF_FILE', __FILE__);

/**
 * CONFLICT GUARD — the WPCode snippets declare the same btgsb_* functions.
 * If a snippet is still active, loading them again is a fatal redeclare and
 * the whole site white-screens. So: detect, refuse to load, and say so.
 */
add_action('admin_notices', function () {
    if (!get_option('btdtf_blocked_by_snippet')) return;
    echo '<div class="notice notice-error"><p><strong>BT DTF Studio is not running.</strong> '
       . 'The old <em>DTF Studio</em> WPCode snippet is still active and declares the same functions. '
       . 'Deactivate the snippet in WPCode, then reload this page.</p></div>';
});

if (function_exists('btgsb_defaults') || function_exists('btgsb_settings_page')) {
    update_option('btdtf_blocked_by_snippet', 1);
    return;   // snippet wins; plugin stays inert so the site keeps running
}
delete_option('btdtf_blocked_by_snippet');

require_once BTDTF_DIR . 'includes/backend.php';   // settings, AJAX, Woo hooks, PDF, Awaiting Items flag
require_once BTDTF_DIR . 'includes/updater.php';
