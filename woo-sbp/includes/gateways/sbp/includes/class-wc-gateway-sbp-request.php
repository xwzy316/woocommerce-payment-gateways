<?php
/**
 * Plugin Name: SB Payment Service for WooCommerce
 * Author URI: https://www.m6shop.com/dzkf/zfcj
 *
 * @class 		WooSBP
 * @extends		WC_Gateway_SBP
 * @version		1.0.1
 * @package		WooCommerce/Classes/Payment
 * @author		xwzy1130
 */
use ArtisanWorkshop\WooCommerce\PluginFramework\v2_0_3 as Framework;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Gateway_SBP_Request {

	/**
	 * SB Payment Service Marchnat ID
	 *
	 * @var string
	 */
	public $marchant_id = null;

	/**
	 * SB Payment Service Service ID
	 *
	 * @var string
	 */
	public $service_id = null;

	/**
	 * SB Payment Service Hash Code
	 *
	 * @var string
	 */
	public $hashkey = null;

	/**
	 * SB Payment Service 3DES Encryption key
	 *
	 * @var string
	 */
	public $des_encry = null;

	/**
	 * SB Payment Service 3DES Initialization key
	 *
	 * @var string
	 */
	public $des_init = null;

	/**
	 * SB Payment Service Basic authentication ID
	 *
	 * @var string
	 */
	public $basic_id = null;

	/**
	 * SB Payment Service Basic authentication Password
	 *
	 * @var string
	 */
	public $basic_pass = null;

	/**
	 * SB Payment Service Sandbox Marchnat ID
	 *
	 * @var string
	 */
	public $sandbox_marchant_id = null;

	/**
	 * SB Payment Service Sandbox Service ID
	 *
	 * @var string
	 */
	public $sandbox_service_id = null;

	/**
	 * SB Payment Service Sandbox Hash Code
	 *
	 * @var string
	 */
	public $sandbox_hashkey = null;

	/**
	 * SB Payment Service Sandbox 3DES Encryption key
	 *
	 * @var string
	 */
	public $sandbox_des_encry = null;

	/**
	 * SB Payment Service Sandbox 3DES Initialization key
	 *
	 * @var string
	 */
	public $sandbox_des_init = null;

	/**
	 * SB Payment Service Sandbox Basic authentication ID
	 *
	 * @var string
	 */
	public $sandbox_basic_id = null;

	/**
	 * SB Payment Service Sandbox Basic authentication Password
	 *
	 * @var string
	 */
	public $sandbox_basic_pass = null;

    /**
     * Framework.
     *
     * @var object
     */
    public $jp4wc_framework;

    /**
	 * Constructor
	 * WC_Gateway_SBP $gateway
	 */
	public function __construct() {
		//SB Payment Service Setting IDs
		$wc_sbp_options = get_option('wc_sbp_options');
		$this->merchant_id = $wc_sbp_options['mid'];
		$this->service_id = $wc_sbp_options['sid'];
		$this->hashkey = $wc_sbp_options['link_hashkey'];
		$this->hashkey = $wc_sbp_options['hashkey'];
		$this->des_encry = $wc_sbp_options['3des_encry'];
		$this->des_init = $wc_sbp_options['3des_init'];
		$this->basic_id = $wc_sbp_options['basic_id'];
		$this->basic_pass = $wc_sbp_options['basic_pass'];

        $this->jp4wc_framework = new Framework\JP4WC_Plugin();
	}

	/**
	 * Make the SB Payment Service request XML data
     *
	 * @param  array  $send_arrays
	 * @param  string $sandbox_mode
	 * @return string XML
	 */
	public function make_xml($send_arrays, $sandbox_mode = 'yes'){
		$set_ids = $this->set_ids($sandbox_mode);
		$xmlstr = '<?xml version="1.0" encoding="Shift_JIS"?>
		<sps-api-request>
		</sps-api-request>
		';

		$xml = new SimpleXMLElement($xmlstr);
		$xml->addAttribute('id',$send_arrays['sps-api-request']);
		$xml->addChild('merchant_id', $set_ids['merchant_id']);
		$xml->addChild('service_id', $set_ids['service_id']);
		foreach((array)$send_arrays['data'] as $key => $value){
			if(is_array($value)){
				$add_child = $xml->addChild($key);
				foreach($value as $key1 => $value1){
					if($key == 'pay_method_info'){
						$add_child->addChild($key1, $this->des_ede3_do_encrypt( $value1, $sandbox_mode));
					}else{
						$add_child->addChild($key1, $value1);
					}
				}
			}else{
				$xml->addChild($key, $value);
			}
		}
		return $xml->asXML();
	}
	/**
	 * Make the SB Payment Service request XML data
     *
	 * @param  array  $send_arrays
	 * @param  string $sandbox_mode
	 * @return string XML
	 */
	public function make_cs_xml($send_arrays, $sandbox_mode = 'yes'){
		$set_ids = $this->set_ids($sandbox_mode);
		$data['sps-api-request'] = $send_arrays['sps-api-request'];
		$data['merchant_id'] = $set_ids['merchant_id'];
		$data['service_id'] = $set_ids['service_id'];
		$xmlstr = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="'.$data['sps-api-request'].'">
';
		$data = array_merge($data, $send_arrays['data']);
		$data = $this->make_decode64( $data, $sandbox_mode );
		foreach((array)$data as $key => $value){
			if(is_array($value)){
				$xmlstr .= '  <'.$key.'>
';
				foreach($value as $key1 => $value1){
					$xmlstr .= '    <'.$key1.'>'.$value1.'</'.$key1.'>
';
				}
				$xmlstr .= '  </'.$key.'>
';
			}else{
				$xmlstr .= '  <'.$key.'>'.$value.'</'.$key.'>
';
			}
		}
		$xmlstr .= '</sps-api-request>';
		return $xmlstr;
	}
	/**
	 * Make the SB Payment Service hash value
     *
	 * @param  array  $send_arrays
	 * @param  string $sandbox_mode
     * @param  string $encode
	 * @return string XML
	 */
	public function make_hash($send_arrays = array(), $sandbox_mode = 'yes', $encode = 'SJIS'){
		$ids = $this->set_ids($sandbox_mode);
		array_unshift($send_arrays['data'], array('service_id' => $ids['service_id']));
		array_unshift($send_arrays['data'], array('merchant_id' => $ids['merchant_id']));
		if(isset($send_arrays['data']['pay_method'])){
			$pay_method = $send_arrays['data']['pay_method'];
			unset($send_arrays['data']['pay_method']);
			array_unshift($send_arrays['data'], array('pay_method' => $pay_method));
		}
		$send_arrays['data']['hashkey'] = $ids['hashkey'];
		$def_value = '';
		foreach((array)$send_arrays['data'] as $key => $value){
			if(is_array($value)){
				foreach($value as $key1 => $value1){
					if($encode == 'SJIS'){
						$def_value .= mb_convert_encoding($value1, 'SJIS', 'UTF-8');
					}
				}
			}else{
				$def_value .= $value;
			}
		}
		if($encode != 'SJIS'){$def_value = mb_convert_encoding($def_value, 'UTF-8', 'auto');}
		return $def_value;
	}
	/**
	 * Set IDs
     *
	 * @param  string $sandbox_mode
	 * @return array
	 */
	public function set_ids($sandbox_mode = 'yes'){
		$ids = array();
        $ids['merchant_id'] = $this->merchant_id;
        $ids['service_id'] = $this->service_id;
        $ids['hashkey'] = $this->hashkey;
        $ids['des_encry'] = $this->des_encry;
        $ids['des_init'] = $this->des_init;
        $ids['basic_id'] = $this->basic_id;
        $ids['basic_pass'] = $this->basic_pass;
		if($sandbox_mode == 'yes'){
			$ids['link_url'] = JP4WC_SBP_SANDBOX_PURCHASE_LINK_URL;
			$ids['api_url'] = JP4WC_SBP_SANDBOX_API_URL;
		}else{
			$ids['link_url'] = JP4WC_SBP_PURCHASE_LINK_URL;
			$ids['api_url'] = JP4WC_SBP_API_URL;
		}
		return $ids;
	}

	/**
	 * SB Payment Service Request
     *
     * @param  string $xml_data
     * @param  boolean $sandbox_mode
     * @param  string $debug
     * @return mixed
	 */
	public function get_sbp_request($xml_data, $sandbox_mode, $debug = 'yes'){
		$ids = $this->set_ids($sandbox_mode);
		$api_url = $ids['api_url'];
		$basic_id = $ids['basic_id'];
		$basic_pass = $ids['basic_pass'];

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $basic_id . ':' . $basic_pass ),
			'Content-type'  => 'application/xhtml+xml'
		);
		$args = array(
			'body' => $xml_data,
			'headers' => $headers
		);
		$result = wp_remote_post( $api_url, $args );
        // Check for error
        if ( is_wp_error( $result ) ) {
            $this->jp4wc_framework->jp4wc_debug_log( 'wp_remote_post has error.'."/n".$result->get_error_message(), $debug, 'woo-sbp', WC_SBP_VERSION, JP4WC_SBP_FRAMEWORK_VERSION);
            return false;
        }
		$body = wp_remote_retrieve_body($result);
        // Check for error
        if ( is_wp_error( $body ) ) {
            $this->jp4wc_framework->jp4wc_debug_log( 'wp_remote_retrieve_body has error.', $debug, 'woo-sbp', WC_SBP_VERSION, JP4WC_SBP_FRAMEWORK_VERSION);
            return false;
        }
		$xml_apply_array = simplexml_load_string( $body );

        if ($xml_apply_array === false) {
            $errors = libxml_get_errors();
            $message = "";

            foreach ($errors as $error) {
                $return .= str_repeat('-', $error->column) . "^\n";

                switch ($error->level) {
                    case LIBXML_ERR_WARNING:
                        $return .= "Warning $error->code: ";
                        break;
                    case LIBXML_ERR_ERROR:
                        $return .= "Error $error->code: ";
                        break;
                    case LIBXML_ERR_FATAL:
                        $return .= "Fatal Error $error->code: ";
                        break;
                }

                $return .= trim($error->message) .
                    "\n  Line: $error->line" .
                    "\n  Column: $error->column";

                if ($error->file) {
                    $return .= "\n  File: $error->file";
                }
                $message .= $return."\n\n--------------------------------------------\n\n";
                //Save debug send data.
                $message .= 'This is simplexml_load_string error data.';
                $this->jp4wc_framework->jp4wc_debug_log( $message, $debug, 'woo-sbp', WC_SBP_VERSION, JP4WC_SBP_FRAMEWORK_VERSION);
            }

            libxml_clear_errors();
        }
		return $xml_apply_array;
	}

	/**
	 * Get post data if set
     *
	 * @param  string $str
     * @param  string $sandbox_mode
     * @return string
     */
	public function des_ede3_do_encrypt( $str, $sandbox_mode ) {
        $ids = $this->set_ids($sandbox_mode);
		$iv = $ids['des_init'];
		$password = $ids['des_encry'];
		$method = 'des-ede3-cbc';
		$decodeTarget = $str;
		$str_result = openssl_encrypt(
			$decodeTarget,
			$method,
			$password,
			0,
			$iv
		);
		return $str_result;
	}

	/**
	 * Get post data if set
     *
	 * @param  string $str
     * @param  string $sandbox_mode
     * @return string
     */
	public function des_ede3_do_decrypt( $str, $sandbox_mode ) {
		$ids = $this->set_ids($sandbox_mode);
		$iv = $ids['des_init'];
		$password = $ids['des_encry'];
		$method = 'des-ede3-cbc';
		$decodeTarget = base64_decode($str);
		$str_result = openssl_decrypt(
			$decodeTarget,
			$method,
			$password,
			OPENSSL_NO_PADDING,
			$iv
		);
		return mb_convert_encoding($str_result, 'UTF-8', 'Shift_JIS');
	}

	/**
	 * Make the billing address data
     *
	 * @param  array  $order_data
	 * @param  string $sandbox_mode
	 * @return string 
	 */
	public function make_billing_data($order_data, $sandbox_mode = 'yes'){
		$billing_text = '';
		foreach($order_data as $key => $value){
			$billing_text .= $key.'='.$value.',';
		}
		$billing_text = rtrim($billing_text, ',');
		return $billing_text;
	}

	/**
	 * Make the post data to url
	 * @param  array  $post_data
	 * @param  string $sandbox_mode
	 * @return string 
	 */
	public function make_send_link($post_data, $sandbox_mode = 'yes'){
		date_default_timezone_set('Asia/Tokyo');
		$post_data['request_date'] = date('YmdHis');
		$post_data['limit_second'] = 600;

		$send_arrays['data'] = $post_data;
		$post_data['sps_hashcode'] = sha1($this->make_hash($send_arrays, $sandbox_mode));

		$ids = $this->set_ids($sandbox_mode);

		$post_url = '?pay_method='.$post_data['pay_method'];
		unset($post_data['pay_method']);
		$post_url .= '&merchant_id='.$ids['merchant_id'];
		$post_url .= '&service_id='.$ids['service_id'];
		foreach( $post_data as $key => $value ){
			$post_url .= '&'.$key.'='.urlencode($value);
		}

		return $ids['link_url'].$post_url;
	}


    /**
     * Make the post data to url
     * @param  array  $data
     * @param  string $sandbox_mode
     * @return array $data
     */
	public function make_decode64( $data , $sandbox_mode){
		$checked_key = array(
			'last_name',
			'first_name',
			'add1',
			'add2',
			'add3',
		);
		foreach ($data as $key => $value){
			if(is_array($value)){
				foreach($value as $key1 => $value1){
					if(in_array($key1, $checked_key)){
						$data[$key][$key1] = $this->des_ede3_do_encrypt( mb_convert_encoding($value1, 'SJIS','UTF-8'), $sandbox_mode );
					}else{
						$data[$key][$key1] = $this->des_ede3_do_encrypt( $value1, $sandbox_mode );
					}
				}
			}else{
				if(in_array($key, $checked_key)){
					$data[$key] = $value;
				}
			}
		}
		return $data;
	}
	/**
	 * Send
     	 *
     	 * @param  array  $data
     	 * @param  string $api_request
     	 * @param  string $sandbox_mode
     	 * @param  string $debug yes | no
	 * @return string
	 */
	public function result_send_sbp_api($data, $api_request, $sandbox_mode, $debug = 'no'){
		date_default_timezone_set('Asia/Tokyo');
		$data['request_date'] = date('YmdHis');
		$data['limit_second'] = 600;

		$send_arrays = array();
		$send_arrays['sps-api-request'] = $api_request;
		$send_arrays['data'] = $data;
		$send_arrays['data']['sps_hashcode'] = sha1($this->make_hash($send_arrays, $sandbox_mode));
		//Save debug send data.
        	$message = 'sps-api-request : ' . $api_request . "\n" . $this->jp4wc_framework->jp4wc_array_to_message($send_arrays['data']) . 'This is send data.';
        	$this->jp4wc_framework->jp4wc_debug_log( $message, $debug, 'woo-sbp', WC_SBP_VERSION, JP4WC_SBP_FRAMEWORK_VERSION);

		//Make XML Data
		$xml_data = $this->make_xml($send_arrays, $sandbox_mode);
		//Send request to SBP API
		$result = $this->get_sbp_request($xml_data, $sandbox_mode, $debug);
		return $result;
	}
}
