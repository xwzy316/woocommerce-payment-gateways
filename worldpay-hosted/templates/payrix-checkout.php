<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorldPay Hosted Checkout</title>
    
    <!-- jQuery 库 -->
    <script type="text/javascript" src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
    
    <?php wp_head(); ?>
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            background: #f5f7fa;
        }
        
        .payment-form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background: #fff;
        }
        
        .payment-form-container h2 {
            color: #1650ff;
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 25px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-row {
            height: 73px;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px;
            background: #fafafa;
            transition: all 0.3s ease;
        }
        
        .form-row:focus-within {
            border-color: #1650ff;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(22, 80, 255, 0.1);
        }
        
        .address-row {
            height: 438px;
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px;
            background: #fafafa;
            transition: all 0.3s ease;
        }
        
        .address-row:focus-within {
            border-color: #1650ff;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(22, 80, 255, 0.1);
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #333;
            letter-spacing: 0.3px;
        }
        
        .form-field-group {
            margin-bottom: 25px;
        }
        
        .button-container {
            margin-top: 35px;
            margin-bottom: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            text-align: center;
        }
        
        #submit-payment {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            border: none;
            width: 100%;
            max-width: 300px;
            outline: none;
            height: 50px;
            background: linear-gradient(135deg, #1650ff 0%, #0d3acc 100%);
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(22, 80, 255, 0.3);
        }
        
        #submit-payment:hover {
            background: linear-gradient(135deg, #0d3acc 0%, #0a2a99 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(22, 80, 255, 0.4);
        }
        
        #submit-payment:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(22, 80, 255, 0.3);
        }
        
        #submit-payment:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        #loading-message {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 16px;
        }
        
        #loading-message p {
            margin: 0;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .payment-success {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-radius: 12px;
            margin: 20px;
            border: 2px solid #bae6fd;
        }
        
        .payment-success h2 {
            color: #059669;
            margin: 0 0 15px 0;
            font-size: 28px;
        }
        
        .payment-success p {
            color: #0369a1;
            margin: 8px 0;
            font-size: 16px;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            border: 2px solid #fcc;
            color: #c33;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .error-message strong {
            display: block;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        /* 响应式调整 */
        @media screen and (max-width: 768px) {
            .payment-form-container {
                padding: 20px 15px;
            }
            
            .payment-form-container h2 {
                font-size: 20px;
            }
            
            #submit-payment {
                height: 48px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="worldpay-content">
        <header>
            <div class="header-content">
                <img
                    src="<?php echo esc_url(WORLDPAY_HOSTED_PLUGIN_URL . 'assets/img/worldpay.png'); ?>"
                    alt="Logo"
                    class="header-content-img"
                />
                <div class="subtitle">Secure Checkout</div>
            </div>
        </header>
        
        <div class="content">
            <div id="order-summary">
                <div id="order-items"></div>
                <div id="order-total"></div>
            </div>
            
            <div id="payment-container">
                <div id="loading-message">
                    <p><?php _e('Loading payment form...', 'worldpay-hosted'); ?></p>
                </div>
                
                <form id="payment-form" style="display: none;">
                    <div class="payment-form-container">
                        <h2>💳 <?php _e('Payment Information', 'worldpay-hosted'); ?></h2>
                        
                        <div class="form-field-group">
                            <label for="card-number"><?php _e('Card Number', 'worldpay-hosted'); ?></label>
                            <div id="card-number" class="form-row"></div>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <div style="flex: 1;" class="form-field-group">
                                <label for="card-expiration"><?php _e('Expiration Date', 'worldpay-hosted'); ?></label>
                                <div id="card-expiration" class="form-row"></div>
                            </div>

                            <div style="flex: 1;" class="form-field-group">
                                <label for="card-cvv"><?php _e('CVV', 'worldpay-hosted'); ?></label>
                                <div id="card-cvv" class="form-row"></div>
                            </div>
                        </div>

                        <div class="form-field-group">
                            <label for="card-name"><?php _e('Cardholder Name', 'worldpay-hosted'); ?></label>
                            <div id="card-name" class="form-row"></div>
                        </div>

                        <!-- <?php _e('Address form hidden because PayFields displays pre-filled addresses as ***', 'worldpay-hosted'); ?> -->
                        <!-- 
                        <div class="form-field-group">
                            <label for="billing-address">📍 <?php _e('Billing Address', 'worldpay-hosted'); ?></label>
                            <div id="billing-address" class="address-row"></div>
                        </div>
                        -->

                        <?php if (is_user_logged_in()): ?>
                        <!-- 保存卡片选项（仅已登录用户可见） -->
                        <div class="save-card-option">
                            <input type="checkbox" id="save-card" name="save-card">
                            <label for="save-card">
                                💾 <?php _e('Save this card for future payments', 'worldpay-hosted'); ?>
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="button-container">
                            <button type="button" id="submit-payment">
                                🔒 <?php _e('Submit Payment', 'worldpay-hosted'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>
