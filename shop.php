<?php
/**
 * Jengo Hardware - Shop Page
 * Product browsing with categories, search, and filters
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

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($search_query) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
switch ($sort_by) {
    case 'price_low':
        $order_by = 'p.price ASC';
        break;
    case 'price_high':
        $order_by = 'p.price DESC';
        break;
    case 'name':
        $order_by = 'p.name ASC';
        break;
    case 'newest':
    default:
        $order_by = 'p.created_at DESC';
        break;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM products p WHERE {$where_clause}";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

// Fetch products
$sql = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE {$where_clause}
    ORDER BY {$order_by}
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch all categories for filter
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmt->fetchAll();

// Get current category name if filtered
$current_category_name = '';
if ($category_id) {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    $current_category_name = $category ? $category['name'] : '';
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

$page_title = "Shop - Jengo Hardware";
include 'includes/header.php';
?>

<div class="shop-page">
    <div class="container">
        <div class="shop-header">
            <div class="breadcrumb">
                <a href="index.php">Home</a> / <span>Shop</span>
                <?php if ($current_category_name): ?>
                    / <span><?php echo htmlspecialchars($current_category_name); ?></span>
                <?php endif; ?>
            </div>
            <h1>
                <?php 
                if ($search_query) {
                    echo "Search Results for: " . htmlspecialchars($search_query);
                } elseif ($current_category_name) {
                    echo htmlspecialchars($current_category_name);
                } else {
                    echo "All Products";
                }
                ?>
            </h1>
            <p class="result-count">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products</p>
        </div>

        <div class="shop-layout">
            <!-- Sidebar Filters -->
            <aside class="shop-sidebar">
                <div class="sidebar-section">
                    <h3>Search</h3>
                    <form action="shop.php" method="GET" class="search-form">
                        <?php if ($category_id): ?>
                            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                        <?php endif; ?>
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($search_query); ?>" class="form-control">
                        <button type="submit" class="btn btn-primary btn-block">Search</button>
                    </form>
                </div>

                <div class="sidebar-section">
                    <h3>Categories</h3>
                    <ul class="category-list">
                        <li>
                            <a href="shop.php" class="<?php echo !$category_id ? 'active' : ''; ?>">
                                All Categories
                            </a>
                        </li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="shop.php?category=<?php echo $category['id']; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="<?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <h3>Sort By</h3>
                    <select id="sortSelect" class="form-control" onchange="updateSort(this.value)">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                    </select>
                </div>

                <?php if ($category_id || $search_query): ?>
                    <div class="sidebar-section">
                        <a href="shop.php" class="btn btn-outline btn-block">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </aside>

            <!-- Products Grid -->
            <div class="shop-content">
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <div class="no-products-icon">📦</div>
                        <h3>No products found</h3>
                        <p>Try adjusting your search or filters</p>
                        <a href="shop.php" class="btn btn-primary">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-image">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <img src="assets/images/placeholder.jpg" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php endif; ?>
                                    <?php if ($product['stock'] == 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php elseif ($product['stock'] < 10): ?>
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
                                        <?php if ($product['stock'] > 0): ?>
                                            <button class="btn btn-sm btn-primary add-to-cart" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                    data-product-price="<?php echo $product['price']; ?>">
                                                <i class="cart-icon"></i> Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                                   class="pagination-btn">Previous</a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="pagination-btn active"><?php echo $i; ?></span>
                                <?php elseif ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                                       class="pagination-btn"><?php echo $i; ?></a>
                                <?php elseif (abs($i - $page) == 3): ?>
                                    <span class="pagination-dots">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search_query ? '&search=' . urlencode($search_query) : ''; ?>&sort=<?php echo $sort_by; ?>" 
                                   class="pagination-btn">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateSort(sortValue) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('sort', sortValue);
    urlParams.set('page', '1'); // Reset to first page
    window.location.href = '?' + urlParams.toString();
}
</script>

<?php include 'includes/footer.php'; ?>
