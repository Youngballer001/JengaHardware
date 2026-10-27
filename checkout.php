<?php
/**
 * Jengo Hardware - Checkout Page
 * Handle order creation with delivery/pickup options
 */

session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo_hardware');

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

// Check if user is logged in (optional - can allow guest checkout)
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Get user details if logged in
$user_details = [];
if ($user_id) {
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch() ?: [];
}

// Calculate cart totals server-side
$cart_items = $_SESSION['cart'];
$subtotal = 0;
$total_items = 0;

// Validate cart items against database and calculate totals
$validated_cart = [];
foreach ($cart_items as $product_id => $item) {
    $stmt = $pdo->prepare("SELECT id, name, price, stock, sku FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product && $product['stock'] >= $item['quantity']) {
        // Use database price for security
        $validated_cart[$product_id] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'price' => $product['price'],
            'quantity' => $item['quantity'],
            'stock' => $product['stock'],
            'image' => $item['image']
        ];
        
        $subtotal += $product['price'] * $item['quantity'];
        $total_items += $item['quantity'];
    }
}

// If cart validation failed, update session cart
if (count($validated_cart) != count($cart_items)) {
    $_SESSION['cart'] = $validated_cart;
    $_SESSION['cart_error'] = "Some items were removed due to stock availability.";
}

// Delivery options and pricing
$delivery_options = [
    'pickup' => ['name' => 'Store Pickup', 'price' => 0, 'description' => 'Pick up from our store - Free'],
    'standard' => ['name' => 'Standard Delivery', 'price' => 300, 'description' => 'Delivery within 3-5 business days'],
    'express' => ['name' => 'Express Delivery', 'price' => 600, 'description' => 'Delivery within 1-2 business days'],
];

$selected_delivery = $_POST['delivery_method'] ?? 'pickup';
$delivery_fee = $delivery_options[$selected_delivery]['price'] ?? 0;
$total = $subtotal + $delivery_fee;

// CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

$csrf_token = generateCSRFToken();
$error = $_SESSION['checkout_error'] ?? '';
$success = $_SESSION['checkout_success'] ?? '';
unset($_SESSION['checkout_error'], $_SESSION['checkout_success']);

$page_title = "Checkout - Jengo Hardware";
include 'includes/header.php';
?>

