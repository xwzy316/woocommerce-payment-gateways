<?php
/**
 * SB Payment Service Gateways for WooCommerce
 *
 * Provides a SB Payment Service.
 * Admin Page control
 *
 * @class 		WC_Admin_Screen_SBPS
 * @version		1.0.0
 * @author		xwzy1130
 */
use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_3 as Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $_SESSION;

class WC_Admin_Screen_SBPS {

	public $jp4wc_plugin;
	public $prefix;
	public $post_prefix;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'wc_admin_sbp_menu' ) ,55 );
		add_action( 'admin_notices', array( $this, 'sbp_ssl_check' ) );
		add_action( 'admin_init', array( $this, 'wc_setting_sbp_init') );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		$this->jp4wc_plugin = new Framework\JP4WC_Plugin();
		$this->prefix =  'wc_sbp_';
		$this->post_prefix =  'sbp_';
	}
	/**
	 * Admin Menu
	 */
	public function wc_admin_sbp_menu() {
		$page = add_submenu_page( 'woocommerce', __( 'SBPS Setting', 'woo-sbp' ), __( 'SBPS Setting', 'woo-sbp' ), 'manage_woocommerce', 'jp4wc-sbp-output', array( $this, 'wc_sbp_output' ) );
	}

	/**
	 * Admin Screen output
	 */
	public function wc_sbp_output() {
		$tab = ! empty( $_GET['tab'] ) && $_GET['tab'] == 'info' ? 'info' : 'setting';
		include( 'views/html-admin-screen.php' );
	}

	/**
	 * Admin page for Setting
	 */
	public function admin_sbp_setting_page() {
		include( 'views/html-admin-setting-screen.php' );
	}

	/**
	 * Admin page for infomation
	 */
	public function admin_sbp_info_page() {
		include( 'views/html-admin-info-screen.php' );
	}
	
	/**
	 * Check if SSL is enabled and notify the user.
	 */
	function sbp_ssl_check() {
		if(isset($this->enabled)){
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				echo '<div class="error"><p>' . sprintf( __('SB Payment Service for WooCommerce is enabled and the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woo-sbp' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
			}
		}
	}

	function wc_setting_sbp_init(){
		global $woocommerce;
		register_setting( 
			'wc_sbp',// Option Group
			'wc_sbp_options',// Option Name
			array( 'validate_options' )// Sanitize callback
		);
		// Basic Setting
		add_settings_section(
			'wc_sbp_basic',// id
			__( 'Basic Setting', 'woo-sbp' ),// title
			'',// callback
			'wc_sbp'// page
		);
		add_settings_field(
			'wc_sbp_basic_ip_address',// id
			__( 'Server IP address', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_basic_ip_address' ),// callback
			'wc_sbp',// page
			'wc_sbp_basic'// section
		);
		add_settings_field(
			'wc_sbp_basic_result_url',// id
			__( 'Result destination URL','woo-sbp' ),// title
			array( $this, 'wc_sbp_basic_result_url' ),// callback
			'wc_sbp',// page
			'wc_sbp_basic'// section
		);

		// Environment connection information Setting
		add_settings_section(
			'wc_sbp_environment',// id
			__( 'Environment connection information', 'woo-sbp' ),// title
			'',// callback
			'wc_sbp'// section
		);
		add_settings_field(
			'wc_sbp_merchant_id',// id
			__( 'Merchant ID', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_merchant_id' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_mid', 'slug' =>'mid','class' => 'wc_sbp_merchant_id', 'text_num' => 5]// $arge
		);
		add_settings_field( 
			'wc_sbp_service_id',// id 
			__( 'Service ID', 'woo-sbp' ), // title
			array( $this, 'wc_sbp_service_id' ), // callback
			'wc_sbp', // page
			'wc_sbp_environment', // section
			['label_for' => 'wc_sbp_sid', 'slug' =>'sid','class' => 'wc_sbp_service_id', 'text_num' => 3]// $arge
		);
		add_settings_field( 
			'wc_sbp_link_hashkey',// id
			__( 'Link Hash Code', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_link_hashkey' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_link_hashkey', 'slug' =>'link_hashkey','class' => 'wc_sbp_link_hashkey', 'text_num' => 45]// $arge
		);
		add_settings_field( 
			'wc_sbp_hashkey',// id
			__( 'Hash Code', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_hashkey' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_hashkey', 'slug' =>'hashkey','class' => 'wc_sbp_hashkey', 'text_num' => 45]// $arge
		);
		add_settings_field( 
			'wc_sbp_3des_encry',// id
			__( '3DES Encryption key', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_3des_encry' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_3des_encry', 'slug' =>'3des_encry','class' => 'wc_sbp_3des_encry', 'text_num' => 27]// $arge
		);
		add_settings_field( 
			'wc_sbp_3des_init',// id
			__( '3DES Initialization key', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_3des_init' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_3des_init', 'slug' =>'3des_init','class' => 'wc_sbp_3des_init', 'text_num' => 9]// $arge
		);
		add_settings_field( 
			'wc_sbp_basic_id',// id
			__( 'Basic authentication ID', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_basic_id' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_basic_id', 'slug' =>'basic_id','class' => 'wc_sbp_basic_id', 'text_num' => 9]// $arge
		);
		add_settings_field( 
			'wc_sbp_basic_pass',// id
			__( 'Basic authentication Password', 'woo-sbp' ),// title
			array( $this, 'wc_sbp_basic_pass' ),// callback
			'wc_sbp',// page
			'wc_sbp_environment',// section
			['label_for' => 'wc_sbp_basic_pass', 'slug' =>'basic_pass','class' => 'wc_sbp_basic_pass', 'text_num' => 45]// $arge
		);

		// SB Payment Service Method Setting
		add_settings_section(
			'wc_sbp_payment_method',
			__( 'SB Payment Service Method', 'woo-sbp' ),
			'',
			'wc_sbp'
		);
		add_settings_field(
			'wc_sbp_payment_cc',
			__( 'Credit Card', 'woo-sbp' ),
			array( $this, 'wc_sbp_payment_cc' ),
			'wc_sbp',
			'wc_sbp_payment_method',
			['label_for' => 'wc_sbp_payment_cc', 'slug' =>'wc_sbp_cc', 'class' => 'wc_sbp_payment_cc']// $arge
		);
		add_settings_field(
			'wc_sbp_payment_mb',
			__( 'Carrier Payment', 'woo-sbp' ),
			array( $this, 'wc_sbp_payment_mb' ),
			'wc_sbp',
			'wc_sbp_payment_method',
			['label_for' => 'wc_sbp_payment_mb', 'slug' =>'wc_sbp_mb', 'class' => 'wc_sbp_payment_mb']// $arge
		);
		add_settings_field(
			'wc_sbp_payment_ap',
			__( 'AliPay', 'woo-sbp' ),
			array( $this, 'wc_sbp_payment_ap' ),
			'wc_sbp',
			'wc_sbp_payment_method',
			['label_for' => 'wc_sbp_payment_ap', 'slug' =>'wc_sbp_ap', 'class' => 'wc_sbp_payment_ap']// $arge
		);

		if(isset($_POST['wc_sbp_options'])){
			foreach($_POST['wc_sbp_options'] as $key => $value){
				$post_data[$key] = sanitize_text_field($value);
			}
		}
		if( isset( $_POST['_wpnonce']) and isset($_GET['page']) and $_GET['page'] == 'jp4wc-sbp-output' ){
			update_option('wc_sbp_options', $post_data);
			$sbp_methods = array('cc','cs','mb','ap');//cc: Credit Card, cs: Convenience store, mb: Carrier Payment, ap: AliPay
			foreach($sbp_methods as $sbp_method){
				$wc_sbp_options = get_option('wc_sbp_options');

				$option_method = $this->prefix.$sbp_method;
				$setting_method = 'woocommerce_sbp_'.$sbp_method.'_settings';
				$woocommerce_sbp_setting = get_option($setting_method);
				if(isset($wc_sbp_options[$option_method]) && $wc_sbp_options[$option_method]){
					if(isset($woocommerce_sbp_setting)){
						$woocommerce_sbp_setting['enabled'] = 'yes';
						update_option( $setting_method, $woocommerce_sbp_setting);
					}
				}else{
					if(isset($woocommerce_sbp_setting)){
						$woocommerce_sbp_setting['enabled'] = 'no';
						update_option( $setting_method, $woocommerce_sbp_setting);
					}
				}
			}
		}
	}
	/**
	 * IP Address field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_basic_ip_address(){
        echo '<b>182.160.10.15</b><br />';
        echo __( 'Since it is different depending on the rental server, please contact the server company if the test is not completed.', 'woo-sbp' );
	}
	/**
	 * IP Address field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_basic_result_url(){
        $version = WC_SBP_PLUGIN_URL.'check/result-notification.php';
        echo '<b>'.$version.'</b><br />';
        echo __( '※In the case of PHP 7.1.0 or later.', 'woo-sbp' );
	}

	/**
	 * Merchant ID input field.
	 *
     * @param array $input
	 * @return mixed
	 */
	public function wc_sbp_merchant_id( $args ){
		$title = __( 'Merchant ID', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * Service ID input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_service_id($args){
		$title = __( 'Service ID', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * Link Hash Code input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_link_hashkey($args){
		$title = __( 'Hash Code', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * Hash Code input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_hashkey($args){
		$title = __( 'Hash Code', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * 3DES Encryption key input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_3des_encry($args){
		$title = __( '3DES Encryption key', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * 3DES Initialization key key input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_3des_init($args){
		$title = __( '3DES Initialization key', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * Basic authentication ID input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_basic_id($args){
		$title = __( 'Basic authentication ID', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}
	/**
	 * Basic authentication Password input field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_basic_pass($args){
		$title = __( 'Basic authentication Password', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_input_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_text($args, $description, 'wc_sbp_options');
	}

	/**
	 * Credit Card payment field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_payment_cc($args){
		$title = __( 'Credit Card', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_checkbox($args, $description, 'wc_sbp_options');
	}
	/**
	 * Convenience store payment field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_payment_cs($args){
		$title = __( 'Convenience store', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_checkbox($args, $description, 'wc_sbp_options');
	}
	/**
	 * Carrier Payment payment field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_payment_mb($args){
		$title = __( 'Carrier Payment', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_checkbox($args, $description, 'wc_sbp_options');
	}
	/**
	 * AliPay payment field.
	 * 
	 * @return mixed
	 */
	public function wc_sbp_payment_ap($args){
		$title = __( 'AliPay', 'woo-sbp' );
        $description = $this->jp4wc_plugin->jp4wc_description_check_pattern( $title );
		$this->jp4wc_plugin->jp4wc_setting_input_checkbox($args, $description, 'wc_sbp_options');
	}
	
	/**
	 * Validate options.
	 * 
	 * @param array $input
	 * @return array
	 */
	public function validate_options( $input ) {
	}
	/**
	 * This function is similar to the function in the Settings API, only the output HTML is changed.
	 * Print out the settings fields for a particular settings section
	 *
	 * @global $wp_settings_fields Storage array of settings fields and their pages/sections
	 *
	 * @since 0.1
	 *
	 * @param string $page Slug title of the admin page who's settings fields you want to show.
	 */
	function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;
	 
		if ( ! isset( $wp_settings_sections[$page] ) )
			return;
	 
		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo '<div id="" class="stuffbox postbox '.$section['id'].'">';
			echo '<button type="button" class="handlediv button-link" aria-expanded="true"><span class="screen-reader-text">' . __('Toggle panel', 'woocommerce-for-japan') . '</span><span class="toggle-indicator" aria-hidden="true"></span></button>';
			if ( $section['title'] )
				echo "<h3 class=\"hndle\"><span>{$section['title']}</span></h3>\n";
	 
			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );

			if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) )
				continue;
			echo '<div class="inside"><table class="form-table">';
			do_settings_fields( $page, $section['id'] );
			echo '</table></div>';
			echo '</div>';
		}
	}
	/**
	 * Enqueue admin scripts and styles.
	 *
     	* @param string $page Slug title of the admin page who's settings fields you want to show.
     	* @global $pagenow
	 */
	public function admin_enqueue_scripts( $page ) {
		global $pagenow;
		wp_register_style( 'custom_wc_sbp_admin_css', plugins_url( 'views/css/admin-wc-sbp.css', __FILE__ ), false, WC_SBP_VERSION );
		wp_enqueue_style( 'custom_wc_sbp_admin_css' );
	}
}

new WC_Admin_Screen_SBPS();
