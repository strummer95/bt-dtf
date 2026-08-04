<?php
/**
 * BT DTF Studio — Shipping module (ported from the DTF Studio - Shipping snippet)
 *
 * Function and class names are btdtf_/BTDTF_ so they cannot collide with the
 * snippet. The shipping method ID stays 'btgsb_shipping' deliberately: that is
 * the key WooCommerce stored in your shipping zone and in the option
 * woocommerce_btgsb_shipping_settings, so your configured rates carry over.
 *
 * Original header:
 * PresStora Studio — Snippet 3: Shipping
 * Registers a custom WooCommerce shipping method for DTF gang sheets.
 * Calculates USPS Priority Mail rate based on sheet weight.
 */

defined('ABSPATH') || exit;

function btdtf_shipping_init() {

    if (class_exists('BTDTF_Shipping_Method')) return;

    class BTDTF_Shipping_Method extends WC_Shipping_Method {

        public function __construct($instance_id = 0) {
            $this->id                 = 'btgsb_shipping';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = 'DTF Sheet Shipping';
            $this->method_description = 'USPS Priority Mail — calculated by sheet weight. Add to your shipping zone.';
            $this->supports           = ['shipping-zones', 'instance-settings'];
            $this->title              = $this->get_option('title', 'Shipping');
            $this->init();
        }

        public function init() {
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title', 'Shipping');
            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->instance_form_fields = [
                'title' => [
                    'title'   => 'Method Title',
                    'type'    => 'text',
                    'default' => 'Shipping',
                    'desc_tip'=> true,
                    'description' => 'Label shown to customers at checkout.',
                ],
                'base_rate' => [
                    'title'   => 'Base Rate ($)',
                    'type'    => 'number',
                    'default' => '8.95',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                    'description' => 'Minimum shipping charge.',
                ],
                'rate_per_oz' => [
                    'title'   => 'Rate Per Oz Over 1lb ($)',
                    'type'    => 'number',
                    'default' => '0.25',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                    'description' => 'Added per oz above 16oz.',
                ],
                'oz_per_sqin' => [
                    'title'   => 'Sheet Weight (oz per sq in)',
                    'type'    => 'number',
                    'default' => '0.012',
                    'custom_attributes' => ['step' => '0.001', 'min' => '0'],
                    'description' => 'DTF transfer film weight per square inch. ~0.012 oz/sq in for standard film.',
                ],
            ];
        }

        public function calculate_shipping($package = []) {
            $base_rate   = floatval($this->get_option('base_rate',   '8.95'));
            $rate_per_oz = floatval($this->get_option('rate_per_oz', '0.25'));
            $oz_per_sqin = floatval($this->get_option('oz_per_sqin', '0.012'));

            // Get total sq inches from cart
            $total_sq_in = 0;
            foreach ($package['contents'] as $item) {
                $cart_item = $item['data'];
                $sq_in = floatval($item['btgsb_sq_inches'] ?? 0);
                if ($sq_in > 0) {
                    $total_sq_in += $sq_in * $item['quantity'];
                }
            }

            // Fallback: use cart item weight if no sq in meta
            if ($total_sq_in <= 0) {
                $cost = $base_rate;
            } else {
                $weight_oz   = $total_sq_in * $oz_per_sqin;
                $over_oz     = max(0, $weight_oz - 16);
                $cost        = $base_rate + ($over_oz * $rate_per_oz);
            }

            $cost = round($cost, 2);

            $this->add_rate([
                'id'    => $this->get_rate_id(),
                'label' => $this->title,
                'cost'  => $cost,
            ]);
        }
    }
}

function btdtf_shipping_snippet_is_active() {
    return function_exists('btgsb_shipping_init') || class_exists('BTGSB_Shipping_Method');
}

add_action('plugins_loaded', function () {
    if (btdtf_shipping_snippet_is_active()) return;   // snippet still on — stay dormant
    btdtf_shipping_register_hooks();
}, 999);

function btdtf_shipping_register_hooks() {
    add_action('woocommerce_shipping_init', 'btdtf_shipping_init');

    add_filter('woocommerce_shipping_methods', function ($methods) {
        $methods['btgsb_shipping'] = 'BTDTF_Shipping_Method';
        return $methods;
    });
}
