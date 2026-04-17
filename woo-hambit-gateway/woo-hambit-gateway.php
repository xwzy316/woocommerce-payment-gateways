<?php
/*
 * Plugin Name: WooCommerce HamBit Gateway
 * Plugin URI: https://www.m6shop.com/dzkf/zfcj
 * Description: Take payments on your store.
 * Author: xyls1130
 * Author URI: https://www.itbunan.xyz/service.html
 * Version: 1.0.6
 * Domain Path: /languages
/*
 * 这个动作挂钩将我们的PHP类注册为WooCommerce支付网关
 */

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* Add a custom payment class to WC
  ------------------------------------------------------------ */

add_action('plugins_loaded', 'woo_hambit_gateway', 0);

function woo_hambit_gateway()
{
	if (!class_exists('WC_Payment_Gateway'))
        	return; 	
    	if(class_exists('WC_HamBit_Gateway'))
        	return;

        //前端特定币种展示通道	
	add_filter( 'woocommerce_available_payment_gateways', 'filter_hambit_payment_gateways_by_currency' );
    	function filter_hambit_payment_gateways_by_currency( $gateways ) {
                // 获取当前前端货币
                $current_currency = get_woocommerce_currency();

                // 如果当前货币不是INR
                    if ( $current_currency !== 'BRL' ) {
                    // 移除某支付方式
                    unset( $gateways['hambit'] );
                }

                return $gateways;
     	}

	class WC_HamBit_Gateway extends WC_Payment_Gateway {
		/**
     		* Gateway instructions that will be added to the thank you page and emails.
     		*
     		* @var string
     		*/
     		public $instructions;

     		/**
     		 * Enable for virtual products.
     		 *
     		 * @var bool
     		 */
     		public $enable_for_virtual;

     		/**
     		 * Constructor for the gateway.
     		 */

		public function __construct()
		{
			$plugin_dir = plugin_dir_url(__FILE__);
            		global $woocommerce;

            		$this->id = 'hambit';
            		$this->icon = apply_filters('woocommerce_hambit_icon', ''.$plugin_dir.'logo.png');
			$this->method_title       = __( 'Pay via HamBit', 'hambit-for-woocommerce' );
        		$this->method_description = __( 'You can pay by HamBit.', 'hambit-for-woocommerce' );
            		$this->has_fields = false;

            		// Load the form fields.
            		$this->init_form_fields();

            		// Load the settings.
            		$this->init_settings();

            		// Define user set variables
            		$this->title = $this->settings['title'];
            		$this->description = $this->settings['description'];
			$this->instructions       = $this->get_option( 'instructions' );
        		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';


			// 商户信息
            		$this->hambit_access_key = $this->settings['accesskey'];

			// 密钥
			$this->hambit_secret_key = html_entity_decode($this->settings['secretkey']);

			//支付完成后订单状态
            		$this->hambit_processing = $this->settings['processing'];

			// API接口
			$this->req_api = "https://apis.hambit.co";

            		// Actions
            		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            		add_action( 'woocommerce_receipt_hambit', array($this, 'receipt_page'));

            		// Payment listener/API hook
            		add_action( 'woocommerce_api_wc_hambit_notify', array( $this, 'check_hambit_ipn_response' ) );
            		add_action( 'woocommerce_api_wc_hambit_return', array( $this, 'check_hambit_return' ) );

            		//load_plugin_textdomain('hambit-for-woocommerce', false,basename( dirname( __FILE__ ) ) . '/languages');
			

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
            		if (!in_array( get_woocommerce_currency(), array('BRL'))) return false;
            		return true;
        	}

		/**
     		* Checks to see whether or not the admin settings are being accessed by the current request.
     		*
     		* @return bool
     		*/
    		private function is_accessing_settings() {
    		    if ( is_admin() ) {
    		        // phpcs:disable WordPress.Security.NonceVerification
    		        if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
    		            return false;
    		        }
    		        if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
    		            return false;
    		        }
    		        if ( ! isset( $_REQUEST['section'] ) || 'cod' !== $_REQUEST['section'] ) {
    		            return false;
    		        }
    		        // phpcs:enable WordPress.Security.NonceVerification

    		        return true;
    		    }

    		    if ( Constants::is_true( 'REST_REQUEST' ) ) {
    		        global $wp;
    		        if ( isset( $wp->query_vars['rest_route'] ) && false !== strpos( $wp->query_vars['rest_route'], '/payment_gateways' ) ) {
    		            return true;
    		        }
    		    }

    		    return false;
    		}

		/**
     		* Loads all of the shipping method options for the enable_for_methods field.
     		*
     		* @return array
     		*/
		private function load_shipping_method_options() {
        		// Since this is expensive, we only want to do it if we're actually on the settings page.
        		if ( ! $this->is_accessing_settings() ) {
        		    return array();
        		}

        		$data_store = WC_Data_Store::load( 'shipping-zone' );
        		$raw_zones  = $data_store->get_zones();

        		foreach ( $raw_zones as $raw_zone ) {
        		    $zones[] = new WC_Shipping_Zone( $raw_zone );
        		}

        		$zones[] = new WC_Shipping_Zone( 0 );

        		$options = array();
        		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

        		    $options[ $method->get_method_title() ] = array();

        		    // Translators: %1$s shipping method name.
        		    $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );

        		    foreach ( $zones as $zone ) {

        		        $shipping_method_instances = $zone->get_shipping_methods();

        		        foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

        		            if ( $shipping_method_instance->id !== $method->id ) {
        		                continue;
        		            }

        		            $option_id = $shipping_method_instance->get_rate_id();

        		            // Translators: %1$s shipping method title, %2$s shipping method id.
        		            $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

        		            // Translators: %1$s zone name, %2$s shipping method instance name.
        		            $option_title = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );

        		            $options[ $method->get_method_title() ][ $option_id ] = $option_title;
        		        }
        		    }
        		}

        		return $options;
    		}

		//签名
		public function get_sign($srcArray, $merKey){
            		if(null == $srcArray){
            		    return "123456";
            		}
            		//先干掉sign字段
            		$keys = array_keys($srcArray);
            		$index = array_search("sign", $keys);
            		if ($index !== FALSE) {
            		    array_splice($srcArray, $index, 1);
            		} 

            		//对数组排序
            		ksort($srcArray);

            		//生成待签名字符串
            		$srcData = "";
            		foreach ($srcArray as $key => $val) {
            		    if($val === null || $val === "" ){
            		        //值为空的跳过，不参与加密
            		        continue;
            		    }
            		    $srcData .= "$key=$val" . "&";
            		}
            		$srcData = substr($srcData, 0, strlen($srcData) - 1);

            		//生成签名字符串
            		//$sign = md5($srcData.$merKey);
			$hmac_sha1 = hash_hmac('sha1', $srcData, $merKey, true);

            		return base64_encode($hmac_sha1);
    		}

		//唯一标识
		public function get_uuid(){
			// 生成16个随机字节
    			$bytes = random_bytes(16);

    			// 设置版本和变体位
    			$bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // 设置版本为4
    			$bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // 设置变体为RFC 4122

    			// 将字节转换为字符串表示
    			$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

    			return $uuid;
                }

		/**
         	* Admin Panel Options
         	* - Options for bits like 'title' and availability on a country-by-country basis
         	**/

        	public function admin_options() {
         	   global $wpdb;
		   $query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hambit_data (
                        id int(11) unsigned NOT NULL AUTO_INCREMENT,
                        mref varchar(100) DEFAULT NULL,
                        sref varchar(255) DEFAULT NULL,
                        order_id varchar(100) DEFAULT NULL,
                        total_cost int(11) DEFAULT NULL,
                        currency char(3) DEFAULT NULL,
                        order_state char(1) DEFAULT NULL,
			timestamp datetime DEFAULT NULL,
                        PRIMARY KEY (id))";
           	   $wpdb->query($query);

         	   ?>
         	   <h3><?php _e('HamBit', 'hambit-for-woocommerce'); ?></h3>
         	   <p><?php _e('HamBit redirects customers to their secure server for making payments.', 'hambit-for-woocommerce'); ?></p>
         	   <table class="form-table">
         	       <?php
         	       if ( $this->is_valid_for_use() ) :

         	           // Generate the HTML For the settings form.
         	           $this->generate_settings_html();

         	       else :
         	           ?>
         	           <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'hambit-for-woocommerce'); ?></strong>: <?php _e('HamBit does not support your store currency.', 'hambit-for-woocommerce' ); ?></p></div>
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
        	            'title' => __('Enable/Disable', 'hambit-for-woocommerce'),
        	            'type' => 'checkbox',
        	            'label' => __('Enable HamBit', 'hambit-for-woocommerce'),
			    'default' => 'yes'
        	        ),
        	        'title' => array
        	        (
        	            'title' => __('Title', 'hambit-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __( 'This controls the title which the user sees during checkout.', 'hambit-for-woocommerce' ),
        	            'default' => __('HamBit', 'hambit-for-woocommerce'),
			    'custom_attributes' => array(
        			'readonly' => 'readonly'
    			    ),
			    'desc_tip' => __('This field is read-only and cannot be edited.', 'hambit-for-woocommerce')
        	        ),
        	        'description' => array(
        	            'title' => __( 'Description', 'woocommerce' ),
        	            'type' => 'textarea',
        	            'description' => __( 'This controls the description which the user sees during checkout.', 'hambit-for-woocommerce' ),
        	            'default' => __( 'Pay via HamBit - you can pay.', 'hambit-for-woocommerce' )
        	        ),
			'instructions'       => array(
            		    'title'       => __( 'Instructions', 'hambit-for-woocommerce' ),
            		    'type'        => 'textarea',
            		    'description' => __( 'Instructions that will be added to the thank you page.', 'hambit-for-woocommerce' ),
            		    'default'     => __( 'Pay with HamBit.', 'hambit-for-woocommerce' ),
            		    'desc_tip'    => true,
            		),
            		'enable_for_virtual' => array(
            		    'title'   => __( 'Accept for virtual orders', 'hambit-for-woocommerce' ),
            		    'label'   => __( 'Accept HamBit if the order is virtual', 'hambit-for-woocommerce' ),
            		    'type'    => 'checkbox',
            		    'default' => 'yes',
            		),

        	        'accesskey' => array
        	        (
        	            'title' => __('Access Key', 'hambit-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __('Access Key provided by HamBit.', 'hambit-for-woocommerce'),
        	            'default' => ''
        	        ),
			'secretkey' => array
                        (
                            'title' => __('Secret Key', 'hambit-for-woocommerce'),
                            'type' => 'text',
                            'description' => __('Secret Key provided by HamBit.', 'hambit-for-woocommerce'),
                            'default' => ''
                        ),
        	        'processing' => array(
        	            'title' => __('Order status', 'hambit-for-woocommerce'),
        	            'default' => 'completed',
        	            'type' => 'select',
        	            'options' => array(
        	                'completed'       => __( 'Completed', 'hambit-for-woocommerce' ),
        	                'processing'  	  => __( 'Processing', 'hambit-for-woocommerce' ),
        	                'on-hold'  	  => __( 'On hold', 'hambit-for-woocommerce' )
        	            )
        	        )
        	    );
		}

		/**
        	* Process the payment and return the result
        	**/
        	function process_payment($order_id)
        	{
			global $woocommerce, $wpdb;
            		$order = new WC_Order($order_id);

			//订单号
			$mref = "U".date("YmdHis", time()+8*60*60).str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

			//订单确认页
                	//define( 'WOOCOMMERCE_CHECKOUT', true );
                	WC()->cart->calculate_totals();
			
			//订单金额
                	$amount = (string)round($order->get_total());
			
			//商户号
			$AccessKey  =  $this->hambit_access_key;

			//异步回调url
                        $notify_url =  WC()->api_request_url( 'wc_hambit_notify' );
			//error_log(__METHOD__ . PHP_EOL .print_r($notify_url, true));
			
			//同步回调url
                        $return_url = WC()->api_request_url( 'wc_okpay_return' ) ;
                        $check = strpos($return_url, '?');
                        if ( $check !== false) {
                                $return_url = $return_url . "&mref=" . $mref;
                        } else {
                                $return_url = $return_url . "?mref=" . $mref;
                        }
			
			//币种
            		$currency_code = get_woocommerce_currency();

			//时间戳
			$timestamp = round(microtime(true) * 1000);

			//uuid
			$uuid_str = $this->get_uuid();

			/*
			 * 加密支付
			$Body = array(
    				"externalOrderId"		=> $mref,
    				"cashierChainType"		=> "ETH",
    				"cashierTokenType"		=> "USDC",
    				"cashierCryptoAmount"		=> $amount,
				"hiddenMerchantLogo" 		=> 1,
				"hiddenMerchantName"		=> 1,
    				"notifyUrl"			=> $notify_url,
				"remark"			=> "USER"
    			);
 			*/
			
			$Body = array(
				"amount"		=> $amount,
				"channelType"		=> "PIX",
				"externalOrderId"	=> $mref,
				"notifyUrl"		=> $notify_url,
				"remark"		=> "USER",
				"returnUrl" 		=> $return_url,
				"inputCpf"		=> 0
			);

			$sign_arr = $Body;
			$sign_arr['access_key'] = $AccessKey;
			$sign_arr['timestamp'] = $timestamp;
			$sign_arr['nonce'] = $uuid_str;

			$sign_str = $this->get_sign($sign_arr, $this->hambit_secret_key);	

			//请求头 
			$Headers = array(
				'Content-Type'		=> 'application/json;charset=utf-8',
				'access_key'		=> $AccessKey,
				'timestamp'		=> $timestamp,
				'nonce'			=> $uuid_str,
				'sign'			=> $sign_str
			);

			//打印日志
			//error_log(__METHOD__ . PHP_EOL .print_r($Body, true));

			//获取支付链接
			//$req_api = $this->req_api.'/api/v3/wallet/pay';
			$req_api = $this->req_api.'/api/v3/bra/createCollectingOrder';
			
			$postData = json_encode($Body);
			
			//打印参数
			error_log(__METHOD__ . PHP_EOL .print_r($Body, true));

			 $args = array(
                        	'headers'     => $Headers,
                        	'timeout'     => 45,
                        	'redirection' => 5,
                        	'httpversion' => '1.0',
                        	'blocking'    => true,
                        	'body'        => $postData,
                	);

			// 提交参数
			$postRequest = wp_remote_post($req_api, $args);

			if ($postRequest['response']['code'] === 200) {
				$result = json_decode($postRequest['body'], true);
                        } else {
                                error_log(__METHOD__ . PHP_EOL . 'Code:' . $postRequest['response']['code'] . PHP_EOL. ' Error:' . $postRequest['response']['message']);

                                //抛出异常
                                throw new Exception("Unable to reach hambit Payments (" . $postRequest['response']['message'] . ")");
                        }

			error_log(__METHOD__ . PHP_EOL .print_r($result, true));

			//结果处理
			if (($result['code'] =='200') && ($result['msgEn'] =='SUCCESS')) {
				//写入数据库
				$query = "insert into {$wpdb->prefix}hambit_data (mref, order_id, total_cost, currency, order_state, timestamp)  values ('".$mref."', '". $order_id . "',".$amount.",'". $currency_code."','I', now())";
            			$wpdb->query($query);

				//支付链接
			   	$PayUrl = $result['data']['cashierUrl'];

				//跳转payurl
                        	return array(
                        	    'result' => 'success',
                        	    'redirect' => $PayUrl
                        	);
            		} else {
				error_log(__METHOD__ .PHP_EOL. print_r($result, true));
				throw new Exception("Unable to redirect payurl (" . $result['msg'] . ")");
            		}
		}

		/**
        	 * 异步回调接口
        	 **/
        	function check_hambit_ipn_response()
		{
			global $woocommerce, $wpdb;

			if (($_SERVER['REQUEST_METHOD'] === 'POST') && preg_match("/wc_hambit_notify/i", $_SERVER['REQUEST_URI'])) {

				//接收post json的数据
				$response = file_get_contents("php://input");
                                error_log(__METHOD__ . PHP_EOL .print_r($response, true));

				//参数
                                $respj = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);

				//判断
				if ($respj['orderStatus'] == 'Completed') {
					#订单号
                                        $mref = $respj['externalOrderId'];
                                        $sref = $respj['orderId'];

                                        #写入数据库
                                        $query = "update {$wpdb->prefix}hambit_data  set order_state = 'C' where mref = '".addslashes($mref)."'";
                                        $wpdb->query($query);

                                        #后续处理
					$check_query = $wpdb->get_results("SELECT order_id FROM {$wpdb->prefix}hambit_data WHERE mref = '".addslashes($mref)."'", ARRAY_A);
                                	$check_query_count = count($check_query);
                                	if($check_query_count >= 1){
                                		$order_id= $check_query[0]['order_id'];

                                                $order = new WC_Order($order_id);
                                                $statustr = $this->hambit_processing;

                                                $order->update_status($statustr, __('Order has been paid by ID: ' . $sref, 'hambit-for-woocommerce'));
                                                wc_reduce_stock_levels( $order->get_id() );

                                                add_post_meta( $order_id, '_paid_date', current_time('mysql'), true );
                                                update_post_meta( $order_id, '_transaction_id', wc_clean($order_id) );

                                                $order->payment_complete(wc_clean($order_id));
                                                $woocommerce->cart->empty_cart();
					}
				
				}

				//接口返回
                		$return_resp = array(
                		    "code"   =>  200,
                		    "success"  =>  true
                		);
                		$jsonResponse = json_encode($return_resp);

                		exit($jsonResponse);
			}

		}

		/**
		 * 同步回调接口
		 **/
		function check_hambit_return()
		{
			global $woocommerce, $wpdb;

			//error_log($_SERVER['REQUEST_URI']);
			if (($_SERVER['REQUEST_METHOD'] === 'GET') && preg_match("/wc_hambit_return/i", $_SERVER['REQUEST_URI'])) {
				//error_log(__METHOD__ . PHP_EOL .print_r($_GET, true));
				$mref = $_GET['merReqNo'];
				
				$check_query = $wpdb->get_results("SELECT order_id,order_state FROM {$wpdb->prefix}huifu_data WHERE ref = '".addslashes($mref)."'", ARRAY_A);
				$check_query_count = count($check_query);
				if($check_query_count >= 1){
					$inv_id = $check_query[0]['order_id'];
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
    	function add_hambit_gateway($methods)
    	{
        	$methods[] = 'WC_HamBit_Gateway';
        	return $methods;
    	}

    	add_filter('woocommerce_payment_gateways', 'add_hambit_gateway');

	// 添加设置链接
	function hambit_action_links($actions) {
		$custom_actions = array(
			'configure' => sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=WC_HamBit_Gateway'), __('设置', 'woo-hambit-gateway')),
		);
		return array_merge($custom_actions, $actions);
	}
	$basename = plugin_basename(__FILE__);
	add_filter("plugin_action_links_$basename", 'hambit_action_links', 10, 1);
}
?>
