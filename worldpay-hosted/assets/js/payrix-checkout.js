/**
 * WorldPay Hosted (Payrix PayFields) Integration
 * 使用 PayFields 嵌入式支付表单，支持Token保存功能
 */

// ===================== 调试日志持久化 =====================
const LOG_STORAGE_KEY = 'payrix_checkout_logs';

/**
 * 持久化日志到localStorage
 */
function persistLog(message, level = 'INFO') {
    const timestamp = new Date().toISOString();
    const logEntry = `[${timestamp}] [${level}] ${message}`;
    
    // 输出到控制台 - 根据调试开关
    if (DEBUG_ENABLED) {
        console.log(logEntry);
    }
    
    // 保存到localStorage
    if (DEBUG_MODE) {
        try {
            const logs = JSON.parse(localStorage.getItem(LOG_STORAGE_KEY) || '[]');
            logs.push(logEntry);
            // 保留最近500条日志
            if (logs.length > 500) {
                logs.shift();
            }
            localStorage.setItem(LOG_STORAGE_KEY, JSON.stringify(logs));
        } catch (e) {
            if (DEBUG_ENABLED) {
                console.error('Failed to persist log:', e);
            }
        }
    }
}

/**
 * 查看持久化的日志（全局函数）
 */
window.viewPayrixLogs = function() {
    try {
        const logs = JSON.parse(localStorage.getItem(LOG_STORAGE_KEY) || '[]');
        // console.log('========== Payrix Checkout Logs (' + logs.length + ' entries) ==========');
        // logs.forEach(log => console.log(log)); // 保留，用于调试时手动查看
        // console.log('========== End of Logs ==========');
        return logs;
    } catch (e) {
        // console.error('Failed to retrieve logs:', e);
        return [];
    }
};

/**
 * 清除持久化的日志（全局函数）
 */
window.clearPayrixLogs = function() {
    localStorage.removeItem(LOG_STORAGE_KEY);
    // console.log('Payrix checkout logs cleared');
};
// ===================== End 调试日志持久化 =====================

// 从后端获取数据
const checkoutData = typeof worldpay_checkout_data !== 'undefined' ? worldpay_checkout_data : { amount: { currency: 'USD', value: '60.00' }, order_id: null, billing_address: {}, i18n: {}, debug_enabled: false, countdown_seconds: 5 };
const amount = checkoutData.amount;
const orderId = checkoutData.order_id;
const billingAddress = checkoutData.billing_address || {};
const i18n = checkoutData.i18n || {};
const DEBUG_ENABLED = checkoutData.debug_enabled || false; // 从后端获取调试开关
const DEBUG_MODE = DEBUG_ENABLED; // 与 DEBUG_ENABLED 保持一致，控制 localStorage 持久化
const COUNTDOWN_SECONDS = checkoutData.countdown_seconds || 5; // 从后端获取倒计时秒数

// 显示调试信息
if (DEBUG_ENABLED) {
    console.log('%c🔧 Payrix Debug Mode Enabled', 'color: #FF6600; font-weight: bold; font-size: 14px;');
    console.log('All logs are automatically saved to localStorage.');
    console.log('After payment completes, run: viewPayrixLogs()');
    console.log('To clear logs, run: clearPayrixLogs()');
}

const CONFIG_URL = '/wp-json/payrix-payment/v1/config';
const UPDATE_ORDER_URL = '/wp-json/payrix-payment/v1/update-order';
const SAVE_TOKEN_URL = '/wp-json/payrix-payment/v1/save-token-from-response'; // 使用直接保存 Token 的端点
const GET_TOKENS_URL = '/wp-json/payrix-payment/v1/get-tokens';
const PAY_WITH_TOKEN_URL = '/wp-json/payrix-payment/v1/pay-with-token';
const SAVE_CUSTOMER_ID_URL = '/wp-json/payrix-payment/v1/save-customer-id';

let paymentConfig = null;
let selectedTokenId = null;

/**
 * 格式化金额显示
 */
function formatAmountForDisplay(amountObj) {
    const value = parseFloat(amountObj.value);
    return value.toFixed(2);
}

/**
 * 渲染订单汇总信息
 */
