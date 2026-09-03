<?php
/**
 * BT Transfers — Completed order email wording
 *
 * WooCommerce sends one "order complete" email no matter how the customer is
 * getting their order, and its stock wording ("Good things are heading your
 * way!") only makes sense for a shipped order. A pickup customer reads that and
 * waits at home for a package that is sitting on the counter in Oswego.
 *
 * This module rewrites the subject and heading of that one email based on the
 * shipping method actually on the order, and drops a short block above the
 * order table telling a pickup customer where to come.
 *
 * It touches nothing else. The order table, totals, footer and every other
 * WooCommerce email are untouched.
 */

defined('ABSPATH') || exit;

/**
 * Is this order being picked up rather than shipped?
 *
 * Returns 'pickup', 'ship', or 'unknown'. 'unknown' matters: an order with no
 * shipping line at all should not be guessed either way, because telling
 * someone their order is ready for pickup when it is actually in the mail is
 * exactly the failure this module exists to fix.
 */
function btdtf_order_fulfillment_type($order) {
    if (!$order instanceof WC_Order) return 'unknown';

    $methods = $order->get_shipping_methods();
    if (empty($methods)) return 'unknown';

    $saw_shipping = false;

    foreach ($methods as $method) {
        $id    = (string) $method->get_method_id();   // e.g. local_pickup, btgsb_shipping
        $label = (string) $method->get_name();        // whatever it is titled at checkout

        // method_id is the reliable signal. The label is a fallback because a
        // pickup option is sometimes set up as a zero-cost flat rate named
        // "Pickup", which has no pickup-specific method_id.
        if (stripos($id, 'pickup') !== false || stripos($label, 'pickup') !== false) {
            return 'pickup';
        }
        $saw_shipping = true;
    }

    return $saw_shipping ? 'ship' : 'unknown';
}

/**
 * Store address, straight from WooCommerce settings so there is no second copy
 * of it to drift out of date. Returns '' if the store address is not filled in.
 */
function btdtf_pickup_address_html() {
    $parts = array_filter([
        get_option('woocommerce_store_address'),
        get_option('woocommerce_store_address_2'),
    ]);
    $city  = get_option('woocommerce_store_city');
    $post  = get_option('woocommerce_store_postcode');

    $state = '';
    $raw   = (string) get_option('woocommerce_default_country');
    if (strpos($raw, ':') !== false) {
        list(, $state) = explode(':', $raw, 2);
    }

    $line2 = trim(implode(' ', array_filter([$city ? $city . ',' : '', $state, $post])));
    if ($line2) $parts[] = $line2;

    if (empty($parts)) return '';
    return implode('<br>', array_map('esc_html', $parts));
}

function btdtf_emails_register_hooks() {

    /* -- Subject and heading ------------------------------------------ */

    add_filter('woocommerce_email_subject_customer_completed_order', function ($subject, $order) {
        $type = btdtf_order_fulfillment_type($order);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        if ($type === 'pickup') {
            return sprintf('Your %s order is ready for pickup', $site);
        }
        if ($type === 'ship') {
            return sprintf('Your %s order has shipped', $site);
        }
        return $subject;
    }, 10, 2);

    add_filter('woocommerce_email_heading_customer_completed_order', function ($heading, $order) {
        $type = btdtf_order_fulfillment_type($order);

        if ($type === 'pickup') {
            return 'Your order is ready for pickup!';
        }
        if ($type === 'ship') {
            return 'Your order is on its way!';
        }
        return $heading;
    }, 10, 2);

    /* -- Callout above the order table --------------------------------- */

    add_action('woocommerce_email_before_order_table', function ($order, $sent_to_admin, $plain_text, $email) {
        if ($sent_to_admin) return;
        if (!isset($email->id) || $email->id !== 'customer_completed_order') return;

        $type = btdtf_order_fulfillment_type($order);
        if ($type !== 'pickup') return;

        $address = btdtf_pickup_address_html();
        $phone   = get_option('woocommerce_store_phone');

        if ($plain_text) {
            echo "\n" . strtoupper('Ready for pickup') . "\n";
            echo "Your order is finished and waiting for you. Nothing is being shipped.\n";
            if ($address) {
                echo "\n" . wp_strip_all_tags(str_replace('<br>', "\n", $address)) . "\n";
            }
            if ($phone) {
                echo $phone . "\n";
            }
            echo "\n";
            return;
        }

        echo '<div style="margin:0 0 24px;padding:18px 20px;background:#f0eff8;border-left:4px solid #27267e;border-radius:4px;">'
           . '<p style="margin:0 0 6px;color:#1a1060;font-size:16px;font-weight:700;">Ready for pickup</p>'
           . '<p style="margin:0;color:#444;font-size:14px;line-height:1.6;">'
           . 'Your order is finished and waiting for you at our shop. Nothing is being shipped.'
           . '</p>';

        if ($address) {
            echo '<p style="margin:12px 0 0;color:#444;font-size:14px;line-height:1.6;">' . $address . '</p>';
        }
        if ($phone) {
            echo '<p style="margin:6px 0 0;color:#444;font-size:14px;line-height:1.6;">'
               . esc_html($phone) . '</p>';
        }

        echo '</div>';
    }, 5, 4);
}

add_action('plugins_loaded', function () {
    btdtf_emails_register_hooks();
}, 999);
