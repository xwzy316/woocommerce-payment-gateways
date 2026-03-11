<?php
/**
 * Plugin Name: SB Payment for WooCommerce
 * Plugin URI: https://www.sbpayment.jp/
 * Description: SB Payment Gateways for WooCommerce
 * Version: 1.0.7
 * Author: xwzy1130
 * Author URI: https://www.m6shop.com/dzkf/zfcj 
 *
 * Text Domain: woo-sbp
 * Domain Path: /i18n/
 *
 * @package woo-sbp
 * @category Payments Method
 * @author xwzy1130
 */
use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_3 as Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WooSBP' ) ) {
	class WooSBP{
		/**
		 * WooCommerce For SoftBank Payment Gateways version.
		 *
		 * @var string
		 */
		public $version = '1.0.4';
	
	    	/**
	    	 * WooCommerce For SoftBank Payment Gateways version.
	    	 *
	    	 * @var string
	    	 */
	    	public $framework_version = '2.0.3';
	
	    	/**
		 * SoftBank Payment Gateways for WooCommerce Constructor.
		 * @access public
		 * @return WooCommerce
		 */
		public function __construct() {
			// WooCommerce For Softbank Payment Gateways version
			define( 'WC_SBP_VERSION', $this->version );
			// JP4WC Framework version
			define( 'JP4WC_SBP_FRAMEWORK_VERSION', $this->framework_version );
			// SB Payment for WooCommerce plugin url
			define( 'WC_SBP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	        	// SB Payment for WooCommerce plugin path
	        	define( 'WC_SBP_ABSPATH', dirname( __FILE__ ) . '/' );
			// API type connection URL
			define( 'JP4WC_SBP_API_URL', 'https://api.sps-system.com/api/xmlapi.do' );
			// Purchase request URL
			define( 'JP4WC_SBP_PURCHASE_LINK_URL', 'https://fep.sps-system.com/f01/FepBuyInfoReceive.do' );
			// Token service connection URL
			define( 'JP4WC_SBP_TOKEN_URL', 'https://token.sps-system.com/sbpstoken/com_sbps_system_token.js' );
			// SANDBOX API type connection URL
			define( 'JP4WC_SBP_SANDBOX_API_URL', 'https://stbfep.sps-system.com/api/xmlapi.do' );
			// SANDBOX Purchase request URL
			define( 'JP4WC_SBP_SANDBOX_PURCHASE_LINK_URL', 'https://stbfep.sps-system.com/f01/FepBuyInfoReceive.do' );
			// SANDBOX Token service connection URL
			define( 'JP4WC_SBP_SANDBOX_TOKEN_URL', 'https://stbtoken.sps-system.com/sbpstoken/com_sbps_system_token.js' );
	        	// Include required files
	        	$this->includes();
	        	$this->init();
		}
		/**
		 * Include required core files used in admin and on the frontend.
		 */
		private function includes() {
	        	//load framework
	        	$version_text = 'v'.str_replace('.', '_', JP4WC_SBP_FRAMEWORK_VERSION);
	        	if ( ! class_exists( '\\ArtisanWorkshop\\WooCommerce\\PluginFramework\\'.$version_text.'\\JP4WC_Plugin' ) ) {
	        	    require_once WC_SBP_ABSPATH.'includes/jp4wc-framework/class-jp4wc-framework.php';
	        	}
			$wc_sbp_options = get_option('wc_sbp_options');
			// Credit Card Payment Gateway
			if(isset($wc_sbp_options['wc_sbp_cc'])){
	            		require_once( WC_SBP_ABSPATH.'includes/gateways/sbp/class-wc-gateway-sbp-cc.php' );	// Credit Card
			}
			// Convenience store
			// Carrier Payment
			if(isset($wc_sbp_options['wc_sbp_mb'])) require_once( WC_SBP_ABSPATH.'includes/gateways/sbp/class-wc-gateway-sbp-mb.php' );
			// AliPay
			if(isset($wc_sbp_options['wc_sbp_ap'])) require_once( WC_SBP_ABSPATH.'includes/gateways/sbp/class-wc-gateway-sbp-ap.php' );
	
			// Admin Setting Screen 
			include_once( WC_SBP_ABSPATH.'includes/class-wc-admin-screen-sbp.php' );
	
		}
		/**
		 * Init WooCommerce when WordPress Initialises.
		 */
		public function init() {
			// Set up localisation
			$this->load_plugin_textdomain();
		}
		/*
		 * Load Localisation files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'woo-sbp' );
			// Global + Frontend Locale
			load_plugin_textdomain( 'woo-sbp', false, plugin_basename( dirname( __FILE__ ) ) . "/i18n" );
		}
	}
	
}
/**
 * Load plugin functions.
 */
add_action( 'plugins_loaded', 'WooSBP_plugin', 0 );

//If WooCommerce Plugins is not activate notice
function WooSBP_fallback_notice(){
	?>
    <div class="error">
        <ul>
            <li><?php echo __( 'SBPayment for WooCommerce is enabled but not effective. It requires WooCommerce in order to work.', 'woo-sbp' );?></li>
        </ul>
    </div>
    <?php
}
function WooSBP_plugin() {
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        new WooSBP();
    } else {
        add_action( 'admin_notices', 'WooSBP_fallback_notice' );
    }
}

register_deactivation_hook( __FILE__, 'jp4wc_sbp_deactivate' );
function jp4wc_sbp_deactivate(){
	flush_rewrite_rules();
}
