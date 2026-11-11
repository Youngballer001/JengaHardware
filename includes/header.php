<?php
$is_payment_page = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'payments';
$root_prefix = $is_payment_page ? '../' : '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Jengo Hardware - Quality hardware products for all your needs">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="actions-url" content="<?php echo $root_prefix; ?>actions.php">
    <title><?php echo $page_title ?? 'Jengo Hardware'; ?></title>
    <link rel="stylesheet" href="<?php echo $root_prefix; ?>assets/css/style.css?v=1.2.0">
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
                        <a href="<?php echo $root_prefix; ?>dashboard.php">Dashboard</a>
                        <a href="<?php echo $root_prefix; ?>auth.php?action=logout">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo $root_prefix; ?>auth.php?action=login">Login</a>
                        <a href="<?php echo $root_prefix; ?>auth.php?action=register">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="header-main">
            <div class="container">
                <div class="header-content">
                    <div class="logo">
                        <a href="<?php echo $root_prefix; ?>index.php">
                            <h1>🔧 Jengo Hardware</h1>
                        </a>
                    </div>

                    <form action="<?php echo $root_prefix; ?>shop.php" method="GET" class="header-search">
                        <input type="text" name="search" placeholder="Search products..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>

                    <div class="header-actions">
                        <a href="<?php echo $root_prefix; ?>cart.php" class="cart-btn">
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
                    <li><a href="<?php echo $root_prefix; ?>index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="<?php echo $root_prefix; ?>shop.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'shop.php' ? 'active' : ''; ?>">Shop</a></li>
                    <li><a href="<?php echo $root_prefix; ?>cart.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">Cart</a></li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                        <li><a href="<?php echo $root_prefix; ?>dashboard.php">My Account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
    </header>

    <main class="main-content">
        <?php if (!empty($_SESSION['redirect_message'])): ?>
            <div class="container">
                <div class="alert alert-<?php echo htmlspecialchars($_SESSION['redirect_type'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($_SESSION['redirect_message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            <?php unset($_SESSION['redirect_message'], $_SESSION['redirect_type']); ?>
        <?php endif; ?>
