<?php

class WorldpayHostedPage {
    
    public function __construct() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('template_include', array($this, 'load_custom_template'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_custom_assets'));
        add_filter('query_vars', array($this, 'register_query_var'));
    }
    
    public function add_rewrite_rules() {
        // 添加重写规则
        add_rewrite_rule(
            '^safe-checkout/?$',
            'index.php?worldpay_checkout_page=1',
            'top'
        );
        
        // 添加带订单ID的重写规则（路径参数方式）
        add_rewrite_rule(
            '^safe-checkout/([^/]+)/?$',
            'index.php?worldpay_checkout_page=1&order_id=$matches[1]',
            'top'
        );
        
        // 添加带订单ID的重写规则（查询参数方式）
        add_rewrite_rule(
            '^safe-checkout/?\?order_id=([^/]+)$',
            'index.php?worldpay_checkout_page=1&order_id=$matches[1]',
            'top'
        );

        // 注册查询变量
        add_rewrite_tag('%worldpay_checkout_page%', '([^&]+)');
        add_rewrite_tag('%order_id%', '([^&]+)');
    }

    public function register_query_var($vars) {
        $vars[] = 'worldpay_checkout_page';
        $vars[] = 'order_id';
        return $vars;
    }
    
    public function load_custom_template($template) {
        if (get_query_var('worldpay_checkout_page')) {
            // 移除用户登录检查，允许游客访问
            
            // 返回插件内的自定义模板
            $custom_template = WORLDPAY_HOSTED_PLUGIN_PATH . 'templates/payrix-checkout.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    public function enqueue_custom_assets() {
        if (get_query_var('worldpay_checkout_page')) {
            // 移除用户登录检查，允许游客访问
            
            // 加载新的 Payrix CSS
            wp_enqueue_style('payrix-checkout-css', 
                WORLDPAY_HOSTED_PLUGIN_URL . 'assets/css/payrix-checkout.css',
                array(), '1.0.0'
            );
            
            // 获取订单ID（尝试多种方式）
            $order_id = get_query_var('order_id');
            
            // 如果通过查询变量没有获取到，尝试直接从请求中获取
            if (empty($order_id) || $order_id === '$1') {
                if (isset($_GET['order_id'])) {
                    $order_id = sanitize_text_field($_GET['order_id']);
                }
            }
            
            // 添加调试日志
            // error_log("WorldPay Hosted: Raw order ID from query var: " . var_export($order_id, true));
            // error_log("WorldPay Hosted: GET parameter order_id: " . (isset($_GET['order_id']) ? var_export($_GET['order_id'], true) : 'not set'));
            // error_log("WorldPay Hosted: Request URI: " . var_export($_SERVER['REQUEST_URI'], true));
            
            // 根据订单ID获取订单金额
            $amount = $this->get_order_amount($order_id);
            
            // 获取订单商品信息
            $items = $this->get_order_items($order_id);
            
            // 获取账单地址信息
            $billing_address = $this->get_billing_address($order_id);
            
            // 加载新的 Payrix JavaScript
            wp_enqueue_script('payrix-checkout-js',
                WORLDPAY_HOSTED_PLUGIN_URL . 'assets/js/payrix-checkout.js',
                array(), '1.0.0', true
            );
            
            // 将金额数据和商品信息传递给前端
            $options = get_option('worldpay_payment_options');
            $debug_enabled = isset($options['enable_debug_log']) && $options['enable_debug_log'] === '1';
            $countdown_seconds = isset($options['redirect_countdown_seconds']) ? intval($options['redirect_countdown_seconds']) : 5;
            
            wp_localize_script(
                'payrix-checkout-js',
                'worldpay_checkout_data', 
                array(
                    'amount' => $amount,
                    'order_id' => $order_id,
                    'items' => $items,
                    'billing_address' => $billing_address,
                    'user_id' => get_current_user_id(), // 添加用户ID
                    'debug_enabled' => $debug_enabled, // 添加调试开关
                    'countdown_seconds' => $countdown_seconds, // 添加倒计时秒数
                    'i18n' => array(
                        'loading' => __('Loading payment form...', 'worldpay-hosted'),
                        'loadingError' => __('Unable to load payment form:', 'worldpay-hosted'),
                        'refreshPage' => __('Please refresh the page and try again.', 'worldpay-hosted'),
                        'checkPaymentInfo' => __('Please check your payment information', 'worldpay-hosted'),
                        'submitPayment' => __('Submit Payment', 'worldpay-hosted'),
                        'processing' => __('Processing...', 'worldpay-hosted'),
                        'retryPayment' => __('Retry Payment', 'worldpay-hosted'),
                        'paymentSuccess' => __('Payment Successful!', 'worldpay-hosted'),
                        'transactionId' => __('Transaction ID:', 'worldpay-hosted'),
                        'updatingOrder' => __('Updating order status...', 'worldpay-hosted'),
                        'redirecting' => __('Redirecting to order confirmation page in', 'worldpay-hosted'),
                        'seconds' => __('seconds', 'worldpay-hosted'),
                        'paymentFailed' => __('Payment Failed', 'worldpay-hosted'),
                        'checkInfoRetry' => __('Please check your payment information and try again', 'worldpay-hosted'),
                        'error' => __('Error:', 'worldpay-hosted'),
                        'payFieldsNotInit' => __('PayFields not initialized, please refresh the page', 'worldpay-hosted'),
                        'invalidMerchant' => __('Invalid payment configuration: Missing Merchant ID', 'worldpay-hosted'),
                        'invalidApiKey' => __('Invalid payment configuration: Missing API Key, please check backend settings', 'worldpay-hosted'),
                        'selectPaymentMethod' => __('Select Payment Method', 'worldpay-hosted'),
                        'useNewCard' => __('Use a new card', 'worldpay-hosted'),
                        'continue' => __('Continue', 'worldpay-hosted'),
                        'confirmPayment' => __('Confirm Payment', 'worldpay-hosted'),
                        'payingWith' => __('Paying with saved card', 'worldpay-hosted'),
                        'amount' => __('Amount', 'worldpay-hosted'),
                        'confirmPay' => __('Confirm and Pay', 'worldpay-hosted'),
                        'cancel' => __('Cancel', 'worldpay-hosted'),
                    )
                )
            );
            
            // 添加调试日志
            /*
            error_log("WorldPay Hosted: Localized script data: " . json_encode(array(
                'amount' => $amount,
                'order_id' => $order_id,
                'items' => $items,
                'billing_address' => $billing_address,
                'user_id' => get_current_user_id()
            )));
            */
        }
    }
    
    /**
     * 根据订单ID获取订单金额
     * 
     * @param string $order_id 订单ID
     * @return array 包含货币和金额的数组
     */
    private function get_order_amount($order_id) {
        // 添加调试日志
        // error_log("WorldPay Hosted: Attempting to get amount for order ID: " . var_export($order_id, true));
        
        // 检查是否为WooCommerce订单
        if (!empty($order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            
            if ($order && is_a($order, 'WC_Order')) {
                // 获取订单总金额（保持原始格式，不乘以100）
                $amount_value = $order->get_total();
                
                // 添加调试日志
                // error_log("WorldPay Hosted: Order ID: $order_id, Currency: " . $order->get_currency() . ", Amount: $amount_value");
                
                return array(
                    'currency' => $order->get_currency(),
                    'value' => strval($amount_value)
                );
            } else {
                // error_log("WorldPay Hosted: Failed to get valid order object for order ID: $order_id");
            }
        } else {
            // error_log("WorldPay Hosted: Either order ID is empty or wc_get_order function does not exist. Order ID: " . var_export($order_id, true));
        }
        
        // 默认金额
        // error_log("WorldPay Hosted: Using default amount for order ID: $order_id");
        return array(
            'currency' => 'USD',
            'value' => '60.00'
        );
    }
    
    /**
     * 根据订单ID获取订单商品信息
     * 
     * @param string $order_id 订单ID
     * @return array 商品信息数组
     */
    private function get_order_items($order_id) {
        // 检查是否为WooCommerce订单
        if (!empty($order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            
            if ($order && is_a($order, 'WC_Order')) {
                $items = array();
                
                // 遍历订单中的商品
                foreach ($order->get_items() as $item_id => $item) {
                    $product = $item->get_product();
                    $items[] = array(
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'price' => $item->get_total() / $item->get_quantity(), // 单价
                        'total' => $item->get_total(), // 小计
                        'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : ''
                    );
                }
                
                return $items;
            }
        }
        
        // 默认商品信息
        return array(
            array(
                'name' => 'Classic Woman Bag',
                'quantity' => 1,
                'price' => '60.00',
                'total' => '60.00',
                'image' => 'https://mdn.alipayobjects.com/portal_pdqp4x/afts/file/A*H8M9RrxlArAAAAAAAAAAAAAAAQAAAQ'
            )
        );
    }
    
    /**
     * 根据订单ID获取账单地址信息
     * 
     * @param string $order_id 订单ID
     * @return array 账单地址信息数组
     */
    private function get_billing_address($order_id) {
        // 检查是否为WooCommerce订单
        if (!empty($order_id) && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            
            if ($order && is_a($order, 'WC_Order')) {
                // 获取账单地址
                $billing_address = array(
                    'firstName' => $order->get_billing_first_name(),
                    'lastName' => $order->get_billing_last_name(),
                    'address1' => $order->get_billing_address_1(),
                    'address2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'zip' => $order->get_billing_postcode(),
                    'country' => $order->get_billing_country(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone()
                );
                
                // error_log("WorldPay Hosted: Billing address for order $order_id: " . json_encode($billing_address));
                
                return $billing_address;
            }
        }
        
        // 返回空数组
        return array();
    }
    
    /**
     * 更新订单状态
     * 
     * @param string $order_id 订单ID
     * @param string $status 状态
     */
    private function update_order_status($order_id, $status) {
        // 检查WooCommerce是否激活
        if (!function_exists('wc_get_order')) {
            // error_log("WorldPay Hosted Payment - WooCommerce not active, cannot update order status");
            return;
        }
        
        // error_log("WorldPay Hosted Payment - Updating order status for order ID: " . $order_id . " to status: " . $status);
        
        try {
            $order = wc_get_order($order_id);
            if ($order && is_a($order, 'WC_Order')) {
                $current_status = $order->get_status();
                // error_log("WorldPay Hosted Payment - Current order status: " . $current_status);
                
                // 只有当订单状态为待付款或on-hold时才更新
                if (($current_status === 'pending' || $current_status === 'on-hold') && $status === 'success') {
                    // 更新订单状态为已完成
                    $order->update_status('completed', __('Payment completed via WorldPay Hosted Payment.', 'worldpay-hosted'));
                    // error_log("WorldPay Hosted Payment - Order status updated to completed for order: " . $order_id);
                    
                    // 减少库存
                    if (function_exists('wc_reduce_stock_levels')) {
                        wc_reduce_stock_levels($order_id);
                        // error_log("WorldPay Hosted Payment - Stock reduced for order: " . $order_id);
                    }
                } else {
                    // error_log("WorldPay Hosted Payment - Order not updated. Current status: " . $current_status . ", Target status: " . $status);
                }
            } else {
                // error_log("WorldPay Hosted Payment - Failed to get valid order object for order ID: " . $order_id);
                // error_log("WorldPay Hosted Payment - Order object type: " . (is_object($order) ? get_class($order) : gettype($order)));
            }
        } catch (Exception $e) {
            // error_log("WorldPay Hosted Payment - Exception updating order status: " . $e->getMessage());
            // error_log("Exception trace: " . $e->getTraceAsString());
        }
    }
}