<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get cart items
$cart_query = "SELECT c.*, p.name, p.price, p.stock, p.image_url
               FROM cart c
               JOIN products p ON c.product_id = p.id
               WHERE c.user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$user_id]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    $_SESSION['error'] = "Your cart is empty!";
    header("Location: cart.php");
    exit();
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax_rate = 0.10; // 10% tax
$tax_amount = $subtotal * $tax_rate;
$shipping_amount = $subtotal > 100 ? 0 : 10; // Free shipping over $100
$total = $subtotal + $tax_amount + $shipping_amount;

// Get user profile for default shipping address
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Safely get POST data with null coalescing
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        $payment_method = $_POST['payment_method'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if (empty($shipping_address)) {
            throw new Exception("Shipping address is required!");
        }
        
        if (empty($payment_method)) {
            throw new Exception("Payment method is required!");
        }
        
        // Validate payment method
        $valid_payment_methods = ['Cash on Delivery', 'Bank Transfer'];
        if (!in_array($payment_method, $valid_payment_methods)) {
            throw new Exception("Invalid payment method selected!");
        }
        
        // Check if payment proof is uploaded for Bank Transfer
        $payment_proof_path = null;
        if ($payment_method == 'Bank Transfer') {
            if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] == UPLOAD_ERR_NO_FILE) {
                throw new Exception("Payment proof is required for Bank Transfer!");
            }
            
            // Validate file
            $file = $_FILES['payment_proof'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error uploading file. Please try again.");
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception("File is too large. Maximum size is 5MB.");
            }
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG, PNG and PDF files are allowed.");
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/payment_proofs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'payment_' . time() . '_' . uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                throw new Exception("Failed to upload file. Please try again.");
            }
            
            $payment_proof_path = 'uploads/payment_proofs/' . $filename;
        }
        
        // Check if cart is still valid (products still in stock)
        foreach ($cart_items as $item) {
            $stock_check_query = "SELECT stock FROM products WHERE id = ?";
            $stock_check_stmt = $db->prepare($stock_check_query);
            $stock_check_stmt->execute([$item['product_id']]);
            $current_stock = $stock_check_stmt->fetchColumn();
            
            if ($current_stock < $item['quantity']) {
                throw new Exception("Sorry, " . $item['name'] . " only has " . $current_stock . " items in stock!");
            }
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Create order
            $order_query = "INSERT INTO orders (user_id, subtotal, tax_amount, shipping_amount, total_amount, status, payment_method, shipping_address, notes, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, CURRENT_TIMESTAMP)";
            $order_stmt = $db->prepare($order_query);
            $order_result = $order_stmt->execute([
                $user_id, 
                $subtotal, 
                $tax_amount, 
                $shipping_amount, 
                $total, 
                $payment_method, 
                $shipping_address, 
                $notes
            ]);
            
            if (!$order_result) {
                throw new Exception("Failed to create order!");
            }
            
            $order_id = $db->lastInsertId();
            
            // Add order items
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $item_stmt = $db->prepare($item_query);
            
            foreach ($cart_items as $item) {
                $item_result = $item_stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price']
                ]);
                
                if (!$item_result) {
                    throw new Exception("Failed to add order item: " . $item['name']);
                }
                
                // Update product stock
                $stock_query = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stock_stmt = $db->prepare($stock_query);
                $stock_result = $stock_stmt->execute([$item['quantity'], $item['product_id']]);
                
                if (!$stock_result) {
                    throw new Exception("Failed to update stock for: " . $item['name']);
                }
            }
            
            // Save payment proof if Bank Transfer
            if ($payment_method == 'Bank Transfer' && $payment_proof_path) {
                $proof_query = "INSERT INTO payment_proofs (order_id, file_path, uploaded_at) VALUES (?, ?, CURRENT_TIMESTAMP)";
                $proof_stmt = $db->prepare($proof_query);
                $proof_result = $proof_stmt->execute([
                    $order_id,
                    $payment_proof_path
                ]);
                
                if (!$proof_result) {
                    throw new Exception("Failed to save payment proof!");
                }
            }
            
            // Clear cart
            $clear_cart_query = "DELETE FROM cart WHERE user_id = ?";
            $clear_cart_stmt = $db->prepare($clear_cart_query);
            $clear_result = $clear_cart_stmt->execute([$user_id]);
            
            if (!$clear_result) {
                throw new Exception("Failed to clear cart!");
            }
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success'] = "Order placed successfully! Order ID: #" . $order_id;
            header("Location: order_success.php?order_id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction
            $db->rollback();
            throw $e; // Re-throw to outer catch
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .checkout-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .order-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            position: sticky;
            top: 100px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            border-radius: 0 0 50px 50px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-place-order {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-place-order:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .cart-item {
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding: 15px 0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .image-placeholder {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.7);
        }
        
        .payment-option {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .payment-option:hover {
            border-color: #667eea;
            background-color: #f8f9fa;
        }
        
        .payment-option.selected {
            border-color: #667eea;
            background-color: #e3f2fd;
        }
        
        .required {
            color: #dc3545;
        }
        
        #payment_proof_container {
            display: none;
            margin-top: 20px;
            padding: 15px;
            border: 1px dashed #667eea;
            border-radius: 10px;
            background-color: #f8f9ff;
        }
        
        .file-upload-label {
            display: block;
            width: 100%;
            padding: 10px;
            background: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-label:hover {
            background: #dde2e6;
        }
        
        .file-upload-info {
            margin-top: 10px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shoe-prints"></i> Kick Store
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="cart.php">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">
                <i class="fas fa-credit-card"></i> Checkout
            </h1>
            <p class="lead">Complete your order</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error)): ?>
        <div class="container">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <form method="POST" action="checkout.php" id="checkoutForm" enctype="multipart/form-data">
            <div class="row">
                <!-- Checkout Form -->
                <div class="col-lg-8">
                    <!-- Shipping Information -->
                    <div class="checkout-section">
                        <h4 class="mb-4">
                            <i class="fas fa-truck"></i> Shipping Information
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Shipping Address <span class="required">*</span></label>
                            <textarea name="shipping_address" class="form-control" rows="4" required 
                                      placeholder="Enter your complete shipping address including street, city, state, and postal code"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <div class="form-text">Please provide a complete address for accurate delivery</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Order Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Any special instructions for your order..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <h4 class="mb-4">
                            <i class="fas fa-credit-card"></i> Payment Method <span class="required">*</span>
                        </h4>
                        
                        <div class="payment-option" onclick="selectPayment('Cash on Delivery')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="Cash on Delivery" id="cod" checked>
                                <label class="form-check-label w-100" for="cod">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-money-bill-wave fa-2x me-3 text-success"></i>
                                        <div>
                                            <h5 class="mb-1">Cash on Delivery</h5>
                                            <p class="mb-0 text-muted">Pay when your order arrives at your doorstep</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-option" onclick="selectPayment('Bank Transfer')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="Bank Transfer" id="transfer">
                                <label class="form-check-label w-100" for="transfer">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-university fa-2x me-3 text-primary"></i>
                                        <div>
                                            <h5 class="mb-1">Bank Transfer</h5>
                                            <p class="mb-0 text-muted">Transfer to our bank account before shipping</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Payment Proof Upload (for Bank Transfer) -->
                        <div id="payment_proof_container">
                            <h5 class="mb-3">
                                <i class="fas fa-receipt"></i> Payment Proof <span class="required">*</span>
                            </h5>
                            
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Bank Account Details:</strong><br>
                                Bank Name: BCA<br>
                                Account Number: 1234-5678-9012<br>
                                Account Name: Kick Store Inc.
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_proof" class="file-upload-label">
                                    <i class="fas fa-upload me-2"></i> Click to upload payment proof
                                </label>
                                <input type="file" name="payment_proof" id="payment_proof" class="form-control d-none" accept="image/jpeg,image/png,image/jpg,application/pdf">
                                <div class="file-upload-info text-muted">
                                    <div id="file_name">No file selected</div>
                                    <small>Accepted formats: JPG, PNG, PDF (Max size: 5MB)</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Cash on Delivery:</strong> Most convenient option - pay when you receive your order.
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h4 class="mb-4">
                            <i class="fas fa-receipt"></i> Order Summary
                        </h4>
                        
                        <!-- Cart Items -->
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item">
                                    <div class="row align-items-center">
                                        <div class="col-3">
                                            <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     class="product-image">
                                            <?php else: ?>
                                                <div class="image-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-6">
                                            <div class="fw-bold small"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <small>Qty: <?php echo $item['quantity']; ?></small>
                                        </div>
                                        <div class="col-3 text-end">
                                            <div class="fw-bold">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pricing -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span class="fw-bold">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (10%):</span>
                            <span class="fw-bold">$<?php echo number_format($tax_amount, 2); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping:</span>
                            <span class="fw-bold">
                                <?php if ($shipping_amount == 0): ?>
                                    <span class="badge bg-success">FREE</span>
                                <?php else: ?>
                                    $<?php echo number_format($shipping_amount, 2); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <hr style="border-color: rgba(255,255,255,0.3);">
                        
                        <div class="d-flex justify-content-between mb-4">
                            <span class="h5">Total:</span>
                            <span class="h4 fw-bold">$<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <button type="submit" class="btn-place-order">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>
                        
                        <div class="text-center mt-3">
                            <small style="color: rgba(255,255,255,0.8);">
                                <i class="fas fa-shield-alt"></i> Secure checkout guaranteed
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-shoe-prints"></i> Kick Store</h5>
                    <p class="text-muted">Your trusted partner for quality footwear.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted">© 2024 Kick Store. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPayment(method) {
            // Remove selected class from all payment options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById(method === 'Cash on Delivery' ? 'cod' : 'transfer').checked = true;
            
            // Show/hide payment proof upload based on selection
            const paymentProofContainer = document.getElementById('payment_proof_container');
            if (method === 'Bank Transfer') {
                paymentProofContainer.style.display = 'block';
            } else {
                paymentProofContainer.style.display = 'none';
            }
        }
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const shippingAddress = document.querySelector('textarea[name="shipping_address"]').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!shippingAddress) {
                e.preventDefault();
                alert('Please enter your shipping address');
                return false;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
            
            // Validate payment proof for Bank Transfer
            if (paymentMethod.value === 'Bank Transfer') {
                const paymentProof = document.getElementById('payment_proof');
                if (!paymentProof.files || !paymentProof.files[0]) {
                    e.preventDefault();
                    alert('Please upload proof of payment for Bank Transfer');
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-place-order');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
            submitBtn.disabled = true;
        });
        
        // Initialize first payment option as selected
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.payment-option').classList.add('selected');
            
            // File upload preview
            const fileInput = document.getElementById('payment_proof');
            const fileNameDisplay = document.getElementById('file_name');
            
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    fileNameDisplay.textContent = this.files[0].name;
                    fileNameDisplay.classList.add('text-success');
                } else {
                    fileNameDisplay.textContent = 'No file selected';
                    fileNameDisplay.classList.remove('text-success');
                }
            });
        });
    </script>
</body>
</html>
