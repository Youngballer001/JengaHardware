<?php
/**
 * Jengo Hardware - Home Page
 * Featured products, categories, and hero section
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

// Fetch featured products (limit 8)
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.stock > 0
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$featured_products = $stmt->fetchAll();

// Fetch categories
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmt->fetchAll();

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

$page_title = "Home - Jengo Hardware";
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Welcome to Jengo Hardware</h1>
            <p class="hero-subtitle">Your one-stop shop for quality hardware products</p>
            <div class="hero-buttons">
                <a href="shop.php" class="btn btn-primary btn-lg">Shop Now</a>
                <a href="shop.php#categories" class="btn btn-outline btn-lg">Browse Categories</a>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="categories-section">
    <div class="container">
        <h2 class="section-title">Shop by Category</h2>
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <a href="shop.php?category=<?php echo $category['id']; ?>" class="category-card">
                    <div class="category-icon">
                        <i class="icon-<?php echo strtolower(str_replace(' ', '-', $category['name'])); ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                    <p><?php echo htmlspecialchars($category['description'] ?? 'Browse products'); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="shop.php" class="btn btn-secondary">View All Products</a>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="featured-products">
    <div class="container">
        <h2 class="section-title">Featured Products</h2>
        <div class="products-grid">
            <?php foreach ($featured_products as $product): ?>
                <div class="product-card">
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="product-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <img src="assets/images/placeholder.jpg" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php endif; ?>
                        <?php if ($product['stock'] < 10): ?>
                            <span class="badge badge-warning">Low Stock</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <div class="product-category">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?>
                        </div>
                        <h3 class="product-name">
                            <a href="product.php?id=<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </a>
                        </h3>
                        <p class="product-description">
                            <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 80)); ?>...
                        </p>
                        <div class="product-footer">
                            <div class="product-price">
                                KSh <?php echo number_format($product['price'], 2); ?>
                            </div>
                            <button class="btn btn-sm btn-primary add-to-cart" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-product-price="<?php echo $product['price']; ?>">
                                <i class="cart-icon"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="features-grid">
            <div class="feature">
                <div class="feature-icon">🚚</div>
                <h3>Fast Delivery</h3>
                <p>Quick and reliable delivery to your doorstep</p>
            </div>
            <div class="feature">
                <div class="feature-icon">💯</div>
                <h3>Quality Products</h3>
                <p>Only the best hardware products</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🔒</div>
                <h3>Secure Payment</h3>
                <p>Safe and secure payment options</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🎯</div>
                <h3>Expert Support</h3>
                <p>Professional customer service</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
