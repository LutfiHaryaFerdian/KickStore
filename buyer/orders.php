<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user's orders
$query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Toko Sepatu Kick</title>
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
        
        .orders-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin: 30px 0;
        }
        
        .order-card {
            background: #FEFEFE;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            border-color: #D2B48C;
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.2);
            transform: translateY(-2px);
        }
        
        .order-header {
            border-bottom: 2px solid #F5DEB3;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .order-id {
            color: #A0522D;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .order-date {
            color: #8B4513;
            font-size: 0.9rem;
        }
        
        .order-total {
            color: #CD853F;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
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
        
        .payment-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .payment-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .payment-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .payment-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            border-radius: 10px;
            padding: 8px 20px;
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
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            color: #A0522D;
        }
        
        .empty-orders i {
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
                        <a class="nav-link active" href="orders.php">
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
                <li class="breadcrumb-item active">Pesanan Saya</li>
            </ol>
        </nav>

        <div class="orders-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 style="color: #A0522D;">
                    <i class="fas fa-shopping-bag"></i> Pesanan Saya
                </h3>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-plus"></i> Belanja Lagi
                </a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Belum Ada Pesanan</h3>
                    <p class="text-muted mb-4">Anda belum pernah melakukan pemesanan. Mulai belanja sekarang!</p>
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-cart"></i> Mulai Belanja
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="order-id">Pesanan #<?php echo $order['id']; ?></div>
                                    <div class="order-date">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="order-total">
                                        Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-credit-card"></i> 
                                        <?php 
                                        $payment_methods = [
                                            'bank_transfer' => 'Transfer Bank',
                                            'cod' => 'Bayar di Tempat',
                                            'e_wallet' => 'E-Wallet'
                                        ];
                                        echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
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
                                </div>
                                <div class="col-md-3">
                                    <span class="payment-badge payment-<?php echo $order['payment_status']; ?>">
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
                        </div>
                        
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <strong>Alamat Pengiriman:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> Lihat Detail
                                </a>
                                
                                <?php if ($order['status'] == 'pending' && $order['payment_status'] == 'pending'): ?>
                                    <button class="btn btn-outline-danger btn-sm mt-2" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-times"></i> Batalkan
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cancelOrder(orderId) {
            if (confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
                // Here you would typically send an AJAX request to cancel the order
                // For now, we'll just redirect to a cancel order page
                window.location.href = 'cancel_order.php?id=' + orderId;
            }
        }
    </script>
</body>
</html>
