# WorldPay Hosted Payment Plugin

worldpay-hosted-online-payments-demo

## 概述

本插件为 WordPress/WooCommerce 提供 WorldPay Hosted (Payrix) 支付集成。使用 Payrix PayFields 实现安全的、符合 PCI 标准的支付处理。

## 主要特性

- ✅ 集成 Payrix PayFields 支付表单
- ✅ 交易会话密钥（Transaction Session Key）支持
- ✅ 符合 PCI 标准的安全支付
- ✅ 支持测试和生产环境
- ✅ WooCommerce 完全集成
- ✅ 响应式设计，支持移动设备
- ✅ 自动订单状态更新
- ✅ 多语言支持（中文/英文）

## 安装配置

### 1. 安装插件

将插件文件上传到 WordPress 的 `wp-content/plugins/` 目录，然后在后台激活插件。

### 2. 配置 Payrix 凭证

进入 WordPress 后台 > 设置 > WorldPay Hosted，配置以下信息：

- **API Environment**: 选择测试或生产环境
- **Merchant ID**: 您的 Payrix 商户 ID（格式：`t1_mer_xxxxx`）
- **API Key**: 您的 Payrix API 密钥
- **Payment Success Status**: 支付成功后的订单状态（已完成/处理中）

### 3. 启用支付网关

如果使用 WooCommerce：
1. 进入 WooCommerce > 设置 > 支付
2. 启用 "WorldPay Hosted Payment" 网关
3. 配置网关标题和描述

## API 文档参考

本插件基于 Payrix API 实现，遵循以下最佳实践：

### 交易会话密钥（Transaction Session Key）

为增强安全性，插件使用交易会话密钥而非固定 API 密钥：

- 每次支付会话自动生成新的临时密钥
- 密钥有时间限制（默认 8 分钟）
- 限制使用次数，防止滥用

### PayFields 集成

使用 Payrix PayFields 确保：

- 敏感支付信息不经过您的服务器
- 符合 PCI DSS 标准
- 使用 iframe 隔离保护支付数据
- 支持动态脚本加载（SPA 模式）

## 技术架构

### 后端（PHP）

- `class-payrix-payment-service.php` - Payrix API 服务类
- `class-payrix-payment-api.php` - REST API 端点
- `class-worldpay-hosted-page.php` - 支付页面处理
- `class-worldpay-settings.php` - 插件设置界面

### 前端（JavaScript）

- `payrix-checkout.js` - PayFields 集成脚本
- 支持响应式设计
- 异步加载 PayFields 库
- 自动处理支付回调

### API 端点

- `POST /wp-json/payrix-payment/v1/config` - 获取支付配置
- `POST /wp-json/payrix-payment/v1/create-session` - 创建交易会话
- `POST /wp-json/payrix-payment/v1/webhook` - Webhook 通知

## 安全最佳实践

本插件遵循 Payrix 官方推荐的安全最佳实践：

1. ✅ 使用交易会话密钥代替固定 API 密钥
2. ✅ PayFields 仅用于令牌创建模式
3. ✅ 所有敏感数据通过 iframe 隔离
4. ✅ 支持失败授权交易规则
5. ✅ API 密钥存储在 WordPress 数据库中

## 开发和测试

### 测试环境

使用测试环境进行开发：

```
API Base URL: https://test-api.payrix.com
PayFields Script: https://test-api.payrix.com/payFieldsScript?spa=1
```

### 测试卡号

请参考 Payrix 官方文档获取测试卡号。

## 故障排查

### 支付表单无法加载

1. 检查 API 凭证是否正确
2. 查看浏览器控制台错误
3. 确认网络可以访问 Payrix API

### 订单状态未更新

1. 检查 Webhook URL 是否配置正确
2. 查看 WordPress 错误日志
3. 确认 WooCommerce 已激活

## 更新日志

### Version 2.0.0
- ✅ 迁移到 Payrix PayFields
- ✅ 实现交易会话密钥
- ✅ 符合 PCI 标准
- ✅ 响应式设计优化

### Version 1.1.0
- 初始 WorldPay Hosted 版本

## 支持

如有问题，请查看：
- [Payrix 官方文档](https://docs.payrix.com/)
- WordPress 错误日志
- 浏览器开发者工具控制台

## 许可证

本插件遵循 GPL v2 或更高版本许可证。
