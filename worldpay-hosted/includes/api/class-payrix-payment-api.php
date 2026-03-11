<?php

/**
 * Class PayrixPaymentAPI
 *
 * REST API endpoints for Payrix Payment processing
 */
class PayrixPaymentAPI {
    
    /**
     * Initialize the API endpoints
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get payment configuration endpoint
        register_rest_route('payrix-payment/v1', '/config', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_payment_config'),
            'permission_callback' => '__return_true',
        ));
        
        // Create transaction session endpoint
        register_rest_route('payrix-payment/v1', '/create-session', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_transaction_session'),
            'permission_callback' => '__return_true',
        ));
        
        // Receive webhook notification endpoint
        register_rest_route('payrix-payment/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'receive_webhook'),
            'permission_callback' => '__return_true',
        ));
        
        // Update order status endpoint (for synchronous payment success)
        register_rest_route('payrix-payment/v1', '/update-order', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_order_status'),
            'permission_callback' => '__return_true',
        ));
        
        // Save payment token from PayFields response endpoint
        register_rest_route('payrix-payment/v1', '/save-token-from-response', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_token_from_response'),
            'permission_callback' => '__return_true',
        ));
        
        // Create payment token from transaction endpoint
        register_rest_route('payrix-payment/v1', '/create-token-from-transaction', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_token_from_transaction'),
            'permission_callback' => '__return_true',
        ));
        
        // Get saved tokens endpoint
        register_rest_route('payrix-payment/v1', '/get-tokens', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_saved_tokens'),
            'permission_callback' => '__return_true',
        ));
        
        // Pay with token endpoint
        register_rest_route('payrix-payment/v1', '/pay-with-token', array(
            'methods' => 'POST',
            'callback' => array($this, 'pay_with_token'),
            'permission_callback' => '__return_true',
        ));
        
        // Get customer ID by email endpoint
        register_rest_route('payrix-payment/v1', '/get-customer-id', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_customer_id_by_email'),
            'permission_callback' => '__return_true',
        ));
        
        // Save customer ID from transaction endpoint
        register_rest_route('payrix-payment/v1', '/save-customer-id', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_customer_id_from_transaction'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Get payment configuration callback
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function get_payment_config($request) {
        $data = $request->get_json_params();
        $order_id = isset($data['orderId']) ? sanitize_text_field($data['orderId']) : null;
        $user_id = isset($data['userId']) ? intval($data['userId']) : 0;
        
        // error_log('API - get_payment_config called with order_id=' . ($order_id ?: 'null') . ', user_id=' . $user_id);
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Get payment configuration (pass user_id)
        $response = $payment_service->getPaymentConfig($order_id, $user_id);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 500;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Create transaction session callback
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function create_transaction_session($request) {
        $data = $request->get_json_params();
        
        // Get session configuration if provided
        $config = isset($data['config']) ? $data['config'] : array();
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Create transaction session
        $response = $payment_service->createTransactionSession($config);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 500;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Receive webhook notification callback
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function receive_webhook($request) {
        // Get request data
        $notification_data = $request->get_json_params();
        
        // Log the webhook receipt
        // error_log('Payrix Payment - Webhook received: ' . json_encode($notification_data));
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Process notification
        $response = $payment_service->processNotification($notification_data);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 400;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Update order status synchronously (called from frontend after payment success)
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function update_order_status($request) {
        // Get request data
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['orderId']) || !isset($data['transactionId'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required fields: orderId or transactionId'
            ), 400);
        }
        
        $order_id = sanitize_text_field($data['orderId']);
        $transaction_id = sanitize_text_field($data['transactionId']);
        
        // error_log('Payrix Payment - Synchronous order update requested: Order ID: ' . $order_id . ', Transaction ID: ' . $transaction_id);
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Update order status
        $response = $payment_service->updateOrderStatusSync($order_id, $transaction_id);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 400;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Create payment token from transaction ID
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function create_token_from_transaction($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['userId']) || !isset($data['transactionId'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required fields: userId or transactionId'
            ), 400);
        }
        
        $user_id = intval($data['userId']);
        $transaction_id = sanitize_text_field($data['transactionId']);
        
        // Verify user
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Invalid user ID'
            ), 400);
        }
        
        // error_log('Payrix Payment - Creating token from transaction for user: ' . $user_id);
        // error_log('Transaction ID: ' . $transaction_id);
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Create token from transaction
        $response = $payment_service->createTokenFromTransaction($transaction_id, $user_id);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 400;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Get saved payment tokens for user
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function get_saved_tokens($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['userId'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required field: userId'
            ), 400);
        }
        
        $user_id = intval($data['userId']);
        
        // For non-logged-in users (user_id = 0), return empty tokens array
        if ($user_id <= 0) {
            // error_log('Payrix Payment - Non-logged-in user, returning empty tokens array');
            return new WP_REST_Response(array(
                'status' => 'success',
                'tokens' => array()
            ), 200);
        }
        
        // Verify user
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Invalid user ID'
            ), 400);
        }
        
        // error_log('Payrix Payment - Fetching tokens from Payrix API for user: ' . $user_id);
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Get tokens from Payrix API
        try {
            $tokens = $payment_service->getTokensForUser($user->user_email);
            
            // error_log('Payrix Payment - Retrieved ' . count($tokens) . ' tokens from Payrix API');
            
            // 记录每个 Token 的详细信息
            /*
            foreach ($tokens as $index => $token) {
                error_log('Token ' . $index . ': ID=' . (isset($token['token_id']) ? $token['token_id'] : 'N/A') . 
                         ', Last4=' . (isset($token['last4']) ? $token['last4'] : 'N/A') .
                         ', Brand=' . (isset($token['brand']) ? $token['brand'] : 'N/A'));
            }
            */
            
