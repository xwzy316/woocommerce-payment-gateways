<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Easypaisa_Direct extends WC_Novam_Gateway {
    public function __construct() {
        $this->id = 'easypaisa_direct';
        $this->method_title = __('Easypaisa Direct', 'woocommerce-payment-novam');
        $this->method_description = __('Accept payments via Easypaisa Direct.', 'woocommerce-payment-novam');
        $this->icon = plugins_url('assets/images/easypaisa.png', dirname(__FILE__));
        $this->out_channel = 'Easypaisa_Direct';

        parent::__construct();
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        echo '<div class="wc-novam-payment-fields">';
        woocommerce_form_field('easypaisa_account', array(
            'type' => 'text',
            'label' => __('Easypaisa Account Number', 'woocommerce-payment-novam'),
            'required' => true,
            'class' => array('form-row-wide'),
            'label_class' => array('wc-novam-label'),
        ), '');
        echo '</div>';
    }

    public function validate_fields() {
        if (empty($_POST['easypaisa_account'])) {
            wc_add_notice(__('Please enter your Easypaisa account number.', 'woocommerce-payment-novam'), 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $account = sanitize_text_field($_POST['easypaisa_account']);

        // 生成符合要求的订单号 (订单ID_时间戳)
        $timestamp = time();
        $order_no = $order_id . '_' . $timestamp;
        
        // 确保订单号只包含数字和下划线，并且长度在要求范围内
        $order_no = preg_replace('/[^0-9_]/', '', $order_no);
        if (strlen($order_no) < 10) {
            // 如果太短，用订单ID的数字部分加上时间戳
            $order_no = preg_replace('/[^0-9]/', '', $order_id) . '_' . $timestamp;
        }
        
        // 确保长度符合要求 (10-35字符)
        if (strlen($order_no) > 35) {
            $order_no = substr($order_no, 0, 35);
        } else if (strlen($order_no) < 10) {
            // 如果仍然太短，直接使用时间戳
            $order_no = str_pad($timestamp, 10, '0', STR_PAD_RIGHT);
            $order_no = substr($order_no, 0, 35);
        }

        $params = array(
            'amount' => number_format($order->get_total(), 2),
            'orderNo' => $order_no,
            'merchNo' => $this->merchant_no,
            'currency' => $this->currency,
            'outChannel' => $this->out_channel,
            'payAccount' => $account,
            'notifyUrl' => $this->get_callback_url(),
        );

        $params['sign'] = $this->generate_signature($params);
        
        // 记录支付参数
        error_log('Easypaisa Direct - Processing payment for order ID: ' . $order_id);
        error_log('Easypaisa Direct - Payment params: ' . print_r($params, true));
        
        // 将订单号保存到订单自定义字段
        update_post_meta($order_id, '_novam_order_no', $order_no);
        
        $response = $this->send_api_request($params);

        if (is_wp_error($response)) {
            error_log('Easypaisa Direct - Payment Error: ' . $response->get_error_message());
            wc_add_notice(__('Payment request failed: ', 'woocommerce-payment-novam') . $response->get_error_message(), 'error');
            return array('result' => 'fail');
        }

        // 记录响应结果
        error_log('Easypaisa Direct - Payment response: ' . print_r($response, true));
        
        if (isset($response['code']) && $response['code'] == 0 && $this->verify_signature($response['data'])) {
            error_log('Easypaisa Direct - Payment successful for order ID: ' . $order_id);
            // 支付成功，跳转到订单接收页面而不是支付链接
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        $error = isset($response['msg']) ? $response['msg'] : __('Unknown error', 'woocommerce-payment-novam');
        error_log('Easypaisa Direct - Payment failed: ' . $error);
        wc_add_notice(__('Payment processing failed: ', 'woocommerce-payment-novam') . $error, 'error');
        return array('result' => 'fail');
    }
}