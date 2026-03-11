<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WorldPay Hosted Payment Gateway for WooCommerce
 *
 * Provides a standard WooCommerce payment gateway that redirects to custom checkout page.
 *
 * @class       WorldPay_WC_Gateway
 * @extends     WC_Payment_Gateway
 * @version     2.0.0
 * @author      WorldPay Hosted
 */
class WorldPay_WC_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'worldpay_hosted';
        $this->has_fields         = false;
        $this->method_title       = __('WorldPay Hosted Payment', 'worldpay-hosted');
        $this->method_description = __('Pay securely with WorldPay Hosted payment gateway.', 'worldpay-hosted');
        $this->supports           = array('products');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        
        // Set icon from custom upload or default
        $custom_icon = $this->get_option('custom_icon');
        $this->icon  = !empty($custom_icon) ? $custom_icon : WORLDPAY_HOSTED_PLUGIN_URL . 'assets/img/worldpay.png';

        // Actions
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }
    
    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = apply_filters('worldpay_hosted_wc_gateway_form_fields', array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'worldpay-hosted'),
                'type'    => 'checkbox',
                'label'   => __('Enable WorldPay Hosted Payment', 'worldpay-hosted'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'worldpay-hosted'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'worldpay-hosted'),
                'default'     => __('WorldPay Hosted Payment', 'worldpay-hosted'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'worldpay-hosted'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'worldpay-hosted'),
                'default'     => __('Pay securely with WorldPay Hosted payment gateway.', 'worldpay-hosted'),
                'desc_tip'    => true,
            ),
            'custom_icon' => array(
                'title'       => __('Payment Method Icon', 'worldpay-hosted'),
                'type'        => 'custom_icon',
                'description' => __('Upload or enter the URL of the payment method icon. Leave empty to use the default icon.', 'worldpay-hosted'),
                'desc_tip'    => true,
            )
        ));
    }

    /**
     * Generate custom icon field HTML with upload button
     *
     * @param string $key
     * @param array  $data
     * @return string
     */
    public function generate_custom_icon_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $value     = $this->get_option($key);
        $attributes = $this->get_custom_attribute_html($data);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>">
                    <?php echo wp_kses_post($data['title']); ?>
                    <?php if (!empty($data['desc_tip'])) : ?>
                        <span class="woocommerce-help-tip" data-tip="<?php echo esc_attr($data['description']); ?>"></span>
                    <?php endif; ?>
                </label>
            </th>
            <td class="forminp">
                <div id="<?php echo esc_attr($field_key); ?>_container">
                    <?php if (!empty($value)) : ?>
                        <div style="margin-bottom: 10px;" id="<?php echo esc_attr($field_key); ?>_preview">
                            <img src="<?php echo esc_url($value); ?>" alt="Payment Icon" style="max-height: 60px; max-width: 150px;">
                        </div>
                    <?php endif; ?>
                    <input
                        type="hidden"
                        id="<?php echo esc_attr($field_key); ?>"
                        name="<?php echo esc_attr($field_key); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        class="worldpay-icon-input"
                        <?php echo $attributes; ?>
                    />
                    <button type="button" class="button worldpay-icon-upload-btn" data-field="<?php echo esc_attr($field_key); ?>">
                        <?php esc_html_e('Upload Icon', 'worldpay-hosted'); ?>
                    </button>
                    <?php if (!empty($value)) : ?>
                        <button type="button" class="button worldpay-icon-remove-btn" data-field="<?php echo esc_attr($field_key); ?>" style="margin-left: 5px;">
                            <?php esc_html_e('Remove Icon', 'worldpay-hosted'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($data['description']) && empty($data['desc_tip'])) : ?>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue scripts and styles in the admin area
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only enqueue on WooCommerce settings pages
        if (strpos($hook_suffix, 'woocommerce') === false) {
            return;
        }

        // Enqueue WordPress media library
        wp_enqueue_media();

        // Register and enqueue our custom icon uploader script
        wp_register_script(
            'worldpay-icon-uploader',
            WORLDPAY_HOSTED_PLUGIN_URL . 'assets/js/icon-uploader.js',
            array('jquery', 'media-editor'),
            WORLDPAY_HOSTED_VERSION,
            true
        );

        // Localize the script with our translations
        wp_localize_script(
            'worldpay-icon-uploader',
            'worldpayIconUpload',
            array(
                'selectTitle' => __('Select Payment Method Icon', 'worldpay-hosted'),
                'useButton'   => __('Use this image', 'worldpay-hosted'),
            )
        );

        wp_enqueue_script('worldpay-icon-uploader');
    }
    
    /**
     * Process and sanitize the custom icon field
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public function validate_custom_icon_field($key, $value) {
        if (empty($value)) {
            return '';
        }
        return esc_url_raw($value);
    }
    
    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting WorldPay Hosted payment', 'worldpay-hosted'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        // Redirect to custom checkout page
        $receipt_url = home_url('/safe-checkout/' . $order_id . '/');

        return array(
            'result'   => 'success',
            'redirect' => esc_url_raw($receipt_url)
        );
    }
    
    /**
     * Register WooCommerce Blocks support
     * This method is called when the payment gateway is initialized
     */
    public static function register_blocks_support() {
        // Check if WooCommerce Blocks is available
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
        
        require_once WORLDPAY_HOSTED_PLUGIN_PATH . 'includes/class-worldpay-blocks-support.php';
        
        // Register the payment method type
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                try {
                    $blocks_support = new WorldPay_Blocks_Support();
                    $payment_method_registry->register($blocks_support);
                } catch (Exception $e) {
                    error_log('[WorldPay WC Gateway] Error registering blocks support: ' . $e->getMessage());
                }
            },
            10
        );
    }
}