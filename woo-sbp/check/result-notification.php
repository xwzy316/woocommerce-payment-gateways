<?php
/**
 * Plugin Name: SB Payment Service for WooCommerce
 * Author URI: https://www.m6shop.com/dzkf/zfcj
 *
 * @class 		WooSBP
 * @version		0.9.3
 * @author		xwzy1130
 */
header("HTTP/1.1 200 OK");
header("Status: 200");
header("Content-Type: text/plain; charset=Shift_JIS ");
if(isset($_POST)){
	$post_data = $_POST;
	if(isset($_POST['order_id'])){
		$order_id = preg_replace('/[^0-9]/', '', $post_data['order_id']);
		if($_POST['res_result'] == 'OK'){
			echo 'OK,';
		}else{
			require( '../../../../wp-blog-header.php' );
			global $wpdb;
			global $woocommerce;
			if(isset($_POST['res_err_code']) and !is_null($_POST['res_err_code'])){
				$context = array( 'source' => 'sbps' );
				$logger = wc_get_logger();
				$logger->debug( $order_id, $context );
				$logger->debug( $_POST['res_err_code'], $context );
			}
			$order = wc_get_order( sanitize_text_field($order_id));
			if($_POST['res_result'] == 'NG'){
				echo 'NG,'.__( 'A request was made NG.', 'woo-sbp' );
			}elseif($_POST['res_result'] == 'CC'){
				$order->add_order_note( __( 'Career has been canceled.', 'woo-sbp' ).$_POST['res_result'] );
			}elseif($_POST['res_result'] == 'CR'){
				$order->add_order_note( __( 'Charging has been canceled.', 'woo-sbp' ).$_POST['res_result'] );
			}elseif($_POST['res_result'] == 'CN'){
				$order->add_order_note( __( 'Expired Canceled.', 'woo-sbp' ).$_POST['res_result'] );
			}elseif($_POST['res_result'] == 'PY'){
				$order->add_order_note( __( 'It is a payment notification.', 'woo-sbp' ).$_POST['res_result'] );
			}elseif($_POST['res_result'] == 'CL'){
				$order->add_order_note( __( 'It is a cancellation by the last billing month.', 'woo-sbp' ).$_POST['res_result'] );
			}
		}
	}else{
		wc4jp_logger( __( 'No Order ID.', 'woo-sbp' ) );
	}
}else{
	wc4jp_logger( __( 'No Post.', 'woo-sbp' ) );
}

function wc4jp_logger($content){
	require( '../../../../wp-blog-header.php' );
	global $wpdb;
	global $woocommerce;
	$context = array( 'source' => 'sbps' );
	$logger = wc_get_logger();
	$logger->debug( $content, $context );
}
?>
