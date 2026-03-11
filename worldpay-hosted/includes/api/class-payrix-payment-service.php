<?php

/**
 * Payrix Payment Service for WordPress
 * Provides payment functionality for the WorldPay Hosted checkout page
 */
class PayrixPaymentService {
    
    private $api_base_url;
    private $merchant_id;
    private $api_key;  // Public API Key (for frontend)
    private $private_api_key;  // Private API Key (for backend API calls)
    private $debug_enabled;  // Debug log switch

    public function __construct() {
        // 从插件设置中获取API凭证
        $options = get_option('worldpay_payment_options');
        
        $this->merchant_id = !empty($options['merchant_id']) ? $options['merchant_id'] : '';
        $this->api_key = !empty($options['api_key']) ? $options['api_key'] : '';
        $this->private_api_key = !empty($options['private_api_key']) ? $options['private_api_key'] : '';
        $this->debug_enabled = isset($options['enable_debug_log']) && $options['enable_debug_log'] === '1';
        
        // 根据环境设置API基础URL（使用加拿大域名）
        $environment = !empty($options['api_environment']) ? $options['api_environment'] : 'test';
        $this->api_base_url = ($environment === 'production') 
            ? 'https://api.payrixcanada.com' 
            : 'https://test-api.payrixcanada.com';
        
        // 简化的初始化日志（不再输出详细信息，减少日志冗余）
        // 详细配置在各个方法中按需输出
    }
    
    /**
     * Debug logging helper
     */
    private function debug_log($message) {
        if ($this->debug_enabled) {
            error_log('[WorldPay Debug] ' . $message);
        }
    }