function renderOrderSummary() {
    const orderTotalContainer = document.getElementById('order-total');
    const formattedAmount = formatAmountForDisplay(amount);
    orderTotalContainer.innerHTML = `<div class="order-total">Total: ${formattedAmount} ${amount.currency}</div>`;
}

/**
 * 加载 PayFields 脚本
 */
function loadPayFieldsScript() {
    return new Promise((resolve, reject) => {
        const scriptId = 'payFieldsScript';
        
        // 检查脚本是否已加载
        if (document.getElementById(scriptId)) {
            if (window.PayFields) {
                resolve(window.PayFields);
            } else {
                reject(new Error('PayFields script loaded but PayFields not available'));
            }
            return;
        }

        const script = document.createElement('script');
        const apiUrl = paymentConfig && paymentConfig.environment === 'PRODUCTION' 
            ? 'https://api.payrixcanada.com/payFieldsScript'
            : 'https://test-api.payrixcanada.com/payFieldsScript';
        
        script.src = apiUrl;
        script.id = scriptId;
        script.type = 'text/javascript';
        
        script.onload = () => {
            // console.log('PayFields script loaded');
            if (window.PayFields) {
                resolve(window.PayFields);
            } else {
                reject(new Error('PayFields not available after script load'));
            }
        };
        
        script.onerror = () => {
            reject(new Error('Failed to load PayFields script'));
        };
        
        document.body.appendChild(script);
    });
}

/**
 * 获取支付配置
 */
async function getPaymentConfig() {
    try {
        const requestData = {
            orderId: orderId,
            userId: checkoutData.user_id || 0  // 传递用户ID
        };
        
        if (DEBUG_ENABLED) {
            console.log('========== API REQUEST: GET PAYMENT CONFIG ==========');
            console.log('URL:', CONFIG_URL);
            console.log('Request Data:', requestData);
        }
        
        const response = await fetch(CONFIG_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData),
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const result = await response.json();
        
        if (DEBUG_ENABLED) {
            console.log('Response Status:', response.status);
            console.log('Response Data:', result);
            console.log('========== END API REQUEST ==========');
        }
        
        if (result.status === 'success') {
            return result;
        } else {
            throw new Error(result.message || 'Failed to get payment configuration');
        }
    } catch (error) {
        if (DEBUG_ENABLED) {
            console.error('Error fetching payment config:', error);
        }
        throw error;
    }
}

/**
 * 初始化 PayFields
 */
async function initializePayFields() {
    try {
        persistLog('============================================================');
        persistLog('Payrix Checkout Page Loaded');
        persistLog('============================================================');
        persistLog('Order ID: ' + orderId);
        persistLog('Amount: ' + amount.value);
        persistLog('User ID: ' + (checkoutData.user_id || '0'));
        
        // 如果用户已登录，先加载已保存的Token
        if (checkoutData.user_id && checkoutData.user_id > 0) {
            persistLog('User logged in, loading saved tokens...');
            await loadSavedTokens();
        } else {
            persistLog('User not logged in, initializing PayFields directly');
            // 未登录用户直接初始化PayFields
            await initializePayFieldsForm();
        }

    } catch (error) {
        persistLog('Error initializing PayFields: ' + error.message, 'ERROR');
        const errorMsg = (i18n.loadingError || 'Unable to load payment form:') + ' ' + error.message + '<br>' + (i18n.refreshPage || 'Please refresh the page and try again.');
        document.getElementById('loading-message').innerHTML = '<p class="error-message">' + errorMsg + '</p>';
    }
}

/**
 * 显示错误消息
 */
function showError(message) {
    const formContainer = document.querySelector('.payment-form-container');
    if (formContainer) {
        // 删除之前的错误消息
        const existingError = formContainer.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `<strong>${i18n.error || 'Error:'}</strong> ${message}`;
        formContainer.insertBefore(errorDiv, formContainer.firstChild);
    }
}

/**
 * 处理支付成功
 */
