<!-- Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 id="productModalTitle">Add Product</h2>
            <span class="modal-close" onclick="closeModal('productModal')">&times;</span>
        </div>
        <form id="productForm" method="POST" action="admin-actions.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" id="productAction" value="create_product">
            <input type="hidden" name="product_id" id="productId" value="">
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="productName">Product Name *</label>
                        <input type="text" id="productName" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="productSKU">SKU *</label>
                        <input type="text" id="productSKU" name="sku" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="productPrice">Price (KSh) *</label>
                        <input type="number" id="productPrice" name="price" class="form-control" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="productStock">Stock *</label>
                        <input type="number" id="productStock" name="stock" class="form-control" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="productCategory">Category</label>
                        <select id="productCategory" name="category_id" class="form-control">
                            <option value="">-- Select Category --</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC");
                            while ($cat = $stmt->fetch()):
                            ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="productStatus">Status *</label>
                        <select id="productStatus" name="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="productDescription">Description</label>
                    <textarea id="productDescription" name="description" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="productImage">Product Image</label>
                    <input type="file" id="productImage" name="image" class="form-control" accept="image/*">
                    <small class="form-text">Max size: 5MB. Formats: JPG, PNG, GIF, WebP</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="categoryModalTitle">Add Category</h2>
            <span class="modal-close" onclick="closeModal('categoryModal')">&times;</span>
        </div>
        <form id="categoryForm" method="POST" action="admin-actions.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" id="categoryAction" value="create_category">
            <input type="hidden" name="category_id" id="categoryId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="categoryName">Category Name *</label>
                    <input type="text" id="categoryName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="categoryDescription">Description</label>
                    <textarea id="categoryDescription" name="description" class="form-control" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="categoryStatus">Status *</label>
                    <select id="categoryStatus" name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Order Status Modal -->
<div id="orderStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Order Status</h2>
            <span class="modal-close" onclick="closeModal('orderStatusModal')">&times;</span>
        </div>
        <form method="POST" action="admin-actions.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="order_id" id="orderStatusId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Order Number</label>
                    <p id="orderStatusNumber" class="form-text-display"></p>
                </div>
                
                <div class="form-group">
                    <label for="orderStatus">New Status *</label>
                    <select id="orderStatus" name="status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('orderStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Payment Status Modal -->
<div id="paymentStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Payment Status</h2>
            <span class="modal-close" onclick="closeModal('paymentStatusModal')">&times;</span>
        </div>
        <form method="POST" action="admin-actions.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_payment_status">
            <input type="hidden" name="order_id" id="paymentStatusOrderId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Order Number</label>
                    <p id="paymentStatusNumber" class="form-text-display"></p>
                </div>
                
                <div class="form-group">
                    <label for="paymentStatus">Payment Status *</label>
                    <select id="paymentStatus" name="payment_status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('paymentStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- User Status Modal -->
