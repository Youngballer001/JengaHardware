/**
 * Jengo Hardware - Main JavaScript Application
 * Handles cart operations, UI interactions, and AJAX requests
 */

// Toast notification function
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => container.removeChild(toast), 300);
    }, 3000);
}

// Update cart count in header
function updateCartCount() {
    const actionsUrl = document.querySelector('meta[name="actions-url"]')?.content || 'actions.php';
    fetch(`${actionsUrl}?action=get_cart_count`)
        .then(response => response.json())
        .then(data => {
            const cartCountElement = document.getElementById('cartCount');
            if (cartCountElement && data.data && data.data.count !== undefined) {
                cartCountElement.textContent = data.data.count;
            }
        })
        .catch(error => console.error('Error updating cart count:', error));
}

function postCartAction(action, productId, quantity) {
    const actionsUrl = document.querySelector('meta[name="actions-url"]')?.content || 'actions.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('csrf_token', csrfToken);
    if (productId !== undefined) formData.append('product_id', productId);
    if (quantity !== undefined) formData.append('quantity', quantity);

    return fetch(actionsUrl, {
        method: 'POST',
        body: formData
    }).then(response => response.json());
}

// Add to cart function
function addToCart(productId, productName, productPrice, quantity = 1) {
    postCartAction('add_to_cart', productId, quantity)
    .then(data => {
        if (data.success) {
            showToast(`${productName} added to cart!`, 'success');
            const cartCountElement = document.getElementById('cartCount');
            if (cartCountElement && data.data && data.data.cart_count !== undefined) {
                cartCountElement.textContent = data.data.cart_count;
            } else {
                updateCartCount();
            }
        } else {
            showToast(data.message || 'Failed to add item to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
    });
}

// Update cart quantity
function updateCartQuantity(productId, quantity) {
    if (quantity < 1) {
        if (confirm('Remove this item from cart?')) {
            removeFromCart(productId);
        }
        return;
    }
    
    postCartAction('update_cart', productId, quantity)
    .then(data => {
        if (data.success) {
            location.reload(); // Reload to update totals
        } else {
            showToast(data.message || 'Failed to update quantity', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
    });
}

// Remove from cart
function removeFromCart(productId) {
    postCartAction('remove_from_cart', productId)
    .then(data => {
        if (data.success) {
            showToast('Item removed from cart', 'success');
            location.reload();
        } else {
            showToast(data.message || 'Failed to remove item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
    });
}

// Clear entire cart
function clearCart() {
    if (!confirm('Are you sure you want to clear your cart?')) {
        return;
    }
    
    postCartAction('clear_cart')
    .then(data => {
        if (data.success) {
            showToast('Cart cleared', 'success');
            location.reload();
        } else {
            showToast(data.message || 'Failed to clear cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred', 'error');
    });
}

// Mobile menu toggle
function toggleMobileMenu() {
    const nav = document.getElementById('mainNav');
    nav.classList.toggle('active');
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const toggleButton = event.target.closest('[data-password-toggle]');
    if (toggleButton) {
        const passwordInput = document.getElementById(toggleButton.dataset.passwordToggle);
        if (passwordInput) {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            toggleButton.textContent = showPassword ? 'Hide' : 'Show';
            toggleButton.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
            toggleButton.setAttribute('aria-label', `${showPassword ? 'Hide' : 'Show'} ${passwordInput.labels[0]?.textContent || 'password'}`);
        }
    }

    const nav = document.getElementById('mainNav');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    
    if (nav && menuBtn && !nav.contains(event.target) && !menuBtn.contains(event.target)) {
        nav.classList.remove('active');
    }
});

// Initialize event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart, .add-to-cart-detail').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = this.getAttribute('data-product-price');
            const quantityInput = document.getElementById('quantity');
            const quantity = this.classList.contains('add-to-cart-detail') && quantityInput
                ? parseInt(quantityInput.value, 10) || 1
                : 1;
            addToCart(productId, productName, productPrice, quantity);
        });
    });

    // Keep checkout delivery totals and address requirements in sync.
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        const subtotal = parseFloat(checkoutForm.dataset.subtotal) || 0;
        const addressSection = document.getElementById('shippingAddressSection');
        const pickupSection = document.getElementById('pickupInfoSection');
        const deliveryAmount = document.getElementById('deliveryAmount');
        const totalAmount = document.getElementById('totalAmount');
        const finalTotal = document.getElementById('finalTotal');
        const currency = amount => `KSh ${amount.toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;

        const updateDelivery = () => {
            const selected = checkoutForm.querySelector('input[name="delivery_method"]:checked');
            const fee = selected ? parseFloat(selected.dataset.price) || 0 : 0;
            const needsAddress = selected && selected.value !== 'pickup';

            if (addressSection) addressSection.style.display = needsAddress ? 'block' : 'none';
            if (pickupSection) pickupSection.style.display = needsAddress ? 'none' : 'block';
            ['address_line1', 'city'].forEach(name => {
                const field = checkoutForm.elements.namedItem(name);
                if (field) field.required = Boolean(needsAddress);
            });
            if (deliveryAmount) deliveryAmount.textContent = fee ? currency(fee) : 'FREE';
            if (totalAmount) totalAmount.textContent = currency(subtotal + fee);
            if (finalTotal) finalTotal.textContent = (subtotal + fee).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        checkoutForm.querySelectorAll('input[name="delivery_method"]').forEach(input => {
            input.addEventListener('change', updateDelivery);
        });
        updateDelivery();
    }
    
    // Quantity input validation
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 999;
            let value = parseInt(this.value);
            
            if (value < min) this.value = min;
            if (value > max) this.value = max;
        });
    });
    
    // Search form enhancement
    const searchForm = document.querySelector('.header-search');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput && !searchInput.value.trim()) {
                e.preventDefault();
                showToast('Please enter a search term', 'warning');
            }
        });
    }
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
    
    // Image lazy loading fallback
    const images = document.querySelectorAll('img[data-src]');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for browsers without IntersectionObserver
        images.forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
    }
    
    // Form validation helper
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Please fill in all required fields', 'error');
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
    
});

// Handle window resize for responsive adjustments
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        // Close mobile menu on desktop
        if (window.innerWidth > 768) {
            const nav = document.getElementById('mainNav');
            if (nav) nav.classList.remove('active');
        }
    }, 250);
});

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Export functions for global access
window.addToCart = addToCart;
window.updateCartQuantity = updateCartQuantity;
window.removeFromCart = removeFromCart;
window.clearCart = clearCart;
window.toggleMobileMenu = toggleMobileMenu;
window.showToast = showToast;
