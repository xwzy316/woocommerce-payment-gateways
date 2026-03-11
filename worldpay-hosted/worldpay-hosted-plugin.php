<?php
/**
 * Plugin Name: WorldPay Hosted Payment Page
 * Description: Provides an independent checkout page for WorldPay Hosted payment
 * Version: 1.8.4
 * Author: xwzy201130
 * Text Domain: worldpay-hosted
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件路径常量
define('WORLDPAY_HOSTED_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WORLDPAY_HOSTED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WORLDPAY_HOSTED_VERSION', '1.8.4');

// 最早阶段：注册 WooCommerce Blocks 支持（在 plugins_loaded 之后）
// 使用 plugins_loaded 钩子确保网关类已加载
add_action('plugins_loaded', 'worldpay_hosted_register_blocks_support', 20);

/**
 * 注册 WooCommerce Blocks 支持
 * 调用 WorldPay_WC_Gateway 类中的方法
 */
function worldpay_hosted_register_blocks_support() {
    // 检查支付网关类是否已加载
    if (!class_exists('WorldPay_WC_Gateway')) {
        return;
    }
    
    WorldPay_WC_Gateway::register_blocks_support();
}

// 包含主要类文件
require_once WORLDPAY_HOSTED_PLUGIN_PATH . 'includes/class-worldpay-hosted-page.php';
require_once WORLDPAY_HOSTED_PLUGIN_PATH . 'includes/api/class-payrix-payment-service.php';
require_once WORLDPAY_HOSTED_PLUGIN_PATH . 'includes/api/class-payrix-payment-api.php';
require_once WORLDPAY_HOSTED_PLUGIN_PATH . 'includes/class-worldpay-settings.php';

// 初始化插件
function init_worldpay_hosted_plugin() {
    try {
        new WorldpayHostedPage();
    } catch (Exception $e) {
        error_log('[WorldPay Plugin] Error initializing WorldpayHostedPage: ' . $e->getMessage());
    }
    
    try {
        new PayrixPaymentAPI();
    } catch (Exception $e) {
        error_log('[WorldPay Plugin] Error initializing PayrixPaymentAPI: ' . $e->getMessage());
    }
    
    try {
        new WorldPaySettings();
    } catch (Exception $e) {
        error_log('[WorldPay Plugin] Error initializing WorldPaySettings: ' . $e->getMessage());
    }
    
    // 只有在WooCommerce激活时才加载和注册支付网关类
    if (class_exists('WC_Payment_Gateway')) {
        require_once WORLDPAY_HOSTED_PLUGIN_PATH . 'includes/class-worldpay-wc-gateway.php';
        add_filter('woocommerce_payment_gateways', 'worldpay_hosted_add_gateway_class');
    }
    
    // 临时：检查并刷新重写规则（仅在需要时执行一次）
    if (get_option('worldpay_hosted_rewrite_flushed') !== '2.0') {
        flush_rewrite_rules();
        update_option('worldpay_hosted_rewrite_flushed', '2.0');
    }
}
add_action('plugins_loaded', 'init_worldpay_hosted_plugin');

// 添加插件设置链接
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'worldpay_hosted_plugin_settings_link');
function worldpay_hosted_plugin_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=worldpay-payment-settings">' . __('Settings', 'worldpay-hosted') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// 加载语言包
add_action('plugins_loaded', 'worldpay_hosted_load_textdomain');
function worldpay_hosted_load_textdomain() {
    load_plugin_textdomain('worldpay-hosted', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// 注册 WooCommerce支付网关
function worldpay_hosted_add_gateway_class($gateways) {
    $gateways[] = 'WorldPay_WC_Gateway';
    return $gateways;
}

// 激活插件时刷新重写规则
register_activation_hook(__FILE__, 'worldpay_hosted_activation');
function worldpay_hosted_activation() {
    // 先注册重写规则
    $page = new WorldpayHostedPage();
    $page->add_rewrite_rules();
    
    // 然后刷新
    flush_rewrite_rules();
}

// 停用插件时清理
register_deactivation_hook(__FILE__, 'worldpay_hosted_deactivation');
function worldpay_hosted_deactivation() {
    flush_rewrite_rules();
}