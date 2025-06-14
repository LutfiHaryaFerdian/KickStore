<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if cart is empty
$cart_query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$_SESSION['user_id']]);
$cart_count = $cart_stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($cart_count == 0) {
    header("Location: cart.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get cart items
$query = "SELECT c.*, p.name, p.price, p.image_url, p.stock, p.brand 
          FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user info
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax = $subtotal * 0.1;
$shipping = $subtotal > 500000 ? 0 : 25000;
$total = $subtotal + $tax + $shipping;

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        // Create order
        $order_query = "INSERT INTO orders (user_id, total_amount, payment_method, payment_status, status, shipping_address, created_at) 
                        VALUES (?, ?, ?, 'pending', 'pending', ?, NOW())";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->execute([
            $_SESSION['user_id'],
            $total,
            $_POST['payment_method'],
            $_POST['shipping_address']
        ]);
        
        $order_id = $db->lastInsertId();
        
        // Create order items
        foreach ($cart_items as $item) {
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $item_stmt = $db->prepare($item_query);
            $item_stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
            
            // Update product stock
            $stock_query = "UPDATE products SET stock = stock - ? WHERE id = ?";
            $stock_stmt = $db->prepare($stock_query);
            $stock_stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Clear cart
        $clear_query = "DELETE FROM cart WHERE user_id = ?";
        $clear_stmt = $db->prepare($clear_query);
        $clear_stmt->execute([$_SESSION['user_id']]);
        
        $db->commit();
        
        header("Location: order_success.php?order_id=" . $order_id);
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Terjadi kesalahan saat memproses pesanan: " . $e->getMessage();
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
        
        .checkout-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin: 30px 0;
        }
        
        .form-control, .form-select {
            border: 2px solid #F5DEB3;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #D2B48C;
            box-shadow: 0 0 0 0.2rem rgba(210, 180, 140, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #D2B48C;
            color: #A0522D;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-color: #CD853F;
            color: white;
        }
        
        .order-item {
            background: #FEFEFE;
            border: 1px solid #F5DEB3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #F5DEB3;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 25px;
            color: #A0522D;
            position: sticky;
            top: 100px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
        }
        
        .summary-row.total {
            border-top: 2px solid #CD853F;
            padding-top: 15px;
            margin-top: 15px;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .payment-option {
            border: 2px solid #F5DEB3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-option:hover {
            border-color: #D2B48C;
            background: #FEFEFE;
        }
        
        .payment-option.selected {
            border-color: #CD853F;
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: #A0522D;
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: #CD853F;
        }
        
        .section-title {
            color: #A0522D;
            border-bottom: 2px solid #F5DEB3;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shoe-prints"></i> Kick Store
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-bag"></i> Pesanan Saya
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mt-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                <li class="breadcrumb-item"><a href="cart.php">Keranjang</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm">
            <div class="row">
                <!-- Checkout Form -->
                <div class="col-lg-8">
                    <div class="checkout-container">
                        <!-- Shipping Information -->
                        <h4 class="section-title">
                            <i class="fas fa-shipping-fast"></i> Informasi Pengiriman
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat Pengiriman *</label>
                            <textarea class="form-control" name="shipping_address" rows="3" required placeholder="Masukkan alamat lengkap untuk pengiriman..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <!-- Payment Method -->
                        <h4 class="section-title mt-4">
                            <i class="fas fa-credit-card"></i> Metode Pembayaran
                        </h4>
                        
                        <div class="payment-option" data-method="bank_transfer">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" class="me-3" required>
                                <div>
                                    <label for="bank_transfer" class="fw-bold mb-1">Transfer Bank</label>
                                    <p class="text-muted mb-0 small">Transfer ke rekening bank yang akan diberikan setelah checkout</p>
                                </div>
                                <i class="fas fa-university ms-auto fa-2x" style="color: #CD853F;"></i>
                            </div>
                        </div>
                        
                        <div class="payment-option" data-method="cod">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="cod" id="cod" class="me-3">
                                <div>
                                    <label for="cod" class="fw-bold mb-1">Bayar di Tempat (COD)</label>
                                    <p class="text-muted mb-0 small">Bayar saat barang diterima (tersedia untuk area tertentu)</p>
                                </div>
                                <i class="fas fa-money-bill-wave ms-auto fa-2x" style="color: #CD853F;"></i>
                            </div>
                        </div>
                        
                        <div class="payment-option" data-method="e_wallet">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="e_wallet" id="e_wallet" class="me-3">
                                <div>
                                    <label for="e_wallet" class="fw-bold mb-1">E-Wallet</label>
                                    <p class="text-muted mb-0 small">Pembayaran melalui OVO, GoPay, DANA, atau LinkAja</p>
                                </div>
                                <i class="fas fa-mobile-alt ms-auto fa-2x" style="color: #CD853F;"></i>
                            </div>
                        </div>

                        <!-- Order Review -->
                        <h4 class="section-title mt-4">
                            <i class="fas fa-list"></i> Review Pesanan
                        </h4>
                        
                        <?php foreach ($cart_items as $item): ?>
                            <div class="order-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                            <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image">
                                        <?php else: ?>
                                            <div class="product-image d-flex align-items-center justify-content-center" style="background: #F5DEB3;">
                                                <i class="fas fa-image" style="color: #CD853F;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <p class="text-muted mb-0 small"><?php echo htmlspecialchars($item['brand']); ?></p>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <span class="fw-bold">x<?php echo $item['quantity']; ?></span>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <span class="fw-bold" style="color: #A0522D;">
                                            Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="summary-card">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-receipt"></i> Ringkasan Pesanan
                        </h5>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Pajak (10%):</span>
                            <span>Rp <?php echo number_format($tax, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Ongkos Kirim:</span>
                            <span>
                                <?php if ($shipping == 0): ?>
                                    <span class="text-success">GRATIS</span>
                                <?php else: ?>
                                    Rp <?php echo number_format($shipping, 0, ',', '.'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check"></i> Buat Pesanan
                            </button>
                            <a href="cart.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Keranjang
                            </a>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt"></i> Transaksi aman dan terpercaya
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment method selection
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
        
        // Form submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            submitBtn.disabled = true;
            
            // Validate payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Silakan pilih metode pembayaran');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
            // Validate shipping address
            const shippingAddress = document.querySelector('textarea[name="shipping_address"]');
            if (!shippingAddress.value.trim()) {
                e.preventDefault();
                alert('Silakan masukkan alamat pengiriman');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
        });
    </script>
</body>
</html>
