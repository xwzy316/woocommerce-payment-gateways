<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WorldPay Hosted Payment Blocks Support
 *
 * @since 1.7.0
 */
final class WorldPay_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'worldpay_hosted';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_worldpay_hosted_settings', array());
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        try {
            $payment_gateways_class = WC()->payment_gateways();
            $payment_gateways       = $payment_gateways_class->payment_gateways();
            $is_active = isset($payment_gateways['worldpay_hosted']) && 'yes' === $payment_gateways['worldpay_hosted']->enabled;
            
            return $is_active;
        } catch (Exception $e) {
            error_log('[WorldPay Blocks] is_active error: ' . $e->getMessage());
            error_log('[WorldPay Blocks] Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        // Ensure initialize was called
        if (!isset($this->settings)) {
            $this->initialize();
        }
        
        $script_path       = '/assets/js/worldpay-blocks.js';
        $script_asset_path = WORLDPAY_HOSTED_PLUGIN_PATH . 'assets/js/worldpay-blocks.asset.php';
        $script_asset      = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version'      => '1.7.0'
            );
        $script_url = WORLDPAY_HOSTED_PLUGIN_URL . $script_path;

        wp_register_script(
            'worldpay-hosted-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        
        // Localize script data to checkout page
        wp_localize_script(
            'worldpay-hosted-blocks',
            'worldpay_hosted_data',
            $this->get_payment_method_data()
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('worldpay-hosted-blocks', 'worldpay-hosted', WORLDPAY_HOSTED_PLUGIN_PATH . 'languages');
        }

        return array('worldpay-hosted-blocks');
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        try {
            $title = $this->get_setting('title');
            $description = $this->get_setting('description');
            $supports = $this->get_supported_features();
            
            // Get custom icon or use default
            $custom_icon = $this->get_setting('custom_icon');
            $icon = !empty($custom_icon) ? $custom_icon : WORLDPAY_HOSTED_PLUGIN_URL . 'assets/img/worldpay.png';
            
            $payment_data = array(
                'title'       => $title,
                'description' => $description,
                'supports'    => $supports,
                'icon'        => $icon,
            );
            
            return $payment_data;
        } catch (Exception $e) {
            error_log('[WorldPay Blocks] get_payment_method_data error: ' . $e->getMessage());
            error_log('[WorldPay Blocks] Stack trace: ' . $e->getTraceAsString());
            return array();
        }
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features() {
        return array('products');
    }
}
