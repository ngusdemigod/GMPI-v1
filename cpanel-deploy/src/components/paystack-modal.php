<?php
/**
 * Paystack Payment Modal Component
 * Bright Light Ministry Int'l Partners Portal
 * 
 * Provides a modal interface for Paystack payments
 */

?>

<!-- Paystack Payment Modal -->
<div id="paystack-payment-modal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h2>Complete Your Donation</h2>
            <button class="modal-close" onclick="closePaystackModal()">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Payment Form -->
            <div id="payment-form-container">
                <form id="paystack-payment-form">
                    <div class="form-group">
                        <label for="donation-amount">Amount</label>
                        <div class="amount-presets">
                            <button type="button" class="amount-btn" data-amount="10">$10</button>
                            <button type="button" class="amount-btn" data-amount="25">$25</button>
                            <button type="button" class="amount-btn" data-amount="50">$50</button>
                            <button type="button" class="amount-btn" data-amount="100">$100</button>
                            <button type="button" class="amount-btn" data-amount="custom">Custom</button>
                        </div>
                        <input type="number" id="donation-amount" name="amount" placeholder="Enter amount" min="1" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="donation-currency">Currency</label>
                        <select id="donation-currency" name="currency" required>
                            <option value="USD">$ - US Dollar</option>
                            <option value="NGN">N - Nigerian Naira</option>
                            <option value="GHS">GHS - Ghanaian Cedi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="donor-email">Email Address</label>
                        <input type="email" id="donor-email" name="email" placeholder="your@email.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="donor-name">Your Name (Optional)</label>
                        <input type="text" id="donor-name" name="first_name" placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="donation-category">Category</label>
                        <select id="donation-category" name="category">
                            <option value="General Offering">General Offering</option>
                            <option value="Tithes">Tithes</option>
                            <option value="Offerings">Offerings</option>
                            <option value="Building Fund">Building Fund</option>
                            <option value="Mission Fund">Mission Fund</option>
                            <option value="Children's Ministry">Children's Ministry</option>
                            <option value="Youth Ministry">Youth Ministry</option>
                            <option value="Worship & Praise">Worship & Praise</option>
                            <option value="Evangelism">Evangelism</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="donation-project">Project (Optional)</label>
                        <select id="donation-project" name="project_id">
                            <option value="">-- Any Project --</option>
                            <!-- Projects will be loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="donation-frequency">Frequency</label>
                        <select id="donation-frequency" name="frequency">
                            <option value="one-time">One-time</option>
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="donation-message">Message (Optional)</label>
                        <textarea id="donation-message" name="description" rows="3" placeholder="Add a message for the ministry..."></textarea>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="anonymous" value="1">
                            Make this donation anonymous
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closePaystackModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Proceed to Payment</button>
                    </div>
                </form>
            </div>
            
            <!-- Processing State -->
            <div id="payment-processing" style="display: none; text-align: center; padding: 40px;">
                <div class="spinner"></div>
                <p>Processing your payment...</p>
            </div>
            
            <!-- Success State -->
            <div id="payment-success" style="display: none; text-align: center; padding: 40px;">
                <div class="success-icon">✓</div>
                <h3>Payment Successful!</h3>
                <p>Thank you for your generous donation. A receipt has been sent to your email.</p>
                <button class="btn btn-primary" onclick="closePaystackModal()">Close</button>
            </div>
            
            <!-- Error State -->
            <div id="payment-error" style="display: none; text-align: center; padding: 40px;">
                <div class="error-icon">✕</div>
                <h3>Payment Failed</h3>
                <p id="error-message">An error occurred. Please try again.</p>
                <button class="btn btn-primary" onclick="retryPayment()">Try Again</button>
                <button class="btn btn-secondary" onclick="closePaystackModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 500px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e7d9b4;
}

.modal-header h2 {
    margin: 0;
    color: #0F1B2D;
    font-size: 20px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.modal-close:hover {
    color: #0F1B2D;
}

.modal-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #0F1B2D;
    font-weight: 500;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #C9A24B;
}

