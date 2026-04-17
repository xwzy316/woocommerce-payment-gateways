<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Novam_Settings {
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        // This page will be under "Settings"
        add_options_page(
            __('Novam Payments Settings', 'woocommerce-payment-novam'),
            __('Novam Payments', 'woocommerce-payment-novam'),
            'manage_options',
            'novam-payments-settings',
            array($this, 'create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        // Set class property
        $this->options = get_option('novam_payments_settings');
        ?>
        <div class="wrap">
            <h1><?php _e('Novam Payments Settings', 'woocommerce-payment-novam'); ?></h1>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields('novam_payments_settings_group');
                do_settings_sections('novam-payments-settings-admin');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            'novam_payments_settings_group', // Option group
            'novam_payments_settings', // Option name
            array($this, 'sanitize') // Sanitize
        );

        add_settings_section(
            'novam_payments_settings_section', // ID
            __('General Settings', 'woocommerce-payment-novam'), // Title
            array($this, 'print_section_info'), // Callback
            'novam-payments-settings-admin' // Page
        );

        add_settings_field(
            'merchant_no', // ID
            __('Merchant Number', 'woocommerce-payment-novam'), // Title
            array($this, 'merchant_no_callback'), // Callback
            'novam-payments-settings-admin', // Page
            'novam_payments_settings_section' // Section
        );

        add_settings_field(
            'secret_key', // ID
            __('Secret Key', 'woocommerce-payment-novam'), // Title
            array($this, 'secret_key_callback'), // Callback
            'novam-payments-settings-admin', // Page
            'novam_payments_settings_section' // Section
        );

        add_settings_field(
            'order_status', // ID
            __('Order Status', 'woocommerce-payment-novam'), // Title
            array($this, 'order_status_callback'), // Callback
            'novam-payments-settings-admin', // Page
            'novam_payments_settings_section' // Section
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input) {
        $new_input = array();
        
        if (isset($input['merchant_no'])) {
            $new_input['merchant_no'] = sanitize_text_field($input['merchant_no']);
        }

        if (isset($input['secret_key'])) {
            $new_input['secret_key'] = sanitize_text_field($input['secret_key']);
        }

        if (isset($input['order_status'])) {
            // 只允许特定的订单状态选项
            $allowed_statuses = array('wc-processing', 'wc-completed');
            if (in_array($input['order_status'], $allowed_statuses)) {
                $new_input['order_status'] = sanitize_text_field($input['order_status']);
            } else {
                $new_input['order_status'] = 'wc-completed'; // 默认值
            }
        }

        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info() {
        _e('Enter your Novam Payments settings below:', 'woocommerce-payment-novam');
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function merchant_no_callback() {
        printf(
            '<input type="text" id="merchant_no" name="novam_payments_settings[merchant_no]" value="%s" />',
            isset($this->options['merchant_no']) ? esc_attr($this->options['merchant_no']) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function secret_key_callback() {
        printf(
            '<input type="password" id="secret_key" name="novam_payments_settings[secret_key]" value="%s" />',
            isset($this->options['secret_key']) ? esc_attr($this->options['secret_key']) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function order_status_callback() {
        // 限制选项为"正在处理"和"已完成"
        $options = array(
            'wc-processing' => __('Processing', 'woocommerce-payment-novam'),
            'wc-completed' => __('Completed', 'woocommerce-payment-novam')
        );
        
        $selected = isset($this->options['order_status']) ? $this->options['order_status'] : 'wc-completed';
        
        echo '<select id="order_status" name="novam_payments_settings[order_status]">';
        foreach ($options as $key => $value) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($value) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Select the order status to set for successfully paid orders.', 'woocommerce-payment-novam') . '</p>';
    }
    
    /**
     * Get all settings
     */
    public static function get_settings() {
        return get_option('novam_payments_settings', array());
    }
    
    /**
     * Get a specific setting
     */
    public static function get_setting($key, $default = '') {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}