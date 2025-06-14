<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        $update_query = "UPDATE orders SET status = ?, payment_status = ?, notes = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        if ($update_stmt->execute([$status, $payment_status, $notes, $order_id])) {
            $success = "Status pesanan berhasil diperbarui!";
        } else {
            $error = "Gagal memperbarui status pesanan!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get order details
$order_query = "SELECT o.*, u.full_name, u.email, u.phone, u.address 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.brand, p.image_url, p.size, p.color 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo $order['id']; ?> - Admin</title>
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
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            margin: 30px 0;
            overflow: hidden;
        }
        
        .page-header {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            padding: 30px;
            color: #A0522D;
        }
        
        .order-info-card, .customer-info-card, .items-card, .status-card {
            background: white;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .order-info-card:hover, .customer-info-card:hover, .items-card:hover, .status-card:hover {
            border-color: #D2B48C;
            box-shadow: 0 5px 20px rgba(210, 180, 140, 0.2);
        }
        
        .section-title {
            color: #A0522D;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #F5DEB3;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #d1ecf1 0%, #74b9ff 100%);
            color: #0c5460;
        }
        
        .status-shipped {
            background: linear-gradient(135deg, #cce5ff 0%, #74b9ff 100%);
            color: #004085;
        }
        
        .status-delivered {
            background: linear-gradient(135deg, #d4edda 0%, #00b894 100%);
            color: #155724;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #f8d7da 0%, #e17055 100%);
            color: #721c24;
        }
        
        .payment-paid {
            background: linear-gradient(135deg, #d4edda 0%, #00b894 100%);
            color: #155724;
        }
        
        .payment-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .payment-failed {
            background: linear-gradient(135deg, #f8d7da 0%, #e17055 100%);
            color: #721c24;
        }
        
        .product-item {
            background: #FEFEFE;
            border: 1px solid #F5DEB3;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .product-item:hover {
            border-color: #D2B48C;
            box-shadow: 0 3px 15px rgba(210, 180, 140, 0.2);
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #F5DEB3;
        }
        
        .image-placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #A0522D;
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%);
            transform: translateY(-2px);
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
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F5DEB3;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #A0522D;
        }
        
        .info-value {
            color: #8B4513;
        }
        
        .total-section {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .total-final {
            font-size: 1.2rem;
            font-weight: 700;
            color: #A0522D;
            border-top: 2px solid #CD853F;
            padding-top: 10px;
            margin-top: 10px;
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
            background: #D2B48C;
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
            background: #CD853F;
        }
        
        .timeline-item.active::before {
            background: #A0522D;
            box-shadow: 0 0 0 4px rgba(160, 82, 45, 0.2);
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin: 15px;
                border-radius: 15px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .order-info-card, .customer-info-card, .items-card, .status-card {
                padding: 20px;
            }
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box"></i> Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
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
        <div class="main-container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold mb-2">Detail Pesanan #<?php echo $order['id']; ?></h2>
                        <p class="mb-0">Kelola dan pantau detail pesanan pelanggan</p>
                    </div>
                    <div class="text-end">
                        <a href="orders.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
                        </a>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Order Information -->
                    <div class="col-lg-8">
                        <!-- Order Details -->
                        <div class="order-info-card">
                            <h5 class="section-title">
                                <i class="fas fa-info-circle"></i> Informasi Pesanan
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">ID Pesanan:</span>
                                        <span class="info-value">#<?php echo $order['id']; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Tanggal Pesanan:</span>
                                        <span class="info-value"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Metode Pembayaran:</span>
                                        <span class="info-value"><?php echo ucfirst($order['payment_method']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">Status Pesanan:</span>
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
                                    <div class="info-item">
                                        <span class="info-label">Status Pembayaran:</span>
                                        <span class="status-badge payment-<?php echo $order['payment_status']; ?>">
                                            <?php 
                                            $payment_text = [
                                                'paid' => 'Dibayar',
                                                'pending' => 'Menunggu',
                                                'failed' => 'Gagal'
                                            ];
                                            echo $payment_text[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                            ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Total Pembayaran:</span>
                                        <span class="info-value fw-bold">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($order['notes'])): ?>
                                <div class="mt-3">
                                    <strong class="info-label">Catatan:</strong>
                                    <p class="mt-2 p-3" style="background: #F5DEB3; border-radius: 10px; color: #A0522D;">
                                        <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Customer Information -->
                        <div class="customer-info-card">
                            <h5 class="section-title">
                                <i class="fas fa-user"></i> Informasi Pelanggan
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">Nama Lengkap:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($order['full_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">Telepon:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($order['phone'] ?? 'Tidak tersedia'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Alamat:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($order['shipping_address'] ?? $order['address'] ?? 'Tidak tersedia'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="items-card">
                            <h5 class="section-title">
                                <i class="fas fa-shopping-cart"></i> Item Pesanan
                            </h5>
                            
                            <?php foreach ($order_items as $item): ?>
                                <div class="product-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-2">
                                            <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                                <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image">
                                            <?php else: ?>
                                                <div class="image-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            <p class="text-muted mb-1"><?php echo htmlspecialchars($item['brand']); ?></p>
                                            <small class="text-muted">
                                                Ukuran: <?php echo htmlspecialchars($item['size']); ?> | 
                                                Warna: <?php echo htmlspecialchars($item['color']); ?>
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="fw-bold">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <span class="badge bg-secondary"><?php echo $item['quantity']; ?> pcs</span>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <span class="fw-bold">Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="total-section">
                                <div class="total-item">
                                    <span>Subtotal:</span>
                                    <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="total-item">
                                    <span>Ongkos Kirim:</span>
                                    <span>Rp 0</span>
                                </div>
                                <div class="total-item total-final">
                                    <span>Total Pembayaran:</span>
                                    <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Management -->
                    <div class="col-lg-4">
                        <div class="status-card">
                            <h5 class="section-title">
                                <i class="fas fa-cog"></i> Kelola Status
                            </h5>
                            
                            <form method="POST">
                                <input type="hidden" name="update_status" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Status Pesanan</label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                        <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                                        <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Terkirim</option>
                                        <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Status Pembayaran</label>
                                    <select name="payment_status" class="form-select" required>
                                        <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Dibayar</option>
                                        <option value="failed" <?php echo $order['payment_status'] == 'failed' ? 'selected' : ''; ?>>Gagal</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Catatan Admin</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan untuk pesanan ini..."><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> Perbarui Status
                                </button>
                            </form>
                        </div>

                        <!-- Order Timeline -->
                        <div class="status-card">
                            <h5 class="section-title">
                                <i class="fas fa-history"></i> Timeline Pesanan
                            </h5>
                            
                            <div class="timeline">
                                <div class="timeline-item <?php echo in_array($order['status'], ['pending', 'confirmed', 'shipped', 'delivered']) ? 'active' : ''; ?>">
                                    <h6 class="fw-bold">Pesanan Dibuat</h6>
                                    <p class="text-muted mb-0"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></p>
                                </div>
                                
                                <?php if (in_array($order['status'], ['confirmed', 'shipped', 'delivered'])): ?>
                                <div class="timeline-item active">
                                    <h6 class="fw-bold">Pesanan Dikonfirmasi</h6>
                                    <p class="text-muted mb-0">Pesanan telah dikonfirmasi admin</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['status'], ['shipped', 'delivered'])): ?>
                                <div class="timeline-item active">
                                    <h6 class="fw-bold">Pesanan Dikirim</h6>
                                    <p class="text-muted mb-0">Pesanan dalam perjalanan</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] == 'delivered'): ?>
                                <div class="timeline-item active">
                                    <h6 class="fw-bold">Pesanan Terkirim</h6>
                                    <p class="text-muted mb-0">Pesanan telah sampai ke pelanggan</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] == 'cancelled'): ?>
                                <div class="timeline-item" style="color: #dc3545;">
                                    <h6 class="fw-bold">Pesanan Dibatalkan</h6>
                                    <p class="text-muted mb-0">Pesanan telah dibatalkan</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form submission with loading state
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memperbarui...';
            submitBtn.disabled = true;
            
            // Re-enable if there's an error (page doesn't redirect)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
