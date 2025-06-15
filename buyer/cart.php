<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();


$query = "SELECT c.*, p.name, p.price, p.image_url, p.stock, p.brand 
          FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);


$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax = $subtotal * 0.1; 
$shipping = $subtotal > 500000 ? 0 : 25000; 
$total = $subtotal + $tax + $shipping;
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
        
        .cart-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin: 30px 0;
        }
        
        .cart-item {
            background: #FEFEFE;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            border-color: #D2B48C;
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.2);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #F5DEB3;
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
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 10px;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
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
        }
        
        .quantity-btn:hover {
            background: #D2B48C;
            color: white;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 2px solid #F5DEB3;
            border-radius: 8px;
            padding: 5px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 20px;
            padding: 25px;
            color: #A0522D;
            position: sticky;
            top: 100px;
        }
        
        .summary-row {
            display: flex;
            justify-content: between;
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
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #A0522D;
        }
        
        .empty-cart i {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.5;
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
    </style>
</head>
<body>

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
                        <a class="nav-link active" href="cart.php">
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

        <nav aria-label="breadcrumb" class="mt-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                <li class="breadcrumb-item active">Keranjang Belanja</li>
            </ol>
        </nav>

        <?php if (empty($cart_items)): ?>

            <div class="cart-container">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Keranjang Belanja Kosong</h3>
                    <p class="text-muted mb-4">Belum ada produk yang ditambahkan ke keranjang Anda.</p>
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag"></i> Mulai Belanja
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">

                <div class="col-lg-8">
                    <div class="cart-container">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 style="color: #A0522D;">
                                <i class="fas fa-shopping-cart"></i> Keranjang Belanja
                            </h3>
                            <span class="badge" style="background: #D2B48C; font-size: 1rem;">
                                <?php echo count($cart_items); ?> item
                            </span>
                        </div>

                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
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
                                    
                                    <div class="col-md-4">
                                        <h6 class="fw-bold mb-1" style="color: #A0522D;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </h6>
                                        <p class="text-muted mb-1">
                                            <small><i class="fas fa-copyright"></i> <?php echo htmlspecialchars($item['brand']); ?></small>
                                        </p>
                                        <p class="fw-bold mb-0" style="color: #CD853F;">
                                            Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="quantity-controls">
                                            <form method="POST" action="update_cart.php" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                <input type="hidden" name="action" value="decrease">
                                                <button type="submit" class="quantity-btn" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </form>
                                            
                                            <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" readonly>
                                            
                                            <form method="POST" action="update_cart.php" class="d-inline">
                                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                <input type="hidden" name="action" value="increase">
                                                <button type="submit" class="quantity-btn" <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <small class="text-muted">Stok: <?php echo $item['stock']; ?></small>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <p class="fw-bold mb-2" style="color: #A0522D;">
                                            Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="col-md-1">
                                        <form method="POST" action="remove_from_cart.php" onsubmit="return confirm('Hapus item ini dari keranjang?')">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Lanjut Belanja
                            </a>
                            <form method="POST" action="remove_from_cart.php" onsubmit="return confirm('Kosongkan seluruh keranjang?')">
                                <input type="hidden" name="clear_cart" value="1">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-trash"></i> Kosongkan Keranjang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>


                <div class="col-lg-4">
                    <div class="summary-card">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-receipt"></i> Ringkasan Pesanan
                        </h5>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span class="ms-auto">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Pajak (10%):</span>
                            <span class="ms-auto">Rp <?php echo number_format($tax, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Ongkos Kirim:</span>
                            <span class="ms-auto">
                                <?php if ($shipping == 0): ?>
                                    <span class="text-success">GRATIS</span>
                                <?php else: ?>
                                    Rp <?php echo number_format($shipping, 0, ',', '.'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($subtotal < 500000 && $shipping > 0): ?>
                            <div class="alert alert-info mt-3 p-2">
                                <small>
                                    <i class="fas fa-info-circle"></i>
                                    Belanja Rp <?php echo number_format(500000 - $subtotal, 0, ',', '.'); ?> lagi untuk gratis ongkir!
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span class="ms-auto">Rp <?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <a href="checkout.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card"></i> Checkout
                            </a>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt"></i> Pembayaran aman dan terpercaya
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.disabled) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
            });
        });
    </script>
</body>
</html>
