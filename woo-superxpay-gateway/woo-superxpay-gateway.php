<?php
/*
 * Plugin Name: WooCommerce Superxpay Gateway (superxpay)
 * Plugin URI: https://www.superxpay.com/
 * Description: Take payments on your store.
 * Author: xwzy
 * Version: 1.0.2
 * Domain Path: /languages
/*
 * 这个动作挂钩将我们的PHP类注册为WooCommerce支付网关
 */

/* Add a custom payment class to WC
  ------------------------------------------------------------ */

add_action('plugins_loaded', 'woo_superxpay_gateway', 0);

function woo_superxpay_gateway()
{
	if (!class_exists('WC_Payment_Gateway'))
        	return; 	
    	if(class_exists('WC_SuperxPay_Gateway'))
        	return;

	class WC_SuperxPay_Gateway extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$plugin_dir = plugin_dir_url(__FILE__);

            		global $woocommerce;
            		$this->id = 'superxpay';
            		$this->icon = apply_filters('woocommerce_superxpay_icon', ''.$plugin_dir.'superxpay.png');
            		$this->has_fields = false;

            		// Load the form fields.
            		$this->init_form_fields();

            		// Load the settings.
            		$this->init_settings();

            		// Define user set variables
            		$this->title = $this->settings['title'];
            		$this->description = $this->settings['description'];

            		$this->superxpay_merchantno = $this->settings['merchantno'];
            		$this->superxpay_merchantkey = html_entity_decode($this->settings['merchantkey']);
            		$this->superxpay_channeltype = $this->settings['channeltype'];
            		$this->superxpay_processing = $this->settings['processing'];

            		// Actions
            		add_action('woocommerce_receipt_superxpay', array($this, 'receipt_page'));
            		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            		// Payment listener/API hook
            		add_action( 'woocommerce_api_wc_superxpay_notify', array( $this, 'check_ipn_response' ) );
            		add_action( 'woocommerce_api_wc_superxpay_return', array( $this, 'check_superpay_return' ) );

            		//load_plugin_textdomain('superxpay-for-woocommerce', false,basename( dirname( __FILE__ ) ) . '/languages');

            		if (!$this->is_valid_for_use())
            		{
            		    $this->enabled = false;
            		}
		}
		
		/**
         	* Check if this gateway is enabled and available in the user's country
         	*/
        	function is_valid_for_use()
        	{
	    		//error_log( get_woocommerce_currency());
            		if (!in_array( get_woocommerce_currency(), array('SGD', 'USD'))) return false;
            		return true;
        	}

		/**
         	* Admin Panel Options
         	* - Options for bits like 'title' and availability on a country-by-country basis
         	**/

        	public function admin_options() {
         	   global $wpdb;
         	   $query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}superxpay_data (id int(11) unsigned NOT NULL AUTO_INCREMENT, ref varchar(100) DEFAULT NULL, ordercode varchar(255) DEFAULT NULL, email varchar(150) DEFAULT NULL, orderid varchar(100) DEFAULT NULL, total_cost int(11) DEFAULT NULL, currency char(3) DEFAULT NULL, tm_password varchar(100) DEFAULT NULL, order_state char(1) DEFAULT NULL, sessionid varchar(32) DEFAULT NULL, timestamp datetime DEFAULT NULL, PRIMARY KEY (id))";
         	   $wpdb->query($query);

         	   ?>
         	   <h3><?php _e('SuperxPay', 'superxpay-for-woocommerce'); ?></h3>
         	   <p><?php _e('SuperxPay redirects customers to their secure server for making payments.', 'superxpay-for-woocommerce'); ?></p>
         	   <table class="form-table">
         	       <?php
         	       if ( $this->is_valid_for_use() ) :

         	           // Generate the HTML For the settings form.
         	           $this->generate_settings_html();

         	       else :
         	           ?>
         	           <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'superxpay-for-woocommerce'); ?></strong>: <?php _e('SuperxPay does not support your store currency.', 'superxpay-for-woocommerce' ); ?></p></div>
         	       <?php
         	       endif;
         	       ?>
         	   </table><!--/.form-table-->
         	   <?php
        	}// End admin_options()


		function init_form_fields()
        	{
        	    $this->form_fields = array
        	    (
        	        'enabled' => array
        	        (
        	            'title' => __('Enable/Disable', 'superxpay-for-woocommerce'),
        	            'type' => 'checkbox',
        	            'label' => __('Enable SuperxPay', 'superxpay-for-woocommerce'),
        	            'default' => 'yes'
        	        ),
        	        'title' => array
        	        (
        	            'title' => __('Title', 'superxpay-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __( 'This controls the title which the user sees during checkout.', 'superxpay-for-woocommerce' ),
        	            'default' => __('SuperxPay', 'superxpay-for-woocommerce')
        	        ),
        	        'description' => array(
        	            'title' => __( 'Description', 'woocommerce' ),
        	            'type' => 'textarea',
        	            'description' => __( 'This controls the description which the user sees during checkout.', 'superxpay-for-woocommerce' ),
        	            'default' => __( 'Pay via SuperxPay - you can pay with your credit card.', 'superxpay-for-woocommerce' )
        	        ),
        	        'merchantno' => array
        	        (
        	            'title' => __('Merchant ID', 'superxpay-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __('Merchant ID provided by SuperxPay.', 'superxpay-for-woocommerce'),
        	            'default' => ''
        	        ),
        	        'merchantkey' => array
        	        (
        	            'title' => __('API Key', 'superxpay-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __('API Key provided by SuperxPay.', 'superxpay-for-woocommerce'),
        	            'default' => ''
        	        ),
        	        'channeltype' => array(
        	            'title' => __('ChannelTpye', 'superxpay-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __('ChannelType by SuperxPay.', 'superxpay-for-woocommerce'),
        	            'default' => '80'
        	        ),
        	        'processing' => array(
        	            'title' => __('Order status', 'superxpay-for-woocommerce'),
        	            'default' => 'completed',
        	            'type' => 'select',
        	            'options' => array(
        	                'completed'       => __( 'Completed', 'superxpay-for-woocommerce' ),
        	                'processing'  	  => __( 'Processing', 'superxpay-for-woocommerce' ),
        	                'on-hold'  	  => __( 'On hold', 'superxpay-for-woocommerce' )
        	            )
        	        )
        	    );
		}

		/**
        	 * Generate the dibs button link
        	 **/
        	public function generate_form($order_id)
		{
			global $woocommerce, $wpdb;
            		$order = new WC_Order( $order_id );

			$curl_api   = "https://payment.superxpay.com/api/pay/SuperXPay";

			$mref = "REF".date("YmdHis", time()+8*60*60).substr(md5(uniqid(rand(), true)), 0, 9);

			$current_version = get_option( 'woocommerce_version', null );

                	define( 'WOOCOMMERCE_CHECKOUT', true );
                	WC()->cart->calculate_totals();
                	$amountcents = round($order->get_total() * 100);
                	$charge = number_format($order->get_total(), '2', '.', '');

            		if($amountcents==0){
                		$order_id = wc_get_order_id_by_order_key($_GET['key']);
                		$order    = wc_get_order( $order_id );
                		$amountcents = round($order->get_total() * 100);
                		$charge = number_format($order->get_total(), '2', '.', '');
            		}

			$MerchantNo  =  $this->superxpay_merchantno;
            		$private_key =   html_entity_decode($this->superxpay_merchantkey);
			$ChannelType  =  $this->superxpay_channeltype;
			$PayWay  =  $this->superxpay_payway;

                	$customer_mail = $order->get_billing_email();
			
			$currency_symbol ='';
            		$currency_code = get_woocommerce_currency();
            		switch ($currency_code) {
            		    case 'USD':
            		        $currency_symbol = 2; 
            		        break;
            		    default:
            		        $currency_symbol = 2;
            		}

			//基本url
                        $base_url = esc_url( home_url( '/' ));

			//同步回调url
			$return_url = WC()->api_request_url( 'wc_superxpay_return' ) ;
			$check = strpos($return_url, '?');
			if ( $check !== false) {
				$return_url = $return_url . "&mref=" . $mref;
			} else {
				$return_url = $return_url . "?mref=" . $mref;
			}
			
			//构造提交参数
                        $Body = array(
                            "MerchantNo"         => $MerchantNo,
                            "OutTradeNo"         => $mref,
                            "ChannelType"        => $ChannelType,
                            "CurrencyType"       => $currency_symbol,          
                            "Amount"             => $amountcents,      // 分
                            "Body"               => $customer_mail,
                            "NotifyUrl"          => WC()->api_request_url( 'wc_superxpay_notify' ),   //异步,注意去掉  woocommerce_api_
                            "ReturnUrl"          => $return_url,   //同步回调地址
                            "Attach"             => "",
                            "Remark"             => "",
                            "TransData"          => "",
                        );

                        $checksum = md5($Body['Amount'] . '&' . $Body['Body'] . '&' . $Body['ChannelType'] . '&' . $Body['CurrencyType'] . '&' . $Body['MerchantNo'] . '&' . $Body['NotifyUrl'] . '&' . $Body['OutTradeNo'] . '&' . $Body['TransData'] . '&' . $private_key);

                        $Body["Sign"] = $checksum;	

			//打印参数
			error_log(__METHOD__ . PHP_EOL .print_r($Body, true));			

			//构建提交变量
			$args = array(
                                'timeout'     => 45,
                                'redirection' => 5,
                                'httpversion' => '1.0',
                                'blocking'    => true,
                                'body'        => $Body,
                        );

			//php打印调用堆栈
			/*
			$tracelog = '';
			$array =debug_backtrace();
			unset($array[0]);
   			foreach($array as $row)
    			{
       				$tracelog .= $row['file'].':'.$row['line'].'行,调用方:'.$row['function']."\n";
    			}
			error_log($tracelog);
			*/
		
			$postRequest = wp_remote_post($curl_api, $args);
			
			if ($postRequest['response']['code'] === 200) {
                		$result = json_decode($postRequest['body'], true, 512, JSON_BIGINT_AS_STRING);
            		} else {
                		error_log(__METHOD__ . PHP_EOL . 'Code:' . $postRequest['response']['code'] . PHP_EOL. ' Error:' . $postRequest['response']['message']);

                		throw new Exception("Unable to reach Viva Payments (" . $postRequest['response']['message'] . ")");
            		}

			error_log(__METHOD__ . PHP_EOL .print_r($result, true));

            		if ($result['Code'] === 1000) {
            		    	$OrderNo = $result['Data']['OrderNo'];
			   	$PayUrl = $result['Data']['ResultCode'];
            		} else {
            		    	throw new Exception("Unable to create order code (" . $result['Msg'] . ")");
            		}

            		$query = "insert into {$wpdb->prefix}superxpay_data (ref, ordercode, email, orderid, total_cost, currency, order_state, timestamp) values ('".$mref."', '".$OrderNo."','". $customer_mail ."','". $order_id . "',$amountcents,'978','I', now())";
            		$wpdb->query($query);

			wc_enqueue_js('
				setInterval(function(){
             				$.blockUI({
             				message: "' . esc_js(__('Thank you for your order. We are now redirecting you to superxpay to make payment.', 'superxpay-for-woocommerce')) . '",
             				baseZ: 99999,
             				overlayCSS:
             				{
             				background: "#fff",
             				opacity: 0.6
             				},
             				css: {
             				padding:        "20px",
             				zindex:         "9999999",
             				textAlign:      "center",
             				color:          "#555",
             				border:         "3px solid #aaa",
             				backgroundColor:"#fff",
             				cursor:         "wait",
             				lineHeight:     "24px",
             				}
             				});
					var start = new Date().getTime();
  					while (new Date().getTime() < start + 1000);
					document.getElementById("submit_superxpay_payment_button").click();
				},1000);
             		');


        		return
        	        	'<div>'."\n".
        	        	'<a class="button alt" id="submit_superxpay_payment_button" href="'.$PayUrl.'">'.__('Pay Now', 'superxpay-for-woocommerce').'</a>'."\n".
				'<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel', 'superxpay-for-woocommerce').'</a>'."\n".
        	        	'</div>'
				;
		}

		/**
        	* Process the payment and return the result
        	**/
        	function process_payment($order_id)
        	{
        	    	$order = new WC_Order($order_id);
        	    	//$current_version = get_option( 'woocommerce_version', null );
		    	//error_log($current_version);

        	        return array
        	        (
        	            'result' => 'success',
        	            'redirect'	=> esc_url_raw(add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), wc_get_page_permalink( 'checkout' ))))
        	        );
        	}

		/**
        	 * receipt_page
        	 **/
        	function receipt_page($order)
        	{
        	    	echo '<p>'.__('Thank you for your order, please click the button below to pay.', 'superxpay-for-woocommerce').'</p>';
		    	//error_log($_SERVER['HTTP_REFERER']);

			//解决函数被调用2次的问题
		    	if ( strpos($_SERVER['HTTP_REFERER'], 'order-pay') === false ) {
        	   		echo $this->generate_form($order);
		    	}

        	}

		/**
        	 * 异步回调接口
        	 **/
        	function check_ipn_response()
		{
			global $woocommerce, $wpdb;

			if (($_SERVER['REQUEST_METHOD'] === 'POST') && preg_match("/wc_superxpay_notify/i", $_SERVER['REQUEST_URI'])) {
				$response = file_get_contents("php://input");
				error_log(__METHOD__ . PHP_EOL .print_r($response, true));

				if ( $response ) {
					$res_data = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
					$private_key =   html_entity_decode($this->superxpay_merchantkey);
					$statustr = $this->superxpay_processing;
					
                        		$checksum = md5($res_data['OrderNo'] . '&' . $res_data['MerchantNo'] . '&' . $res_data['Amount'] . '&' . $res_data['OutTradeNo'] . '&' . $res_data['Status'] . '&' . $private_key);

					//error_log($checksum);
					//error_log($res_data['Sign']);

					if ($checksum == $res_data['Sign']) {
						$tm_ref = $res_data['OutTradeNo'];
						$check_query = $wpdb->get_results("SELECT orderid,order_state FROM {$wpdb->prefix}superxpay_data WHERE ref = '".addslashes($tm_ref)."'", ARRAY_A);
                           			$check_query_count = count($check_query);

						//error_log("SELECT orderid,order_state FROM {$wpdb->prefix}superxpay_data WHERE ref = '".addslashes($tm_ref)."'");
						//error_log($check_query_count);
						if( $check_query_count >= 1 ) {
							if($check_query[0]['order_state'] == 'I' && $res_data['Status'] == '1') {
								$query = "update {$wpdb->prefix}superxpay_data set order_state='C' where ref='".addslashes($tm_ref)."'";
                            					$wpdb->query($query);

								$inv_id = $check_query[0]['orderid'];
                            					$order = new WC_Order($inv_id);
								$order->update_status($statustr, __('Order has been paid by ID: ' . $res_data['OrderNo'], 'superxpay-for-woocommerce'));
								wc_reduce_stock_levels( $order->get_id() );
								add_post_meta( $inv_id, '_paid_date', current_time('mysql'), true );
								update_post_meta( $inv_id, '_transaction_id', wc_clean($tm_ref) );

								$order->payment_complete(wc_clean($tm_ref));
                            					$woocommerce->cart->empty_cart();
							}
						}
						
					}
				}
				//接口返回
				exit("SUCCESS");
			}

		}
		/**
		 * 同步回调接口
		 **/
		function check_superpay_return()
		{
			global $woocommerce, $wpdb;

			//error_log($_SERVER['REQUEST_URI']);

			if (($_SERVER['REQUEST_METHOD'] === 'GET') && preg_match("/wc_superxpay_return/i", $_SERVER['REQUEST_URI'])) {
				error_log(__METHOD__ . PHP_EOL .print_r($_GET, true));
				$tm_ref = $_GET['mref'];
				error_log($tm_ref);
				$check_query = $wpdb->get_results("SELECT orderid,order_state FROM {$wpdb->prefix}superxpay_data WHERE ref = '".addslashes($tm_ref)."'", ARRAY_A);
				$check_query_count = count($check_query);
				if($check_query_count >= 1){
					$inv_id = $check_query[0]['orderid'];
					$inv_state = $check_query[0]['order_state'];

					switch ( $inv_state ) {
                            			case 'C':
							$order = new WC_Order($inv_id);
							 wp_redirect(esc_url_raw(add_query_arg('key', $order->get_order_key(), add_query_arg('order-received', $inv_id, $this->get_return_url($order)))));
                                			break;
                            			default:
							wp_redirect( wc_get_cart_url() );
                        		}
					exit;
				} 
			}
			wp_redirect(home_url());
		}
	}


	/**
     	* Add the gateway to WooCommerce
     	**/
    	function add_superxpay_gateway($methods)
    	{
        	$methods[] = 'WC_SuperxPay_Gateway';
        	return $methods;
    	}
    	add_filter('woocommerce_payment_gateways', 'add_superxpay_gateway');
}

?>
