<?php
/*
Plugin Name: WooCommerce Novam Direct Payments
Plugin URI: https://1novam.xyz
Description: 支持Jazzcash Direct和Easypaisa Direct的WooCommerce支付插件
Version: 1.0.4
Author: xwzy201130
Author URI: https://1novam.xyz
License: GPLv2 or later
Text Domain: woocommerce-payment-novam
Domain Path: /languages
Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit;
}

// 定义插件路径常量
define('WC_NOVAM_PAYMENTS_PATH', plugin_dir_path(__FILE__));
define('WC_NOVAM_PAYMENTS_URL', plugin_dir_url(__FILE__));

// 检查WooCommerce是否激活
add_action('plugins_loaded', 'wc_novam_payments_init', 10);
function wc_novam_payments_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_novam_payments_missing_wc_notice');
        return;
    }

    // 加载文本域
    load_plugin_textdomain('woocommerce-payment-novam', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // 引入网关类
    require_once __DIR__ . '/includes/class-wc-novam-gateway.php';
    require_once __DIR__ . '/includes/class-wc-novam-settings.php';
    require_once __DIR__ . '/includes/class-wc-jazzcash-direct.php';
    require_once __DIR__ . '/includes/class-wc-easypaisa-direct.php';

    // 初始化设置类
    new WC_Novam_Settings();

    // 注册支付网关
    add_filter('woocommerce_payment_gateways', 'wc_novam_payments_register_gateways');
    function wc_novam_payments_register_gateways($gateways) {
        $gateways[] = 'WC_Jazzcash_Direct';
        $gateways[] = 'WC_Easypaisa_Direct';
        return $gateways;
    }

    // 加载Block结账样式
    add_action('wp_enqueue_scripts', 'wc_novam_payments_enqueue_assets');
    function wc_novam_payments_enqueue_assets() {
        if ((is_checkout() && !is_wc_endpoint_url()) || is_wc_endpoint_url('order-received')) {
            wp_enqueue_style(
                'wc-novam-payments-checkout',
                plugins_url('assets/css/checkout.css', __FILE__),
                array(),
                '1.0.0'
            );
        }
    }

    // 支持Block结账
    add_action('woocommerce_blocks_loaded', 'wc_novam_payments_support_blocks');
    function wc_novam_payments_support_blocks() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once __DIR__ . '/includes/class-wc-novam-blocks-support.php';
            
            add_action('woocommerce_blocks_payment_method_type_registration', function($payment_method_registry) {
                $payment_method_registry->register(new WC_Novam_Blocks_Support());
            });
        }
    }
    
    // 添加自定义模板路径
    add_filter('woocommerce_locate_template', 'wc_novam_payments_template_path', 20, 3);
    function wc_novam_payments_template_path($template, $template_name, $template_path) {
        // 只处理订单详情相关的模板
        if ($template_name === 'order/order-details-item.php') {
            $plugin_template = WC_NOVAM_PAYMENTS_PATH . 'templates/woocommerce/' . $template_name;
            if (file_exists($plugin_template)) {
                error_log('Using custom template: ' . $plugin_template);
                return $plugin_template;
            }
        }
        
        if ($template_name === 'order/order-details.php') {
            $plugin_template = WC_NOVAM_PAYMENTS_PATH . 'templates/woocommerce/' . $template_name;
            if (file_exists($plugin_template)) {
                error_log('Using custom template: ' . $plugin_template);
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    // 在订单确认页面移除操作栏
    add_action('woocommerce_thankyou', 'wc_novam_remove_order_actions', 10);
    function wc_novam_remove_order_actions($order_id) {
        // 只针对使用我们支付方式的订单
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $payment_method = $order->get_payment_method();
        if (in_array($payment_method, ['jazzcash_direct', 'easypaisa_direct'])) {
            // 移除操作按钮
            remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
        }
    }
    
    // 在WooCommerce后台订单详情页面显示自定义字段
    add_action('woocommerce_admin_order_data_after_order_details', 'wc_novam_display_order_no_field');
    function wc_novam_display_order_no_field($order) {
        $novam_order_no = get_post_meta($order->get_id(), '_novam_order_no', true);
        if ($novam_order_no) {
            echo '<p><strong>' . __('Novam Order Number:', 'woocommerce-payment-novam') . '</strong> ' . esc_html($novam_order_no) . '</p>';
        }
    }
}

// 缺少WooCommerce提示
function wc_novam_payments_missing_wc_notice() {
    echo '<div class="error"><p>' . sprintf(
        __('Novam Direct Payments requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-payment-novam'),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    ) . '</p></div>';
}

// 添加插件设置链接
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_novam_payments_action_links');
function wc_novam_payments_action_links($links) {
    $settings_link = array(
        'settings' => '<a href="' . admin_url('options-general.php?page=novam-payments-settings') . '">' . __('Settings', 'woocommerce-payment-novam') . '</a>'
    );
    return array_merge($settings_link, $links);
}