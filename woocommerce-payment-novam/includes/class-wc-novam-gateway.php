<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class WC_Novam_Gateway extends WC_Payment_Gateway {
    public $api_domain;
    public $merchant_no;
    public $secret_key;
    public $currency;
    public $out_channel;

    public function __construct() {
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
            'block_checkout' // 支持Block结账
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        
        // 从通用设置获取商户参数
        $novam_settings = WC_Novam_Settings::get_settings();
        $this->merchant_no = isset($novam_settings['merchant_no']) ? $novam_settings['merchant_no'] : '';
        $this->secret_key = isset($novam_settings['secret_key']) ? $novam_settings['secret_key'] : '';
        
        // API域名写死
        $this->api_domain = '1novam.xyz';
        
        // 货币使用WooCommerce设置
        $this->currency = get_woocommerce_currency();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id . '_callback', array($this, 'handle_callback'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-payment-novam'),
                'type' => 'checkbox',
                'label' => __('Enable this payment method', 'woocommerce-payment-novam'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'woocommerce-payment-novam'),
                'type' => 'text',
                'description' => __('This will be shown to customers at checkout.', 'woocommerce-payment-novam'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce-payment-novam'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers will see at checkout.', 'woocommerce-payment-novam'),
                'default' => '',
            ),
        );
    }

    // 获取回调URL
    protected function get_callback_url() {
        return home_url('/wc-api/' . $this->id . '_callback');
    }

    // 生成签名
    protected function generate_signature($params) {
        // 排除值为空的参数和sign参数
        $filtered_params = array();
        foreach ($params as $key => $value) {
            if ($key !== 'sign' && !empty($value)) {
                $filtered_params[$key] = $value;
            }
        }
        
        // 对参数按键进行字典序排序
        ksort($filtered_params);
        
        // 拼接参数字符串
        $sign_str = '';
        foreach ($filtered_params as $key => $value) {
            $sign_str .= $key . '=' . $value . '&';
        }
        
        // 移除末尾的&符号
        $sign_str = rtrim($sign_str, '&');
        
        // 拼接密钥
        $sign_str .= $this->secret_key;
        
        error_log('Novam Payments - Sign string: ' . $sign_str);
        
        // 生成MD5签名
        $signature = md5($sign_str);
        error_log('Novam Payments - Generated signature: ' . $signature);
        
        return $signature;
    }

    // 验证签名
    protected function verify_signature($data) {
        if (!isset($data['sign'])) {
            error_log('Novam Payments - Signature verification failed: sign parameter missing');
            return false;
        }
        
        $sign = $data['sign'];
        unset($data['sign']);
        
        $generated_sign = $this->generate_signature($data);
        $is_valid = $generated_sign === $sign;
        
        error_log('Novam Payments - Signature verification result: ' . ($is_valid ? 'valid' : 'invalid') . 
                  ', received: ' . $sign . ', generated: ' . $generated_sign);
        
        return $is_valid;
    }

    // 发送API请求
    protected function send_api_request($params) {
        $url = 'https://' . $this->api_domain . '/api/payIn';
        
        // 记录请求参数
        error_log('Novam Payments - Request URL: ' . $url);
        error_log('Novam Payments - Request Params: ' . print_r($params, true));
        
        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($params),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('Novam Payments - Request Error: ' . print_r($response, true));
            return new WP_Error('api_error', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // 记录响应结果
        error_log('Novam Payments - Response Code: ' . $code);
        error_log('Novam Payments - Response Body: ' . $body);
        
        if ($code !== 200) {
            return new WP_Error('api_error', sprintf(__('API returned status code: %d', 'woocommerce-payment-novam'), $code));
        }

        return json_decode($body, true);
    }

    // 处理支付回调
    public function handle_callback() {
        $raw_data = file_get_contents('php://input');
        
        // 记录回调原始数据
        error_log('Novam Payments - Callback Raw Data: ' . $raw_data);
        
        $data = json_decode($raw_data, true);
        
        // 检查基本结构
        if (!$data || !isset($data['data'])) {
            error_log('Novam Payments - Callback Error: Invalid data structure');
            wp_send_json(array('code' => 500, 'msg' => 'Invalid data structure'), 400);
        }

        // 验证签名（只验证data部分）
        if (!$this->verify_signature($data['data'])) {
            error_log('Novam Payments - Callback Error: Invalid signature');
            wp_send_json(array('code' => 500, 'msg' => 'Invalid signature'), 400);
        }

        // 从orderNo中提取原始订单ID
        $order_no = sanitize_text_field($data['data']['orderNo']);
        error_log('Novam Payments - Callback orderNo: ' . $order_no);
        
        // 尝试从orderNo中提取原始订单ID
        $order_id = false;
        if (strpos($order_no, '_') !== false) {
            // 如果orderNo包含下划线，提取下划线前的部分作为订单ID
            $parts = explode('_', $order_no);
            $order_id = $parts[0];
        } else {
            // 否则直接使用orderNo作为订单ID
            $order_id = $order_no;
        }
        
        error_log('Novam Payments - Extracted order ID: ' . $order_id);
        
        // 获取订单对象
        $order = wc_get_order($order_id);

        if (!$order) {
            error_log('Novam Payments - Callback Error: Order not found for order ID: ' . $order_id);
            wp_send_json(array('code' => 500, 'msg' => 'Order not found'), 404);
        }

        // 检查订单状态
        if ($order->needs_payment()) {
            // 获取通用设置中的订单状态
            $novam_settings = WC_Novam_Settings::get_settings();
            $order_status = isset($novam_settings['order_status']) ? $novam_settings['order_status'] : 'wc-completed';
            
            // 更新订单状态
            $order->update_status($order_status, sprintf(__('Payment completed successfully via API callback. Amount: %s', 'woocommerce-payment-novam'), $data['data']['amount']));
            error_log('Novam Payments - Callback Success: Order status updated to ' . $order_status . ' for order ID: ' . $order_id);
        }
        
        // 根据文档要求，返回ok字符串
        echo 'ok';
        wp_die();
    }

    // 输出Block结账所需的脚本
    public function get_block_support_script() {
        return plugins_url('assets/js/block-support.js', dirname(__FILE__));
    }
}