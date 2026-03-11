<?php
/**
 * Plugin Name: SB Payment Service for WooCommerce
 * Author URI: https://www.m6shop.com/dzkf/zfcj
 *
 * @class 		WooSBP
 * @extends		WC_Gateway_SBP_MB
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author		xwzy1130
 */

use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_3 as Framework;
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Gateway_SBP_MB extends WC_Payment_Gateway {

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

		$this->id                = 'sbp_mb';
		$this->has_fields        = false;
		$this->order_button_text = __( 'Proceed to SBPS Carrier Payment.', 'woo-sbp' );

        // Create plugin fields and settings
		$this->init_form_fields();
		$this->init_settings();
		$this->method_title       = __( 'SBPS Carrier Payment Gateway', 'woo-sbp' );
		$this->method_description = __( 'Allows payments by SBPS Carrier in Japan.', 'woo-sbp' );

        // When no save setting error at chackout page
		if(is_null($this->title)){
			$this->title = __( 'Please set this payment at Control Panel! ', 'woo-sbp' ).$this->method_title;
		}
		// Get setting values
		foreach ( $this->settings as $key => $val ) $this->$key = $val;

        // Set Carrier Company
		$this->cc_type = array();
		if(isset($this->setting_cc_dc)) $this->cc_type = array_merge($this->cc_type, array('docomo' => __( 'docomo', 'woo-sbp' )));
		if(isset($this->setting_cc_sb)) $this->cc_type = array_merge($this->cc_type, array('softbank2' => __( 'Softbank B', 'woo-sbp' )));
		if(isset($this->setting_cc_au)) $this->cc_type = array_merge($this->cc_type, array('auone' => __( 'Au', 'woo-sbp' )));

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		// Completed Payment
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'order_completed_' . $this->id ), 20, 0 );
	    // integrate SBPS when completed
		add_action( 'woocommerce_order_status_completed', array( $this, 'order_status_completed_sbp_mb' ));

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
				'label'       => __( 'Enable SBPS Carrier Payment', 'woo-sbp' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'       => array(
				'title'       => __( 'Title', 'woo-sbp' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woo-sbp' ),
				'default'     => __( 'Carrier Payment (SBPS)', 'woo-sbp' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woo-sbp' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sbp' ),
				'default'     => __( 'Pay with your Carrier Payment via SBPS.', 'woo-sbp' )
			),
			'order_button_text' => array(
				'title'       => __( 'Order Button Text', 'woo-sbp' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sbp' ),
				'default'     => __( 'Proceed to SBPS Carrier Payment', 'woo-sbp' )
			),
			'sandbox_mode' => array(
				'title'       => __( 'Sandbox Mode', 'woo-sbp' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Sandbox Mode', 'woo-sbp' ),
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => sprintf( __( 'If you use %s, please check it.', 'woo-sbp' ),__( 'Sandbox Mode', 'woo-sbp' )),
			),
			'setting_cc_dc' => array(
				'title'       => __( 'Set Carrier Company', 'woo-sbp' ),
				'id'              => 'wc-sbp-cc',
				'type'        => 'checkbox',
				'label'       => __( 'docomo', 'woo-sbp' ),
				'default'     => 'yes',
			),
			'setting_cc_sb' => array(
				'id'              => 'wc-sbp-cs-lm',
				'type'        => 'checkbox',
				'label'       => __( 'Softbank B', 'woo-sbp' ),
				'default'     => 'yes',
			),
			'setting_cc_au' => array(
				'id'              => 'wc-sbp-cs-f',
				'type'        => 'checkbox',
				'label'       => __( 'Au', 'woo-sbp' ),
				'default'     => 'yes',
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
	 * UI - Payment page fields for SB Payment Service.
	 */
	function payment_fields() {
		// Description of payment method from settings
		if ( $this->description ) { ?>
			<p><?php echo $this->description; ?></p>
		<?php } ?>
		<fieldset  style="padding-left: 40px;">
		<p><?php _e( 'Please select Carrier Company which you want to pay', 'woo-sbp' );?></p>
		<?php $this->cc_select(); ?>
		</fieldset>
		<?php
    }

	function cc_select() {
		?><select name="cc_type">
		<?php foreach($this->cc_type as $num => $value){?>
			<option value="<?php echo $num; ?>"><?php echo $value;?></option>
		<?php }?>
		</select><?php 
	}

	/**
	 * Process the payment and return the result.
	 */
	function process_payment( $order_id ) {
		include_once( 'includes/class-wc-gateway-sbp-request.php' );

		$order = wc_get_order( $order_id );
		$user = wp_get_current_user();
		if(0 != $user->ID){
			$customer_id   = $user->ID;
		}else{
			$customer_id   = $order_id.'-user';
		}
		$send_data = array();

		//Check test mode
		$sandbox_mode = $this->sandbox_mode;

		$amount = $order->get_total();

		$post_data = array();
		$post_data = array(
			'pay_method' => $this->get_post('cc_type'),
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
		$url_pagecon = WC_SBP_PLUGIN_URL;
		$post_data['pagecon_url'] = $url_pagecon.'check/result-notification.php';

		$sbp_request = new WC_Gateway_SBP_Request();

		//
/*		$data['pay_method_info']['issue_type'] = 0;
		$data['pay_method_info']['last_name'] = $order->get_billing_last_name();
		$data['pay_method_info']['first_name'] = $order->get_billing_first_name();
		$data['pay_method_info']['first_zip'] = substr($order->get_billing_postcode(),0,3);
		$data['pay_method_info']['second_zip'] = substr($order->get_billing_postcode(),4,4);
		$data['pay_method_info']['add1'] = $states['JP'][$order->get_billing_state()];
		$data['pay_method_info']['add2'] = $order->get_billing_city().$order->get_billing_address_1();
		$data['pay_method_info']['add3'] = $order->get_billing_address_2();
		$data['pay_method_info']['tel'] = $order->get_billing_phone();
		$data['pay_method_info']['mail'] = $order->get_billing_email();
		$data['pay_method_info']['seiakudate'] = date('Ymd');
		$data['pay_method_info']['webcvstype'] = $this->get_post('Convenience');
		$data['pay_method_info']['bill_date'] = date('Ymd', strtotime('+'.$this->payment_limit_days.' day'));*/
//$order->add_order_note($post_data['pagecon_url']);

		//Make send link
		$send_link = $sbp_request->make_send_link($post_data, $sandbox_mode);
		//Save Carrier Company type
		update_post_meta( $order_id, '_sbp_pay_method', strval($this->get_post('cc_type')) );
		$cc_array = $this->cc_type;
		$order->add_order_note( sprintf( __('The mobile carrier you specify a payment is %s.', 'woo-sbp' ), $cc_array[strval($this->get_post('cc_type'))]));
		// Return thank you redirect
		return array (
			'result'   => 'success',
			'redirect' => $send_link,
		);
	}

	/**
	 * Complated Payment 
	 */
	function order_completed_sbp_mb(){
		if( isset($_GET['sbp_complete']) and isset($_POST['res_tracking_id'])){
			$order_id = preg_replace('/[^0-9]/', '', $_GET['sbp_complete']);
			$order = wc_get_order( sanitize_text_field($order_id));

			// Remove cart
			WC()->cart->empty_cart();
			$order->payment_complete( $_POST['res_tracking_id'] );
		}
	}
	/**
	 * Complated Order
	 */
	function order_status_completed_sbp_mb( $order_id ){
		include_once( 'includes/class-wc-gateway-sbp-request.php' );
		$sbp_request = new WC_Gateway_SBP_Request();

		$order = wc_get_order( $order_id );
		$payment_method = $order->get_payment_method();
		if( isset($payment_method) and isset($transaction_id) and $payment_method == $this->id and $this->payment_action != 'sale' ){
			//Check test mode
			$sandbox_mode = $this->sandbox_mode;

			// Set SBPS transaction ID
			$data['tracking_id'] = $order->get_transaction_id();
			// Set order total amount
			$data['pay_option_manage']['amount'] = $order->get_total();
			// Set API id
			if(get_post_meta( $order_id, '_sbp_pay_method', true ) == 'docomo'){
				$api_request = 'ST02-00201-401';//Sales requirement docomo
			}elseif(get_post_meta( $order_id, '_sbp_pay_method', true ) == 'softbank2'){
				$api_request = 'ST02-00201-405';//Sales requirement Softbank
			}elseif(get_post_meta( $order_id, '_sbp_pay_method', true ) == 'auone'){
				$api_request = 'ST02-00201-402';//Sales requirement Au
			}

			//Send request to SBP API
			$xml_apply_array = $sbp_request->result_send_sbp_api($data, $api_request, $sandbox_mode, $this->debug);

			if($xml_apply_array->res_result == 'OK'){// Success Sales requirement request (When complete)
				$order->add_order_note(__( 'Success Sales requirement request.', 'woo-sbp' ));
			}elseif($xml_apply_array->res_result == 'NG'){// Failed Sales requirement request (When complete)
				$order->add_order_note(sprintf( __( 'Error Code : %s, please check it.', 'woo-sbp' ),$xml_apply_array->res_err_code).' '.__( 'Failed Sales requirement request', 'woo-sbp' ));
			}else{
				$order->add_order_note(__( 'Something Error happened at Sales requirement request.', 'woo-sbp' ));
			}
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
	 * @return  boolean | object True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		include_once( 'includes/class-wc-gateway-sbp-request.php' );
		$sbp_request = new WC_Gateway_SBP_Request();

		$order = wc_get_order( $order_id );

		//Check test mode
		$sandbox_mode = $this->sandbox_mode;
		// Set SBPS transaction ID
		$data['tracking_id'] = $order->get_transaction_id();
		// Set API id
		if(get_post_meta( $order_id, '_sbp_pay_method', true ) == 'docomo'){
			$api_request = 'ST02-00303-401';//Total Amount refund docomo
		}elseif(get_post_meta( $order_id, '_sbp_pay_method', true ) == 'softbank2'){
			$api_request = 'ST02-00303-405';//Total Amount refund Softbank
		}elseif(get_post_meta( $order_id, '_sbp_pay_method', true ) == 'auone'){
			$api_request = 'ST02-00303-402';//Total Amount refund Au
		}

		//Send request to SBP API
		$xml_apply_array = $sbp_request->result_send_sbp_api($data, $api_request, $sandbox_mode, $this->debug);

		if($xml_apply_array->res_result == 'OK'){// Success refund request
			$order->add_order_note(__( 'Success refund request.', 'woo-sbp' ));
			return true;
		}elseif($xml_apply_array->res_result == 'NG'){// Failed refund request
			return new WP_Error( 'sbp_refund_error', sprintf( __( 'Error Code : %s, please check it.', 'woo-sbp' ),$xml_apply_array->res_err_code).' '.__( 'Failed refund request', 'woo-sbp' ) );
		}else{
			return new WP_Error( 'sbp_refund_error', __( 'Something Error happened at refund request.', 'woo-sbp' ) );
		}
	}

	/**
	 * Get post data if set
	 */
	private function get_post( $name ) {
		if ( isset( $_POST[ $name ] ) ) {
			return sanitize_text_field( $_POST[ $name ] );
		}
		return null;
	}
}

/**
 * Add the gateway to woocommerce
 */
function add_wc_sbp_mb_gateway( $methods ) {
	$methods[] = 'WC_Gateway_SBP_MB';
	return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_wc_sbp_mb_gateway' );

/**
 * Edit the available gateway to woocommerce
 */
function edit_sbp_mb_available_gateways( $methods ) {
	if ( isset($currency) ) {
	}else{
		$currency = get_woocommerce_currency();
	}
	if($currency !='JPY'){
		unset($methods['sbp_mb']);
	}
	return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'edit_sbp_mb_available_gateways' );

/**
 * Error and cancellation from payment site also correspond to return.	
 */
function woocommerce_sbp_mb_before_cart(){
	if( isset($_GET['sbp_cancel_order']) ){
		$order = wc_get_order( sanitize_text_field( $_GET['sbp_cancel_order'] ) );
		$description = __( 'Canceled at payment site.', 'woo-sbp' );
	}elseif( isset($_GET['sbp_error_order']) ){
		$order = wc_get_order( sanitize_text_field( $_GET['sbp_error_order'] ) );
		$description = __( 'Error at payment site.', 'woo-sbp' );
	}
	if(isset($order)){
		$payment_method = $order->get_payment_method();
		if(isset($payment_method) and $payment_method === 'sbp_mb'){
			$order->set_status('cancelled', $description);
			$order->save();
		}
	}
}

add_action( 'woocommerce_before_cart', 'woocommerce_sbp_mb_before_cart' );

/**
 * Complete from payment site also correspond to return.
 */
function woocommerce_thankyou_sbp_mb_complete( $order_id ){
	if(isset($_GET['sbp_complete']) and $order_id == sanitize_text_field( $_GET['sbp_complete'] ) ){
		$order = wc_get_order($order_id);
		$order->payment_complete(strval($_POST['res_tracking_id']));
	}
}
add_action( 'woocommerce_thankyou_sbp_mb', 'woocommerce_thankyou_sbp_mb_complete' );
