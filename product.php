<?php
/**
 * Jengo Hardware - Product Detail Page
 * Detailed product view with images, description, and add to cart
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

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header("Location: shop.php");
    exit();
}

// Fetch product details
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.id as category_id
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: shop.php");
    exit();
}

// Fetch related products from same category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' AND p.stock > 0
    ORDER BY RAND()
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

$page_title = htmlspecialchars($product['name']) . " - Jengo Hardware";
include 'includes/header.php';
?>

<div class="product-detail-page">
    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Home</a> / 
            <a href="shop.php">Shop</a> / 
            <?php if ($product['category_name']): ?>
                <a href="shop.php?category=<?php echo $product['category_id']; ?>">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a> / 
            <?php endif; ?>
            <span><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <div class="product-detail">
            <div class="product-images">
                <div class="main-image">
                    <?php if (!empty($product['image'])): ?>
                        <img id="mainProductImage" src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                        <img id="mainProductImage" src="assets/images/placeholder.jpg" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php endif; ?>
                </div>
                <!-- Add thumbnail gallery here if multiple images available -->
            </div>

            <div class="product-details">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-meta">
                    <span class="product-sku">SKU: <?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></span>
                    <?php if ($product['category_name']): ?>
                        <span class="product-category-badge">
                            <a href="shop.php?category=<?php echo $product['category_id']; ?>">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </a>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="product-price-section">
                    <div class="product-price-large">
                        KSh <?php echo number_format($product['price'], 2); ?>
                    </div>
                    <div class="product-stock">
                        <?php if ($product['stock'] == 0): ?>
                            <span class="stock-status out-of-stock">Out of Stock</span>
                        <?php elseif ($product['stock'] < 10): ?>
                            <span class="stock-status low-stock">
                                Only <?php echo $product['stock']; ?> left in stock
                            </span>
                        <?php else: ?>
                            <span class="stock-status in-stock">In Stock</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="product-description">
                    <h3>Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?></p>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="product-actions">
                        <div class="quantity-selector">
                            <label>Quantity:</label>
                            <div class="quantity-input">
                                <button type="button" class="qty-btn" onclick="decreaseQuantity()">-</button>
                                <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" readonly>
                                <button type="button" class="qty-btn" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg add-to-cart-detail" 
                                data-product-id="<?php echo $product['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                data-product-price="<?php echo $product['price']; ?>">
                            <i class="cart-icon"></i> Add to Cart
                        </button>
                    </div>
                <?php else: ?>
                    <div class="product-actions">
                        <button class="btn btn-secondary btn-lg" disabled>Out of Stock</button>
                    </div>
                <?php endif; ?>

                <div class="product-info-cards">
                    <div class="info-card">
                        <div class="info-icon">🚚</div>
                        <div class="info-text">
                            <strong>Fast Delivery</strong>
                            <p>Quick shipping available</p>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-icon">🔒</div>
                        <div class="info-text">
                            <strong>Secure Payment</strong>
                            <p>Safe checkout process</p>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-icon">💯</div>
                        <div class="info-text">
                            <strong>Quality Guaranteed</strong>
                            <p>Authentic products only</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <section class="related-products">
                <h2 class="section-title">Related Products</h2>
                <div class="products-grid">
                    <?php foreach ($related_products as $related): ?>
                        <div class="product-card">
                            <a href="product.php?id=<?php echo $related['id']; ?>" class="product-image">
                                <?php if (!empty($related['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($related['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($related['name']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/placeholder.jpg" 
                                         alt="<?php echo htmlspecialchars($related['name']); ?>">
                                <?php endif; ?>
                            </a>
                            <div class="product-info">
                                <div class="product-category">
                                    <?php echo htmlspecialchars($related['category_name'] ?? 'General'); ?>
                                </div>
                                <h3 class="product-name">
                                    <a href="product.php?id=<?php echo $related['id']; ?>">
                                        <?php echo htmlspecialchars($related['name']); ?>
                                    </a>
                                </h3>
                                <div class="product-footer">
                                    <div class="product-price">
                                        KSh <?php echo number_format($related['price'], 2); ?>
                                    </div>
                                    <button class="btn btn-sm btn-primary add-to-cart" 
                                            data-product-id="<?php echo $related['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($related['name']); ?>"
                                            data-product-price="<?php echo $related['price']; ?>">
                                        <i class="cart-icon"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>

<script>
const maxStock = <?php echo $product['stock']; ?>;

function increaseQuantity() {
    const qtyInput = document.getElementById('quantity');
    let currentValue = parseInt(qtyInput.value);
    if (currentValue < maxStock) {
        qtyInput.value = currentValue + 1;
    }
}

function decreaseQuantity() {
    const qtyInput = document.getElementById('quantity');
    let currentValue = parseInt(qtyInput.value);
    if (currentValue > 1) {
        qtyInput.value = currentValue - 1;
    }
}

// Handle add to cart from detail page
document.querySelector('.add-to-cart-detail')?.addEventListener('click', function() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const productId = this.getAttribute('data-product-id');
    const productName = this.getAttribute('data-product-name');
    const productPrice = this.getAttribute('data-product-price');
    
    addToCart(productId, productName, productPrice, quantity);
});
</script>

<?php include 'includes/footer.php'; ?>
