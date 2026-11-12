<?php
/*
Plugin Name: BT Transfers
Plugin URI: https://boomerts.com
Description: Boomer T's DTF transfer + gang sheet builder — settings and pricing tiers, sheet upload/AJAX, production files on the order screen and admin email, browser-side PDF generation, the Awaiting Items tracking flag, the DTF shipping method, and Save & Resume.
Version: 0.9.2
Author: Duck and Rabbit Co.
*/

if (!defined('ABSPATH')) exit;

define('BTDTF_VERSION', '0.9.2');
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
require_once BTDTF_DIR . 'includes/emails.php';     // completed-order wording: pickup vs shipped
require_once BTDTF_DIR . 'includes/frontend.php';   // [gang_sheet_builder] builder UI
require_once BTDTF_DIR . 'includes/meta-pixel.php'; // Meta Purchase event for DTF orders
require_once BTDTF_DIR . 'includes/updater.php';
require_once BTDTF_DIR . 'includes/bt-admin.php';   // shared BT Updates panel (byte-identical across BT plugins)
require_once BTDTF_DIR . 'includes/admin.php';      // status + updates sections on the settings page

/**
 * The site's two addresses disagree, which is what "Not secure" means here.
 *
 * WordPress keeps two: the Site Address (home), the public address people
 * visit, and the WordPress Address (siteurl), which pages, the admin and
 * asset links are built from. They are meant to match. A server move, or a
 * certificate added after the fact, routinely leaves siteurl on http while
 * home is already https - and then the site keeps serving itself over http
 * no matter how good the certificate is, and the browser says so.
 *
 * It is a one-line fix in Settings > General that is easy to never find, so
 * it is offered here as a button. Only when the two differ by scheme alone,
 * only ever moving TO https, and only after checking the https address
 * actually answers - if it does not, nothing is changed.
 */
/**
 * The value siteurl SHOULD take, or '' to leave the site alone.
 *
 * Deliberately narrow. It only acts when the two addresses are the same
 * address written with a different scheme, and only to move onto https. A
 * WordPress in a subdirectory legitimately has a siteurl the home address
 * does not share, and somebody may have set these apart on purpose; neither
 * is ours to rewrite.
 */
function btdtf_siteurl_fix_target($site, $home) {
    $site = untrailingslashit(trim((string) $site));
    $home = untrailingslashit(trim((string) $home));
    if ($site === '' || $home === '' || $site === $home) return '';
    if (stripos($home, 'https://') !== 0) return '';          // never move TO http
    if (stripos($site, 'http://')  !== 0) return '';          // only fix an http siteurl
    $bare = function ($u) { return strtolower(preg_replace('#^https?://#i', '', $u)); };
    if ($bare($site) !== $bare($home)) return '';             // different address, not a scheme difference
    return $home;
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;

    $msg = isset($_GET['btdtf_url_fix']) ? sanitize_key(wp_unslash($_GET['btdtf_url_fix'])) : '';
    if ($msg === 'done') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>WordPress Address updated.</strong> '
           . 'Both addresses now read ' . esc_html(get_option('home')) . '. '
           . 'Reload the front of the site and the padlock should be there. If anything still shows a warning, '
           . 'it is an http link saved inside a page rather than a setting.</p></div>';
    } elseif ($msg === 'failed') {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Nothing was changed.</strong> '
           . esc_html(get_option('home')) . ' did not answer, so the WordPress Address was left alone. '
           . 'Check the certificate is installed and that port 443 is open on the server, then try again.</p></div>';
    }

    $site = (string) get_option('siteurl');
    $home = (string) get_option('home');
    if (btdtf_siteurl_fix_target($site, $home) === '') return;

    echo '<div class="notice notice-warning"><p>'
       . '<strong>This site\'s two addresses do not match, which is why browsers show &ldquo;Not secure&rdquo;.</strong><br>'
       . 'WordPress Address: <code>' . esc_html($site) . '</code><br>'
       . 'Site Address: <code>' . esc_html($home) . '</code></p>'
       . '<p>Pages and the admin are built from the first one, so the site keeps serving over http even though '
       . 'its certificate works. Setting them to match is what clears the warning.</p>'
       . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0 6px">'
       . '<input type="hidden" name="action" value="btdtf_fix_siteurl">'
       . wp_nonce_field('btdtf_fix_siteurl', '_wpnonce', true, false)
       . '<button type="submit" class="button button-primary">Set WordPress Address to ' . esc_html($home) . '</button>'
       . ' <span style="color:#666;margin-left:8px">Checks that address answers first, and changes nothing if it does not.</span>'
       . '</form></div>';
});

add_action('admin_post_btdtf_fix_siteurl', function () {
    if (!current_user_can('manage_options')) wp_die('Unauthorized', '', array('response' => 403));
    check_admin_referer('btdtf_fix_siteurl');

    $home = (string) get_option('home');
    $fail = function () { wp_safe_redirect(add_query_arg('btdtf_url_fix', 'failed', admin_url())); exit; };

    if (btdtf_siteurl_fix_target(get_option('siteurl'), $home) === '') $fail();

    // Do not take the site to an address that does not answer.
    $resp = wp_remote_get($home, array('timeout' => 15, 'redirection' => 2));
    $code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);
    if ($code < 200 || $code >= 400) $fail();

    update_option('siteurl', untrailingslashit($home));

    // admin_url() builds on the value we just wrote, so this lands on https.
    wp_safe_redirect(add_query_arg('btdtf_url_fix', 'done', admin_url()));
    exit;
});

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
