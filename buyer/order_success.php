<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['order_id'])) {
    header("Location: orders.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['order_id'], $_SESSION['user_id']]);
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Toko Sepatu Kick</title>
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
        
        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 40px;
            margin: 30px 0;
            text-align: center;
        }
        
        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 3rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .order-summary {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            color: #A0522D;
        }
        
        .order-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #F5DEB3;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
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
        
        .payment-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .next-steps {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
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
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h2 class="fw-bold mb-3" style="color: #28a745;">Pesanan Berhasil Dibuat!</h2>
            <p class="lead text-muted mb-4">
                Terima kasih atas pesanan Anda. Kami akan segera memproses pesanan Anda.
            </p>
            
            <div class="order-summary">
                <h4 class="fw-bold mb-3">
                    <i class="fas fa-receipt"></i> Detail Pesanan #<?php echo $order['id']; ?>
                </h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Tanggal Pesanan:</strong><br>
                        <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Pembayaran:</strong><br>
                        <span class="h5">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Metode Pembayaran:</strong><br>
                        <?php 
                        $payment_methods = [
                            'bank_transfer' => 'Transfer Bank',
                            'cod' => 'Bayar di Tempat',
                            'e_wallet' => 'E-Wallet'
                        ];
                        echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                        ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong><br>
                        <span class="badge bg-warning">Menunggu Konfirmasi</span>
                    </div>
                </div>
                
                <div class="text-start">
                    <strong>Alamat Pengiriman:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="text-start">
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
            
            <!-- Payment Instructions -->
            <?php if ($order['payment_method'] == 'bank_transfer'): ?>
                <div class="payment-info">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-university"></i> Instruksi Pembayaran
                    </h5>
                    <p class="mb-2">Silakan transfer ke salah satu rekening berikut:</p>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Bank BCA</strong><br>
                            1234567890<br>
                            a.n. Kick Store
                        </div>
                        <div class="col-md-4">
                            <strong>Bank Mandiri</strong><br>
                            0987654321<br>
                            a.n. Kick Store
                        </div>
                        <div class="col-md-4">
                            <strong>Bank BRI</strong><br>
                            1122334455<br>
                            a.n. Kick Store
                        </div>
                    </div>
                    <p class="mt-3 mb-0 small">
                        <strong>Penting:</strong> Setelah transfer, mohon konfirmasi pembayaran melalui WhatsApp ke 08123456789 dengan menyertakan bukti transfer dan nomor pesanan.
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Next Steps -->
            <div class="next-steps">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-list-check"></i> Langkah Selanjutnya
                </h5>
                <ol class="text-start">
                    <?php if ($order['payment_method'] == 'bank_transfer'): ?>
                        <li>Lakukan pembayaran sesuai instruksi di atas</li>
                        <li>Konfirmasi pembayaran via WhatsApp</li>
                        <li>Tunggu konfirmasi dari admin</li>
                    <?php elseif ($order['payment_method'] == 'cod'): ?>
                        <li>Tunggu konfirmasi pesanan dari admin</li>
                        <li>Siapkan uang pas saat barang diantar</li>
                    <?php else: ?>
                        <li>Tunggu instruksi pembayaran via email/WhatsApp</li>
                        <li>Lakukan pembayaran sesuai instruksi</li>
                    <?php endif; ?>
                    <li>Pesanan akan diproses setelah pembayaran dikonfirmasi</li>
                    <li>Barang akan dikirim dalam 1-3 hari kerja</li>
                    <li>Pantau status pesanan di halaman "Pesanan Saya"</li>
                </ol>
            </div>
            
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="orders.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Lihat Pesanan Saya
                </a>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-shopping-bag"></i> Belanja Lagi
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
