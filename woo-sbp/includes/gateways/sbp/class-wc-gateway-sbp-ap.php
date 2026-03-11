<?php
/**
 * Plugin Name: SB Payment Service for WooCommerce
 * Author URI: https://www.m6shop.com/dzkf/zfcj
 *
 * @class 		WooSBP
 * @extends		WC_Gateway_SBP_AP
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author		xwzy1130
 */

use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_3 as Framework;
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Gateway_SBP_AP extends WC_Payment_Gateway {

    /**
     * Framework.
     *
     * @var object
     */
    public $jp4wc_framework;

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		$this->id                = 'sbp_ap';
		$this->has_fields        = false;
		$this->order_button_text = __( 'Proceed to Alipay Payment.', 'woo-sbp' );

        // Create plugin fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->method_title       = __( 'SBPS Alipay Payment Gateway', 'woo-sbp' );
		$this->method_description = __( 'Allows payments by SBPS Alipay Payment in Japan.', 'woo-sbp' );

        // When no save setting error at chackout page
		if(is_null($this->title)){
			$this->title = __( 'Please set this payment at Control Panel! ', 'woo-sbp' ).$this->method_title;
		}
		// Get setting values
		foreach ( $this->settings as $key => $val ) $this->$key = $val;

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Completed Payment
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'order_completed_' . $this->id ), 20, 0 );
	    // Error and Cancel Cart
	    add_action( 'woocommerce_before_cart_table', array( $this, 'cart_page_notice'));

        // Set framework
        $this->jp4wc_framework = new Framework\JP4WC_Plugin();
	}
	/**
	* Initialize Gateway Settings Form Fields.
	*/
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woo-sbp' ),
				'label'       => __( 'Enable SBPS Alipay Payment', 'woo-sbp' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woo-sbp' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woo-sbp' ),
				'default'     => __( 'Alipay Payment (SBPS)', 'woo-sbp' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woo-sbp' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sbp' ),
				'default'     => __( 'Pay with your Alipay Payment via SBPS.', 'woo-sbp' )
			),
			'order_button_text' => array(
				'title'       => __( 'Order Button Text', 'woo-sbp' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sbp' ),
				'default'     => __( 'Proceed to SBPS Alipay Payment', 'woo-sbp' )
			),
			'sandbox_mode' => array(
				'title'       => __( 'Sandbox Mode', 'woo-sbp' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Sandbox Mode', 'woo-sbp' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => sprintf( __( 'If you use %s, please check it.', 'woo-sbp' ),__( 'Sandbox Mode', 'woo-sbp' )),
			),
            'debug' => array(
                'title'   => __( 'Debug Mode', 'woo-sbp' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Debug Mode', 'woo-sbp' ),
                'default' => 'no',
                'description' => __( 'Save debug data using WooCommerce logging.', 'woo-sbp' ),
            ),
		);
	}

	/**
	 * UI - Payment page fields for SBPS.
	 */
	function payment_fields() {
		// Description of payment method from settings
		if ( $this->description ) { ?>
			<p><?php echo $this->description; ?></p>
		<?php } ?>
		<?php
    }

	/**
	 * Process the payment and return the result.
     *
     * @param  int $order_id
     * @return  array
	 */
	function process_payment( $order_id ) {
		include_once( 'includes/class-wc-gateway-sbp-request.php' );

		$order = new WC_Order( $order_id );
		$user = wp_get_current_user();
		if(0 != $user->ID){
			$customer_id   = $user->ID;
		}else{
			$customer_id   = $order_id.'-user';
		}

		//Check test mode
		$sandbox_mode = $this->sandbox_mode;

		$amount = $order->get_total();

		$post_data = array(
			'pay_method' => 'alipay',
			'cust_code' => $customer_id,
			'order_id' => 'wc-'.$order_id,
			'item_id' => 'woo-item',
			'amount' => $amount,
			'pay_type' => 0,
			'service_type' => 0,//Sale
			'terminal_type' => 0,
		);
		$post_data['success_url'] = $this->get_return_url( $order ).'&sbp_complete='.$order_id;
		$post_data['cancel_url'] = wc_get_cart_url().'?sbp_cancel_order='.$order_id;
		$post_data['error_url'] = wc_get_cart_url().'?sbp_error_order='.$order_id;
		$post_data['pagecon_url'] = WC_SBP_PLUGIN_URL.'check/result-notification.php';

		$sbp_request = new WC_Gateway_SBP_Request();

		//Order billing data
/*		$order_data['LAST_NAME'] = $order->get_billing_last_name();
		$order_data['FIRST_NAME'] = $order->get_billing_first_name();
		$order_data['FIRST_ZIP'] = substr($order->get_billing_postcode(),0,3);
		$order_data['SECOND_ZIP'] = substr($order->get_billing_postcode(),4,4);
		$order_data['ADD1'] = $states['JP'][$order->get_billing_state()];
		$order_data['ADD2'] = $order->get_billing_city().$order->get_billing_address_1();
		$order_data['ADD3'] = $order->get_billing_address_2();
		$order_data['TEL'] = $order->get_billing_phone();
		$order_data['MAIL'] = $order->get_billing_email();
		$order_data['ITEM_NAME'] = 'woo-item';*/

//$order->add_order_note($sbp_request->make_hash($make_hash_data, $sandbox_mode));

		//Make send link
		$send_link = $sbp_request->make_send_link($post_data, $sandbox_mode);

		// Return thank you redirect
		return array (
			'result'   => 'success',
			'redirect' => $send_link,
		);
	}

	/**
	 * Completed Payment
	 */
	function order_completed_sbp_ap(){
		if( isset($_GET['sbp_complete']) and isset($_POST['res_tracking_id'])){
			$order_id = preg_replace('/[^0-9]/', '', $_GET['sbp_complete']);
			$order = wc_get_order( sanitize_text_field($order_id));

			// Remove cart
			WC()->cart->empty_cart();
			$order->payment_complete( $_POST['res_tracking_id'] );
		}
	}

    /**
     * When return error and cancel processing
     */
    public function cart_page_notice(){
		if(isset($_GET['sbp_cancel_order'])){
			$order = wc_get_order( sanitize_text_field($_GET['sbp_cancel_order']) );
			$order->update_status( 'cancelled', __( 'This order cancelled via Alipay.', 'woo-sbp' ));
			echo __( 'This order cancelled via Alipay.', 'woo-sbp' );
		}elseif(isset($_GET['sbp_error_order'])){
			$order = wc_get_order( sanitize_text_field($_GET['sbp_error_order']) );
			$order->update_status( 'cancelled', __( 'This order has an error via Alipay.', 'woo-sbp' ));			
			echo __( 'This order has an error via Alipay.', 'woo-sbp' );
		}
	}
    /**
     * Check payment details for valid format
     */
	function validate_fields() {
	}

	/**
	 * Process a refund if supported
	 * @param  int $order_id
	 * @param  float $amount
	 * @param  string $reason
	 * @return  boolean True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
	}

	/**
	 * Get post data if set
	 */
	private function get_post( $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			return sanitize_text_field($_POST[ $name ]);
		}
		return null;
	}
}

