<?php
/**
 * Meta (Facebook) Purchase tracking for DTF Studio orders.
 *
 * The Meta Pixel base code is already on every page of the site (Code Snippets,
 * "Meta Pixel"). This module only adds the Purchase event on the order received
 * page, for orders that contain the gang sheet product, so ads can optimize for
 * real DTF sales. Fires once per order: a refresh of the thank-you page does not
 * count a second sale. AddToCart is fired from the builder (frontend.php).
 *
 * If the pixel is not on the page (snippet off, ad blocker) nothing happens.
 */
if (!defined('ABSPATH')) exit;

add_action('woocommerce_thankyou', 'btdtf_meta_purchase', 20);

function btdtf_meta_purchase($order_id) {
    if (!$order_id || !function_exists('wc_get_order')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;
    if ($order->get_meta('_btdtf_fb_purchase_sent')) return;
    if ($order->has_status('failed')) return;

    $pid = (int) get_option('btgsb_product_id', 0);
    if (!$pid) return;
    $has_dtf = false;
    foreach ($order->get_items() as $item) {
        if ((int) $item->get_product_id() === $pid) { $has_dtf = true; break; }
    }
    if (!$has_dtf) return;

    $order->update_meta_data('_btdtf_fb_purchase_sent', time());
    $order->save();

    $data = array(
        'value'        => round((float) $order->get_total(), 2),
        'currency'     => $order->get_currency(),
        'content_name' => 'DTF Gang Sheet',
        'content_type' => 'product',
        'content_ids'  => array((string) $pid),
    );
    $event_id = 'dtf-purchase-' . $order->get_id();
    ?>
<script>
(function(){
  if (typeof fbq !== 'function') return;
  fbq('track', 'Purchase', <?php echo wp_json_encode($data); ?>, {eventID: <?php echo wp_json_encode($event_id); ?>});
})();
</script>
    <?php
}