<div id="userStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update User Status</h2>
            <span class="modal-close" onclick="closeModal('userStatusModal')">&times;</span>
        </div>
        <form method="POST" action="admin-actions.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_user_status">
            <input type="hidden" name="user_id" id="userStatusId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>User</label>
                    <p id="userStatusName" class="form-text-display"></p>
                </div>
                
                <div class="form-group">
                    <label for="userStatus">Status *</label>
                    <select id="userStatus" name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('userStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- User Role Modal -->
<div id="userRoleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update User Role</h2>
            <span class="modal-close" onclick="closeModal('userRoleModal')">&times;</span>
        </div>
        <form method="POST" action="admin-actions.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_user_role">
            <input type="hidden" name="user_id" id="userRoleId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>User</label>
                    <p id="userRoleName" class="form-text-display"></p>
                </div>
                
                <div class="form-group">
                    <label for="userRole">Role *</label>
                    <select id="userRole" name="role" class="form-control" required>
                        <option value="customer">Customer</option>
                        <option value="attendant">Attendant</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('userRoleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

.modal-large {
    max-width: 900px;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 22px;
    color: #2d3748;
}

.modal-close {
    font-size: 32px;
    font-weight: bold;
    color: #a0aec0;
    cursor: pointer;
    line-height: 1;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #2d3748;
}

.modal-body {
    padding: 30px;
}

.modal-footer {
    padding: 20px 30px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-text-display {
    font-weight: 600;
    color: #2d3748;
    font-size: 16px;
}

@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        max-height: 95vh;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// Product functions
function openProductModal(mode = 'create', productId = null) {
    if (mode === 'create') {
        document.getElementById('productModalTitle').textContent = 'Add Product';
        document.getElementById('productAction').value = 'create_product';
        document.getElementById('productForm').reset();
        document.getElementById('productId').value = '';
    } else if (mode === 'edit' && productId) {
        document.getElementById('productModalTitle').textContent = 'Edit Product';
        document.getElementById('productAction').value = 'update_product';
        document.getElementById('productId').value = productId;
        loadProductData(productId);
    }
    openModal('productModal');
}

function loadProductData(productId) {
    fetch(`admin-actions.php?action=get_product&id=${productId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const product = result.data;
                document.getElementById('productName').value = product.name;
                document.getElementById('productSKU').value = product.sku;
                document.getElementById('productPrice').value = product.price;
                document.getElementById('productStock').value = product.stock;
                document.getElementById('productCategory').value = product.category_id || '';
                document.getElementById('productStatus').value = product.status;
                document.getElementById('productDescription').value = product.description || '';
            }
        })
        .catch(error => console.error('Error:', error));
}

// Category functions
function openCategoryModal(mode = 'create', categoryId = null) {
    if (mode === 'create') {
        document.getElementById('categoryModalTitle').textContent = 'Add Category';
        document.getElementById('categoryAction').value = 'create_category';
        document.getElementById('categoryForm').reset();
        document.getElementById('categoryId').value = '';
    } else if (mode === 'edit' && categoryId) {
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        document.getElementById('categoryAction').value = 'update_category';
        document.getElementById('categoryId').value = categoryId;
        loadCategoryData(categoryId);
    }
    openModal('categoryModal');
}

function loadCategoryData(categoryId) {
    fetch(`admin-actions.php?action=get_category&id=${categoryId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const category = result.data;
                document.getElementById('categoryName').value = category.name;
                document.getElementById('categoryDescription').value = category.description || '';
                document.getElementById('categoryStatus').value = category.status;
            }
        })
        .catch(error => console.error('Error:', error));
}

// Order status functions
function openOrderStatusModal(orderId, orderNumber, currentStatus) {
    document.getElementById('orderStatusId').value = orderId;
    document.getElementById('orderStatusNumber').textContent = orderNumber;
    document.getElementById('orderStatus').value = currentStatus;
    openModal('orderStatusModal');
}

function openPaymentStatusModal(orderId, orderNumber, currentStatus) {
    document.getElementById('paymentStatusOrderId').value = orderId;
    document.getElementById('paymentStatusNumber').textContent = orderNumber;
    document.getElementById('paymentStatus').value = currentStatus;
    openModal('paymentStatusModal');
}

// User functions
function openUserStatusModal(userId, userName, currentStatus) {
    document.getElementById('userStatusId').value = userId;
    document.getElementById('userStatusName').textContent = userName;
    document.getElementById('userStatus').value = currentStatus;
    openModal('userStatusModal');
}

function openUserRoleModal(userId, userName, currentRole) {
    document.getElementById('userRoleId').value = userId;
    document.getElementById('userRoleName').textContent = userName;
    document.getElementById('userRole').value = currentRole;
    openModal('userRoleModal');
}

// Delete confirmation
function confirmDelete(type, id, name) {
    if (confirm(`Are you sure you want to delete this ${type}: ${name}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'admin-actions.php';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = `delete_${type}`;
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = `${type}_id`;
        idInput.value = id;
        
        form.appendChild(csrfInput);
        form.appendChild(actionInput);
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
