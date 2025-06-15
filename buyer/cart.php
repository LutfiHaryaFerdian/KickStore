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

// Get cart items with product details including images
$query = "SELECT c.id as cart_id, c.quantity, c.created_at,
             p.id as product_id, p.name, p.price, p.stock, p.brand, p.color, p.size, p.image_url,
             cat.name as category_name
      FROM cart c
      JOIN products p ON c.product_id = p.id
      LEFT JOIN categories cat ON p.category_id = cat.id
      WHERE c.user_id = ?
      ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

$tax_rate = 0.10; // 10% tax
$tax_amount = $subtotal * $tax_rate;
$shipping = $subtotal > 100000 ? 0 : 15000; // Free shipping over Rp 100,000
$total = $subtotal + $tax_amount + $shipping;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Toko Sepatu Kick</title>
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
        
        .cart-item {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.2);
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 20px;
        }
        
        .cart-item:hover {
            box-shadow: 0 8px 25px rgba(210, 180, 140, 0.3);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #F5DEB3;
        }
        
        .image-placeholder {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
            border-radius: 10px;
            border: 2px solid #F5DEB3;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #CD853F;
        }
        
        .price-tag {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .cart-summary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.3);
            position: sticky;
            top: 100px;
        }
        
        .btn-checkout {
            background: white;
            color: #CD853F;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-checkout:hover {
            background: #FEFEFE;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
            color: #A0522D;
        }
        
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
            border-radius: 20px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            border-radius: 0 0 50px 50px;
        }
        
        .remove-btn {
            background: #dc3545;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        
        .remove-btn:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .product-details {
            flex-grow: 1;
        }
        
        .badge-custom {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-right: 5px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 2px solid #D2B48C;
            background: white;
            color: #A0522D;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .quantity-btn:hover:not(:disabled) {
            background: #D2B48C;
            color: white;
            transform: scale(1.1);
        }
        
        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-display {
            min-width: 50px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
            color: #A0522D;
            padding: 5px 10px;
            background: #F5DEB3;
            border-radius: 8px;
        }
        
        .stock-info {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        
        .stock-low {
            color: #fd7e14;
            font-weight: bold;
        }
        
        .stock-good {
            color: #28a745;
        }
        
        @media (max-width: 768px) {
            .cart-item .row {
                text-align: center;
            }
            
            .product-image,
            .image-placeholder {
                width: 100px;
                height: 100px;
                margin: 0 auto 15px;
            }
            
            .cart-summary {
                position: static;
                margin-top: 30px;
            }
            
            .quantity-controls {
                justify-content: center;
                margin: 15px 0;
            }
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Keranjang Belanja
                            <?php if ($total_items > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $total_items; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-list"></i> Pesanan Saya
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-edit"></i> Profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Keluar
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">
                <i class="fas fa-shopping-cart"></i> Keranjang Belanja
            </h1>
            <p class="lead">Tinjau item yang dipilih sebelum checkout</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="container">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
                <h2 class="fw-bold mb-3">Keranjang Anda Kosong</h2>
                <p class="text-muted mb-4">Sepertinya Anda belum menambahkan item apapun ke keranjang Anda.</p>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> Mulai Belanja
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="fw-bold">
                            <i class="fas fa-list"></i> Item Keranjang (<?php echo count($cart_items); ?>)
                        </h3>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus"></i> Lanjut Belanja
                        </a>
                    </div>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="card cart-item">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <!-- Product Image -->
                                    <div class="col-md-3 col-sm-4 text-center mb-3 mb-md-0">
                                        <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                            <img src="../<?php echo $item['image_url']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="product-image">
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <i class="fas fa-image fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Product Details -->
                                    <div class="col-md-4 col-sm-8 product-details">
                                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($item['name']); ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($item['brand']); ?></span>
                                            <?php if (!empty($item['color'])): ?>
                                                <span class="badge bg-info me-1"><?php echo htmlspecialchars($item['color']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['size'])): ?>
                                                <span class="badge bg-dark">Ukuran: <?php echo htmlspecialchars($item['size']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category_name'] ?? 'Tanpa Kategori'); ?>
                                        </p>
                                        <div class="price-tag">
                                            Rp<?php echo number_format($item['price'], 0, ',', '.'); ?> per item
                                        </div>
                                    </div>
                                    
                                    <!-- Quantity Controls -->
                                    <div class="col-md-3 col-sm-6">
                                        <div class="quantity-controls">
                                            <form method="POST" action="update_cart.php" class="d-inline">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <input type="hidden" name="action" value="decrease">
                                                <button type="submit" class="quantity-btn" 
                                                        <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>
                                                        title="Kurangi kuantitas">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </form>
                                            
                                            <div class="quantity-display">
                                                <?php echo $item['quantity']; ?>
                                            </div>
                                            
                                            <form method="POST" action="update_cart.php" class="d-inline">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                <input type="hidden" name="action" value="increase">
                                                <button type="submit" class="quantity-btn" 
                                                        <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>
                                                        title="Tambah kuantitas">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="stock-info text-center">
                                            <?php if ($item['stock'] <= 0): ?>
                                                <span class="stock-warning">Stok habis</span>
                                            <?php elseif ($item['stock'] <= 5): ?>
                                                <span class="stock-low">Stok: <?php echo $item['stock']; ?> tersisa</span>
                                            <?php elseif ($item['stock'] <= 10): ?>
                                                <span class="stock-low">Stok: <?php echo $item['stock']; ?></span>
                                            <?php else: ?>
                                                <span class="stock-good">Stok: <?php echo $item['stock']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Price & Remove -->
                                    <div class="col-md-2 col-sm-6 text-center">
                                        <div class="fw-bold h5 mb-3" style="color: #A0522D;">
                                            Rp<?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                        </div>
                                        <form method="POST" action="remove_from_cart.php" class="d-inline">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <button type="submit" class="remove-btn" 
                                                    onclick="return confirm('Hapus item ini dari keranjang?')" 
                                                    title="Hapus dari keranjang">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Stock Warning -->
                                <?php if ($item['quantity'] > $item['stock']): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Hanya <?php echo $item['stock']; ?> item tersedia di stok!
                                    </div>
                                <?php elseif ($item['stock'] <= 5 && $item['stock'] > 0): ?>
                                    <div class="alert alert-info mt-3 mb-0">
                                        <i class="fas fa-info-circle"></i> 
                                        Hanya <?php echo $item['stock']; ?> item tersisa di stok!
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h4 class="fw-bold mb-4">
                            <i class="fas fa-calculator"></i> Ringkasan Pesanan
                        </h4>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal (<?php echo $total_items; ?> items):</span>
                            <span class="fw-bold">Rp<?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Pajak (10%):</span>
                            <span class="fw-bold">Rp<?php echo number_format($tax_amount, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Ongkir:</span>
                            <span class="fw-bold">
                                <?php if ($shipping == 0): ?>
                                    <span class="badge-custom">GRATIS</span>
                                <?php else: ?>
                                    Rp<?php echo number_format($shipping, 0, ',', '.'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($subtotal < 100000 && $subtotal > 0): ?>
                            <div class="alert alert-info p-2 mb-3" style="background: rgba(255,255,255,0.2); border: none; color: white;">
                                <small>
                                    <i class="fas fa-truck"></i> 
                                    Tambah Rp<?php echo number_format(100000 - $subtotal, 0, ',', '.'); ?> lagi untuk ongkir GRATIS!
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <hr style="border-color: rgba(255,255,255,0.3);">
                        
                        <div class="d-flex justify-content-between mb-4">
                            <span class="h5">Total:</span>
                            <span class="h4 fw-bold">Rp<?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <form method="POST" action="checkout.php">
                            <button type="submit" class="btn-checkout">
                                <i class="fas fa-credit-card"></i> Lanjut ke Pembayaran
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small style="color: rgba(255,255,255,0.8);">
                                <i class="fas fa-shield-alt"></i> Checkout aman terjamin
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading state to quantity buttons
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.disabled) {
                    const icon = this.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    
                    // Reset after form submission
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 1000);
                }
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
