<?php
/**
 * Paystack JavaScript Helper
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Provides JavaScript utilities for Paystack integration
 */

?>
<script>
/**
 * Paystack JavaScript Helper Functions
 */
const PaystackHelper = {
    /**
     * Initialize a payment
     * @param {Object} options - Payment options
     * @returns {Promise} Payment result
     */
    async initializePayment(options) {
        try {
            const response = await fetch('api/paystack/initialize.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(options)
            });
            
            const result = await response.json();
            
            if (result.success && result.data) {
                return {
                    success: true,
                    authorizationUrl: result.data.authorization_url,
                    reference: result.data.reference
                };
            } else {
                throw new Error(result.error || 'Payment initialization failed');
            }
        } catch (error) {
            console.error('Payment initialization error:', error);
            return {
                success: false,
                error: error.message
            };
        }
    },
    
    /**
     * Verify a payment
     * @param {string} reference - Payment reference
     * @returns {Promise} Verification result
     */
    async verifyPayment(reference) {
        try {
            const response = await fetch('api/paystack/verify.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reference })
            });
            
            const result = await response.json();
            
            if (result.success && result.data) {
                return {
                    success: true,
                    data: result.data
                };
            } else {
                throw new Error(result.error || 'Payment verification failed');
            }
        } catch (error) {
            console.error('Payment verification error:', error);
            return {
                success: false,
                error: error.message
            };
        }
    },
    
    /**
     * Create a subscription
     * @param {Object} options - Subscription options
     * @returns {Promise} Subscription result
     */
    async createSubscription(options) {
        try {
            const response = await fetch('api/paystack/subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(options)
            });
            
            const result = await response.json();
            
            if (result.success && result.data) {
                return {
                    success: true,
                    authorizationUrl: result.data.authorization_url,
                    reference: result.data.reference
                };
            } else {
                throw new Error(result.error || 'Subscription creation failed');
            }
        } catch (error) {
            console.error('Subscription creation error:', error);
            return {
                success: false,
                error: error.message
            };
        }
    },
    
    /**
     * Format currency amount
     * @param {number} amount - Amount
     * @param {string} currency - Currency code
     * @returns {string} Formatted amount
     */
    formatAmount(amount, currency = 'USD') {
        const symbols = {
            'USD': '$',
            'NGN': '₦',
            'GHS': 'GH₵',
            'EUR': '€',
            'GBP': '£'
        };
        
        const symbol = symbols[currency] || currency + ' ';
        const formatted = parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        return symbol + formatted;
    },
    
    /**
     * Get currency symbol
     * @param {string} currency - Currency code
     * @returns {string} Currency symbol
     */
    getCurrencySymbol(currency) {
        const symbols = {
            'USD': '$',
            'NGN': '₦',
            'GHS': 'GH₵',
            'EUR': '€',
            'GBP': '£'
        };
        
        return symbols[currency] || currency;
    },
    
    /**
     * Show payment success message
     * @param {Object} data - Payment data
     */
    showPaymentSuccess(data) {
        const modal = document.getElementById('payment-success');
        if (modal) {
            modal.style.display = 'block';
            document.getElementById('payment-form-container').style.display = 'none';
            document.getElementById('payment-processing').style.display = 'none';
            document.getElementById('payment-error').style.display = 'none';
        }
    },
    
    /**
     * Show payment error message
     * @param {string} message - Error message
     */
    showPaymentError(message) {
        const modal = document.getElementById('payment-error');
        if (modal) {
            document.getElementById('error-message').textContent = message;
            modal.style.display = 'block';
            document.getElementById('payment-form-container').style.display = 'none';
            document.getElementById('payment-processing').style.display = 'none';
            document.getElementById('payment-success').style.display = 'none';
        }
    },
    
    /**
     * Show processing state
     */
    showProcessing() {
        const processing = document.getElementById('payment-processing');
        if (processing) {
            processing.style.display = 'block';
            document.getElementById('payment-form-container').style.display = 'none';
            document.getElementById('payment-success').style.display = 'none';
            document.getElementById('payment-error').style.display = 'none';
        }
    },
    
    /**
     * Reset payment form
     */
    resetPaymentForm() {
        const form = document.getElementById('paystack-payment-form');
        if (form) {
            form.reset();
        }
        
        document.getElementById('payment-form-container').style.display = 'block';
        document.getElementById('payment-processing').style.display = 'none';
        document.getElementById('payment-success').style.display = 'none';
        document.getElementById('payment-error').style.display = 'none';
    }
};

// Expose globally
window.PaystackHelper = PaystackHelper;

// Handle return from Paystack checkout
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const reference = urlParams.get('reference');
    const trxref = urlParams.get('trxref');
    
    if (reference || trxref) {
        // Verify payment on return
        const ref = reference || trxref;
        PaystackHelper.verifyPayment(ref).then(result => {
            if (result.success) {
                PaystackHelper.showPaymentSuccess(result.data);
            } else {
                PaystackHelper.showPaymentError(result.error);
            }
        });
    }
});
</script>