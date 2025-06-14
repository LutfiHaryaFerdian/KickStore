<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['id'], $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.brand, p.image_url 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order['id']]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate breakdown
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.1;
$shipping = $order['total_amount'] - $subtotal - $tax;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order['id']; ?> - Toko Sepatu Kick</title>
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
        
        .order-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin: 30px 0;
        }
        
        .order-header {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: #A0522D;
        }
        
        .status-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
            color: white;
        }
        
        .status-shipped {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
        }
        
        .status-delivered {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .order-item {
            background: #FEFEFE;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            border-color: #D2B48C;
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.2);
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
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
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #F5DEB3;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #D2B48C;
        }
        
        .timeline-item.active::before {
            background: #CD853F;
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
                <li class="breadcrumb-item"><a href="orders.php">Pesanan Saya</a></li>
                <li class="breadcrumb-item active">Detail Pesanan #<?php echo $order['id']; ?></li>
            </ol>
        </nav>

        <div class="row">
            <!-- Order Details -->
            <div class="col-lg-8">
                <div class="order-container">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h3 class="fw-bold mb-2">Pesanan #<?php echo $order['id']; ?></h3>
                                <p class="mb-1">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-credit-card"></i> 
                                    <?php 
                                    $payment_methods = [
                                        'bank_transfer' => 'Transfer Bank',
                                        'cod' => 'Bayar di Tempat',
                                        'e_wallet' => 'E-Wallet'
                                    ];
                                    echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'Menunggu Konfirmasi',
                                        'confirmed' => 'Dikonfirmasi',
                                        'shipped' => 'Sedang Dikirim',
                                        'delivered' => 'Terkirim',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    echo $status_text[$order['status']] ?? ucfirst($order['status']);
                                    ?>
                                </span>
                                <div class="mt-2">
                                    <h4 class="fw-bold">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <h5 class="fw-bold mb-3" style="color: #A0522D;">
                        <i class="fas fa-box"></i> Item Pesanan
                    </h5>
                    
                    <?php foreach ($order_items as $item): ?>
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
                                    <p class="text-muted mb-0 small">Harga: Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
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

                    <!-- Shipping Address -->
                    <h5 class="fw-bold mb-3 mt-4" style="color: #A0522D;">
                        <i class="fas fa-map-marker-alt"></i> Alamat Pengiriman
                    </h5>
                    <div class="order-item">
                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                    </div>

                    <!-- Order Timeline -->
                    <h5 class="fw-bold mb-3 mt-4" style="color: #A0522D;">
                        <i class="fas fa-truck"></i> Status Pengiriman
                    </h5>
                    <div class="order-item">
                        <div class="timeline">
                            <div class="timeline-item <?php echo in_array($order['status'], ['pending', 'confirmed', 'shipped', 'delivered']) ? 'active' : ''; ?>">
                                <h6 class="fw-bold">Pesanan Dibuat</h6>
                                <p class="text-muted small mb-0"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div class="timeline-item <?php echo in_array($order['status'], ['confirmed', 'shipped', 'delivered']) ? 'active' : ''; ?>">
                                <h6 class="fw-bold">Pesanan Dikonfirmasi</h6>
                                <p class="text-muted small mb-0">Pesanan sedang diproses</p>
                            </div>
                            <div class="timeline-item <?php echo in_array($order['status'], ['shipped', 'delivered']) ? 'active' : ''; ?>">
                                <h6 class="fw-bold">Pesanan Dikirim</h6>
                                <p class="text-muted small mb-0">Barang dalam perjalanan</p>
                            </div>
                            <div class="timeline-item <?php echo $order['status'] == 'delivered' ? 'active' : ''; ?>">
                                <h6 class="fw-bold">Pesanan Diterima</h6>
                                <p class="text-muted small mb-0">Barang telah sampai</p>
                            </div>
                        </div>
                    </div>
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
                            <?php if ($shipping <= 0): ?>
                                <span class="text-success">GRATIS</span>
                            <?php else: ?>
                                Rp <?php echo number_format($shipping, 0, ',', '.'); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                    
                    <div class="mt-4">
                        <div class="mb-2">
                            <strong>Status Pembayaran:</strong><br>
                            <span class="badge <?php echo $order['payment_status'] == 'paid' ? 'bg-success' : ($order['payment_status'] == 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                <?php 
                                $payment_text = [
                                    'paid' => 'Sudah Dibayar',
                                    'pending' => 'Menunggu Pembayaran',
                                    'failed' => 'Pembayaran Gagal'
                                ];
                                echo $payment_text[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="orders.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Pesanan
                        </a>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-bag"></i> Belanja Lagi
                        </a>
                    </div>
                    
                    <?php if ($order['status'] == 'pending' && $order['payment_status'] == 'pending'): ?>
                        <div class="d-grid mt-2">
                            <button class="btn btn-outline-danger" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                <i class="fas fa-times"></i> Batalkan Pesanan
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cancelOrder(orderId) {
            if (confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
                window.location.href = 'cancel_order.php?id=' + orderId;
            }
        }
    </script>
</body>
</html>
