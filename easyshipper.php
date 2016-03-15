<?php
/*
Plugin Name: EasyShipper
Plugin URI: http://seanvoss.com/easypost
Description: Provides an integration for EasyPost for woo-commerece.
Version: 0.5
Author: Sean Voss
Author URI: http://seanvoss.com/easypost

*/

/*
 * Title   : EasyPost shipping extension for WooCommerce
 * Author  : Sean Voss
 * Url     : http://seanvoss.com/easypost
 * License : http://seanvoss.com/license/license.html
 */

/**
 * Check if WooCommerce is active
 **/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option( 'active_plugins')))) {

    // Order of Plugin Loading Requires this line, should not be necessary
    require_once (dirname(__FILE__) .'/../woocommerce/woocommerce.php');

    if (class_exists('WC_Shipping_Method'))
    {
        include_once('easypost_shipping.php');
    }
}
