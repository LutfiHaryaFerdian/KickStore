<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total products
$product_query = "SELECT COUNT(*) as total FROM products";
$product_stmt = $db->prepare($product_query);
$product_stmt->execute();
$stats['products'] = $product_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total orders
$order_query = "SELECT COUNT(*) as total FROM orders";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute();
$stats['orders'] = $order_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total users (buyers)
$user_query = "SELECT COUNT(*) as total FROM users WHERE role = 'buyer'";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute();
$stats['users'] = $user_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$revenue_query = "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'";
$revenue_stmt = $db->prepare($revenue_query);
$revenue_stmt->execute();
$stats['revenue'] = $revenue_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent orders
$recent_orders_query = "SELECT o.*, u.full_name 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 5";
$recent_orders_stmt = $db->prepare($recent_orders_query);
$recent_orders_stmt->execute();
$recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products
$low_stock_query = "SELECT * FROM products WHERE stock <= 5 AND status = 'active' ORDER BY stock ASC LIMIT 5";
$low_stock_stmt = $db->prepare($low_stock_query);
$low_stock_stmt->execute();
$low_stock_products = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Toko Sepatu Kick</title>
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
        
        .dashboard-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 25px;
            color: #A0522D;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.3);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-weight: 600;
            opacity: 0.8;
        }
        
        .recent-orders-card, .low-stock-card {
            background: white;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .section-title {
            color: #A0522D;
            border-bottom: 2px solid #F5DEB3;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .order-item, .product-item {
            background: #FEFEFE;
            border: 1px solid #F5DEB3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .order-item:hover, .product-item:hover {
            border-color: #D2B48C;
            box-shadow: 0 3px 10px rgba(210, 180, 140, 0.2);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
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
        
        .welcome-section {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: #A0522D;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid #D2B48C;
            color: #A0522D;
            border-radius: 10px;
            padding: 10px 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-action-btn:hover {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-color: #CD853F;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-shoe-prints"></i> Kick Store Admin
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box"></i> Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-bag"></i> Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags"></i> Kategori
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Pengguna
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-2">Selamat Datang, <?php echo $_SESSION['full_name']; ?>!</h2>
                    <p class="mb-0">Kelola toko sepatu Anda dengan mudah melalui dashboard admin ini.</p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-chart-line fa-4x opacity-50"></i>
                </div>
            </div>
            
            <div class="quick-actions">
                <a href="products.php?action=add" class="quick-action-btn">
                    <i class="fas fa-plus"></i> Tambah Produk
                </a>
                <a href="orders.php" class="quick-action-btn">
                    <i class="fas fa-eye"></i> Lihat Pesanan
                </a>
                <a href="categories.php" class="quick-action-btn">
                    <i class="fas fa-tags"></i> Kelola Kategori
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['products']); ?></div>
                    <div class="stat-label">Total Produk</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['orders']); ?></div>
                    <div class="stat-label">Total Pesanan</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['users']); ?></div>
                    <div class="stat-label">Total Pelanggan</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number">Rp <?php echo number_format($stats['revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="recent-orders-card">
                    <h5 class="section-title">
                        <i class="fas fa-clock"></i> Pesanan Terbaru
                    </h5>
                    
                    <?php if (empty($recent_orders)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                            <p>Belum ada pesanan</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <strong>Pesanan #<?php echo $order['id']; ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['full_name']); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong><br>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($order['created_at'])); ?></small>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'pending' => 'Menunggu',
                                                'confirmed' => 'Dikonfirmasi',
                                                'shipped' => 'Dikirim',
                                                'delivered' => 'Terkirim',
                                                'cancelled' => 'Dibatalkan'
                                            ];
                                            echo $status_text[$order['status']] ?? ucfirst($order['status']);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="orders.php" class="btn btn-primary">
                                <i class="fas fa-list"></i> Lihat Semua Pesanan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="col-lg-4">
                <div class="low-stock-card">
                    <h5 class="section-title">
                        <i class="fas fa-exclamation-triangle"></i> Stok Menipis
                    </h5>
                    
                    <?php if (empty($low_stock_products)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3 opacity-50"></i>
                            <p>Semua produk stok aman</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($low_stock_products as $product): ?>
                            <div class="product-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($product['brand']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo $product['stock'] == 0 ? 'bg-danger' : 'bg-warning'; ?>">
                                            <?php echo $product['stock']; ?> tersisa
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="products.php" class="btn btn-primary">
                                <i class="fas fa-box"></i> Kelola Produk
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
