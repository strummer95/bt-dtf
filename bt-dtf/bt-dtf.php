<?php
/*
Plugin Name: BT DTF Studio
Plugin URI: https://boomerts.com
Description: Boomer T's DTF gang sheet builder — settings, pricing tiers, sheet upload/AJAX, production files on the order screen and admin email, browser-side PDF generation, and the Awaiting Items tracking flag.
Version: 0.2.0
Author: Duck and Rabbit Co.
*/

if (!defined('ABSPATH')) exit;

define('BTDTF_VERSION', '0.2.0');
define('BTDTF_DIR',  plugin_dir_path(__FILE__));
define('BTDTF_URL',  plugin_dir_url(__FILE__));
define('BTDTF_FILE', __FILE__);

/**
 * Safe to load in any order, with or without the old WPCode snippets active:
 *
 *  - Every function here is prefixed btdtf_ and every constant BTDTF_, so it
 *    shares no name with the snippet's btgsb_* set. A fatal redeclare — the
 *    thing that took the site down on 0.1.0 — is impossible.
 *  - Nothing hooks at load time. backend.php waits for plugins_loaded (999),
 *    after WPCode has run, and stands down if the snippet is still active.
 *
 * Option names, order meta keys, AJAX actions and the nonce are deliberately
 * left as btgsb_*, so existing orders, saved settings and the DTF Studio
 * Frontend snippet all keep working unchanged.
 */
require_once BTDTF_DIR . 'includes/backend.php';
require_once BTDTF_DIR . 'includes/updater.php';
