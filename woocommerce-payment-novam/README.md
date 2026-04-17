# WooCommerce Novam Payment Gateway

[![Version](https://img.shields.io/badge/version-1.0.4-blue.svg)]()
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0+-green.svg)](https://woocommerce.com/)

Novam 支付网关插件，支持巴基斯坦本地支付方式 Jazzcash 和 Easypaisa。

## 功能特性

- ✅ Jazzcash Direct 支付
- ✅ Easypaisa Direct 支付
- ✅ 巴基斯坦本地化支持
- ✅ 实时支付状态查询
- ✅ 异步通知处理
- ✅ 签名验签安全

## 安装方法

1. 将 `woocommerce-payment-novam` 文件夹上传到 `/wp-content/plugins/`
2. 在 WordPress 后台激活插件
3. 进入 WooCommerce → 设置 → 支付，启用 Novam Payment

## 配置说明

### 1. 申请 Novam 账号

联系 Novam 官方申请商户账号：

- 官网：https://novam.tech/
- 邮箱：support@novam.tech

### 2. 插件设置

| 配置项 | 说明 |
|--------|------|
| Merchant ID | 商户 ID |
| API Key | API 密钥 |
| 测试模式 | 启用/禁用测试环境 |
| 支付方式 | 选择 Jazzcash/Easypaisa |

## 支付方式说明

### Jazzcash
巴基斯坦最大的移动支付平台，支持：
- 手机钱包支付
- 银行卡支付
- 便利店现金支付

### Easypaisa
巴基斯坦领先的数字金融服务：
- 手机账户支付
- 银行转账
- 账单支付

## 支付流程

```
用户下单 → 选择 Novam 支付 → 选择 Jazzcash/Easypaisa
→ 跳转支付页面 → 用户输入手机号确认支付
→ 异步通知回调 → 更新订单状态
```

## API 集成

### 创建支付订单

```php
$payment_data = array(
    'merchant_id' => $merchant_id,
    'amount' => $order_amount,
    'currency' => 'PKR',
    'order_id' => $order_id,
    'payment_method' => 'jazzcash' // 或 easypaisa
);

$response = wp_remote_post('https://api.novam.tech/v1/payment', array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode($payment_data)
));
```

### 处理异步通知

```php
public function handle_webhook($data) {
    // 验证签名
    if (!$this->verify_signature($data)) {
        return false;
    }
    
    // 更新订单状态
    $order = wc_get_order($data['order_id']);
    if ($data['status'] === 'success') {
        $order->payment_complete();
    } else {
        $order->update_status('failed');
    }
    
    return true;
}
```

## Webhook 配置

在 Novam 后台配置回调 URL：

```
https://你的域名.com/wp-json/novam/v1/notify
```

## 订单状态

| Novam 状态 | WooCommerce 状态 |
|-----------|-----------------|
| Pending | 待处理 |
| Success | 已完成 |
| Failed | 失败 |
| Cancelled | 已取消 |

## 常见问题

### 支付页面无法打开

确认服务器可以访问 Novam API 域名，检查防火墙设置。

### 异步通知未收到

1. 检查 Webhook URL 是否可公开访问
2. 查看服务器错误日志
3. 联系 Novam 技术支持确认配置

## 技术文档

- [Novam API 文档](https://docs.novam.tech/)
- [更多支付插件](https://github.com/xwzy316/woocommerce-payment-gateways)

## 定制开发

需要对接其他支付通道？[联系我](https://www.itbunan.xyz/service.html)

---

**作者**: xyls1130  
**博客**: [IT 技术家园](https://www.itbunan.xyz/)