function handlePaymentSuccess(response, transactionId) {
    persistLog('\nHandling payment success...');
    persistLog('Success response: ' + JSON.stringify(response));
    persistLog('Transaction ID (passed): ' + (transactionId || 'Not provided'));
    
    // 隐藏支付表单
    const paymentForm = document.getElementById('payment-form');
    if (paymentForm) {
        paymentForm.style.display = 'none';
    }
    
    // 隐藏token支付确认界面（包括取消按钮）
    const tokenPaymentConfirm = document.getElementById('token-payment-confirm');
    if (tokenPaymentConfirm) {
        tokenPaymentConfirm.style.display = 'none';
    }
    
    // 提取交易ID
    if (!transactionId) {
        transactionId = (response && response.data && response.data[0] && response.data[0].id) || response.id || 'N/A';
    }
    
    persistLog('Final Transaction ID for order update: ' + transactionId);
    
    // 检查是否需要保存 Token（只对已登录用户）
    if (checkoutData.user_id && checkoutData.user_id > 0) {
        // 检查用户是否勾选了“保存支付信息”复选框
        const saveCardCheckbox = document.getElementById('save-card');
        const shouldSaveToken = saveCardCheckbox ? saveCardCheckbox.checked : false;
        
        persistLog('User logged in (ID: ' + checkoutData.user_id + ')');
        persistLog('Save card checkbox checked: ' + shouldSaveToken);
        
        if (shouldSaveToken && transactionId && transactionId !== 'N/A') {
            // 检查响应中是否包含 token 信息
            let tokenData = null;
            
            // PayFields 可能返回 token 在不同的地方
            if (response && response.data && response.data[0]) {
                const txnData = response.data[0];
                // 检查是否有 token 字段
                if (txnData.token) {
                    tokenData = txnData.token;
                } else if (txnData.payment && txnData.payment.token) {
                    tokenData = txnData.payment.token;
                }
            } else if (response && response.token) {
                tokenData = response.token;
            }
            
            if (tokenData) {
                persistLog('Token found in response: ' + JSON.stringify(tokenData));
                persistLog('Saving token to user account...');
                
                // 保存 Token
                saveTokenToUserAccount(tokenData, transactionId, checkoutData.user_id, billingAddress.email)
                    .then(() => {
                        persistLog('✓ Token saved successfully');
                    })
                    .catch((error) => {
                        persistLog('Failed to save token: ' + error.message, 'ERROR');
                        // 继续处理，不阻塞支付流程
                    });
            } else {
                persistLog('No token found in response - using alternative method', 'WARN');
                // 如果响应中没有 token，尝试从交易中提取
                saveCustomerIdFromTransaction(transactionId, checkoutData.user_id, billingAddress.email)
                    .then(() => {
                        persistLog('✓ Customer ID saved from transaction');
                    })
                    .catch((error) => {
                        persistLog('Failed to save customer ID: ' + error.message, 'ERROR');
                    });
            }
        } else {
            persistLog('Token save skipped. shouldSaveToken=' + shouldSaveToken + ', transactionId=' + transactionId);
        }
    } else {
        persistLog('User not logged in - skipping token save');
    }
    
    // 显示成功消息
    const successMessage = document.createElement('div');
    successMessage.className = 'payment-success';
    successMessage.innerHTML = `
        <h2>${i18n.paymentSuccess || 'Payment Successful!'}</h2>
        <p>${i18n.transactionId || 'Transaction ID:'} ${transactionId}</p>
        <p>${i18n.updatingOrder || 'Updating order status...'}</p>
    `;
    document.getElementById('payment-container').appendChild(successMessage);
    
    // 如果有订单ID，同步更新订单状态后再跳转
    if (orderId) {
        persistLog('Updating order status for order: ' + orderId);
        updateOrderStatusSync(orderId, transactionId)
            .then((result) => {
                persistLog('Order status updated successfully: ' + JSON.stringify(result));
                // 更新成功消息，添加倒计时显示
                successMessage.innerHTML = `
                    <h2>${i18n.paymentSuccess || 'Payment Successful!'}</h2>
                    <p>${i18n.transactionId || 'Transaction ID:'} ${transactionId}</p>
                    <p id="countdown-message">${i18n.redirecting || 'Redirecting to order confirmation page in'} <span id="countdown-timer">${COUNTDOWN_SECONDS}</span> ${i18n.seconds || 'seconds'}...</p>
                `;
                
                // 添加倒计时
                let countdown = COUNTDOWN_SECONDS;
                const countdownTimer = setInterval(() => {
                    countdown--;
                    const timerElement = document.getElementById('countdown-timer');
                    if (timerElement) {
                        timerElement.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(countdownTimer);
                        persistLog('Redirecting to order confirmation page for order ID: ' + orderId);
                        window.location.href = `/checkout/order-received/${orderId}/`;
                    }
                }, 1000);
            })
            .catch(error => {
                persistLog('Failed to update order status: ' + error.message, 'ERROR');
                // 即使更新失败也跳转（Webhook会处理）
                successMessage.innerHTML = `
                    <h2>${i18n.paymentSuccess || 'Payment Successful!'}</h2>
                    <p>${i18n.transactionId || 'Transaction ID:'} ${transactionId}</p>
                    <p id="countdown-message">${i18n.redirecting || 'Redirecting to order confirmation page in'} <span id="countdown-timer">${COUNTDOWN_SECONDS}</span> ${i18n.seconds || 'seconds'}...</p>
                `;
                
                // 添加倒计时
                let countdown = COUNTDOWN_SECONDS;
                const countdownTimer = setInterval(() => {
                    countdown--;
                    const timerElement = document.getElementById('countdown-timer');
                    if (timerElement) {
                        timerElement.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(countdownTimer);
                        persistLog('Redirecting to order confirmation page for order ID (despite error): ' + orderId);
                        window.location.href = `/checkout/order-received/${orderId}/`;
                    }
                }, 1000);
            });
    } else {
        persistLog('No order ID found, skipping order status update');
    }
}

