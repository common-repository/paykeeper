<?php
/**
 * Plugin Name: PayKeeper
 * Plugin URI: https://docs.paykeeper.ru/cms/
 * Description: Легко добавляет платежный шлюз PayKeeper в плагин WooCommerce для приема платежей через платформу PayKeeper.
 * Version: 2.0
 * Author: PayKeeper development department
 * Author URI: https://paykeeper.ru/
 * Requires at least: 4.2
 * License: GPL2 or Later
 */

if (!defined('ABSPATH')) {
    //Exit if accessed directly
    exit;
}

if (!class_exists('WC_PayKeeper_Gateway_Addon')){

    class WC_PayKeeper_Gateway_Addon {

		var $version = '2.0';
		var $db_version = '2.0';
		var $plugin_url;
		var $plugin_path;

		function __construct() {
			$this->define_constants();
			$this->loader_operations();
			add_action('init', array(&$this, 'plugin_init'), 0);
		}

		function define_constants() {
			define('WC_PK_ADDON_VERSION', $this->version);
			define('WC_PK_ADDON_URL', $this->plugin_url());
			define('WC_PK_ADDON_PATH', $this->plugin_path());
		}

		function includes() {
			include_once('paykeeper-gateway-class.php');
		}

		function loader_operations() {
			add_action('plugins_loaded', array(&$this, 'plugins_loaded_handler')); //plugins loaded hook
		}

		function plugins_loaded_handler() {
			//Runs when plugins_loaded action gets fired
			include_once('paykeeper-gateway-class.php');
			add_filter('woocommerce_payment_gateways', array(&$this, 'init_paykeeper_gateway'));
		}

		function do_db_upgrade_check() {
			//NOP
		}

		function plugin_url() {
			if ($this->plugin_url)
				return $this->plugin_url;
			return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
		}

		function plugin_path() {
			if ($this->plugin_path)
				return $this->plugin_path;
			return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
		}

		function plugin_init() {
			//NOP
		}

		function init_paykeeper_gateway($methods) {
			array_push($methods, 'WC_PK_Gateway');
			return $methods;
		}
	}
}

$GLOBALS['WC_PayKeeper_Gateway_Addon'] = new WC_PayKeeper_Gateway_Addon();

add_action('plugins_loaded', 'woocommerce_paykeeper_plugin', 0);
function woocommerce_paykeeper_plugin(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class

    include(plugin_dir_path(__FILE__) . 'paykeeper-gateway-class.php');
}


add_filter('woocommerce_payment_gateways', 'add_paykeeper');

function add_paykeeper($gateways) {
  $gateways[] = 'WC_PK_Gateway';
  return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature
*/
function paykeeper_declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'paykeeper_declare_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'paykeeper_oawoo_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type
 */
function paykeeper_oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new Paykeeper_Blocks );
        }
    );
}