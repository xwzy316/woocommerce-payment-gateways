<?php
/*
 * Plugin Name: WooCommerce Huifu Gateway (汇付天下)
 * Plugin URI: https://hfgj.testpnr.com/
 * Description: Take payments on your store.
 * Author: xwzy1130
 * Version: 1.0.11
 * Domain Path: /languages
/*
 * 这个动作挂钩将我们的PHP类注册为WooCommerce支付网关
 */

/* Add a custom payment class to WC
  ------------------------------------------------------------ */

add_action('plugins_loaded', 'woo_huifu_gateway', 0);

function woo_huifu_gateway()
{
	if (!class_exists('WC_Payment_Gateway'))
        	return; 	
    	if(class_exists('WC_Huifu_Gateway'))
        	return;

	class WC_Huifu_Gateway extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$plugin_dir = plugin_dir_url(__FILE__);

            		global $woocommerce;
            		$this->id = 'huifu';
            		$this->icon = apply_filters('woocommerce_huifu_icon', ''.$plugin_dir.'logo.png');
            		$this->has_fields = false;

            		// Load the form fields.
            		$this->init_form_fields();

            		// Load the settings.
            		$this->init_settings();

            		// Define user set variables
            		$this->title = $this->settings['title'];
            		$this->description = $this->settings['description'];

            		$this->huifu_merchantno = $this->settings['merchantno'];

			//支付通道
            		$this->huifu_processing = $this->settings['processing'];

            		// Actions
            		add_action('woocommerce_receipt_huifu', array($this, 'receipt_page'));
            		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            		// Payment listener/API hook
            		add_action( 'woocommerce_api_wc_huifu_notify', array( $this, 'check_huifu_ipn_response' ) );
            		add_action( 'woocommerce_api_wc_huifu_return', array( $this, 'check_huifu_return' ) );

            		//load_plugin_textdomain('huifu-for-woocommerce', false,basename( dirname( __FILE__ ) ) . '/languages');

            		if (!$this->is_valid_for_use())
            		{
            		    $this->enabled = false;
            		}
		}
		
		//拼接字符串
		public function param_ck_null($kq_va,$kq_na) {
                	if($kq_va == ""){
                        	$kq_va="";
                	}else{
                		return $kq_va=$kq_na.'='.$kq_va.'&';
                	}
        	}

		/**
         	* Check if this gateway is enabled and available in the user's country
         	*/
        	function is_valid_for_use()
        	{
	    		//error_log( get_woocommerce_currency());
            		if (!in_array( get_woocommerce_currency(), array('CNY', 'USD'))) return false;
            		return true;
        	}

		/**
         	* Admin Panel Options
         	* - Options for bits like 'title' and availability on a country-by-country basis
         	**/

        	public function admin_options() {
         	   global $wpdb;
		   $query = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}huifu_data (
                        id int(11) unsigned NOT NULL AUTO_INCREMENT,
                        ref varchar(100) DEFAULT NULL,
                        ordercode varchar(255) DEFAULT NULL,
                        email varchar(64) DEFAULT NULL,
                        phone varchar(64) DEFAULT NULL,
                        orderid varchar(100) DEFAULT NULL,
                        total_cost int(11) DEFAULT NULL,
                        currency char(3) DEFAULT NULL,
                        order_state char(1) DEFAULT NULL,
                        timestamp datetime DEFAULT NULL,
                        PRIMARY KEY (id))";
           	   $wpdb->query($query);

         	   ?>
         	   <h3><?php _e('Huifu', 'huifu-for-woocommerce'); ?></h3>
         	   <p><?php _e('Huifu redirects customers to their secure server for making payments.', 'huifu-for-woocommerce'); ?></p>
         	   <table class="form-table">
         	       <?php
         	       if ( $this->is_valid_for_use() ) :

         	           // Generate the HTML For the settings form.
         	           $this->generate_settings_html();

         	       else :
         	           ?>
         	           <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'huifu-for-woocommerce'); ?></strong>: <?php _e('Huifu does not support your store currency.', 'huifu-for-woocommerce' ); ?></p></div>
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
        	            'title' => __('Enable/Disable', 'huifu-for-woocommerce'),
        	            'type' => 'checkbox',
        	            'label' => __('Enable Huifu', 'huifu-for-woocommerce'),
        	            'default' => 'yes'
        	        ),
        	        'title' => array
        	        (
        	            'title' => __('Title', 'huifu-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __( 'This controls the title which the user sees during checkout.', 'huifu-for-woocommerce' ),
        	            'default' => __('Huifu', 'huifu-for-woocommerce')
        	        ),
        	        'description' => array(
        	            'title' => __( 'Description', 'woocommerce' ),
        	            'type' => 'textarea',
        	            'description' => __( 'This controls the description which the user sees during checkout.', 'huifu-for-woocommerce' ),
        	            'default' => __( 'Pay via Huifu - you can pay with your credit card.', 'huifu-for-woocommerce' )
        	        ),
        	        'merchantno' => array
        	        (
        	            'title' => __('Merchant ID', 'huifu-for-woocommerce'),
        	            'type' => 'text',
        	            'description' => __('Merchant ID provided by Huifu.', 'huifu-for-woocommerce'),
        	            'default' => ''
        	        ),
        	        'processing' => array(
        	            'title' => __('Order status', 'huifu-for-woocommerce'),
        	            'default' => 'completed',
        	            'type' => 'select',
        	            'options' => array(
        	                'completed'       => __( 'Completed', 'huifu-for-woocommerce' ),
        	                'processing'  	  => __( 'Processing', 'huifu-for-woocommerce' ),
        	                'on-hold'  	  => __( 'On hold', 'huifu-for-woocommerce' )
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
            		$order = new WC_Order( $order_id );

			//测试
			$curl_api = "https://hfgj.testpnr.com/pay/unifiedorder.htm";

			//正式
			#$curl_api   = "https://global.chinapnr.com/pay/unifiedorder.htm";

			$mref = "REF".date("YmdHis", time()+8*60*60).substr(md5(uniqid(rand(), true)), 0, 9);

			//订单确认页
                	//define( 'WOOCOMMERCE_CHECKOUT', true );
                	WC()->cart->calculate_totals();
			
			//订单金额
                	$amount = number_format($order->get_total(), '2', '.', '');
            		if($amount==0.00){
                		$order_id = wc_get_order_id_by_order_key($_GET['key']);
                		$order    = wc_get_order( $order_id );
                		$amount = number_format($order->get_total(), '2', '.', '');
            		}
			
			//订单金额 分
			$amountcents = round($amount * 100);

			//商户号
			$MerchantNo  =  $this->huifu_merchantno.'01';

			$customer_name =  $order->get_billing_first_name() . $order->get_billing_last_name();
			$customer_phone = $order->get_billing_phone();
			
			//异步回调url
                        $notify_url =  WC()->api_request_url( 'wc_huifu_notify' );

			//同步回调url
			$return_url = WC()->api_request_url( 'wc_huifu_return' ) ;
			$check = strpos($return_url, '?');
			if ( $check !== false) {
				$return_url = $return_url . "&mref=" . $mref;
			} else {
				$return_url = $return_url . "?mref=" . $mref;
			}

			//商品信息
			//获取第一个商品信息
                	$items = $order->get_items();

                	if ( ! empty( $items ) ) {
                	    $first_item = reset( $items );  // 获取第一个商品
                	    $product_name = preg_replace("/[^A-Za-z0-9]/", "", $first_item->get_name());
                	    $product_num = $first_item->get_quantity();
                	}
			//商品代码
                	$product_id = "100200";
			$product_desc = $product_id."|".$product_name."|".$product_num;
			
			//币种
            		$currency_code = get_woocommerce_currency();
			//$currency_code = "CNY";

			//终端号
			$terminalId = "0010001";
			//联系方式
			$payerContactType = 2;

			//订单创建时间
			$orderTime = date("YmdHis", time()+8*60*60);

			//查询流水号
			$inquireTrxNo = "";

			//非必填项
			//身份证号
			$payerIdentityCard = "";
			//卡号
			$cardNumber = "";
			//客户编号
			$customerId = "";

			//支付方式 pc端
			$deviceType= "1";
			//聚合
			$payType = "6"; 

			//扩展字段
			$ext1 = "ext1";
			$ext2 = "ext2";

			//构造提交参数
                        $Body = array(
			     	"inputCharset" => "1",
                		"pageUrl" => $return_url,
                		"bgUrl" => $notify_url,
                		"version" => "3.0",
                		"language" => "1",
                		"signType" => "4",
                		"merchantAcctId" => $MerchantNo,
                		"terminalId" => $terminalId,
                		"payerName" => $customer_name,
                		"payerContactType" => $payerContactType,
                		"payerContact" => $customer_phone,
                		"payerIdentityCard" => $payerIdentityCard,
                		"mobileNumber" => $customer_phone,
                		"cardNumber" => $cardNumber,
                		"customerId" => $customerId,
                		"orderId" => $mref,
                		"settlementCurrency" => $currency_code,
                		"orderCurrency" => $currency_code,
                		"orderAmount" => $amountcents,
                		"orderTime" => $orderTime,
                		"inquireTrxNo" => $inquireTrxNo,
                		"productName" => $product_name,
                		"productNum" => $product_num,
                		"productId" => $product_id,
                		"productDesc" => $product_desc,
                		"ext1" => $ext1,
                		"ext2" => $ext2,
                		"deviceType" => $deviceType,
                		"payType" => $payType,
                		"bankId" => "",
                		"customerIp" => "",
                		"redoFlag" => "1"
                        );

			//组织签名串
			$kq_all_para=$this->param_ck_null($return_url,"pageUrl");
        		$kq_all_para.=$this->param_ck_null($notify_url,'bgUrl');
        		$kq_all_para.=$this->param_ck_null($MerchantNo,'merchantAcctId');
        		$kq_all_para.=$this->param_ck_null($terminalId,'terminalId');
        		$kq_all_para.=$this->param_ck_null($customerId,'customerId');
        		$kq_all_para.=$this->param_ck_null($mref,'orderId');
        		$kq_all_para.=$this->param_ck_null($amountcents,'orderAmount');
        		$kq_all_para.=$this->param_ck_null($orderTime,'orderTime');
        		$kq_all_para.=$this->param_ck_null($product_desc,'productDesc');
        		$kq_all_para.=$this->param_ck_null($ext1,'ext1');
        		$kq_all_para.=$this->param_ck_null($ext2,'ext2');
        		$kq_all_para.=$this->param_ck_null($deviceType,'deviceType');
        		$kq_all_para.=$this->param_ck_null($payType,'payType');

        		$kq_all_para=substr($kq_all_para,0,strlen($kq_all_para)-1);

			//获取私钥
			$fp = fopen(__DIR__."/"."10020230621.key", "r");
        		$priv_key = fread($fp, filesize(__DIR__."/".'10020230621.key'));
        		fclose($fp);
        		$pkeyid = openssl_get_privatekey($priv_key);

        		// compute signature
        		openssl_sign($kq_all_para, $signMsg, $pkeyid,OPENSSL_ALGO_SHA1);

        		// free the key from memory
        		openssl_free_key($pkeyid);

                        $checksum = base64_encode($signMsg);
                        $Body["signMsg"] = $checksum;	

			//打印参数
			//error_log(__METHOD__ . PHP_EOL .print_r($Body, true));			

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
				parse_str($postRequest['body'], $result);
            		} else {
                		error_log(__METHOD__ . PHP_EOL . 'Code:' . $postRequest['response']['code'] . PHP_EOL. ' Error:' . $postRequest['response']['message']);

				//抛出异常
                		throw new Exception("Unable to reach huifu Payments (" . $postRequest['response']['message'] . ")");
            		}
			
			//打印结果
			error_log(__METHOD__ . PHP_EOL .print_r($result, true));

            		if ($result['type'] == '2') {
            		    	$OrderNo = "";

				#写入数据库
				$query = "insert into {$wpdb->prefix}huifu_data (ref, ordercode, phone, orderid, total_cost, currency, order_state, timestamp) 
					values ('".$mref."', '".$OrderNo."','". $customer_phone ."','". $order_id . "',".$amountcents.",'". $currency_code."','I', now())";
            			$wpdb->query($query);

			   	$PayUrl = $result['url'];

				//跳转payurl
                        	return array(
                        	    'result' => 'success',
                        	    'redirect' => $PayUrl
                        	);
            		} else {
            		    	throw new Exception("Unable to redirect payurl (" . $result['respMsg'] . ")");
            		}


		}


		/**
        	 * 异步回调接口
        	 **/
        	function check_huifu_ipn_response()
		{
			global $woocommerce, $wpdb;
			if (($_SERVER['REQUEST_METHOD'] === 'POST') && preg_match("/wc_huifu_notify/i", $_SERVER['REQUEST_URI'])) 
			{

				//error_log(__METHOD__ . PHP_EOL .print_r($_POST, true));

				if ( $_POST ) {
					$statustr = $this->huifu_processing;
					
					//验证签名
					//银行交易号 ，ChinaPnr交易在银行支付时对应的交易号，如果不是通过银行卡支付，则为空
        				$kq_check_all_para=$this->param_ck_null($_POST['bankDealId'],'bankDealId');

        				//银行代码，如果payType为00，该值为空；如果payType为10,该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['bankId'],'bankId');

        				//ChinaPnr交易号，商户每一笔交易都会在ChinaPnr生成一个交易号。
        				$kq_check_all_para.=$this->param_ck_null($_POST['dealId'],'dealId');

        				//ChinaPnr交易时间，ChinaPnr对交易进行处理的时间,格式：yyyyMMddHHmmss，如：20071117020101
        				$kq_check_all_para.=$this->param_ck_null($_POST['dealTime'],'dealTime');

        				//错误代码 ，请参照《人民币网关接口文档》最后部分的详细解释。
        				$kq_check_all_para.=$this->param_ck_null($_POST['errCode'],'errCode');

        				//扩展字段1，该值与提交时相同
        				$kq_check_all_para.=$this->param_ck_null($_POST['ext1'],'ext1');
        				//扩展字段2，该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['ext2'],'ext2');

        				//人民币网关账号，该账号为11位人民币网关商户编号+01,该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['merchantAcctId'],'merchantAcctId');

        				//订单金额，金额以“分”为单位，商户测试以1分测试即可，切勿以大金额测试,该值与支付时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['orderAmount'],'orderAmount');
        				$kq_check_all_para.=$this->param_ck_null($_POST['orderCurrency'],'orderCurrency');

        				//商户订单号，,该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['orderId'],'orderId');

        				//订单提交时间，格式：yyyyMMddHHmmss，如：20071117020101,该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['orderTime'],'orderTime');

        				//处理结果， 10支付成功，11 支付失败，00订单申请成功，01 订单申请失败
        				$kq_check_all_para.=$this->param_ck_null($_POST['payResult'],'payResult');

        				//支付方式，一般为00，代表所有的支付方式。如果是银行直连商户，该值为10,该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['payType'],'payType');

        				//商户终端号，该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['terminalId'],'terminalId');

        				//网关版本，该值与提交时相同。
        				$kq_check_all_para.=$this->param_ck_null($_POST['version'],'version');

        				$trans_body=substr($kq_check_all_para,0,strlen($kq_check_all_para)-1);

        				$signMsgDe=urldecode($_POST['signMsg']);

        				$MAC=base64_decode($signMsgDe);
        				$trans_body_de=urldecode($trans_body);

					$fp = fopen(__DIR__."/"."ChinaPnR.rsa.cer", "r");
        				$cert = fread($fp, filesize(__DIR__."/"."ChinaPnR.rsa.cer"));
        				fclose($fp);
        				$pubkeyid = openssl_get_publickey($cert);
        				$ok = openssl_verify($trans_body_de, $MAC, $pubkeyid);

					//if ($checksum == $res_data['sign']) {
					if ( $ok == 1 ) 
					{
						$tm_ref = $_POST['orderId'];
						$check_query = $wpdb->get_results("SELECT orderid,order_state FROM {$wpdb->prefix}huifu_data WHERE ref = '".addslashes($tm_ref)."'", ARRAY_A);
                           			$check_query_count = count($check_query);


						if( $check_query_count >= 1 ) {
							if($check_query[0]['order_state'] == 'I' && $_POST['payResult'] == '10') {
								$query = "update {$wpdb->prefix}huifu_data set order_state='C', ordercode= ". $_POST['dealId']." where ref='".addslashes($tm_ref)."'";
                            					$wpdb->query($query);

								$inv_id = $check_query[0]['orderid'];
                            					$order = new WC_Order($inv_id);
								$order->update_status($statustr, __('Order has been paid by ID: ' . $_POST['dealId'], 'huifu-for-woocommerce'));
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
		function check_huifu_return()
		{
			global $woocommerce, $wpdb;

			//error_log($_SERVER['REQUEST_URI']);

			if (($_SERVER['REQUEST_METHOD'] === 'GET') && preg_match("/wc_huifu_return/i", $_SERVER['REQUEST_URI'])) {
				//error_log(__METHOD__ . PHP_EOL .print_r($_GET, true));
				$tm_ref = $_GET['mref'];
				//error_log($tm_ref);
				$check_query = $wpdb->get_results("SELECT orderid,order_state FROM {$wpdb->prefix}huifu_data WHERE ref = '".addslashes($tm_ref)."'", ARRAY_A);
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
    	function add_huifu_gateway($methods)
    	{
        	$methods[] = 'WC_Huifu_Gateway';
        	return $methods;
    	}

    	add_filter('woocommerce_payment_gateways', 'add_huifu_gateway');
}
?>
