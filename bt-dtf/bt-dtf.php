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
 * CONFLICT GUARD
 *
 * The plugin and the old "DTF Studio — Backend" WPCode snippet declare the
 * same btgsb_* functions. PHP fatals on redeclare, which white-screens the
 * site — so both must never run at once.
 *
 * function_exists() is NOT usable here: plugins load during wp-settings.php,
 * long before WPCode evaluates its snippets, so the snippet's functions do
 * not exist yet at this point. Instead we look for an ACTIVE snippet whose
 * code declares ANY function this plugin declares, reading WPCode's own storage.
 *
 * If one is found the plugin stays completely inert and shows a notice, so
 * the site keeps running on the snippet exactly as before.
 */
function btdtf_declared_functions() {
    // Every function this plugin declares. If ANY active WPCode snippet also
    // declares one of these, running both is a fatal redeclare.
    return array(
        'btgsb_defaults', 'btgsb_ensure_product', 'btgsb_settings_page',
        'btgsb_ensure_png_dpi', 'btgsb_production_files_html',
        'btgsb_ajax_save_sheet', 'btgsb_ajax_add_to_cart',
        'btgsb_render_pdf_download_page',
        'btgsb_await_is', 'btgsb_await_set', 'btgsb_await_badge',
        'btgsb_await_bulk_actions', 'btgsb_await_bulk_handle',
        'btgsb_await_count', 'btgsb_await_view_link',
        'btgsb_branded_email_html', 'btgsb_send_customer_email',
    );
}

/** Titles of active snippets that would collide. Empty array = safe to load. */
function btdtf_conflicting_snippets() {
    $cached = get_transient('btdtf_snippet_conflict');
    if (is_array($cached)) return $cached;

    global $wpdb;
    $titles = array();

    $where = array();
    $vals  = array('%wpcode%');
    foreach (btdtf_declared_functions() as $fn) {
        $where[] = 'post_content LIKE %s';
        $vals[]  = '%' . $wpdb->esc_like('function ' . $fn) . '%';
    }
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT post_title FROM {$wpdb->posts}
          WHERE post_type LIKE %s AND post_status = 'publish'
            AND (" . implode(' OR ', $where) . ")", $vals
    ));
    if ($rows) $titles = array_merge($titles, $rows);

    // Some WPCode versions keep the code in postmeta instead.
    $where = array();
    $vals  = array('%wpcode%');
    foreach (btdtf_declared_functions() as $fn) {
        $where[] = 'm.meta_value LIKE %s';
        $vals[]  = '%' . $wpdb->esc_like('function ' . $fn) . '%';
    }
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT p.post_title FROM {$wpdb->postmeta} m
           INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
          WHERE p.post_type LIKE %s AND p.post_status = 'publish'
            AND (" . implode(' OR ', $where) . ")", $vals
    ));
    if ($rows) $titles = array_merge($titles, $rows);

    $titles = array_values(array_unique(array_filter($titles)));
    set_transient('btdtf_snippet_conflict', $titles, 60);
    return $titles;
}

// Clearing the cache after any snippet edit means the notice updates promptly.
add_action('save_post', function ($id, $post) {
    if (isset($post->post_type) && strpos($post->post_type, 'wpcode') !== false)
        delete_transient('btdtf_snippet_conflict');
}, 10, 2);

$btdtf_conflicts = btdtf_conflicting_snippets();
if ($btdtf_conflicts) {
    add_action('admin_notices', function () {
        $list = btdtf_conflicting_snippets();
        echo '<div class="notice notice-error"><p><strong>BT DTF Studio is installed but NOT running.</strong> '
           . 'These active WPCode snippets declare the same functions this plugin does, and running both at once '
           . 'would crash the site &mdash; so the plugin has stayed switched off:</p><p><strong>'
           . esc_html(implode(', ', $list)) . '</strong></p>'
           . '<p>Deactivate them in <strong>WPCode &rarr; Code Snippets</strong>, then reload this page. '
           . 'Everything keeps working on the snippets until you do.</p></div>';
    });
    return;   // snippets win - plugin loads nothing
}

require_once BTDTF_DIR . 'includes/backend.php';   // settings, AJAX, Woo hooks, PDF, Awaiting Items flag
require_once BTDTF_DIR . 'includes/updater.php';