.amount-presets {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.amount-btn {
    padding: 8px 16px;
    border: 1px solid #C9A24B;
    background: #fff;
    color: #0F1B2D;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.amount-btn:hover {
    background: #C9A24B;
    color: #fff;
}

.amount-btn.active {
    background: #C9A24B;
    color: #fff;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
    width: auto;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.btn {
    flex: 1;
    padding: 14px 24px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #C9A24B 0%, #B89441 100%);
    color: #fff;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(201, 162, 75, 0.4);
}

.btn-secondary {
    background: #f5f5f5;
    color: #0F1B2D;
}

.btn-secondary:hover {
    background: #e7d9b4;
}

/* Processing, Success, Error States */
#payment-processing,
#payment-success,
#payment-error {
    padding: 60px 40px;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #C9A24B;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.success-icon {
    width: 80px;
    height: 80px;
    background: #5B7B6A;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    margin: 0 auto 20px;
}

.error-icon {
    width: 80px;
    height: 80px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    margin: 0 auto 20px;
}

#payment-success h3,
#payment-error h3 {
    margin: 0 0 10px;
}

#payment-success p,
#payment-error p {
    color: #666;
    margin: 0 0 24px;
}

#error-message {
    color: #dc3545;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 600px) {
    .modal-content {
        width: 95%;
        max-height: 95vh;
    }
    
    .amount-presets {
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Paystack Modal Functions
let currentProjectId = null;

function openPaystackModal(options = {}) {
    const modal = document.getElementById('paystack-payment-modal');
    const form = document.getElementById('paystack-payment-form');
    
    // Reset form
    form.reset();
    document.getElementById('payment-form-container').style.display = 'block';
    document.getElementById('payment-processing').style.display = 'none';
    document.getElementById('payment-success').style.display = 'none';
    document.getElementById('payment-error').style.display = 'none';
    
    // Set default values from options
    if (options.amount) {
        document.getElementById('donation-amount').value = options.amount;
    }
    if (options.currency) {
        document.getElementById('donation-currency').value = options.currency;
    }
    if (options.email) {
        document.getElementById('donor-email').value = options.email;
    }
    if (options.category) {
        document.getElementById('donation-category').value = options.category;
    }
    
    // Load projects if needed
    if (options.projectId) {
        currentProjectId = options.projectId;
        loadProjects();
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePaystackModal() {
    const modal = document.getElementById('paystack-payment-modal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// Amount preset selection
document.querySelectorAll('.amount-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const amount = this.dataset.amount;
        const amountInput = document.getElementById('donation-amount');
        
        if (amount === 'custom') {
            amountInput.focus();
        } else {
            amountInput.value = amount;
        }
    });
});

// Load projects
async function loadProjects() {
    const projectSelect = document.getElementById('donation-project');
    
    try {
        const response = await fetch('../api/projects.php');
        const projects = await response.json();
        
        projectSelect.innerHTML = '<option value="">-- Any Project --</option>';
        
        if (projects.success && projects.data) {
            projects.data.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = project.title;
                projectSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Failed to load projects:', error);
    }
}

// Form submission
document.getElementById('paystack-payment-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    // Show processing state
    document.getElementById('payment-form-container').style.display = 'none';
    document.getElementById('payment-processing').style.display = 'block';
    
    try {
        // Determine if subscription or one-time payment
        const isSubscription = data.frequency !== 'one-time';
        
        let response;
        if (isSubscription) {
            // Create subscription
            response = await fetch('../api/paystack/subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            // Initialize payment
            response = await fetch('../api/paystack/initialize.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }
        
        const result = await response.json();
        
        if (result.success && result.data) {
            // Redirect to Paystack checkout
            if (result.data.authorization_url) {
                window.location.href = result.data.authorization_url;
            } else {
                throw new Error('No authorization URL received');
            }
        } else {
            throw new Error(result.error || 'Payment initialization failed');
        }
        
    } catch (error) {
        console.error('Payment error:', error);
        
        document.getElementById('payment-processing').style.display = 'none';
        document.getElementById('payment-error').style.display = 'block';
        document.getElementById('error-message').textContent = error.message;
    }
});

// Retry payment
function retryPayment() {
    document.getElementById('payment-error').style.display = 'none';
    document.getElementById('payment-form-container').style.display = 'block';
}

// Close modal on overlay click
document.querySelector('.modal-overlay').addEventListener('click', closePaystackModal);

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePaystackModal();
    }
});

// Expose function globally
window.openPaystackModal = openPaystackModal;
window.closePaystackModal = closePaystackModal;
window.retryPayment = retryPayment;
</script>
