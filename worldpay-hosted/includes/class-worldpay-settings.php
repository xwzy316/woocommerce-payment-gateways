<?php

class WorldPaySettings {
    
    private $options;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }
    
    public function add_plugin_page() {
        // 添加插件设置页面到WordPress后台
        add_options_page(
            __('WorldPay Hosted Payment Settings', 'worldpay-hosted'), 
            __('WorldPay Hosted', 'worldpay-hosted'), 
            'manage_options', 
            'worldpay-payment-settings', 
            array($this, 'create_admin_page')
        );
    }
    
    public function create_admin_page() {
        // 获取保存的选项
        $this->options = get_option('worldpay_payment_options');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WorldPay Hosted Payment Settings', 'worldpay-hosted'); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields('worldpay_payment_options_group');
                    do_settings_sections('worldpay-payment-settings');
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function page_init() {
        register_setting(
            'worldpay_payment_options_group',
            'worldpay_payment_options',
            array($this, 'sanitize')
        );
        
        add_settings_section(
            'worldpay_payment_setting_section',
            __('Payrix API Credentials', 'worldpay-hosted'),
            array($this, 'print_section_info'),
            'worldpay-payment-settings'
        );
        
        add_settings_field(
            'api_environment',
            __('API Environment', 'worldpay-hosted'),
            array($this, 'api_environment_callback'),
            'worldpay-payment-settings',
            'worldpay_payment_setting_section'
        );
        
        add_settings_field(
            'merchant_id',
            __('Merchant ID', 'worldpay-hosted'),
            array($this, 'merchant_id_callback'),
            'worldpay-payment-settings',
            'worldpay_payment_setting_section'
        );
        
        add_settings_field(
            'api_key',
            __('Public API Key (Frontend)', 'worldpay-hosted'),
            array($this, 'api_key_callback'),
            'worldpay-payment-settings',
            'worldpay_payment_setting_section'
        );
        
        add_settings_field(
            'private_api_key',
            __('Private API Key (Backend)', 'worldpay-hosted'),
            array($this, 'private_api_key_callback'),
            'worldpay-payment-settings',
            'worldpay_payment_setting_section'
        );

        // 添加订单状态设置部分
        add_settings_section(
            'worldpay_order_status_section',
            __('Order Status Settings', 'worldpay-hosted'),
            array($this, 'print_order_status_section_info'),
            'worldpay-payment-settings'
        );
        
        add_settings_field(
            'payment_success_status',
            __('Payment Success Status', 'worldpay-hosted'),
            array($this, 'payment_success_status_callback'),
            'worldpay-payment-settings',
            'worldpay_order_status_section'
        );
        
        add_settings_field(
            'redirect_countdown_seconds',
            __('Redirect Countdown (seconds)', 'worldpay-hosted'),
            array($this, 'redirect_countdown_seconds_callback'),
            'worldpay-payment-settings',
            'worldpay_order_status_section'
        );
        
        // 添加调试设置部分
        add_settings_section(
            'worldpay_debug_section',
            __('Debug Settings', 'worldpay-hosted'),
            array($this, 'print_debug_section_info'),
            'worldpay-payment-settings'
        );
        
        add_settings_field(
            'enable_debug_log',
            __('Enable Debug Log', 'worldpay-hosted'),
            array($this, 'enable_debug_log_callback'),
            'worldpay-payment-settings',
            'worldpay_debug_section'
        );
        
        add_settings_field(
            'clear_customer_cache',
            __('Clear Customer ID Cache', 'worldpay-hosted'),
            array($this, 'clear_customer_cache_callback'),
            'worldpay-payment-settings',
            'worldpay_debug_section'
        );
    }
    
    public function sanitize($input) {
        $new_input = array();
        
        // 处理清理缓存操作
        if (isset($input['clear_customer_cache']) && $input['clear_customer_cache'] === '1') {
            $this->clear_all_customer_cache();
            add_settings_error(
                'worldpay_payment_options',
                'cache_cleared',
                __('Customer ID cache has been cleared for all users.', 'worldpay-hosted'),
                'updated'
            );
        }
        
        if (isset($input['api_environment'])) {
            $new_input['api_environment'] = sanitize_text_field($input['api_environment']);
        }
        
        if (isset($input['merchant_id'])) {
            $new_input['merchant_id'] = sanitize_text_field($input['merchant_id']);
        }
        
        if (isset($input['api_key'])) {
            $new_input['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['private_api_key'])) {
            $new_input['private_api_key'] = sanitize_text_field($input['private_api_key']);
        }
        
        if (isset($input['payment_success_status'])) {
            $new_input['payment_success_status'] = sanitize_text_field($input['payment_success_status']);
        }
        
        if (isset($input['redirect_countdown_seconds'])) {
            $seconds = intval($input['redirect_countdown_seconds']);
            // 限制在1-60秒之间
            $new_input['redirect_countdown_seconds'] = max(1, min(60, $seconds));
        }
        
        if (isset($input['enable_debug_log'])) {
            $new_input['enable_debug_log'] = $input['enable_debug_log'] === '1' ? '1' : '0';
        }
        
        return $new_input;
    }
    
    public function print_section_info() {
        print __('Enter your Payrix (WorldPay Hosted) API credentials below:', 'worldpay-hosted');
    }
    
    public function print_order_status_section_info() {
        print __('Configure the order status updates after payment notification:', 'worldpay-hosted');
    }
    
    public function print_debug_section_info() {
        print __('Enable detailed logging for debugging API requests and responses:', 'worldpay-hosted');
    }
    
    public function api_environment_callback() {
        $selected = isset($this->options['api_environment']) ? esc_attr($this->options['api_environment']) : 'test';
        ?>
        <select id="api_environment" name="worldpay_payment_options[api_environment]">
            <option value="test" <?php selected($selected, 'test'); ?>><?php _e('Test Environment', 'worldpay-hosted'); ?></option>
            <option value="production" <?php selected($selected, 'production'); ?>><?php _e('Production Environment', 'worldpay-hosted'); ?></option>
        </select>
        <p class="description"><?php _e('Select the API environment (Test or Production).', 'worldpay-hosted'); ?></p>
        <?php
    }
    
    public function merchant_id_callback() {
        printf(
            '<input type="text" id="merchant_id" name="worldpay_payment_options[merchant_id]" value="%s" class="regular-text" /><p class="description">%s</p>',
            isset($this->options['merchant_id']) ? esc_attr($this->options['merchant_id']) : '',
            __('Your Payrix Merchant ID (e.g., t1_mer_xxxxx)', 'worldpay-hosted')
        );
    }
    
    public function api_key_callback() {
        printf(
            '<input type="password" id="api_key" name="worldpay_payment_options[api_key]" value="%s" class="regular-text" /><p class="description">%s</p>',
            isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : '',
            __('Your Payrix Public API Key (used for frontend PayFields initialization)', 'worldpay-hosted')
        );
    }
    
    public function private_api_key_callback() {
        printf(
            '<input type="password" id="private_api_key" name="worldpay_payment_options[private_api_key]" value="%s" class="regular-text" /><p class="description">%s</p>',
            isset($this->options['private_api_key']) ? esc_attr($this->options['private_api_key']) : '',
            __('Your Payrix Private API Key (used for backend API calls like creating tokens)', 'worldpay-hosted')
        );
    }

    public function payment_success_status_callback() {
        $selected = isset($this->options['payment_success_status']) ? esc_attr($this->options['payment_success_status']) : 'completed';
        ?>
        <select id="payment_success_status" name="worldpay_payment_options[payment_success_status]">
            <option value="completed" <?php selected($selected, 'completed'); ?>><?php _e('Completed', 'worldpay-hosted'); ?></option>
            <option value="processing" <?php selected($selected, 'processing'); ?>><?php _e('Processing', 'worldpay-hosted'); ?></option>
        </select>
        <p class="description"><?php _e('Choose the order status to set when payment is successfully received.', 'worldpay-hosted'); ?></p>
        <?php
    }
    
    public function redirect_countdown_seconds_callback() {
        $value = isset($this->options['redirect_countdown_seconds']) ? intval($this->options['redirect_countdown_seconds']) : 5;
        ?>
        <input type="number" id="redirect_countdown_seconds" name="worldpay_payment_options[redirect_countdown_seconds]" value="<?php echo $value; ?>" min="1" max="60" class="small-text" />
        <p class="description"><?php _e('Number of seconds to wait before redirecting to order confirmation page after successful payment (1-60 seconds).', 'worldpay-hosted'); ?></p>
        <?php
    }
    
    public function enable_debug_log_callback() {
        $checked = isset($this->options['enable_debug_log']) && $this->options['enable_debug_log'] === '1' ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" id="enable_debug_log" name="worldpay_payment_options[enable_debug_log]" value="1" <?php echo $checked; ?> />
            <?php _e('Enable detailed API request and response logging', 'worldpay-hosted'); ?>
        </label>
        <p class="description"><?php _e('When enabled, all API requests and responses will be logged. Frontend logs will appear in browser console, backend logs in PHP error log.', 'worldpay-hosted'); ?></p>
        <?php
    }
    
    public function clear_customer_cache_callback() {
        ?>
        <label>
            <input type="checkbox" id="clear_customer_cache" name="worldpay_payment_options[clear_customer_cache]" value="1" />
            <?php _e('Clear all cached customer IDs', 'worldpay-hosted'); ?>
        </label>
        <p class="description"><?php _e('Check this box and click "Save Changes" to clear all customer ID cache for all users (both test and production environments).', 'worldpay-hosted'); ?></p>
        <p class="description" style="color: #d63638;"><?php _e('Warning: This will remove all cached customer IDs. Users will need to re-authenticate on their next payment.', 'worldpay-hosted'); ?></p>
        <?php
    }
    
    /**
     * 清理所有用户的 customer ID 缓存
     */
    private function clear_all_customer_cache() {
        global $wpdb;
        
        // 删除所有 payrix_customer_id_test 和 payrix_customer_id_production meta
        $deleted_test = $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'payrix_customer_id_test'"
        );
        
        $deleted_production = $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'payrix_customer_id_production'"
        );
        
        // 记录日志
        error_log('WorldPay Hosted: Cleared customer ID cache - Test: ' . $deleted_test . ' records, Production: ' . $deleted_production . ' records');
        
        return $deleted_test + $deleted_production;
    }
}