/**
 * 同步更新订单状态
 */
async function updateOrderStatusSync(orderId, transactionId) {
    try {
        const requestData = {
            orderId: orderId,
            transactionId: transactionId
        };
        
        if (DEBUG_ENABLED) {
            console.log('========== API REQUEST: UPDATE ORDER STATUS ==========');
            console.log('URL:', UPDATE_ORDER_URL);
            console.log('Request Data:', requestData);
        }
        
        const response = await fetch(UPDATE_ORDER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData),
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! Status: ${response.status}, Details: ${errorText}`);
        }

        const result = await response.json();
        
        if (DEBUG_ENABLED) {
            console.log('Response Status:', response.status);
            console.log('Response Data:', result);
            console.log('========== END API REQUEST ==========');
        }
        
        if (result.status === 'success' || result.status === 'skipped') {
            return result;
        } else {
            throw new Error(result.message || 'Failed to update order status');
        }
    } catch (error) {
        if (DEBUG_ENABLED) {
            console.error('Error updating order status:', error);
        }
        throw error;
    }
}

/**
 * 保存 Token 到用户账户
 * @param {object} tokenData - Token 数据
 * @param {string} transactionId - 交易ID
 * @param {number} userId - 用户ID
 * @param {string} userEmail - 用户邮箱
 */
async function saveTokenToUserAccount(tokenData, transactionId, userId, userEmail) {
    try {
        // 转换 PayFields 返回的 token 数据为后端期望的格式
        const formattedTokenData = {
            token_id: tokenData.id,  // PayFields 返回的 token ID
            last4: tokenData.payment && tokenData.payment.number ? tokenData.payment.number : '',  // 卡号后4位
            brand: 'card',  // 默认为 card，PayFields 没有返回具体品牌
            cardholder_name: (tokenData.customer && tokenData.customer.first && tokenData.customer.last) 
                ? (tokenData.customer.first + ' ' + tokenData.customer.last).trim() 
                : '',
            exp_month: '',  // PayFields 没有返回过期日期
            exp_year: '',
            customer_id: tokenData.customer && tokenData.customer.id ? tokenData.customer.id : ''  // 添加 customer ID
        };
        
        const requestData = {
            transactionId: transactionId,
            userId: userId,
            userEmail: userEmail,
            tokenData: formattedTokenData  // 传递转换后的数据
        };
        
        if (DEBUG_ENABLED) {
            console.log('========== API REQUEST: SAVE TOKEN ==========');
            console.log('URL:', SAVE_TOKEN_URL);
            console.log('Request Data:', requestData);
        }
        
        const response = await fetch(SAVE_TOKEN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData),
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! Status: ${response.status}, Details: ${errorText}`);
        }

        const result = await response.json();
        
        if (DEBUG_ENABLED) {
            console.log('Response Status:', response.status);
            console.log('Response Data:', result);
            console.log('========== END API REQUEST ==========');
        }
        
        if (result.status === 'success') {
            return result;
        } else {
            throw new Error(result.message || 'Failed to save token');
        }
    } catch (error) {
        if (DEBUG_ENABLED) {
            console.error('Error saving token:', error);
        }
        throw error;
    }
}

