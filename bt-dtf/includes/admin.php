<?php
/**
 * BT Transfers — admin status page (status + update check).
 * Same pattern as BT Portal / BT Catalog / BT Quote.
 *
 * This is a submenu under the existing BT Transfers menu (slug btgsb-settings),
 * so the Sheet Builder Settings page stays exactly where it was.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    // Rename the auto-created first submenu item from "BT Transfers" to
    // "Sheet Settings" so the group reads cleanly.
    global $submenu;
    if (isset($submenu['btgsb-settings'][0][0])) $submenu['btgsb-settings'][0][0] = 'Sheet Settings';

    add_submenu_page(
        'btgsb-settings',
        'Status & Updates',
        'Status & Updates',
        'manage_options',
        'bt-transfers-status',
        'btdtf_admin_status_page'
    );
}, 20);

function btdtf_admin_status_page() {
    if (isset($_POST['btdtf_check_update']) && check_admin_referer('btdtf_check_update')) {
        delete_transient('btdtf_manifest');
        delete_site_transient('update_plugins');
        wp_update_plugins();
        echo '<div class="notice notice-success"><p>Update check complete. '
           . '<a href="' . esc_url(admin_url('plugins.php')) . '">Go to the Plugins page</a> if an update is available.</p></div>';
    }

    $manifest = function_exists('btdtf_update_manifest') ? btdtf_update_manifest() : null;
    $latest   = ($manifest && !empty($manifest['version'])) ? $manifest['version'] : '—';
    $behind   = ($latest !== '—' && version_compare($latest, BTDTF_VERSION, '>'));

    // Which modules are live vs standing by behind a still-active snippet.
    $modules = array(
        'Backend (settings, uploads, order screen)' => !function_exists('btgsb_defaults'),
        'Shipping (DTF Sheet Shipping method)'      => !function_exists('btgsb_shipping_init'),
        'Save & Resume'                             => !function_exists('btgsb_ajax_save_sheet_email'),
    );

    $product_id = (int) get_option('btgsb_product_id', 0);
    $settings   = get_option('btgsb_settings', array());
    $tiers      = isset($settings['pricing_tiers']) ? count((array) $settings['pricing_tiers']) : 0;

    global $wpdb;
    $saves_table = $wpdb->prefix . 'btgsb_saves';
    $saves = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $saves_table)) === $saves_table)
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $saves_table") : 0;
    ?>
    <div class="wrap">
      <h1>BT Transfers</h1>

      <table class="widefat striped" style="max-width:680px;margin-top:12px;">
        <tbody>
          <tr><td><strong>Installed version</strong></td><td><?php echo esc_html(BTDTF_VERSION); ?></td></tr>
          <tr><td><strong>Latest version</strong></td>
              <td><?php echo esc_html($latest); ?>
              <?php if ($behind): ?>
                <span style="color:#b8860b;font-weight:700"> &mdash; update available</span>
              <?php elseif ($latest !== '—'): ?>
                <span style="color:#2E7D32;font-weight:700"> &mdash; up to date</span>
              <?php endif; ?></td></tr>
          <tr><td><strong>Checkout product</strong></td>
              <td><?php echo $product_id
                    ? esc_html(get_the_title($product_id)) . ' (ID ' . $product_id . ')'
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
        The Frontend snippet (the builder UI itself) is not part of the plugin yet and should stay active.
      </p>

      <form method="post" style="margin-top:16px;">
        <?php wp_nonce_field('btdtf_check_update'); ?>
        <button type="submit" name="btdtf_check_update" value="1" class="button button-primary">Check for updates now</button>
      </form>
    </div>
    <?php
}