<div class="checkout-page">
    <div class="container">
        <h1 class="page-title">Checkout</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['cart_error'])): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['cart_error']); ?></div>
            <?php unset($_SESSION['cart_error']); ?>
        <?php endif; ?>

        <div class="checkout-layout">
            <!-- Order Form -->
            <div class="checkout-form-section">
                <form action="actions.php" method="POST" id="checkoutForm" class="checkout-form">
                    <input type="hidden" name="action" value="create_order">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Customer Information -->
                    <div class="form-section">
                        <h2>Customer Information</h2>
                        
                        <?php if (!$is_logged_in): ?>
                            <div class="alert alert-info">
                                Have an account? <a href="auth.php?action=login&redirect=checkout.php">Login</a> for faster checkout
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_name">Full Name *</label>
                                <input type="text" id="customer_name" name="customer_name" 
                                       value="<?php echo htmlspecialchars($user_details['name'] ?? ''); ?>" 
                                       required class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer_email">Email Address *</label>
                                <input type="email" id="customer_email" name="customer_email" 
                                       value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>" 
                                       required class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="customer_phone">Phone Number *</label>
                                <input type="tel" id="customer_phone" name="customer_phone" 
                                       value="<?php echo htmlspecialchars($user_details['phone'] ?? ''); ?>" 
                                       required class="form-control" placeholder="+254 XXX XXX XXX">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delivery Method -->
                    <div class="form-section">
                        <h2>Delivery Method</h2>
                        
                        <div class="delivery-options">
                            <?php foreach ($delivery_options as $key => $option): ?>
                                <label class="delivery-option">
                                    <input type="radio" name="delivery_method" value="<?php echo $key; ?>" 
                                           <?php echo $selected_delivery == $key ? 'checked' : ''; ?>
                                           data-price="<?php echo $option['price']; ?>">
                                    <div class="delivery-option-content">
                                        <div class="delivery-option-header">
                                            <strong><?php echo $option['name']; ?></strong>
                                            <span class="delivery-price">
                                                <?php echo $option['price'] > 0 ? 'KSh ' . number_format($option['price'], 2) : 'FREE'; ?>
                                            </span>
                                        </div>
                                        <p class="delivery-description"><?php echo $option['description']; ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Shipping Address (show only if not pickup) -->
                    <div class="form-section" id="shippingAddressSection" style="display: <?php echo $selected_delivery == 'pickup' ? 'none' : 'block'; ?>;">
                        <h2>Shipping Address</h2>
                        
                        <div class="form-group">
                            <label for="address_line1">Street Address *</label>
                            <input type="text" id="address_line1" name="address_line1" 
                                   class="form-control" placeholder="House/Building number and street name">
                        </div>
                        
                        <div class="form-group">
                            <label for="address_line2">Apartment, Suite, etc. (optional)</label>
                            <input type="text" id="address_line2" name="address_line2" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City/Town *</label>
                                <input type="text" id="city" name="city" 
                                       class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" 
                                       class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pickup Information -->
                    <div class="form-section" id="pickupInfoSection" style="display: <?php echo $selected_delivery == 'pickup' ? 'block' : 'none'; ?>;">
                        <div class="info-box">
                            <h3>Store Pickup Information</h3>
                            <p><strong>Location:</strong> Jengo Hardware, Nairobi, Kenya</p>
                            <p><strong>Hours:</strong> Monday - Saturday, 8:00 AM - 6:00 PM</p>
                            <p><strong>Note:</strong> You will receive an email/SMS when your order is ready for pickup</p>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="form-section">
                        <h2>Payment Method</h2>
                        
                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="mpesa" checked>
                                <div class="payment-option-content">
                                    <strong>M-Pesa</strong>
                                    <p>Pay securely with M-Pesa</p>
                                </div>
                            </label>
                            
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="card">
                                <div class="payment-option-content">
                                    <strong>Credit/Debit Card</strong>
                                    <p>Pay with Visa, Mastercard</p>
                                </div>
                            </label>
                            
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="cash">
                                <div class="payment-option-content">
                                    <strong>Cash on Delivery/Pickup</strong>
                                    <p>Pay when you receive your order</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Order Notes -->
                    <div class="form-section">
                        <h2>Order Notes (Optional)</h2>
                        <div class="form-group">
                            <textarea id="order_notes" name="order_notes" rows="4" 
                                      class="form-control" 
                                      placeholder="Any special instructions for your order?"></textarea>
                        </div>
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="form-section">
                        <label class="checkbox-label">
                            <input type="checkbox" name="agree_terms" required>
                            I agree to the <a href="#" target="_blank">Terms and Conditions</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        Place Order - KSh <span id="finalTotal"><?php echo number_format($total, 2); ?></span>
                    </button>
                </form>
            </div>
            
            <!-- Order Summary -->
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="summary-items">
                    <?php foreach ($validated_cart as $item): ?>
                        <div class="summary-item">
                            <div class="summary-item-image">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/placeholder.jpg" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="summary-item-details">
                                <div class="summary-item-name">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </div>
                                <div class="summary-item-meta">
                                    Qty: <?php echo $item['quantity']; ?> × 
                                    KSh <?php echo number_format($item['price'], 2); ?>
                                </div>
                            </div>
                            <div class="summary-item-total">
                                KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-calculations">
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $total_items; ?> items)</span>
                        <span id="subtotalAmount">KSh <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Delivery Fee</span>
                        <span id="deliveryAmount">
                            <?php echo $delivery_fee > 0 ? 'KSh ' . number_format($delivery_fee, 2) : 'FREE'; ?>
                        </span>
                    </div>
                    <div class="summary-divider"></div>
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span id="totalAmount">KSh <?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
                
                <div class="secure-checkout">
                    <p>🔒 Secure Checkout</p>
                    <p class="text-muted">Your information is protected</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.checkout-page {
    padding: 30px 0;
}

