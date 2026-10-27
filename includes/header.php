<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Jengo Hardware - Quality hardware products for all your needs">
    <title><?php echo $page_title ?? 'Jengo Hardware'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-top">
            <div class="container">
                <div class="header-info">
                    <span>📞 Contact: +254 XXX XXX XXX</span>
                    <span>📧 Email: info@jengohardware.com</span>
                </div>
                <div class="header-auth">
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="auth.php?action=logout">Logout</a>
                    <?php else: ?>
                        <a href="auth.php?action=login">Login</a>
                        <a href="auth.php?action=register">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="header-main">
            <div class="container">
                <div class="header-content">
                    <div class="logo">
                        <a href="index.php">
                            <h1>🔧 Jengo Hardware</h1>
                        </a>
                    </div>

                    <form action="shop.php" method="GET" class="header-search">
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>

                    <div class="header-actions">
                        <a href="cart.php" class="cart-btn">
                            🛒 Cart 
                            <span class="cart-count" id="cartCount"><?php echo $cart_count ?? 0; ?></span>
                        </a>
                    </div>

                    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
                </div>
            </div>
        </div>

        <nav class="main-nav" id="mainNav">
            <div class="container">
                <ul class="nav-menu">
                    <li><a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="shop.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop.php' ? 'active' : ''; ?>">Shop</a></li>
                    <li><a href="cart.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">Cart</a></li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                        <li><a href="dashboard.php">My Account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main class="main-content">
