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
$shipping = $subtotal > 100 ? 0 : 10; // Free shipping over $100
$total = $subtotal + $tax_amount + $shipping;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .cart-item {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 20px;
        }
        
        .cart-item:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #f8f9fa;
        }
        
        .image-placeholder {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
        
        
        
        
        
        
        
        .price-tag {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .cart-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: sticky;
            top: 100px;
        }
        
        .btn-checkout {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-checkout:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
        }
        
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if ($total_items > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $total_items; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-list"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-edit"></i> Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
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
                <i class="fas fa-shopping-cart"></i> Shopping Cart
            </h1>
            <p class="lead">Review your selected items before checkout</p>
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
                <h2 class="fw-bold mb-3">Your Cart is Empty</h2>
                <p class="text-muted mb-4">Looks like you haven't added any items to your cart yet.</p>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="fw-bold">
                            <i class="fas fa-list"></i> Cart Items (<?php echo count($cart_items); ?>)
                        </h3>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus"></i> Continue Shopping
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
                                    <div class="col-md-7 col-sm-8 product-details">
                                        <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($item['name']); ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($item['brand']); ?></span>
                                            <span class="badge bg-info me-1"><?php echo htmlspecialchars($item['color']); ?></span>
                                            <span class="badge bg-dark">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category_name'] ?? 'No Category'); ?>
                                        </p>
                                        <div class="price-tag">
                                            $<?php echo number_format($item['price'], 2); ?> each
                                        </div>
                                    </div>
                                    
                                    <!-- Price & Remove -->
                                    <div class="col-md-2 col-sm-12 text-center ">
                                        <div class="fw-bold h5 mb-3">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                        <form method="POST" action="remove_from_cart.php" class="d-inline d-flex justify-content-end">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <button type="submit" class="remove-btn" 
                                                    onclick="return confirm('Remove this item from cart?')" 
                                                    title="Remove from cart">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Stock Warning -->
                                <?php if ($item['quantity'] > $item['stock']): ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Only <?php echo $item['stock']; ?> items available in stock!
                                    </div>
                                <?php elseif ($item['stock'] <= 5): ?>
                                    <div class="alert alert-info mt-3 mb-0">
                                        <i class="fas fa-info-circle"></i> 
                                        Only <?php echo $item['stock']; ?> items left in stock!
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
                            <i class="fas fa-calculator"></i> Order Summary
                        </h4>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal (<?php echo $total_items; ?> items):</span>
                            <span class="fw-bold">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Tax (10%):</span>
                            <span class="fw-bold">$<?php echo number_format($tax_amount, 2); ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span>Shipping:</span>
                            <span class="fw-bold">
                                <?php if ($shipping == 0): ?>
                                    <span class="badge-custom">FREE</span>
                                <?php else: ?>
                                    $<?php echo number_format($shipping, 2); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($subtotal < 100 && $subtotal > 0): ?>
                            <div class="alert alert-info p-2 mb-3" style="background: rgba(255,255,255,0.2); border: none; color: white;">
                                <small>
                                    <i class="fas fa-truck"></i> 
                                    Add $<?php echo number_format(100 - $subtotal, 2); ?> more for FREE shipping!
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <hr style="border-color: rgba(255,255,255,0.3);">
                        
                        <div class="d-flex justify-content-between mb-4">
                            <span class="h5">Total:</span>
                            <span class="h4 fw-bold">$<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <form method="POST" action="checkout.php">
                            <button type="submit" class="btn-checkout">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small style="color: rgba(255,255,255,0.8);">
                                <i class="fas fa-shield-alt"></i> Secure checkout guaranteed
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
</body>
</html>