.checkout-layout {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 40px;
}

.form-section {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.form-section h2 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #2d3748;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #2d3748;
}

.delivery-options,
.payment-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.delivery-option,
.payment-option {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.delivery-option:has(input:checked),
.payment-option:has(input:checked) {
    border-color: #667eea;
    background: #f7fafc;
}

.delivery-option input,
.payment-option input {
    margin-right: 15px;
    margin-top: 3px;
}

.delivery-option-content,
.payment-option-content {
    flex: 1;
}

.delivery-option-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.delivery-price {
    font-weight: 600;
    color: #667eea;
}

.delivery-description,
.payment-option-content p {
    font-size: 14px;
    color: #718096;
    margin: 0;
}

.info-box {
    background: #e7f3ff;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #4299e1;
}

.info-box h3 {
    font-size: 16px;
    margin-bottom: 10px;
}

.info-box p {
    margin: 5px 0;
    font-size: 14px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    font-size: 14px;
}

.checkbox-label input {
    margin-right: 8px;
}

.order-summary {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    height: fit-content;
    position: sticky;
    top: 100px;
}

.order-summary h2 {
    font-size: 20px;
    margin-bottom: 20px;
}

.summary-items {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #e2e8f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-item-image {
    width: 60px;
    height: 60px;
    border-radius: 5px;
    overflow: hidden;
    background: #f7fafc;
    flex-shrink: 0;
}

.summary-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.summary-item-details {
    flex: 1;
}

.summary-item-name {
    font-weight: 500;
    margin-bottom: 5px;
}

.summary-item-meta {
    font-size: 14px;
    color: #718096;
}

.summary-item-total {
    font-weight: 600;
    color: #667eea;
}

.summary-calculations {
    border-top: 1px solid #e2e8f0;
    padding-top: 20px;
}

.secure-checkout {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}

.secure-checkout p:first-child {
    font-weight: 600;
    color: #48bb78;
}

@media (max-width: 992px) {
    .checkout-layout {
        grid-template-columns: 1fr;
    }
    
    .order-summary {
        position: static;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Update totals when delivery method changes
document.querySelectorAll('input[name="delivery_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const deliveryPrice = parseFloat(this.dataset.price);
        const subtotal = <?php echo $subtotal; ?>;
        const total = subtotal + deliveryPrice;
        
        // Update delivery amount
        document.getElementById('deliveryAmount').textContent = 
            deliveryPrice > 0 ? 'KSh ' + deliveryPrice.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') : 'FREE';
        
        // Update total
        document.getElementById('totalAmount').textContent = 
            'KSh ' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        document.getElementById('finalTotal').textContent = 
            total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        
        // Show/hide address section
        const isPickup = this.value === 'pickup';
        document.getElementById('shippingAddressSection').style.display = isPickup ? 'none' : 'block';
        document.getElementById('pickupInfoSection').style.display = isPickup ? 'block' : 'none';
        
        // Toggle required attributes on address fields
        document.querySelectorAll('#shippingAddressSection input[required], #shippingAddressSection [data-required]').forEach(field => {
            if (isPickup) {
                field.removeAttribute('required');
                field.dataset.required = 'true';
            } else if (field.dataset.required) {
                field.setAttribute('required', 'required');
            }
        });
    });
});

// Form validation
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked').value;
    
    if (deliveryMethod !== 'pickup') {
        const address = document.getElementById('address_line1').value.trim();
        const city = document.getElementById('city').value.trim();
        
        if (!address || !city) {
            e.preventDefault();
            showToast('Please fill in the shipping address', 'error');
            return false;
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