            return new WP_REST_Response(array(
                'status' => 'success',
                'tokens' => $tokens
            ), 200);
        } catch (Exception $e) {
            // error_log('Payrix Payment - Error getting tokens: ' . $e->getMessage());
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Pay with saved token
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function pay_with_token($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['orderId']) || !isset($data['tokenId'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required fields: orderId or tokenId'
            ), 400);
        }
        
        $order_id = sanitize_text_field($data['orderId']);
        $token_id = sanitize_text_field($data['tokenId']);
        
        // error_log('Payrix Payment - Pay with token: Order ID: ' . $order_id . ', Token ID: ' . $token_id);
        
        // 验证 Token ID 格式
        if (empty($token_id) || !is_string($token_id)) {
            // error_log('Payrix Payment - ERROR: Invalid token ID format');
            // error_log('Token ID: ' . var_export($token_id, true));
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Invalid token ID format'
            ), 400);
        }
        
        // 检查 Token ID 是否以 t1_tok_ 开头
        if (strpos($token_id, 't1_tok_') !== 0) {
            // error_log('Payrix Payment - WARNING: Token ID may have invalid format');
            // error_log('Token ID: ' . $token_id);
        }
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Process payment with token
        $response = $payment_service->payWithToken($order_id, $token_id);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 400;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Get customer ID by email
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function get_customer_id_by_email($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['email'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required field: email'
            ), 400);
        }
        
        $email = sanitize_email($data['email']);
        
        // error_log('Payrix Payment - Get customer ID by email: ' . $email);
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Get customer ID
        try {
            $customer_id = $payment_service->getCustomerIdByEmail($email);
            
            if ($customer_id) {
                return new WP_REST_Response(array(
                    'status' => 'success',
                    'customerId' => $customer_id
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'message' => 'Customer not found'
                ), 404);
            }
        } catch (Exception $e) {
            // error_log('Payrix Payment - Error getting customer ID: ' . $e->getMessage());
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Save payment token from PayFields response
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function save_token_from_response($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['userId']) || !isset($data['tokenData'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required fields: userId or tokenData'
            ), 400);
        }
        
        $user_id = intval($data['userId']);
        $token_data = $data['tokenData'];
        
        // Verify user
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Invalid user ID'
            ), 400);
        }
        
        // error_log('Payrix Payment - Saving token from PayFields response for user: ' . $user_id);
        // error_log('Token data received: ' . json_encode($token_data, JSON_PRETTY_PRINT));
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Save token directly from PayFields data
        $response = $payment_service->saveTokenFromPayFieldsData($token_data, $user_id);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 400;
        return new WP_REST_Response($response, $status_code);
    }
    
    /**
     * Save customer ID from transaction
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response REST response object
     */
    public function save_customer_id_from_transaction($request) {
        $data = $request->get_json_params();
        
        // Validate required fields
        if (!isset($data['transactionId'])) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required field: transactionId'
            ), 400);
        }
        
        $transaction_id = sanitize_text_field($data['transactionId']);
        $user_id = isset($data['userId']) ? intval($data['userId']) : null;
        $user_email = isset($data['userEmail']) ? sanitize_email($data['userEmail']) : null;
        
        // 至少需要 userId 或 userEmail 之一
        if (!$user_id && !$user_email) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Missing required field: userId or userEmail'
            ), 400);
        }
        
        // Initialize payment service
        $payment_service = new PayrixPaymentService();
        
        // Save customer ID from transaction (优先使用 user_id)
        $response = $payment_service->saveCustomerIdFromTransaction($transaction_id, $user_id, $user_email);
        
        // Return response
        $status_code = ($response['status'] === 'success') ? 200 : 400;
        return new WP_REST_Response($response, $status_code);
    }
}
