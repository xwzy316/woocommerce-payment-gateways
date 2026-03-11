/**
 * WorldPay Hosted Payment Block Integration
 */

// Check dependencies
let deps_ok = true;
if (typeof window.wc === 'undefined') {
    window.console.error('[WorldPay Blocks] ERROR: window.wc is not defined');
    deps_ok = false;
} else if (typeof window.wc.wcBlocksRegistry === 'undefined') {
    window.console.error('[WorldPay Blocks] ERROR: window.wc.wcBlocksRegistry is not defined');
    deps_ok = false;
} else if (typeof window.wc.wcSettings === 'undefined') {
    window.console.error('[WorldPay Blocks] ERROR: window.wc.wcSettings is not defined');
    deps_ok = false;
} else if (typeof window.wp === 'undefined') {
    window.console.error('[WorldPay Blocks] ERROR: window.wp is not defined');
    deps_ok = false;
}

if (!deps_ok) {
    window.console.error('[WorldPay Blocks] Dependencies failed to load. Cannot register payment method.');
} else {
    try {
        const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
        const { getSetting } = window.wc.wcSettings;
        const { decodeEntities } = window.wp.htmlEntities;
        
        const settings = getSetting('worldpay_hosted_data', {});
        
        const defaultLabel = decodeEntities(settings.title) || 'WorldPay Hosted Payment';
        const defaultDescription = decodeEntities(settings.description) || 'Pay securely with WorldPay Hosted payment gateway.';
        
        /**
         * Content component
         */
        const Content = () => {
            return decodeEntities(defaultDescription || '');
        };
        
        /**
         * Label component
         */
        const Label = (props) => {
            const { PaymentMethodLabel } = props.components;
            const icon = settings.icon;
            
            // If icon URL exists, render as HTML element
            if (icon) {
                return React.createElement(
                    'div',
                    { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
                    React.createElement(PaymentMethodLabel, { text: defaultLabel }),
                    React.createElement('img', {
                        src: icon,
                        alt: defaultLabel,
                        style: { 
                            maxHeight: '40px',
                            marginLeft: '10px'
                        }
                    })
                );
            }
            
            return React.createElement(PaymentMethodLabel, { text: defaultLabel });
        };
        
        /**
         * WorldPay Hosted payment method config object.
         */
        const WorldPayHostedPaymentMethod = {
            name: 'worldpay_hosted',
            label: React.createElement(Label, null),
            content: React.createElement(Content, null),
            edit: React.createElement(Content, null),
            canMakePayment: () => true,
            ariaLabel: defaultLabel,
            supports: {
                features: settings.supports || [],
            },
        };
        
        // Register payment method
        registerPaymentMethod(WorldPayHostedPaymentMethod);
        
    } catch (error) {
        window.console.error('[WorldPay Blocks] Error during payment method registration:', error);
        window.console.error('[WorldPay Blocks] Error message:', error.message);
        window.console.error('[WorldPay Blocks] Stack trace:', error.stack);
    }
}