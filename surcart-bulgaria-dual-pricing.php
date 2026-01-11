<?php

/**
 * Plugin Name: SureCart Bulgaria Dual Pricing
 * Description: Dual pricing for SureCart in Bulgaria.
 * Version: 1.0.2
 * Author: SureCart
 * Author URI: https://surecart.com
 */

if (! defined('ABSPATH')) {
    exit;
}

include_once __DIR__ . '/surecart-bulgaria-set-pricing-attribute.php';

/**
 * Bootstrap the plugin.
 */
add_action('plugins_loaded', function () {
    // SureCart plugin is not loaded.
    if (! defined('SURECART_PLUGIN_FILE')) {
        return;
    }

    // SureCart plugin is loaded.
    new SureCartBulgariaSetPricingAttribute();
});
