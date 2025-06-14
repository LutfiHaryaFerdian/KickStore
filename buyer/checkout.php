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
    $_SESSION['error'] = "Keranjang Anda kosong!";
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
$shipping_amount = $subtotal > 100000 ? 0 : 15000; // Free shipping over Rp 100,000
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
            throw new Exception("Alamat pengiriman wajib diisi!");
        }
        
        if (empty($payment_method)) {
            throw new Exception("Metode pembayaran wajib dipilih!");
        }
        
        // Validate payment method
        $valid_payment_methods = ['cod', 'bank_transfer', 'e_wallet'];
        if (!in_array($payment_method, $valid_payment_methods)) {
            throw new Exception("Metode pembayaran tidak valid!");
        }
        
        // Check if payment proof is uploaded for Bank Transfer
        $payment_proof_path = null;
        $original_filename = null;
        $file_size = 0;
        $file_type = null;
        
        if ($payment_method == 'bank_transfer') {
            if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] == UPLOAD_ERR_NO_FILE) {
                throw new Exception("Bukti pembayaran wajib diunggah untuk Transfer Bank!");
            }
            
            // Validate file
            $file = $_FILES['payment_proof'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error mengunggah file. Silakan coba lagi.");
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception("File terlalu besar. Maksimal 5MB.");
            }
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Tipe file tidak valid. Hanya JPG, PNG dan PDF yang diizinkan.");
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
                throw new Exception("Gagal mengunggah file. Silakan coba lagi.");
            }
            
            $payment_proof_path = 'uploads/payment_proofs/' . $filename;
            $original_filename = $file['name'];
            $file_size = $file['size'];
            $file_type = $file['type'];
        }
        
        // Check if cart is still valid (products still in stock)
        foreach ($cart_items as $item) {
            $stock_check_query = "SELECT stock FROM products WHERE id = ?";
            $stock_check_stmt = $db->prepare($stock_check_query);
            $stock_check_stmt->execute([$item['product_id']]);
            $current_stock = $stock_check_stmt->fetchColumn();
            
            if ($current_stock < $item['quantity']) {
                throw new Exception("Maaf, " . $item['name'] . " hanya memiliki " . $current_stock . " item di stok!");
            }
        }
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Create order
            $order_query = "INSERT INTO orders (user_id, subtotal, tax_amount, shipping_amount, total_amount, status, payment_method, payment_status, shipping_address, notes, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $payment_status = ($payment_method == 'bank_transfer') ? 'pending' : 'pending';
            $order_stmt = $db->prepare($order_query);
            $order_result = $order_stmt->execute([
                $user_id, 
                $subtotal, 
                $tax_amount, 
                $shipping_amount, 
                $total, 
                $payment_method, 
                $payment_status,
                $shipping_address, 
                $notes
            ]);
            
            if (!$order_result) {
                throw new Exception("Gagal membuat pesanan!");
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
                    throw new Exception("Gagal menambahkan item pesanan: " . $item['name']);
                }
                
                // Update product stock
                $stock_query = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stock_stmt = $db->prepare($stock_query);
                $stock_result = $stock_stmt->execute([$item['quantity'], $item['product_id']]);
                
                if (!$stock_result) {
                    throw new Exception("Gagal memperbarui stok untuk: " . $item['name']);
                }
            }
            
            // Save payment proof if Bank Transfer
            if ($payment_method == 'bank_transfer' && $payment_proof_path) {
                $proof_query = "INSERT INTO payment_proofs (order_id, file_path, original_filename, file_size, file_type, uploaded_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                $proof_stmt = $db->prepare($proof_query);
                $proof_result = $proof_stmt->execute([
                    $order_id,
                    $payment_proof_path,
                    $original_filename,
                    $file_size,
                    $file_type
                ]);
                
                if (!$proof_result) {
                    throw new Exception("Gagal menyimpan bukti pembayaran!");
                }
            }
            
            // Clear cart
            $clear_cart_query = "DELETE FROM cart WHERE user_id = ?";
            $clear_cart_stmt = $db->prepare($clear_cart_query);
            $clear_result = $clear_cart_stmt->execute([$user_id]);
            
            if (!$clear_result) {
                throw new Exception("Gagal mengosongkan keranjang!");
            }
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success'] = "Pesanan berhasil dibuat! ID Pesanan: #" . $order_id;
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Toko Sepatu Kick</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%) !important;
            box-shadow: 0 2px 20px rgba(160, 82, 45, 0.3);
        }
        
        .checkout-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .order-summary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            position: sticky;
            top: 100px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            border-radius: 0 0 50px 50px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #D2B48C;
            box-shadow: 0 0 0 0.2rem rgba(210, 180, 140, 0.25);
        }
        
        .btn-place-order {
            background: white;
            color: #CD853F;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-place-order:hover {
            background: #FEFEFE;
            transform: translateY(-2px);
            color: #A0522D;
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
            border: 2px solid #F5DEB3;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .payment-option:hover {
            border-color: #D2B48C;
            background-color: #FEFEFE;
        }
        
        .payment-option.selected {
            border-color: #CD853F;
            background-color: #F5DEB3;
        }
        
        .required {
            color: #dc3545;
        }
        
        #payment_proof_container {
            display: none;
            margin-top: 20px;
            padding: 20px;
            border: 2px dashed #D2B48C;
            border-radius: 15px;
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
        }
        
        .file-upload-area {
            border: 2px dashed #CD853F;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: #A0522D;
            background: #FEFEFE;
        }
        
        .file-upload-area.dragover {
            border-color: #A0522D;
            background: #F5DEB3;
        }
        
        .file-preview {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border: 1px solid #D2B48C;
        }
        
        .bank-info {
            background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .bank-account {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .copy-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shoe-prints"></i> Kick Store
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="cart.php">
                    <i class="fas fa-arrow-left"></i> Kembali ke Keranjang
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
            <p class="lead">Selesaikan pesanan Anda</p>
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
                        <h4 class="mb-4" style="color: #A0522D;">
                            <i class="fas fa-truck"></i> Informasi Pengiriman
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat Pengiriman <span class="required">*</span></label>
                            <textarea name="shipping_address" class="form-control" rows="4" required 
                                      placeholder="Masukkan alamat lengkap termasuk jalan, kota, provinsi, dan kode pos"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <div class="form-text">Harap berikan alamat lengkap untuk pengiriman yang akurat</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Catatan Pesanan (Opsional)</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Instruksi khusus untuk pesanan Anda..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <h4 class="mb-4" style="color: #A0522D;">
                            <i class="fas fa-credit-card"></i> Metode Pembayaran <span class="required">*</span>
                        </h4>
                        
                        <div class="payment-option" onclick="selectPayment('cod')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="cod" id="cod" checked>
                                <label class="form-check-label w-100" for="cod">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-money-bill-wave fa-2x me-3 text-success"></i>
                                        <div>
                                            <h5 class="mb-1">Bayar di Tempat (COD)</h5>
                                            <p class="mb-0 text-muted">Bayar ketika pesanan tiba di depan pintu Anda</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="payment-option" onclick="selectPayment('bank_transfer')">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" value="bank_transfer" id="bank_transfer">
                                <label class="form-check-label w-100" for="bank_transfer">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-university fa-2x me-3" style="color: #CD853F;"></i>
                                        <div>
                                            <h5 class="mb-1">Transfer Bank</h5>
                                            <p class="mb-0 text-muted">Transfer ke rekening bank kami dan upload bukti pembayaran</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Payment Proof Upload (for Bank Transfer) -->
                        <div id="payment_proof_container">
                            <h5 class="mb-3" style="color: #A0522D;">
                                <i class="fas fa-receipt"></i> Informasi Transfer Bank
                            </h5>
                            
                            <div class="bank-info">
                                <h6 class="mb-3">
                                    <i class="fas fa-university"></i> Detail Rekening Bank
                                </h6>
                                
                                <div class="bank-account">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Bank BCA</strong><br>
                                            <span>No. Rekening: 1234-5678-9012</span><br>
                                            <span>Atas Nama: Kick Store Indonesia</span>
                                        </div>
                                        <button type="button" class="copy-btn" onclick="copyToClipboard('1234-5678-9012')">
                                            <i class="fas fa-copy"></i> Salin
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="bank-account">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Bank Mandiri</strong><br>
                                            <span>No. Rekening: 9876-5432-1098</span><br>
                                            <span>Atas Nama: Kick Store Indonesia</span>
                                        </div>
                                        <button type="button" class="copy-btn" onclick="copyToClipboard('9876-5432-1098')">
                                            <i class="fas fa-copy"></i> Salin
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <small>
                                        <i class="fas fa-info-circle"></i> 
                                        <strong>Total yang harus ditransfer: Rp <?php echo number_format($total, 0, ',', '.'); ?></strong>
                                    </small>
                                </div>
                            </div>
                            
                            <h6 class="mb-3" style="color: #A0522D;">
                                <i class="fas fa-upload"></i> Upload Bukti Pembayaran <span class="required">*</span>
                            </h6>
                            
                            <div class="file-upload-area" id="fileUploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: #CD853F;"></i>
                                <h6>Klik atau seret file ke sini</h6>
                                <p class="text-muted mb-0">Format yang diterima: JPG, PNG, PDF (Maks: 5MB)</p>
                                <input type="file" name="payment_proof" id="payment_proof" class="d-none" accept="image/jpeg,image/png,image/jpg,application/pdf">
                            </div>
                            
                            <div id="file_preview" class="file-preview" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-alt fa-2x me-3" style="color: #CD853F;"></i>
                                        <div>
                                            <div class="fw-bold" id="file_name"></div>
                                            <small class="text-muted" id="file_size"></small>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile()">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Petunjuk:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Transfer sesuai dengan jumlah total yang tertera</li>
                                    <li>Upload bukti transfer yang jelas dan dapat dibaca</li>
                                    <li>Pesanan akan diproses setelah pembayaran dikonfirmasi</li>
                                    <li>Konfirmasi pembayaran maksimal 1x24 jam</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h4 class="mb-4">
                            <i class="fas fa-receipt"></i> Ringkasan Pesanan
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
                                            <div class="fw-bold">Rp<?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pricing -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span class="fw-bold">Rp<?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Pajak (10%):</span>
                            <span class="fw-bold">Rp<?php echo number_format($tax_amount, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Ongkir:</span>
                            <span class="fw-bold">
                                <?php if ($shipping_amount == 0): ?>
                                    <span class="badge bg-success">GRATIS</span>
                                <?php else: ?>
                                    Rp<?php echo number_format($shipping_amount, 0, ',', '.'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <hr style="border-color: rgba(255,255,255,0.3);">
                        
                        <div class="d-flex justify-content-between mb-4">
                            <span class="h5">Total:</span>
                            <span class="h4 fw-bold">Rp<?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <button type="submit" class="btn-place-order">
                            <i class="fas fa-check-circle"></i> Buat Pesanan
                        </button>
                        
                        <div class="text-center mt-3">
                            <small style="color: rgba(255,255,255,0.8);">
                                <i class="fas fa-shield-alt"></i> Checkout aman terjamin
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="py-5 mt-5" style="background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%); color: white;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-shoe-prints"></i> Kick Store</h5>
                    <p class="text-light opacity-75">Mitra terpercaya untuk sepatu berkualitas.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-light opacity-75">© 2024 Kick Store. Hak cipta dilindungi.</p>
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
            document.getElementById(method).checked = true;
            
            // Show/hide payment proof upload based on selection
            const paymentProofContainer = document.getElementById('payment_proof_container');
            if (method === 'bank_transfer') {
                paymentProofContainer.style.display = 'block';
            } else {
                paymentProofContainer.style.display = 'none';
                // Reset file input if switching away from bank transfer
                document.getElementById('payment_proof').value = '';
                document.getElementById('file_preview').style.display = 'none';
            }
        }
        
        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('payment_proof');
        const filePreview = document.getElementById('file_preview');
        
        // Click to upload
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Drag and drop
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            // Validate file
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!allowedTypes.includes(file.type)) {
                alert('Tipe file tidak valid. Hanya JPG, PNG dan PDF yang diizinkan.');
                fileInput.value = '';
                return;
            }
            
            if (file.size > maxSize) {
                alert('File terlalu besar. Maksimal 5MB.');
                fileInput.value = '';
                return;
            }
            
            // Show preview
            document.getElementById('file_name').textContent = file.name;
            document.getElementById('file_size').textContent = formatFileSize(file.size);
            filePreview.style.display = 'block';
        }
        
        function removeFile() {
            fileInput.value = '';
            filePreview.style.display = 'none';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show success message
                const btn = event.target.closest('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Tersalin';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                }, 2000);
            });
        }
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const shippingAddress = document.querySelector('textarea[name="shipping_address"]').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!shippingAddress) {
                e.preventDefault();
                alert('Harap masukkan alamat pengiriman Anda');
                return false;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Harap pilih metode pembayaran');
                return false;
            }
            
            // Validate payment proof for Bank Transfer
            if (paymentMethod.value === 'bank_transfer') {
                const paymentProof = document.getElementById('payment_proof');
                if (!paymentProof.files || !paymentProof.files[0]) {
                    e.preventDefault();
                    alert('Harap unggah bukti pembayaran untuk Transfer Bank');
                    return false;
                }
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-place-order');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses Pesanan...';
            submitBtn.disabled = true;
        });
        
        // Initialize first payment option as selected
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.payment-option').classList.add('selected');
        });
    </script>
</body>
</html>
