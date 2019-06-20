<?php
/*
Plugin Name: Razorpay Payment Gateway for Gravity Forms
Plugin URI: https://github.com/isaumya/razorpay-gravity-forms-updated
Description: Integrates Gravity Forms with Razorpay Payments, enabling end users to purchase goods and services through Gravity Forms.
Version: 1.1.1
Author: Saumya Majumder
Author URI: https://www.isaumya.com
Text Domain: razorpay-gravity-forms
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

This is the official Razorpay payment gateway plugin for Gravity Forma. Allows you to accept credit cards, debit cards, netbanking and wallet with the gravity form plugin. It uses a seamles integration, allowing the customer to pay on your website without being redirected away from your website.

*/

define('GF_RAZORPAY_VERSION', '1.1.1');

add_action('gform_loaded', array('GF_Razorpay_Bootstrap', 'load'), 5);

class GF_Razorpay_Bootstrap {

  public static function load() {

    if (!method_exists('GFForms', 'include_payment_addon_framework')) {
      return;
    }

    require_once('class-gf-razorpay.php');

    GFAddOn::register('GFRazorpay');

    // Register Indian Currency in the list of Gravity Forms Currency
    add_filter('gform_currencies', function (array $currencies) {
      $currencies['INR'] = array(
        'name'               => __('Indian Rupee', 'gravityforms'),
        'symbol_left'        => '&#8377;',
        'symbol_right'       => '',
        'symbol_padding'     => ' ',
        'thousand_separator' => ',',
        'decimal_separator'  => '.',
        'decimals'           => 2
      );

      return $currencies;
    });
  }
}

function gf_razorpay() {
  return GFRazorpay::get_instance();
}
