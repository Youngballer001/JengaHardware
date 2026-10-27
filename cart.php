<?php
/**
 * Jengo Hardware - Shopping Cart
 * View and manage cart items
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

// Note: Cart operations are now handled by actions.php

// Get cart items
$cart_items = $_SESSION['cart'] ?? [];
$cart_total = 0;
$cart_count = 0;

foreach ($cart_items as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_count += $item['quantity'];
}

$page_title = "Shopping Cart - Jengo Hardware";
include 'includes/header.php';
?>

<div class="cart-page">
    <div class="container">
        <h1 class="page-title">Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">🛒</div>
                <h2>Your cart is empty</h2>
                <p>Add some products to get started!</p>
                <a href="shop.php" class="btn btn-primary btn-lg">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items-section">
                    <div class="cart-header">
                        <h2>Cart Items (<?php echo $cart_count; ?>)</h2>
                        <button onclick="clearCart()" class="btn btn-outline btn-sm">Clear Cart</button>
                    </div>

                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item" data-product-id="<?php echo $item['id']; ?>">
                                <div class="cart-item-image">
                                    <a href="product.php?id=<?php echo $item['id']; ?>">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="uploads/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <img src="assets/images/placeholder.jpg" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php endif; ?>
                                    </a>
                                </div>

                                <div class="cart-item-details">
                                    <h3 class="cart-item-name">
                                        <a href="product.php?id=<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </a>
                                    </h3>
                                    <div class="cart-item-price">
                                        KSh <?php echo number_format($item['price'], 2); ?>
                                    </div>
                                </div>

                                <div class="cart-item-quantity">
                                    <div class="quantity-selector">
                                        <button type="button" class="qty-btn" 
                                                onclick="updateCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">
                                            -
                                        </button>
                                        <input type="number" value="<?php echo $item['quantity']; ?>" 
                                               min="1" class="qty-input" readonly>
                                        <button type="button" class="qty-btn" 
                                                onclick="updateCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                                            +
                                        </button>
                                    </div>
                                </div>

                                <div class="cart-item-subtotal">
                                    <div class="subtotal-amount">
                                        KSh <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                    <button class="btn-remove" onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $cart_count; ?> items)</span>
                        <span>KSh <?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span class="text-muted">Calculated at checkout</span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span>KSh <?php echo number_format($cart_total, 2); ?></span>
                    </div>

                    <div class="summary-actions">
                        <a href="checkout.php" class="btn btn-primary btn-block btn-lg">
                            Proceed to Checkout
                        </a>
                        <a href="shop.php" class="btn btn-outline btn-block">
                            Continue Shopping
                        </a>
                    </div>

                    <div class="payment-methods">
                        <p class="text-muted text-center">We accept:</p>
                        <div class="payment-icons">
                            <span>💳 M-Pesa</span>
                            <span>💳 Card</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