/**
 * 从交易中保存customer ID
 * @param {string} transactionId - 交易ID
 * @param {number} userId - 用户ID
 * @param {string} userEmail - 用户邮箱
 */
async function saveCustomerIdFromTransaction(transactionId, userId, userEmail) {
    try {
        const requestData = {
            transactionId: transactionId,
            userId: userId,
            userEmail: userEmail
        };
        
        if (DEBUG_ENABLED) {
            console.log('========== API REQUEST: SAVE CUSTOMER ID ==========');
            console.log('URL:', SAVE_CUSTOMER_ID_URL);
            console.log('Request Data:', requestData);
        }
        
        const response = await fetch(SAVE_CUSTOMER_ID_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData),
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP error! Status: ${response.status}, Details: ${errorText}`);
        }

        const result = await response.json();
        
        if (DEBUG_ENABLED) {
            console.log('Response Status:', response.status);
            console.log('Response Data:', result);
            console.log('========== END API REQUEST ==========');
        }
        
        if (result.status === 'success') {
            return result;
        } else {
            throw new Error(result.message || 'Failed to save customer ID');
        }
    } catch (error) {
        if (DEBUG_ENABLED) {
            console.error('Error saving customer ID:', error);
        }
        throw error;
    }
}

/**
 * 处理支付失败
 */
function handlePaymentFailure(response) {
    // console.error('Failure response:', response);
    
    // 打印更详细的错误日志
    // console.error('Full failure response structure:', JSON.stringify(response, null, 2));
    
    // 详细输出错误数组
    if (response && response.errors) {
        // console.error('Detailed errors:', JSON.stringify(response.errors, null, 2));
    }
    
    // 获取错误信息
    let errorMessage = i18n.paymentFailed || 'Payment Failed';
    
    if (response && response.errors && response.errors.length > 0) {
        const errorMessages = response.errors.map(err => {
            const msg = err.msg || err.message || 'Unknown error';
            const code = err.code ? ` (Code: ${err.code})` : '';
            return msg + code;
        });
        errorMessage = errorMessages.join('; ');
    } else if (response && response.message) {
        errorMessage = response.message;
    }
    
    // console.error('Constructed error message:', errorMessage);
    
    showError(errorMessage + '<br><small>' + (i18n.checkInfoRetry || 'Please check your payment information and try again') + '</small>');
    
    // 重新启用提交按钮
    const submitButton = document.getElementById('submit-payment');
    if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = i18n.retryPayment || 'Retry Payment';
    }
    
    // 清除字段
    if (window.PayFields && PayFields.clearFields) {
        // console.log('Clearing PayFields form');
        PayFields.clearFields();
    }
}

/**
 * 提交支付表单
 */
function submitPayment() {
    persistLog('Submit payment button clicked');
    
    if (!window.PayFields) {
        persistLog('PayFields not available', 'ERROR');
        showError(i18n.payFieldsNotInit || 'PayFields not initialized, please refresh the page');
        return;
    }
    
    // 禁用按钮防止重复提交
    const submitButton = document.getElementById('submit-payment');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = i18n.processing || 'Processing...';
    }
    
    // 清除之前的错误消息
    const existingError = document.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // 提交支付
    try {
        persistLog('Calling PayFields.submit()');
        PayFields.submit();
    } catch (error) {
        persistLog('Error calling PayFields.submit(): ' + error.message, 'ERROR');
        showError('Payment submission failed: ' + error.message);
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = i18n.submitPayment || 'Submit Payment';
        }
    }
}

/**
 * 页面加载完成后初始化
 */
document.addEventListener('DOMContentLoaded', () => {
    renderOrderSummary();
    initializePayFields();
});

// ===================== Token 管理相关功能 =====================

/**
 * 加载已保存的Token
 */
async function loadSavedTokens() {
    try {
        if (!checkoutData.user_id || checkoutData.user_id <= 0) {
            persistLog('User not logged in, skipping token loading');
            await initializePayFieldsForm();
            return;
        }
        
        persistLog('Fetching saved tokens for user: ' + checkoutData.user_id);
        
        const requestData = { userId: checkoutData.user_id };
        
        if (DEBUG_ENABLED) {
            console.log('========== API REQUEST: GET SAVED TOKENS ==========');
            console.log('URL:', GET_TOKENS_URL);
            console.log('Request Data:', requestData);
        }
        
        const response = await fetch(GET_TOKENS_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData),
        });

        if (!response.ok) {
            throw new Error('Failed to fetch saved tokens');
        }

        const result = await response.json();
        
        if (DEBUG_ENABLED) {
            console.log('Response Status:', response.status);
            console.log('Response Data:', result);
            console.log('========== END API REQUEST ==========');
        }
        
        persistLog('Saved tokens response: ' + JSON.stringify(result));
        
        if (result.status === 'success' && result.tokens && result.tokens.length > 0) {
            // 有保存的Token，显示Token选择界面
            displayTokenSelection(result.tokens);
        } else {
            // 没有保存的Token，直接显示支付表单
            persistLog('No saved tokens found, showing payment form');
            await initializePayFieldsForm();
        }
    } catch (error) {
        persistLog('Error loading saved tokens: ' + error.message, 'ERROR');
        if (DEBUG_ENABLED) {
            console.error('Error loading saved tokens:', error);
        }
        // 发生错误时仍然显示支付表单
        await initializePayFieldsForm();
    }
}

/**
 * 显示Token选择界面
 */
function displayTokenSelection(tokens) {
    persistLog('Displaying token selection UI with ' + tokens.length + ' tokens');
    
    // 打印所有token详情
    tokens.forEach((token, index) => {
        persistLog('Token ' + index + ': ' + JSON.stringify(token));
    });
    
    const loadingMessage = document.getElementById('loading-message');
    if (loadingMessage) {
        loadingMessage.style.display = 'none';
    }
    
    const paymentContainer = document.getElementById('payment-container');
    const tokenSelectionHtml = `
        <div class="token-selection">
            <h3>${i18n.selectPaymentMethod || 'Select Payment Method'}</h3>
            <div class="saved-cards">
                ${tokens.map(token => {
                    const last4 = token.last4 || 'Unknown';
                    const tokenId = token.token_id || '';
                    persistLog('Rendering token option: ' + tokenId + ', last4: ' + last4);
                    return `
                        <div class="card-option" data-token-id="${tokenId}">
                            <input type="radio" name="payment-token" value="${tokenId}" id="token-${tokenId}">
                            <label for="token-${tokenId}">
                                <span class="card-brand">${token.brand || 'Card'}</span>
                                <span class="card-number">**** **** **** ${last4}</span>
                                ${token.cardholder_name ? '<span class="card-name">' + token.cardholder_name + '</span>' : ''}
                            </label>
                        </div>
                    `;
                }).join('')}
                <div class="card-option new-card">
                    <input type="radio" name="payment-token" value="new" id="token-new">
                    <label for="token-new">
                        <span class="card-brand">+</span>
                        <span class="card-number">${i18n.useNewCard || 'Use a new card'}</span>
                    </label>
                </div>
            </div>
            <button type="button" id="continue-with-selection" class="submit-button">
                ${i18n.continue || 'Continue'}
            </button>
        </div>
    `;
    
    paymentContainer.innerHTML = tokenSelectionHtml + paymentContainer.innerHTML;
    
    // 绑定按钮事件
    document.getElementById('continue-with-selection').addEventListener('click', handleContinuePayment);
}

/**
 * 处理继续支付
 */
function handleContinuePayment() {
    const selectedRadio = document.querySelector('input[name="payment-token"]:checked');
    if (!selectedRadio) {
        showError(i18n.selectPaymentMethod || 'Please select a payment method');
        return;
    }
    
    const selectedValue = selectedRadio.value;
    persistLog('Selected token: ' + selectedValue);
    
    if (selectedValue === 'new') {
        // 使用新卡
        persistLog('User selected to use a new card');
        document.querySelector('.token-selection').style.display = 'none';
        initializePayFieldsForm(false); // 新卡模式，不保存token
    } else {
        // 使用已保存的Token - 通过PayFields token模式
        persistLog('Using saved token with PayFields: ' + selectedValue);
        selectedTokenId = selectedValue;
        document.querySelector('.token-selection').style.display = 'none';
        initializePayFieldsWithToken(selectedValue);
    }
}

/**
 * 使用Token初始化支付（不使用PayFields，直接调用后端API）
 */
async function initializePayFieldsWithToken(tokenId) {
    try {
        persistLog('============================================================');
        persistLog('初始化Token支付');
        persistLog('Token ID: ' + tokenId);
        persistLog('============================================================');
        
        // 验证Token ID
        if (!tokenId || typeof tokenId !== 'string' || tokenId.trim() === '') {
            throw new Error('Invalid token ID: ' + tokenId);
        }
        
        // 检查Token ID格式
        if (!tokenId.startsWith('t1_tok_')) {
            persistLog('WARNING: Token ID format may be incorrect', 'WARN');
            persistLog('Expected format: t1_tok_xxx, got: ' + tokenId, 'WARN');
        }
        
        // 隐藏loading消息
        const loadingMessage = document.getElementById('loading-message');
        if (loadingMessage) {
            loadingMessage.style.display = 'none';
        }
        
        // 显示确认支付界面
        const paymentContainer = document.getElementById('payment-container');
        const confirmHtml = `
            <div id="token-payment-confirm" class="payment-form-container">
                <h3>${i18n.confirmPayment || 'Confirm Payment'}</h3>
                <p>${i18n.payingWith || 'Paying with saved card'}</p>
                <p>${i18n.amount || 'Amount'}: <strong>${amount.value} ${amount.currency}</strong></p>
                <button type="button" id="confirm-token-payment" class="submit-button">
                    ${i18n.confirmPay || 'Confirm and Pay'}
                </button>
                <button type="button" id="cancel-token-payment" class="cancel-button">
                    ${i18n.cancel || 'Cancel'}
                </button>
            </div>
        `;
        
        paymentContainer.innerHTML += confirmHtml;
        
        // 绑定确认按钮
        document.getElementById('confirm-token-payment').addEventListener('click', async function() {
            persistLog('Confirm token payment clicked');
            this.disabled = true;
            this.textContent = i18n.processing || 'Processing...';
            
            try {
                // 调用后端API使用token支付
                persistLog('Calling backend API to pay with token: ' + tokenId);
                
                const requestData = {
                    tokenId: tokenId,
                    orderId: orderId,
                    amount: amount.value,
                    currency: amount.currency
                };
                
                if (DEBUG_ENABLED) {
                    console.log('========== API REQUEST: PAY WITH TOKEN ==========');
                    console.log('URL:', PAY_WITH_TOKEN_URL);
                    console.log('Request Data:', requestData);
                }
                
                const response = await fetch(PAY_WITH_TOKEN_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(requestData),
                });
                
                if (!response.ok) {
                    throw new Error('HTTP error! Status: ' + response.status);
                }
                
                const result = await response.json();
                
                if (DEBUG_ENABLED) {
                    console.log('Response Status:', response.status);
                    console.log('Response Data:', result);
                    console.log('========== END API REQUEST ==========');
                }
                
                persistLog('Token payment response: ' + JSON.stringify(result));
                
                if (result.status === 'success') {
                    // 支付成功（使用已保存token支付，不需要再保存customer ID）
                    const transactionId = result.transaction_id || result.transactionId || '';
                    persistLog('Token payment successful, transaction ID: ' + transactionId);
                    handlePaymentSuccess(result, transactionId, false);
                } else {
                    // 支付失败
                    throw new Error(result.message || 'Payment failed');
                }
                
            } catch (error) {
                persistLog('Error paying with token: ' + error.message, 'ERROR');
                if (DEBUG_ENABLED) {
                    console.error('Error paying with token:', error);
                }
                showError('Payment failed: ' + error.message);
                this.disabled = false;
                this.textContent = i18n.confirmPay || 'Confirm and Pay';
            }
        });
        
        // 绑定取消按钮
        document.getElementById('cancel-token-payment').addEventListener('click', function() {
            persistLog('Cancel token payment clicked');
            window.location.reload();
        });

    } catch (error) {
        persistLog('Error initializing token payment: ' + error.message, 'ERROR');
        showError(error.message);
    }
}

/**
 * 初始化PayFields表单
 */
async function initializePayFieldsForm() {
    try {
        persistLog('初始化PayFields表单');
        
        // 获取配置
        paymentConfig = await getPaymentConfig();
        persistLog('Payment config received: ' + JSON.stringify(paymentConfig));

        // 检查配置是否有效
        if (!paymentConfig || !paymentConfig.merchant) {
            throw new Error(i18n.invalidMerchant || 'Invalid payment configuration: Missing Merchant ID');
        }
        
        if (!paymentConfig.apiKey) {
            throw new Error(i18n.invalidApiKey || 'Invalid payment configuration: Missing API Key, please check backend settings');
        }

        // 加载 PayFields 脚本
        await loadPayFieldsScript();
        persistLog('PayFields script loaded');

        // 配置 PayFields - 只设置必需的配置项
        PayFields.config.apiKey = paymentConfig.apiKey;
        PayFields.config.merchant = paymentConfig.merchant;
        
        // 设置金额（以分为单位）
        const amountValue = parseFloat(amount.value);
        PayFields.config.amount = Math.round(amountValue * 100);
        
        // 使用 txnToken 模式进行交易（直接支付）
        PayFields.config.mode = 'txnToken';
        PayFields.config.txnType = 'sale'; // 立即扣款
        
        persistLog('PayFields configured: mode=txnToken, amount=' + PayFields.config.amount);

        // 定义支付字段
        PayFields.fields = [
            { type: "number", element: "#card-number" },
            { type: "cvv", element: "#card-cvv" },
            { type: "name", element: "#card-name" },
            { type: "expiration", element: "#card-expiration" }
        ];
        
        persistLog('PayFields fields defined');

        // 成功回调
        PayFields.onSuccess = async function(response) {
            persistLog('\n========== PAYMENT SUCCESS CALLBACK ==========');
            persistLog('Full response: ' + JSON.stringify(response, null, 2));
            
            // 详细分析响应结构
            if (response) {
                persistLog('Response type: ' + typeof response);
                persistLog('Response keys: ' + Object.keys(response).join(', '));
                
                if (response.data && Array.isArray(response.data) && response.data[0]) {
                    persistLog('Transaction data keys: ' + Object.keys(response.data[0]).join(', '));
                    persistLog('Transaction data: ' + JSON.stringify(response.data[0], null, 2));
                }
            }
            
            // 提取交易ID
            const transactionId = (response && response.data && response.data[0] && response.data[0].id) || response.id || '';
            persistLog('Transaction ID extracted: ' + transactionId);
            persistLog('========== END PAYMENT SUCCESS CALLBACK ==========\n');
            
            // 处理支付成功
            handlePaymentSuccess(response, transactionId);
        };

        // 失败回调
        PayFields.onFailure = function(response) {
            persistLog('支付失败回调: ' + JSON.stringify(response), 'ERROR');
            handlePaymentFailure(response);
        };
        
        // 验证失败回调
        PayFields.onValidationFailure = function() {
            persistLog('表单验证失败', 'ERROR');
            showError(i18n.checkPaymentInfo || 'Please check your payment information');
            const submitButton = document.getElementById('submit-payment');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = i18n.submitPayment || 'Submit Payment';
            }
        };
        
        persistLog('PayFields callbacks registered');
        
        // 显示支付表单
        const paymentForm = document.getElementById('payment-form');
        const loadingMessage = document.getElementById('loading-message');
        
        if (paymentForm) {
            paymentForm.style.display = 'block';
            persistLog('Payment form displayed');
        }
        
        if (loadingMessage) {
            loadingMessage.style.display = 'none';
            persistLog('Loading message hidden');
        }
        
        // 绑定提交按钮
        const submitButton = document.getElementById('submit-payment');
        if (submitButton) {
            submitButton.addEventListener('click', (e) => {
                e.preventDefault();
                persistLog('Submit button clicked');
                
                // 直接提交，不再动态修改 PayFields 配置
                // 是否保存 token 将在支付成功回调中根据复选框状态处理
                submitPayment();
            });
            persistLog('Submit button event bound');
        }

    } catch (error) {
        persistLog('Error initializing PayFields form: ' + error.message, 'ERROR');
        throw error;
    }
}

