<?php
/**
 * BT Transfers — status + updates sections.
 *
 * These render at the BOTTOM OF THE PLUGIN'S OWN TOP-LEVEL PAGE, which is where
 * every BT plugin keeps them: BT Portal, BT Catalog, BT Quote and BT Accounts
 * all end their main admin page with Status, then the shared Updates panel.
 * BT Transfers used to hide the same thing behind a "Status & Updates" submenu,
 * which meant the update check was in a different place here than everywhere
 * else for no reason. There is no submenu now — 0.7.5.
 *
 * The Updates block itself is bt_admin_updates_panel() from includes/bt-admin.php,
 * the shared BT Admin Kit, so the wording and the button match the other four.
 * Anything plugin-specific goes ABOVE that panel, which is what the Status and
 * Modules tables below are.
 */
if (!defined('ABSPATH')) exit;

/**
 * Old bookmarks to admin.php?page=bt-transfers-status land on the settings page,
 * which is where this content lives now.
 */
add_action('admin_init', function () {
    if (!is_admin()) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'bt-transfers-status') return;
    if (!current_user_can('manage_options')) return;
    wp_safe_redirect(admin_url('admin.php?page=btgsb-settings'));
    exit;
});

/**
 * Status, Modules and the shared Updates panel.
 * Called from the end of btdtf_settings_page() in backend.php.
 */
function btdtf_admin_status_sections() {
    if (!current_user_can('manage_options')) return;

    // Which modules are live vs standing by behind a still-active snippet.
    $modules = array(
        'Backend (settings, uploads, order screen)' => !function_exists('btgsb_defaults'),
        'Shipping (DTF Sheet Shipping method)'      => !function_exists('btgsb_shipping_init'),
        'Save & Resume'                             => !function_exists('btgsb_ajax_save_sheet_email'),
        'Frontend (the builder UI itself)'          => !function_exists('btgsb_render_builder'),
    );

    $product_id = (int) get_option('btgsb_product_id', 0);
    $settings   = get_option('btgsb_settings', array());
    $tiers      = isset($settings['pricing_tiers']) ? count((array) $settings['pricing_tiers']) : 0;

    global $wpdb;
    $saves_table = $wpdb->prefix . 'btgsb_saves';
    $saves = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $saves_table)) === $saves_table)
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $saves_table") : 0;
    ?>
    <hr style="margin:28px 0">

    <h2>Status</h2>
    <table class="widefat striped" style="max-width:680px;margin-top:12px;">
      <tbody>
        <tr><td><strong>Checkout product</strong></td>
            <td><?php echo $product_id
                  ? esc_html(get_the_title($product_id)) . ' (ID ' . esc_html($product_id) . ')'
                  : '<em>not set</em>'; ?></td></tr>
        <tr><td><strong>Pricing tiers</strong></td><td><?php echo esc_html($tiers); ?></td></tr>
        <tr><td><strong>Saved sheets</strong></td><td><?php echo esc_html($saves); ?></td></tr>
        <tr><td><strong>Builder shortcode</strong></td><td><code>[gang_sheet_builder]</code></td></tr>
      </tbody>
    </table>

    <h2 style="margin-top:26px">Modules</h2>
    <table class="widefat striped" style="max-width:680px;">
      <tbody>
      <?php foreach ($modules as $label => $live): ?>
        <tr>
          <td><strong><?php echo esc_html($label); ?></strong></td>
          <td><?php echo $live
                ? '<span style="color:#2E7D32;font-weight:700">Running from the plugin</span>'
                : '<span style="color:#b8860b;font-weight:700">Standing by</span> &mdash; its WPCode snippet is still active'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p style="color:#666;max-width:680px">
      A module stands down while its old snippet is active so nothing runs twice.
      Deactivate the snippet in WPCode and the plugin picks it up on the next page load.
    </p>
    <?php

    // Shared BT panel — same layout, wording and button on every BT plugin.
    if (function_exists('bt_admin_updates_panel')) {
        bt_admin_updates_panel(array(
            'slug'     => 'bt-dtf',
            'version'  => BTDTF_VERSION,
            'manifest' => 'btdtf_update_manifest',
            'flush'    => 'btdtf_force_update_check',
        ));
    }
}