    /**
     * Create transaction session key
     *
     * @param array $config Session configuration
     * @return array API response
     */
    public function createTransactionSession($config = array()) {
        $default_config = array(
            'duration' => 8,
            'maxTimesApproved' => 4,
            'maxTimesUse' => 10
        );
        
        $configurations = wp_parse_args($config, $default_config);
        
        $data = array(
            'merchant' => $this->merchant_id,
            'configurations' => $configurations
        );

        try {
            $response = $this->makeApiRequest('/txnSessions', 'POST', $data);
            
            if (isset($response['response']['data'][0]['key'])) {
                return array(
                    'status' => 'success',
                    'sessionKey' => $response['response']['data'][0]['key'],
                    'sessionId' => $response['response']['data'][0]['id'],
                    'data' => $response
                );
            } else {
                throw new Exception('Failed to retrieve session key from response');
            }
        } catch (Exception $e) {
            // error_log('WorldPay Hosted (Payrix): Error creating transaction session - ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Get merchant and session configuration for frontend
     *
     * @param string $order_id Order ID
     * @return array Configuration data
     */
    public function getPaymentConfig($order_id = null, $user_id = null) {
        $this->debug_log('========== GET PAYMENT CONFIG ==========');
        $this->debug_log('Order ID: ' . ($order_id ?: 'null'));
        $this->debug_log('User ID (passed): ' . ($user_id ?: 'null'));
        
        // 直接返回 API Key（参考官方示例代码）
        $config = array(
            'status' => 'success',
            'merchant' => $this->merchant_id,
            'apiKey' => $this->api_key,
            'environment' => (strpos($this->api_base_url, 'test-api') !== false) ? 'TEST' : 'PRODUCTION',
            'apiBaseUrl' => $this->api_base_url
        );

        // 添加订单相关信息
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order && is_a($order, 'WC_Order')) {
                $config['amount'] = $order->get_total();
                $config['currency'] = $order->get_currency();
                $config['orderId'] = $order_id;
            }
        }
        
        // 如果没有传递user_id，尝试获取当前登录用户
        if ($user_id === null) {
            $user_id = get_current_user_id();
            $this->debug_log('User ID not passed, using current user: ' . $user_id);
        }
        
        $this->debug_log('Final user ID to use: ' . $user_id);
        
        // 优先从订单获取 billing email（checkout 页实际输入的邮箱）
        $billing_email = null;
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order && is_a($order, 'WC_Order')) {
                $billing_email = $order->get_billing_email();
                $this->debug_log('Billing email from order: ' . ($billing_email ?: 'null'));
            }
        }
        
        // 如果没有订单或订单中没有邮箱，才使用用户资料中的邮箱
        if (!$billing_email && $user_id > 0) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                $billing_email = $user->user_email;
                $this->debug_log('Fallback to user profile email: ' . $billing_email);
            }
        }
        
        // 不再在配置阶段创建 customer ID
        // PayFields 会在 txnToken 模式下自动创建 customer（如果设置了 customer 对象）
        if ($billing_email) {
            $this->debug_log('Using email for customer lookup: ' . $billing_email);
            
            // 仅尝试从缓存获取已存在的customer ID（不创建新的）
            $customer_id = $this->getExistingCustomerId($billing_email);
            $this->debug_log('Cached Customer ID: ' . ($customer_id ?: 'null'));
            
            if ($customer_id) {
                $config['existingCustomerId'] = $customer_id;
                $this->debug_log('✓ Existing customer ID added to config: ' . $customer_id);
            } else {
                $this->debug_log('✗ No cached customer ID found (will be auto-created by PayFields)');
            }
        } else {
            $this->debug_log('No email available (user not logged in or no order)');
        }
        
        $this->debug_log('Final config: ' . json_encode($config));
        $this->debug_log('========== END GET PAYMENT CONFIG ==========');

        return $config;
    }
    
    /**
     * 仅获取已存在的 Customer ID（不创建新的）
     *
     * @param string $email 用户邮箱
     * @return string|null Customer ID
     */
    private function getExistingCustomerId($email) {
        try {
            // 获取当前环境标识
            $current_env = (strpos($this->api_base_url, 'test-api') !== false) ? 'test' : 'production';
            
            // 从 user meta 中获取缓存
            $user = get_user_by('email', $email);
            if (!$user) {
                return null;
            }
            
            // 使用环境特定的 meta key
            $meta_key = 'payrix_customer_id_' . $current_env;
            $cached_customer_id = get_user_meta($user->ID, $meta_key, true);
            
            if (!$cached_customer_id) {
                return null;
            }
            
            // 验证缓存的 customer ID 是否匹配当前环境
            $customer_prefix = substr($cached_customer_id, 0, 3); // t1_ 或 p1_
            $expected_prefix = ($current_env === 'test') ? 't1_' : 'p1_';
            
            if ($customer_prefix !== $expected_prefix) {
                // 前缀不匹配，清除缓存
                delete_user_meta($user->ID, $meta_key);
                return null;
            }
            
            return $cached_customer_id;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 获取用户的所有Tokens（从Pay rix API）
     *
     * @param string $email 用户邮箱
     * @return array Tokens数组
     */
    public function getTokensForUser($email) {
        try {
            // error_log('========== GET TOKENS FOR USER ==========');
            // error_log('Email: ' . $email);
            
            // 只查询已存在的customer ID，不创建（避免页面加载时创建 customer）
            $customer_id = $this->getExistingCustomerId($email);
            if (!$customer_id) {
                // error_log('No customer ID found for email: ' . $email);
                // error_log('========== END GET TOKENS ==========');
                return array();
            }
            
            // error_log('!!! Using customer ID: ' . $customer_id . ' for email: ' . $email);
            // error_log('Fetching tokens for customer: ' . $customer_id);
            
            // 查询该customer的所有tokens，按创建时间降序排序，只获取最近10个
            $tokens_endpoint = '/tokens?filter[customer]=' . urlencode($customer_id) . '&embed=payment&sort=-created&page[size]=10';
            $response = $this->makeApiRequest($tokens_endpoint, 'GET', array(), true); // 使用Private Key
            
            // error_log('Tokens API response: ' . json_encode($response, JSON_PRETTY_PRINT));
            
            $tokens_data = array();
            if (isset($response['response']['data'])) {
                $tokens_data = $response['response']['data'];
            } elseif (isset($response['data'])) {
                $tokens_data = $response['data'];
            }
            
            // error_log('API returned ' . count($tokens_data) . ' tokens, filtering by customer ID: ' . $customer_id);
            
            // 格式化tokens，并添加过滤逻辑
            $formatted_tokens = array();
            $seen_last4 = array(); // 用于去重，避免显示多个相同的卡
            
            foreach ($tokens_data as $token) {
                // !!!关键过滤：只保留属于当前customer的tokens
                $token_customer = isset($token['customer']) ? $token['customer'] : '';
                if ($token_customer !== $customer_id) {
                    continue; // 静默跳过不属于当前customer的token
                }
                
                // 跳过inactive或frozen的tokens
                if (isset($token['inactive']) && $token['inactive'] == 1) {
                    continue;
                }
                if (isset($token['frozen']) && $token['frozen'] == 1) {
                    continue;
                }
                
                // 跳过pending状态的tokens
                if (isset($token['status']) && $token['status'] === 'pending') {
                    continue;
                }
                
                $payment_info = isset($token['payment']) ? $token['payment'] : array();
                $last4 = isset($payment_info['number']) ? $payment_info['number'] : '';
                
                // 跳过没有last4的token
                if (empty($last4)) {
                    continue;
                }
                
                // 去重：如果已经有相同last4的卡，跳过
                if (isset($seen_last4[$last4])) {
                    continue;
                }
                
                $seen_last4[$last4] = true;
                
                $formatted_tokens[] = array(
                    'token_id' => $token['id'],
                    'last4' => $last4,
                    'brand' => isset($payment_info['type']) ? $payment_info['type'] : 'card',
                    'exp_month' => '',
                    'exp_year' => '',
                    'cardholder_name' => '',
                    'created_at' => isset($token['created']) ? $token['created'] : ''
                );
                
                // 最多返回3个有效的不同卡片
                if (count($formatted_tokens) >= 3) {
                    break;
                }
            }
            
            // error_log('Formatted ' . count($formatted_tokens) . ' tokens for customer: ' . $customer_id);
            return $formatted_tokens;
            
        } catch (Exception $e) {
            // error_log('Error getting tokens for user: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 获取或创建Payrix Customer ID
     *
     * @param string $email 用户邮箱
     * @param string $first_name 名
     * @param string $last_name 姓
     * @return string|null Customer ID
     */
    private function getOrCreateCustomerId($email, $first_name = '', $last_name = '') {
        try {
            $this->debug_log('========== GET OR CREATE CUSTOMER ==========');
            $this->debug_log('Email: ' . $email);
            
            // 获取当前环境标识
            $current_env = (strpos($this->api_base_url, 'test-api') !== false) ? 'test' : 'production';
            $this->debug_log('Current environment: ' . $current_env);
            
            // 先尝试从 user meta 中获取
            $user = get_user_by('email', $email);
            if ($user) {
                $this->debug_log('WordPress User found: ID=' . $user->ID . ', Email=' . $user->user_email);
                
                // 使用环境特定的 meta key
                $meta_key = 'payrix_customer_id_' . $current_env;
                $cached_customer_id = get_user_meta($user->ID, $meta_key, true);
                
                if ($cached_customer_id) {
                    $this->debug_log('Using CACHED customer ID (' . $current_env . '): ' . $cached_customer_id . ' for user: ' . $user->ID);
                    
                    // 验证缓存的 customer ID 是否匹配当前环境
                    $customer_prefix = substr($cached_customer_id, 0, 3); // t1_ 或 p1_
                    $expected_prefix = ($current_env === 'test') ? 't1_' : 'p1_';
                    
                    if ($customer_prefix === $expected_prefix) {
                        $this->debug_log('✓ Cached customer ID matches current environment');
                        $this->debug_log('========== END GET OR CREATE CUSTOMER ==========');
                        return $cached_customer_id;
                    } else {
                        $this->debug_log('⚠ WARNING: Cached customer ID prefix (' . $customer_prefix . ') does not match expected (' . $expected_prefix . ')');
                        $this->debug_log('Clearing invalid cache and creating new customer...');
                        delete_user_meta($user->ID, $meta_key);
                    }
                } else {
                    $this->debug_log('No cached customer ID for user: ' . $user->ID . ' (env: ' . $current_env . ')');
                }
            } else {
                $this->debug_log('WARNING: WordPress user not found for email: ' . $email);
            }
            
            // 策略：直接尝试创建customer，如果email已存在，Payrix会返回错误，然后我们再查询
            $this->debug_log('Attempting to create new customer directly...');
            
            $customer_data = array(
                'merchant' => $this->merchant_id,
                'email' => $email,
                'first' => $first_name ?: 'Customer',
                'last' => $last_name ?: 'User',
            );
            
            $this->debug_log('Creating customer with data: ' . json_encode($customer_data));
            
            $create_response = $this->makeApiRequest('/customers', 'POST', $customer_data, true);
            $this->debug_log('Create customer response: ' . json_encode($create_response, JSON_PRETTY_PRINT));
            
            // 检查是否创建成功
            $customer_id = null;
            if (isset($create_response['response']['data'][0]['id'])) {
                $customer_id = $create_response['response']['data'][0]['id'];
                $this->debug_log('NEW customer created successfully: ' . $customer_id);
            } elseif (isset($create_response['data'][0]['id'])) {
                $customer_id = $create_response['data'][0]['id'];
                $this->debug_log('NEW customer created successfully: ' . $customer_id);
            }
            
            // 如果创建成功，缓存并返回
            if ($customer_id) {
                if ($user) {
                    $meta_key = 'payrix_customer_id_' . $current_env;
                    update_user_meta($user->ID, $meta_key, $customer_id);
                    $this->debug_log('Cached NEW customer ID to user meta (' . $current_env . '): User ' . $user->ID . ' => Customer ' . $customer_id);
                }
                $this->debug_log('========== END GET OR CREATE CUSTOMER ==========');
                return $customer_id;
            }
            
            // 创建失败，检查是否是因为email已存在
            $errors = array();
            if (isset($create_response['response']['errors'])) {
                $errors = $create_response['response']['errors'];
            } elseif (isset($create_response['errors'])) {
                $errors = $create_response['errors'];
            }
            
            $this->debug_log('Customer creation failed, errors: ' . json_encode($errors));
            
            // 如果是email已存在的错误，则查询所有customers并手动匹配
            $email_exists = false;
            foreach ($errors as $error) {
                if (isset($error['field']) && $error['field'] === 'email') {
                    $email_exists = true;
                    $this->debug_log('Email already exists in Payrix, will search for existing customer');
                    break;
                }
            }
            
            if ($email_exists) {
                // 获取所有customers（不使用filter，因为filter不可靠）
                $this->debug_log('Fetching ALL customers to find matching email...');
                $all_customers_response = $this->makeApiRequest('/customers?page[size]=100', 'GET', array(), true);
                
                $all_customers = array();
                if (isset($all_customers_response['response']['data'])) {
                    $all_customers = $all_customers_response['response']['data'];
                } elseif (isset($all_customers_response['data'])) {
                    $all_customers = $all_customers_response['data'];
                }
                
                $this->debug_log('Retrieved ' . count($all_customers) . ' customers, searching for email match...');
                
                // 遍历所有customers，找到email匹配的
                foreach ($all_customers as $cust) {
                    $cust_email = isset($cust['email']) ? $cust['email'] : '';
                    if (!empty($cust_email) && strtolower($cust_email) === strtolower($email)) {
                        $customer_id = $cust['id'];
                        $this->debug_log('✓ Found existing customer with matching email: ' . $customer_id);
                        
                        // 缓存
                        if ($user) {
                            $meta_key = 'payrix_customer_id_' . $current_env;
                            update_user_meta($user->ID, $meta_key, $customer_id);
                            $this->debug_log('Cached customer ID to user meta (' . $current_env . '): User ' . $user->ID . ' => Customer ' . $customer_id);
                        }
                        $this->debug_log('========== END GET OR CREATE CUSTOMER ==========');
                        return $customer_id;
                    }
                }
                
                $this->debug_log('ERROR: Email exists error but could not find matching customer!');
            }
            
            $this->debug_log('ERROR: Failed to create or find customer');
            $this->debug_log('========== END GET OR CREATE CUSTOMER ==========');
            return null;
            
        } catch (Exception $e) {
            $this->debug_log('Error getting/creating customer ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 从交易响应中保存 customer ID
     * 在支付成功后调用，从交易数据中提取并缓存 customer ID
     *
     * @param string $transaction_id 交易ID
     * @param int $user_id 用户ID
     * @param string $user_email 用户邮箱（后备）
     * @return array Response data
     */
    public function saveCustomerIdFromTransaction($transaction_id, $user_id = null, $user_email = null) {
        try {
            $this->debug_log('========== SAVE CUSTOMER ID FROM TRANSACTION ==========');
            $this->debug_log('Transaction ID: ' . $transaction_id);
            $this->debug_log('User ID: ' . ($user_id ?: 'null'));
            $this->debug_log('User Email: ' . ($user_email ?: 'null'));
            
            // 获取交易详情
            $txn_response = $this->makeApiRequest('/txns/' . $transaction_id . '?embed=token', 'GET', array(), true);
            $this->debug_log('Transaction response: ' . json_encode($txn_response, JSON_PRETTY_PRINT));
            
            $txn_data = null;
            if (isset($txn_response['response']['data'][0])) {
                $txn_data = $txn_response['response']['data'][0];
            } elseif (isset($txn_response['data'][0])) {
                $txn_data = $txn_response['data'][0];
            }
            
            if (!$txn_data) {
                throw new Exception('Transaction data not found');
            }
            
            // 从交易数据中提取 customer ID
            $customer_id = null;
            
            // 方法1: 直接从交易的 customer 字段获取
            if (isset($txn_data['customer']) && !empty($txn_data['customer'])) {
                $customer_id = $txn_data['customer'];
                $this->debug_log('Found customer ID from txn.customer: ' . $customer_id);
            }
            // 方法2: 从嵌入的 token 数据中获取
            elseif (isset($txn_data['token']) && is_array($txn_data['token']) && isset($txn_data['token']['customer'])) {
                $customer_id = $txn_data['token']['customer'];
                $this->debug_log('Found customer ID from txn.token.customer: ' . $customer_id);
            }
            
            if (!$customer_id) {
                $this->debug_log('WARNING: Customer ID not found in transaction data');
                return array(
                    'status' => 'error',
                    'message' => 'Customer ID not found in transaction'
                );
            }
            
            // 获取当前环境
            $current_env = (strpos($this->api_base_url, 'test-api') !== false) ? 'test' : 'production';
            $this->debug_log('Current environment: ' . $current_env);
            
            // 验证 customer ID 前缀是否匹配环境
            $customer_prefix = substr($customer_id, 0, 3);
            $expected_prefix = ($current_env === 'test') ? 't1_' : 'p1_';
            
            if ($customer_prefix !== $expected_prefix) {
                $this->debug_log('ERROR: Customer ID prefix (' . $customer_prefix . ') does not match environment (' . $expected_prefix . ')');
                return array(
                    'status' => 'error',
                    'message' => 'Customer ID environment mismatch'
                );
            }
            
            // 优先使用 user_id 获取用户，如果没有再尝试用 email
            $user = null;
            if ($user_id && $user_id > 0) {
                $user = get_userdata($user_id);
                $this->debug_log('Lookup user by ID ' . $user_id . ': ' . ($user ? 'Found' : 'Not found'));
            }
            
            // 如果通过 ID 没找到，尝试用 email
            if (!$user && $user_email) {
                $user = get_user_by('email', $user_email);
                $this->debug_log('Fallback: Lookup user by email ' . $user_email . ': ' . ($user ? 'Found' : 'Not found'));
            }
            
            if (!$user) {
                $this->debug_log('WARNING: WordPress user not found');
                return array(
                    'status' => 'error',
                    'message' => 'User not found'
                );
            }
            
            $meta_key = 'payrix_customer_id_' . $current_env;
            $existing_customer_id = get_user_meta($user->ID, $meta_key, true);
            
            if ($existing_customer_id && $existing_customer_id === $customer_id) {
                $this->debug_log('Customer ID already cached: ' . $customer_id);
            } else {
                update_user_meta($user->ID, $meta_key, $customer_id);
                $this->debug_log('✓ Cached customer ID: User ' . $user->ID . ' => Customer ' . $customer_id . ' (env: ' . $current_env . ')');
            }
            
            $this->debug_log('========== END SAVE CUSTOMER ID ==========');
            
            return array(
                'status' => 'success',
                'customer_id' => $customer_id,
                'environment' => $current_env
            );
            
        } catch (Exception $e) {
            $this->debug_log('Error saving customer ID: ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Process payment notification from Payrix
     *
     * @param array $notification_data Notification data
     * @return array Response data
     */
    public function processNotification($notification_data) {
        // error_log('WorldPay Hosted (Payrix) Payment - Notification received: ' . json_encode($notification_data));

        try {
            // 验证通知数据
            if (!isset($notification_data['id']) || !isset($notification_data['status'])) {
                throw new Exception('Invalid notification data');
            }

            $transaction_id = $notification_data['id'];
            $status = $notification_data['status'];

            // error_log('WorldPay Hosted (Payrix) Payment - Transaction ID: ' . $transaction_id . ', Status: ' . $status);

            // 检查支付是否成功
            if ($status === 'approved' || $status === 'settled') {
                // 从 customFields 或 referenceOrderId 中获取订单ID
                $order_id = null;
                if (isset($notification_data['customFields']['order_id'])) {
                    $order_id = $notification_data['customFields']['order_id'];
                } elseif (isset($notification_data['merchant_reference'])) {
                    $order_id = $notification_data['merchant_reference'];
                }

                if ($order_id) {
                    $this->updateOrderStatus($order_id, 'success', $transaction_id);
                }

                return array(
                    'status' => 'success',
                    'message' => 'Payment notification processed successfully'
                );
            } else {
                // error_log('WorldPay Hosted (Payrix) Payment - Payment not approved. Status: ' . $status);
                return array(
                    'status' => 'pending',
                    'message' => 'Payment status: ' . $status
                );
            }
        } catch (Exception $e) {
            // error_log('WorldPay Hosted (Payrix) Payment - Error processing notification: ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update WooCommerce order status synchronously (called from frontend)
     *
     * @param string $order_id Order ID
     * @param string $transaction_id Transaction ID
     * @return array Response data
     */
    public function updateOrderStatusSync($order_id, $transaction_id) {
        // error_log('WorldPay Hosted (Payrix) Payment - Sync update order: ' . $order_id . ', Transaction: ' . $transaction_id);

        if (!function_exists('wc_get_order')) {
            // error_log('WorldPay Hosted (Payrix) Payment - WooCommerce not active');
            return array(
                'status' => 'error',
                'message' => 'WooCommerce not active'
            );
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order || !is_a($order, 'WC_Order')) {
                // error_log('WorldPay Hosted (Payrix) Payment - Invalid order ID: ' . $order_id);
                return array(
                    'status' => 'error',
                    'message' => 'Invalid order ID'
                );
            }

            $current_status = $order->get_status();
            // error_log('WorldPay Hosted (Payrix) Payment - Current order status: ' . $current_status);

            // 只有当订单状态为待付款或on-hold时才更新
            if ($current_status === 'pending' || $current_status === 'on-hold') {
                // 获取设置的订单状态
                $options = get_option('worldpay_payment_options');
                $target_status = isset($options['payment_success_status']) ? $options['payment_success_status'] : 'completed';

                // 添加交易ID到订单
                if ($transaction_id) {
                    $order->set_transaction_id($transaction_id);
                }

                // 更新订单状态
                $order->update_status($target_status, __('Payment completed via WorldPay Hosted (Payrix) - Synchronous update.', 'worldpay-hosted'));
                // error_log('WorldPay Hosted (Payrix) Payment - Order status updated to ' . $target_status . ' for order: ' . $order_id);

                // 减少库存
                if (function_exists('wc_reduce_stock_levels')) {
                    wc_reduce_stock_levels($order_id);
                    // error_log('WorldPay Hosted (Payrix) Payment - Stock reduced for order: ' . $order_id);
                }

                return array(
                    'status' => 'success',
                    'message' => 'Order status updated successfully',
                    'order_status' => $target_status
                );
            } else {
                // error_log('WorldPay Hosted (Payrix) Payment - Order status not updated. Current status: ' . $current_status);
                return array(
                    'status' => 'skipped',
                    'message' => 'Order status is not pending or on-hold',
                    'current_status' => $current_status
                );
            }
        } catch (Exception $e) {
            // error_log('WorldPay Hosted (Payrix) Payment - Error updating order status: ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Update WooCommerce order status
     *
     * @param string $order_id Order ID
     * @param string $status Payment status
     * @param string $transaction_id Transaction ID
     */
    private function updateOrderStatus($order_id, $status, $transaction_id = '') {
        if (!function_exists('wc_get_order')) {
            // error_log('WorldPay Hosted (Payrix) Payment - WooCommerce not active');
            return;
        }

        try {
            $order = wc_get_order($order_id);
            if ($order && is_a($order, 'WC_Order')) {
                $current_status = $order->get_status();
                // error_log('WorldPay Hosted (Payrix) Payment - Current order status: ' . $current_status);

                if (($current_status === 'pending' || $current_status === 'on-hold') && $status === 'success') {
                    // 获取设置的订单状态
                    $options = get_option('worldpay_payment_options');
                    $target_status = isset($options['payment_success_status']) ? $options['payment_success_status'] : 'completed';

                    // 添加交易ID到订单
                    if ($transaction_id) {
                        $order->set_transaction_id($transaction_id);
                    }

                    // 更新订单状态
                    $order->update_status($target_status, __('Payment completed via WorldPay Hosted (Payrix).', 'worldpay-hosted'));
                    // error_log('WorldPay Hosted (Payrix) Payment - Order status updated to ' . $target_status . ' for order: ' . $order_id);

                    // 减少库存
                    if (function_exists('wc_reduce_stock_levels')) {
                        wc_reduce_stock_levels($order_id);
                        // error_log('WorldPay Hosted (Payrix) Payment - Stock reduced for order: ' . $order_id);
                    }
                }
            }
        } catch (Exception $e) {
            // error_log('WorldPay Hosted (Payrix) Payment - Error updating order status: ' . $e->getMessage());
        }
    }

    /**
     * Make API request to Payrix
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param bool $use_private_key Whether to use private API key
     * @return array Response data
     */
    private function makeApiRequest($endpoint, $method = 'GET', $data = array(), $use_private_key = false) {
        $url = $this->api_base_url . $endpoint;
        
        // 选择使用哪个API Key
        $api_key = $use_private_key ? $this->private_api_key : $this->api_key;
        
        if (empty($api_key)) {
            $key_type = $use_private_key ? 'Private' : 'Public';
            $this->debug_log('ERROR: ' . $key_type . ' API Key is not configured');
            throw new Exception($key_type . ' API Key is not configured');
        }

        $args = array(
            'method' => $method,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'APIKEY' => $api_key
            ),
            'timeout' => 30
        );

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        $this->debug_log('========== API REQUEST ==========');
        $this->debug_log('URL: ' . $url);
        $this->debug_log('Method: ' . $method);
        $this->debug_log('Using: ' . ($use_private_key ? 'Private' : 'Public') . ' Key');
        if ($method === 'POST' && !empty($data)) {
            $this->debug_log('Request Data: ' . json_encode($data, JSON_PRETTY_PRINT));
        }
        
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->debug_log('ERROR: ' . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        $this->debug_log('Response Status: ' . wp_remote_retrieve_response_code($response));
        $this->debug_log('Response Body: ' . $body);
        $this->debug_log('========== END API REQUEST ==========');

        return $decoded;
    }

    /**
     * 从交易创建Token（使用Private Key）
     *
     * @param string $transaction_id 交易ID
     * @param int $user_id 用户ID
     * @return array 响应数据
     */
    public function createTokenFromTransaction($transaction_id, $user_id) {
        // error_log('');
        // error_log('================================================================================');
        // error_log('PayrixPaymentService - CREATE TOKEN FROM TRANSACTION');
        // error_log('================================================================================');
        // error_log('Transaction ID: ' . $transaction_id);
        // error_log('User ID: ' . $user_id);
        // error_log('API Base URL: ' . $this->api_base_url);
        // error_log('Merchant ID: ' . $this->merchant_id);
        
        try {
            // Step 1: 获取交易详情（使用Private Key）
            // error_log('');
            // error_log('STEP 1: Fetching transaction details...');
            // error_log('API Request: GET /txns/' . $transaction_id);
            
            $transaction_response = $this->makeApiRequest('/txns/' . $transaction_id, 'GET', array(), true);
            // error_log('Transaction API Raw Response:');
            // error_log(json_encode($transaction_response, JSON_PRETTY_PRINT));
            
            // 检查响应结构
            if (!isset($transaction_response['response']) && !isset($transaction_response['data'])) {
                // error_log('ERROR: Invalid API response structure - missing both "response" and "data" keys');
                // error_log('Available keys: ' . json_encode(array_keys($transaction_response)));
                throw new Exception('Invalid API response structure: missing required keys');
            }
            
            // 提取交易数据
            $transaction_data = null;
            if (isset($transaction_response['response']['data'][0])) {
                $transaction_data = $transaction_response['response']['data'][0];
            } elseif (isset($transaction_response['data'][0])) {
                $transaction_data = $transaction_response['data'][0];
            }
            
            if (!$transaction_data) {
                // error_log('ERROR: Transaction data not found in response');
                throw new Exception('Transaction data not found');
            }
            
            // error_log('SUCCESS: Transaction data extracted');
            
            // 从交易中提取支付信息（卡片信息）
            $card_info = array(
                'number' => isset($transaction_data['payment']) ? substr($transaction_data['payment'], -4) : '',
                'expiration' => isset($transaction_data['expiration']) ? $transaction_data['expiration'] : '',
                'first' => isset($transaction_data['first']) ? $transaction_data['first'] : '',
                'last' => isset($transaction_data['last']) ? $transaction_data['last'] : '',
            );
            
            // error_log('Card info from transaction: ' . json_encode($card_info));
            
            // Step 2: 直接创建Token（使用交易中的payment引用）
            // error_log('');
            // error_log('STEP 2: Creating token from transaction...');
            
            // 创建Token时需要提供customer信息
            $token_data = array(
                'merchant' => $this->merchant_id,
                'txn' => $transaction_id,  // 使用交易ID
                'customer' => array(
                    'first' => $card_info['first'],
                    'last' => $card_info['last'],
                )
            );
            
            // error_log('Token creation data: ' . json_encode($token_data));
            // error_log('API Request: POST /tokens');
            
            $token_response = $this->makeApiRequest('/tokens', 'POST', $token_data, true);
            // error_log('Token API Raw Response:');
            // error_log(json_encode($token_response, JSON_PRETTY_PRINT));
            
            // 提取token信息
            $token_info = null;
            if (isset($token_response['response']['data'][0])) {
                $token_info = $token_response['response']['data'][0];
            } elseif (isset($token_response['data'][0])) {
                $token_info = $token_response['data'][0];
            }
            
            if (!$token_info || !isset($token_info['id'])) {
                // error_log('ERROR: Token not created or invalid response');
                throw new Exception('Failed to create token');
            }
            
            // error_log('SUCCESS: Token created: ' . $token_info['id']);
            // error_log('Token status: ' . (isset($token_info['status']) ? $token_info['status'] : 'unknown'));
            
            // 如果Token是pending状态，等待并轮询直到active
            $token_status = isset($token_info['status']) ? $token_info['status'] : 'unknown';
            $max_attempts = 5;
            $attempt = 0;
            
            while ($token_status === 'pending' && $attempt < $max_attempts) {
                $attempt++;
                // error_log('Token is pending, waiting 2 seconds... (attempt ' . $attempt . '/' . $max_attempts . ')');
                sleep(2);
                
                // 重新获取Token状态
                $token_status_response = $this->makeApiRequest('/tokens/' . $token_info['id'], 'GET', array(), true);
                if (isset($token_status_response['response']['data'][0]['status'])) {
                    $token_status = $token_status_response['response']['data'][0]['status'];
                } elseif (isset($token_status_response['data'][0]['status'])) {
                    $token_status = $token_status_response['data'][0]['status'];
                }
                
                // error_log('Current token status: ' . $token_status);
                
                if ($token_status !== 'pending') {
                    break;
                }
            }
            
            if ($token_status === 'pending') {
                // error_log('WARNING: Token is still pending after ' . $max_attempts . ' attempts');
            } else {
                // error_log('Token status is now: ' . $token_status);
            }
            
            // Step 3: 获取payment详情以获取真实的卡片信息
            // error_log('');
            // error_log('STEP 3: Fetching payment details for card info...');
            
            // 使用payment引用ID通过filter查询
            $payment_ref = isset($transaction_data['payment']) ? $transaction_data['payment'] : '';
            if ($payment_ref) {
                // error_log('Payment reference: ' . $payment_ref);
                
                // 使用filter参数查询payment
                $payment_endpoint = '/payments?filter[reference]=' . urlencode($payment_ref);
                // error_log('API Request: GET ' . $payment_endpoint);
                
                $payment_response = $this->makeApiRequest($payment_endpoint, 'GET', array(), true);
                // error_log('Payment API Raw Response:');
                // error_log(json_encode($payment_response, JSON_PRETTY_PRINT));
                
                // 提取payment信息
                $payment_info = null;
                if (isset($payment_response['response']['data'][0])) {
                    $payment_info = $payment_response['response']['data'][0];
                } elseif (isset($payment_response['data'][0])) {
                    $payment_info = $payment_response['data'][0];
                }
                
                if ($payment_info) {
                    // error_log('SUCCESS: Payment info retrieved');
                    // error_log('Payment details: ' . json_encode($payment_info));
                    
                    // 更新卡片信息
                    if (isset($payment_info['number'])) {
                        $card_info['number'] = $payment_info['number'];
                    }
                    if (isset($payment_info['type'])) {
                        $card_info['type'] = $payment_info['type'];
                    }
                } else {
                    // error_log('WARNING: Payment info not found, will use transaction data');
                }
            }
            
            // Step 4: 保存Token到user meta
            // error_log('');
            // error_log('STEP 4: Saving token to user meta...');
            
            $saved_tokens = get_user_meta($user_id, 'payrix_payment_tokens', true);
            if (!is_array($saved_tokens)) {
                $saved_tokens = array();
            }
            
            // 准备保存的token数据
            $token_to_save = array(
                'token_id' => $token_info['id'],
                'last4' => isset($card_info['number']) ? substr($card_info['number'], -4) : '',
                'brand' => isset($card_info['type']) ? $card_info['type'] : 'card',
                'exp_month' => isset($card_info['expiration']) ? substr($card_info['expiration'], 0, 2) : '',
                'exp_year' => isset($card_info['expiration']) ? substr($card_info['expiration'], 2, 2) : '',
                'cardholder_name' => isset($card_info['firstname']) && isset($card_info['lastname']) 
                    ? $card_info['firstname'] . ' ' . $card_info['lastname'] 
                    : '',
                'created_at' => current_time('mysql')
            );
            
            // error_log('Token data to save: ' . json_encode($token_to_save));
            
            // 检查是否已存在相同token
            $token_exists = false;
            foreach ($saved_tokens as $existing_token) {
                if (isset($existing_token['token_id']) && $existing_token['token_id'] === $token_to_save['token_id']) {
                    $token_exists = true;
                    // error_log('Token already exists, skipping save');
                    break;
                }
            }
            
            if (!$token_exists) {
                $saved_tokens[] = $token_to_save;
                update_user_meta($user_id, 'payrix_payment_tokens', $saved_tokens);
                // error_log('SUCCESS: Token saved to user meta for user: ' . $user_id);
                // error_log('Total saved tokens: ' . count($saved_tokens));
            }
            
            // error_log('');
            // error_log('================================================================================');
            // error_log('TOKEN CREATION COMPLETED SUCCESSFULLY');
            // error_log('================================================================================');
            // error_log('');
            
            return array(
                'status' => 'success',
                'message' => 'Token created and saved successfully',
                'token_id' => $token_info['id']
            );
            
        } catch (Exception $e) {
            // error_log('');
            // error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // error_log('ERROR CREATING TOKEN');
            // error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // error_log('Error message: ' . $e->getMessage());
            // error_log('Error trace: ' . $e->getTraceAsString());
            // error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // error_log('');
            
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * 使用Token进行支付
     *
     * @param string $order_id 订单ID
     * @param string $token_id Token ID
     * @return array 响应数据
     */
    public function payWithToken($order_id, $token_id) {
        // error_log('PayrixPaymentService - Pay with token');
        // error_log('Order ID: ' . $order_id);
        // error_log('Token ID: ' . $token_id);
        
        try {
            // 首先验证Token是否有效
            // error_log('Validating token...');
            $token_check = $this->makeApiRequest('/tokens/' . $token_id, 'GET', array(), true);
            // error_log('Token validation response: ' . json_encode($token_check, JSON_PRETTY_PRINT));
            
            $token_data = null;
            if (isset($token_check['response']['data'][0])) {
                $token_data = $token_check['response']['data'][0];
            } elseif (isset($token_check['data'][0])) {
                $token_data = $token_check['data'][0];
            }
            
            if (!$token_data) {
                throw new Exception('Token not found: ' . $token_id);
            }
            
            $token_status = isset($token_data['status']) ? $token_data['status'] : 'unknown';
            // error_log('Token status: ' . $token_status);
            
            // 检查是否为pending状态
            if ($token_status === 'pending') {
                throw new Exception('Token is still pending activation. Please wait a moment and try again.');
            }
            
            // 检查是否为inactive或frozen
            if (isset($token_data['inactive']) && $token_data['inactive'] == 1) {
                throw new Exception('Token is inactive and cannot be used for payment');
            }
            
            if (isset($token_data['frozen']) && $token_data['frozen'] == 1) {
                throw new Exception('Token is frozen and cannot be used for payment');
            }
            
            // error_log('Token is valid and ready for payment');
            
            // DEBUG: 检查token的merchant
            // error_log('DEBUG - Token data analysis:');
            // error_log('  Token ID: ' . $token_data['id']);
            // error_log('  Token customer: ' . (isset($token_data['customer']) ? $token_data['customer'] : 'N/A'));
            // error_log('  Token created: ' . (isset($token_data['created']) ? $token_data['created'] : 'N/A'));
            // error_log('  Token origin: ' . (isset($token_data['origin']) ? $token_data['origin'] : 'N/A'));
            // error_log('  Current merchant ID: ' . $this->merchant_id);
            
            // 获取customer信息以确认merchant
            if (isset($token_data['customer'])) {
                $customer_id = $token_data['customer'];
                // error_log('Fetching customer data to verify merchant: ' . $customer_id);
                $customer_response = $this->makeApiRequest('/customers/' . $customer_id, 'GET', array(), true);
                
                if (isset($customer_response['response']['data'][0]['merchant'])) {
                    $token_merchant = $customer_response['response']['data'][0]['merchant'];
                    // error_log('Token merchant (from customer): ' . $token_merchant);
                    // error_log('Current config merchant: ' . $this->merchant_id);
                    
                    if ($token_merchant !== $this->merchant_id) {
                        // error_log('ERROR: Merchant mismatch! Token belongs to ' . $token_merchant . ' but trying to use with ' . $this->merchant_id);
                        throw new Exception('Token belongs to a different merchant account');
                    }
                } elseif (isset($customer_response['data'][0]['merchant'])) {
                    $token_merchant = $customer_response['data'][0]['merchant'];
                    // error_log('Token merchant (from customer): ' . $token_merchant);
                    // error_log('Current config merchant: ' . $this->merchant_id);
                    
                    if ($token_merchant !== $this->merchant_id) {
                        // error_log('ERROR: Merchant mismatch! Token belongs to ' . $token_merchant . ' but trying to use with ' . $this->merchant_id);
                        throw new Exception('Token belongs to a different merchant account');
                    }
                }
            }
            
            // 获取订单信息
            if (!function_exists('wc_get_order')) {
                throw new Exception('WooCommerce not active');
            }
            
            $order = wc_get_order($order_id);
            if (!$order || !is_a($order, 'WC_Order')) {
                throw new Exception('Invalid order ID');
            }
            
            // 准备交易数据
            $amount = $order->get_total();
            $amount_in_cents = intval($amount * 100);
            
            // !!!关键：使用token的hash值，而不是token ID
            $token_hash = isset($token_data['token']) ? $token_data['token'] : null;
            if (!$token_hash) {
                // error_log('ERROR: Token hash not found in token data');
                throw new Exception('Invalid token data: missing token hash');
            }
            
            // error_log('Using token hash for payment: ' . $token_hash);
            
            $transaction_data = array(
                'merchant' => $this->merchant_id,
                'token' => $token_hash,  // 使用hash值，不是ID
                'total' => $amount_in_cents,
                'type' => 1, // Sale
                'origin' => 8, // API/Ecommerce origin (required)
            );
            
            // error_log('Transaction data: ' . json_encode($transaction_data));
            
            // 创建交易（使用Private Key）
            $response = $this->makeApiRequest('/txns', 'POST', $transaction_data, true);
            // error_log('Transaction response: ' . json_encode($response));
            
            // 检查响应
            $transaction_id = null;
            if (isset($response['response']['data'][0]['id'])) {
                $transaction_id = $response['response']['data'][0]['id'];
            } elseif (isset($response['data'][0]['id'])) {
                $transaction_id = $response['data'][0]['id'];
            }
            
            if (!$transaction_id) {
                // 检查是否有错误
                if (isset($response['response']['errors']) && !empty($response['response']['errors'])) {
                    $error_msg = $response['response']['errors'][0]['msg'] ?? 'Unknown error';
                    throw new Exception('Payment failed: ' . $error_msg);
                } elseif (isset($response['errors']) && !empty($response['errors'])) {
                    $error_msg = $response['errors'][0]['msg'] ?? 'Unknown error';
                    throw new Exception('Payment failed: ' . $error_msg);
                }
                throw new Exception('Failed to create transaction');
            }
            
            // error_log('Transaction created successfully: ' . $transaction_id);
            
            return array(
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'transactionId' => $transaction_id
            );
            
        } catch (Exception $e) {
            // error_log('ERROR paying with token: ' . $e->getMessage());
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * 通过邮箱获取客户ID
     *
     * @param string $email 邮箱地址
     * @return string|null 客户ID
     */
    public function getCustomerIdByEmail($email) {
        // error_log('PayrixPaymentService - Get customer ID by email: ' . $email);
        
        try {
            // 搜索客户
            $response = $this->makeApiRequest('/customers?email=' . urlencode($email), 'GET');
            
            // 提取客户ID
            if (isset($response['response']['data'][0]['id'])) {
                return $response['response']['data'][0]['id'];
            } elseif (isset($response['data'][0]['id'])) {
                return $response['data'][0]['id'];
            }
            
            return null;
            
        } catch (Exception $e) {
            // error_log('ERROR getting customer ID: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 直接保存PayFields返回的Token数据（无需Private Key）
     *
     * @param array $token_data Token数据
     * @param int $user_id 用户ID
     * @return array 响应数据
     */
    public function saveTokenFromPayFieldsData($token_data, $user_id) {
        // error_log('');
        // error_log('================================================================================');
        // error_log('PayrixPaymentService - SAVE TOKEN FROM PAYFIELDS DATA');
        // error_log('================================================================================');
        // error_log('User ID: ' . $user_id);
        // error_log('Token data: ' . json_encode($token_data, JSON_PRETTY_PRINT));
        
        try {
            // 验证必要字段
            if (!isset($token_data['token_id']) || empty($token_data['token_id'])) {
                throw new Exception('Missing token_id in token data');
            }
            
            // 获取已保存的tokens
            $saved_tokens = get_user_meta($user_id, 'payrix_payment_tokens', true);
            if (!is_array($saved_tokens)) {
                $saved_tokens = array();
            }
            
            // error_log('Current saved tokens count: ' . count($saved_tokens));
            
            // 准备保存的token数据
            $token_to_save = array(
                'token_id' => $token_data['token_id'],
                'last4' => isset($token_data['last4']) ? $token_data['last4'] : '',
                'brand' => isset($token_data['brand']) ? $token_data['brand'] : 'card',
                'exp_month' => isset($token_data['exp_month']) ? $token_data['exp_month'] : '',
                'exp_year' => isset($token_data['exp_year']) ? $token_data['exp_year'] : '',
                'cardholder_name' => isset($token_data['cardholder_name']) ? trim($token_data['cardholder_name']) : '',
                'created_at' => current_time('mysql')
            );
            
            // error_log('Token to save: ' . json_encode($token_to_save, JSON_PRETTY_PRINT));
            
            // 检查是否已存在相同token
            $token_exists = false;
            foreach ($saved_tokens as $existing_token) {
                if (isset($existing_token['token_id']) && $existing_token['token_id'] === $token_to_save['token_id']) {
                    $token_exists = true;
                    // error_log('Token already exists, skipping save');
                    break;
                }
            }
            
            if (!$token_exists) {
                $saved_tokens[] = $token_to_save;
                $update_result = update_user_meta($user_id, 'payrix_payment_tokens', $saved_tokens);
                
                if ($update_result === false) {
                    // error_log('WARNING: update_user_meta returned false');
                } else {
                    // error_log('SUCCESS: Token saved to user meta for user: ' . $user_id);
                    // error_log('Total saved tokens: ' . count($saved_tokens));
                }
            }
            
            // 如果 token_data 中包含 customer_id，同时保存 customer ID
            if (isset($token_data['customer_id']) && !empty($token_data['customer_id'])) {
                $customer_id = $token_data['customer_id'];
                $current_env = (strpos($this->api_base_url, 'test-api') !== false) ? 'test' : 'production';
                $meta_key = 'payrix_customer_id_' . $current_env;
                
                // error_log('Saving customer ID to user meta: ' . $customer_id);
                update_user_meta($user_id, $meta_key, $customer_id);
                // error_log('Customer ID saved successfully');
            }
            
            // error_log('================================================================================');
            // error_log('TOKEN SAVE COMPLETED');
            // error_log('================================================================================');
            // error_log('');
            
            return array(
                'status' => 'success',
                'message' => 'Token saved successfully',
                'token_id' => $token_to_save['token_id']
            );
            
        } catch (Exception $e) {
            // error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // error_log('ERROR SAVING TOKEN FROM PAYFIELDS DATA');
            // error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // error_log('Error message: ' . $e->getMessage());
            // error_log('Error trace: ' . $e->getTraceAsString());
            // error_log('!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');
            // error_log('');
            
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }
}
