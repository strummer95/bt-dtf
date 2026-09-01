<?php
/*
Plugin Name: BT Transfers
Plugin URI: https://boomerts.com
Description: Boomer T's DTF transfer + gang sheet builder — settings and pricing tiers, sheet upload/AJAX, production files on the order screen and admin email, browser-side PDF generation, the Awaiting Items tracking flag, the DTF shipping method, and Save & Resume.
Version: 0.4.7
Author: Duck and Rabbit Co.
*/

if (!defined('ABSPATH')) exit;

define('BTDTF_VERSION', '0.4.7');
define('BTDTF_DIR',  plugin_dir_path(__FILE__));
define('BTDTF_URL',  plugin_dir_url(__FILE__));
define('BTDTF_FILE', __FILE__);

/**
 * Safe to load in any order, with or without the DTF Studio WPCode snippets.
 *
 *  - Every function is prefixed btdtf_ and every class BTDTF_, so no name is
 *    shared with the snippets' btgsb_* set. A fatal redeclare is impossible.
 *  - Nothing hooks at load time. Each module waits for plugins_loaded (999),
 *    after WPCode has run, and stands down INDEPENDENTLY if its own snippet is
 *    still active — so you can cut over one snippet at a time.
 *
 * Option names, DB tables, order meta keys, AJAX actions, the nonce, the cron
 * hook and the shipping method ID all stay btgsb_*, so existing orders, saved
 * sheets, shipping-zone rates and the Frontend snippet keep working unchanged.
 */
require_once BTDTF_DIR . 'includes/backend.php';    // settings, AJAX, Woo hooks, PDF, Awaiting Items
require_once BTDTF_DIR . 'includes/shipping.php';   // DTF Sheet Shipping method
require_once BTDTF_DIR . 'includes/save.php';       // Save & Resume
require_once BTDTF_DIR . 'includes/frontend.php';   // [gang_sheet_builder] builder UI
require_once BTDTF_DIR . 'includes/updater.php';
require_once BTDTF_DIR . 'includes/admin.php';      // status + check-for-updates page

/** Which snippets are still holding a module dormant (shown on the Plugins screen). */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'plugins') return;

    $waiting = array();
    if (function_exists('btgsb_defaults'))              $waiting[] = 'DTF Studio — Backend';
    if (function_exists('btgsb_shipping_init'))         $waiting[] = 'DTF Studio - Shipping';
    if (function_exists('btgsb_ajax_save_sheet_email')) $waiting[] = 'DTF Studio - Save';
    if (function_exists('btgsb_render_builder'))        $waiting[] = 'DTF Studio - Frontend';
    if (!$waiting) return;

    echo '<div class="notice notice-info"><p><strong>BT Transfers is installed and running alongside your snippets.</strong> '
       . 'These modules are standing by because their snippet is still active: <strong>'
       . esc_html(implode(', ', $waiting)) . '</strong>. '
       . 'Nothing is broken — deactivate them in WPCode one at a time whenever you are ready, '
       . 'and the plugin picks each one up on the next page load.</p></div>';
});