/**
 * Add the gateway to woocommerce
 */
function add_wc_sbp_ap_gateway( $methods ) {
	$methods[] = 'WC_Gateway_SBP_AP';
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_wc_sbp_ap_gateway' );

/**
 * Edit the available gateway to woocommerce
 */
function edit_sbp_ap_available_gateways( $methods ) {
	if ( isset($currency) ) {
	}else{
		$currency = get_woocommerce_currency();
	}
	if($currency !='JPY'){
		unset($methods['sbp_ap']);
	}
	return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'edit_sbp_ap_available_gateways' );

/**
 * Error and cancellation from payment site also correspond to return.	
 */
function woocommerce_sbp_ap_before_cart(){
	if( isset($_GET['sbp_cancel_order']) ){
		$order = wc_get_order( sanitize_text_field( $_GET['sbp_cancel_order'] ) );
		$description = __( 'Canceled at payment site.', 'woo-sbp' );
	}elseif( isset($_GET['sbp_error_order']) ){
		$order = wc_get_order( sanitize_text_field( $_GET['sbp_error_order'] ) );
		$description = __( 'Error at payment site.', 'woo-sbp' );;
	}
	if(isset($order)){
		$payment_method = $order->get_payment_method();
		if(isset($payment_method) and $payment_method === 'sbp_ap'){
			$order->set_status('failed', $description);
			$order->save();
		}
	}
}

add_action( 'woocommerce_before_cart', 'woocommerce_sbp_ap_before_cart' );
