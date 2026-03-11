<?php
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Novam_Blocks_Support extends AbstractPaymentMethodType {
    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'novam_payments';

    /**
     * Payment gateways.
     *
     * @var array
     */
    private $gateways = array();

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_easypaisa_direct_settings', array());
        
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateways = array(
            'jazzcash_direct' => $gateways['jazzcash_direct'] ?? null,
            'easypaisa_direct' => $gateways['easypaisa_direct'] ?? null,
        );
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        foreach ($this->gateways as $gateway) {
            if ($gateway && $gateway->is_available()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $handles = array();

        foreach ($this->gateways as $gateway) {
            if ($gateway && $gateway->is_available()) {
                wp_register_script(
                    'wc-' . $gateway->id . '-block',
                    plugins_url('assets/js/block-' . $gateway->id . '.js', dirname(__FILE__)),
                    array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n'),
                    '1.0.0',
                    true
                );
                $handles[] = 'wc-' . $gateway->id . '-block';
            }
        }

        return $handles;
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script client side.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $data = array();
        
        foreach ($this->gateways as $id => $gateway) {
            if ($gateway && $gateway->is_available()) {
                $data[$id] = array(
                    'title'       => $gateway->title,
                    'description' => $gateway->description,
                    'icon'        => $gateway->icon,
                    'supports'    => $gateway->supports,
                );
            }
        }
        
        return $data;
    }
}