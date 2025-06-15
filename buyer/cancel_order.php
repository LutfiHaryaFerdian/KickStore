<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID pesanan tidak valid!";
    header("Location: orders.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_order'])) {
    $cancel_reason = $_POST['cancel_reason'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Check if order exists and belongs to user
        $check_query = "SELECT * FROM orders WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$order_id, $user_id]);
        $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception("Pesanan tidak ditemukan atau tidak dapat dibatalkan!");
        }
        
        // Get order items to restore stock
        $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->execute([$order_id]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Restore product stock
        foreach ($order_items as $item) {
            $restore_stock_query = "UPDATE products SET stock = stock + ? WHERE id = ?";
            $restore_stock_stmt = $db->prepare($restore_stock_query);
            $restore_stock_stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Update order status
        $cancel_query = "UPDATE orders SET 
                        status = 'cancelled', 
                        payment_status = 'cancelled',
                        notes = CONCAT(COALESCE(notes, ''), '\n\nDibatalkan oleh pelanggan: ', ?)
                        WHERE id = ?";
        $cancel_stmt = $db->prepare($cancel_query);
        $cancel_stmt->execute([$cancel_reason, $order_id]);
        
        // Log cancellation
        $log_query = "INSERT INTO order_logs (order_id, action, description, created_at) 
                     VALUES (?, 'cancelled', ?, NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$order_id, "Pesanan dibatalkan oleh pelanggan. Alasan: " . $cancel_reason]);
        
        $db->commit();
        
        $_SESSION['success'] = "Pesanan berhasil dibatalkan. Stok produk telah dikembalikan.";
        header("Location: orders.php");
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

// Get order details
$order_query = "SELECT o.*, 
                COUNT(oi.id) as total_items,
                SUM(oi.quantity) as total_quantity
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.id = ? AND o.user_id = ?
                GROUP BY o.id";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id, $user_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Pesanan tidak ditemukan!";
    header("Location: orders.php");
    exit();
}

// Check if order can be cancelled
$can_cancel = in_array($order['status'], ['pending', 'confirmed']) && $order['payment_status'] != 'paid';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batalkan Pesanan #<?php echo $order['id']; ?> - Toko Sepatu Kick</title>
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
        
        .cancel-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 40px;
            margin: 30px 0;
            max-width: 800px;
        }
        
        .cancel-header {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: #721c24;
            text-align: center;
        }
        
        .order-summary {
            background: #FEFEFE;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F5DEB3;
        }
        
        .order-info:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #A0522D;
        }
        
        .info-value {
            color: #8B4513;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #d1ecf1 0%, #74b9ff 100%);
            color: #0c5460;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            color: #856404;
        }
        
        .warning-box h5 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .warning-box ul {
            margin-bottom: 0;
            padding-left: 20px;
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
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
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
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .cannot-cancel {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #dc3545;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            color: #721c24;
        }
        
        .cannot-cancel i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.7;
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
                <li class="breadcrumb-item"><a href="order_details.php?id=<?php echo $order['id']; ?>">Detail Pesanan #<?php echo $order['id']; ?></a></li>
                <li class="breadcrumb-item active">Batalkan Pesanan</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="cancel-container">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$can_cancel): ?>
                        <!-- Cannot Cancel -->
                        <div class="cannot-cancel">
                            <i class="fas fa-times-circle"></i>
                            <h3>Pesanan Tidak Dapat Dibatalkan</h3>
                            <p class="mb-4">
                                <?php if ($order['status'] == 'shipped' || $order['status'] == 'delivered'): ?>
                                    Pesanan sudah dalam tahap pengiriman atau telah terkirim dan tidak dapat dibatalkan.
                                <?php elseif ($order['payment_status'] == 'paid'): ?>
                                    Pesanan sudah dibayar dan tidak dapat dibatalkan. Silakan hubungi customer service untuk bantuan.
                                <?php elseif ($order['status'] == 'cancelled'): ?>
                                    Pesanan ini sudah dibatalkan sebelumnya.
                                <?php else: ?>
                                    Pesanan dalam status yang tidak memungkinkan untuk dibatalkan.
                                <?php endif; ?>
                            </p>
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="orders.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Pesanan
                                </a>
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> Lihat Detail
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Cancel Form -->
                        <div class="cancel-header">
                            <h2 class="fw-bold mb-2">
                                <i class="fas fa-exclamation-triangle"></i> Batalkan Pesanan
                            </h2>
                            <p class="mb-0">Anda akan membatalkan pesanan #<?php echo $order['id']; ?></p>
                        </div>

                        <!-- Order Summary -->
                        <div class="order-summary">
                            <h5 class="fw-bold mb-3" style="color: #A0522D;">
                                <i class="fas fa-receipt"></i> Ringkasan Pesanan
                            </h5>
                            
                            <div class="order-info">
                                <span class="info-label">ID Pesanan:</span>
                                <span class="info-value">#<?php echo $order['id']; ?></span>
                            </div>
                            
                            <div class="order-info">
                                <span class="info-label">Tanggal Pesanan:</span>
                                <span class="info-value"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
                            </div>
                            
                            <div class="order-info">
                                <span class="info-label">Total Item:</span>
                                <span class="info-value"><?php echo $order['total_items']; ?> produk (<?php echo $order['total_quantity']; ?> pcs)</span>
                            </div>
                            
                            <div class="order-info">
                                <span class="info-label">Total Pembayaran:</span>
                                <span class="info-value fw-bold">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                            </div>
                            
                            <div class="order-info">
                                <span class="info-label">Status:</span>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'Menunggu Konfirmasi',
                                        'confirmed' => 'Dikonfirmasi'
                                    ];
                                    echo $status_text[$order['status']] ?? ucfirst($order['status']);
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Warning -->
                        <div class="warning-box">
                            <h5><i class="fas fa-exclamation-triangle"></i> Perhatian!</h5>
                            <p class="mb-2">Dengan membatalkan pesanan ini:</p>
                            <ul>
                                <li>Stok produk akan dikembalikan ke inventori</li>
                                <li>Pesanan tidak dapat diaktifkan kembali</li>
                                <li>Jika sudah melakukan pembayaran, proses refund akan diproses dalam 1-3 hari kerja</li>
                                <li>Anda perlu membuat pesanan baru jika ingin membeli produk yang sama</li>
                            </ul>
                        </div>

                        <!-- Cancel Form -->
                        <form method="POST" id="cancelForm">
                            <input type="hidden" name="cancel_order" value="1">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold" style="color: #A0522D;">
                                    <i class="fas fa-comment"></i> Alasan Pembatalan *
                                </label>
                                <select name="cancel_reason" class="form-select mb-3" required>
                                    <option value="">Pilih alasan pembatalan...</option>
                                    <option value="Berubah pikiran">Berubah pikiran</option>
                                    <option value="Menemukan harga lebih murah">Menemukan harga lebih murah</option>
                                    <option value="Produk tidak sesuai kebutuhan">Produk tidak sesuai kebutuhan</option>
                                    <option value="Kesalahan dalam pemesanan">Kesalahan dalam pemesanan</option>
                                    <option value="Masalah pembayaran">Masalah pembayaran</option>
                                    <option value="Pengiriman terlalu lama">Pengiriman terlalu lama</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                                
                                <textarea name="additional_notes" class="form-control" rows="3" placeholder="Catatan tambahan (opsional)..."></textarea>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali
                                </a>
                                <button type="submit" class="btn btn-danger" id="cancelBtn">
                                    <i class="fas fa-times"></i> Batalkan Pesanan
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Konfirmasi Pembatalan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Apakah Anda yakin ingin membatalkan pesanan #<?php echo $order['id']; ?>?</p>
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Tindakan ini tidak dapat dibatalkan. Stok produk akan dikembalikan dan pesanan akan ditandai sebagai dibatalkan.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Tidak
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">
                        <i class="fas fa-check"></i> Ya, Batalkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cancelForm = document.getElementById('cancelForm');
            const cancelBtn = document.getElementById('cancelBtn');
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            const confirmCancel = document.getElementById('confirmCancel');
            
            // Prevent direct form submission
            cancelForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                const reason = document.querySelector('select[name="cancel_reason"]').value;
                if (!reason) {
                    alert('Silakan pilih alasan pembatalan!');
                    return;
                }
                
                // Show confirmation modal
                confirmModal.show();
            });
            
            // Handle confirmation
            confirmCancel.addEventListener('click', function() {
                // Show loading state
                cancelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membatalkan...';
                cancelBtn.disabled = true;
                
                // Submit form
                cancelForm.submit();
            });
            
            // Handle reason change for "Lainnya"
            document.querySelector('select[name="cancel_reason"]').addEventListener('change', function() {
                const textarea = document.querySelector('textarea[name="additional_notes"]');
                if (this.value === 'Lainnya') {
                    textarea.required = true;
                    textarea.placeholder = 'Silakan jelaskan alasan pembatalan...';
                    textarea.focus();
                } else {
                    textarea.required = false;
                    textarea.placeholder = 'Catatan tambahan (opsional)...';
                }
            });
        });
    </script>
</body>
</html>
