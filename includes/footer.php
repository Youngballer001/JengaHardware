    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-section">
                        <h3>Jengo Hardware</h3>
                        <p>Your trusted partner for quality hardware products. We provide the best products at competitive prices.</p>
                    </div>

                    <div class="footer-section">
                        <h3>Quick Links</h3>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="shop.php">Shop</a></li>
                            <li><a href="cart.php">Cart</a></li>
                            <li><a href="auth.php">Account</a></li>
                        </ul>
                    </div>

                    <div class="footer-section">
                        <h3>Categories</h3>
                        <ul class="footer-links">
                            <?php
                            if (isset($pdo)) {
                                $stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name LIMIT 5");
                                $footer_categories = $stmt->fetchAll();
                                foreach ($footer_categories as $cat) {
                                    echo '<li><a href="shop.php?category=' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</a></li>';
                                }
                            }
                            ?>
                        </ul>
                    </div>

                    <div class="footer-section">
                        <h3>Contact Us</h3>
                        <ul class="footer-contact">
                            <li>📍 Nairobi, Kenya</li>
                            <li>📞 +254 XXX XXX XXX</li>
                            <li>📧 info@jengohardware.com</li>
                            <li>🕒 Mon-Sat: 8AM - 6PM</li>
                        </ul>
                    </div>
                </div>

                <div class="footer-bottom">
                    <p>&copy; <?php echo date('Y'); ?> Jengo Hardware. All rights reserved.</p>
                    <div class="footer-social">
                        <a href="#" aria-label="Facebook">📘</a>
                        <a href="#" aria-label="Twitter">🐦</a>
                        <a href="#" aria-label="Instagram">📷</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Toast notification container -->
    <div id="toast-container"></div>

    <script src="assets/js/app.js"></script>
</body>
</html>
