<!-- Order Status Modal -->
<div id="orderStatusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Order Status</h2>
            <span class="modal-close" onclick="closeModal('orderStatusModal')">&times;</span>
        </div>
        <form method="POST" action="attendant-actions.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="order_id" id="orderStatusId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Order Number</label>
                    <p id="orderStatusNumber" class="form-text-display"></p>
                </div>
                
                <div class="form-group">
                    <label>Customer</label>
                    <p id="orderCustomerName" class="form-text-display"></p>
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
                    <small class="form-text">Select the new status for this order</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('orderStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
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
    max-width: 500px;
    animation: modalSlideIn 0.3s ease-out;
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

.form-text-display {
    font-weight: 600;
    color: #2d3748;
    font-size: 16px;
    margin: 5px 0;
}

.form-text {
    display: block;
    margin-top: 5px;
    color: #718096;
    font-size: 13px;
}

@media (max-width: 768px) {
    .modal-content {
        width: 95%;
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

// Order status functions
function openOrderStatusModal(orderId, orderNumber, customerName, currentStatus) {
    document.getElementById('orderStatusId').value = orderId;
    document.getElementById('orderStatusNumber').textContent = orderNumber;
    document.getElementById('orderCustomerName').textContent = customerName || 'N/A';
    document.getElementById('orderStatus').value = currentStatus;
    openModal('orderStatusModal');
}
</script>
