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

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'];

// Get order details
$order_query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id, $user_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found!";
    header("Location: orders.php");
    exit();
}

// Get order items with product details
$items_query = "SELECT oi.*, p.name, p.brand, p.color, p.size, p.image_url, c.name as category_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE oi.order_id = ?
                ORDER BY oi.id";

$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo htmlspecialchars($order['id']); ?> - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .status-badge {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-item {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .image-placeholder {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            position: sticky;
            top: 100px;
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
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="orders.php">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Order Header -->
        <div class="order-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-receipt"></i> Order #<?php echo htmlspecialchars($order['id']); ?>
                    </h1>
                    <p class="mb-3">
                        <i class="fas fa-calendar"></i> 
                        Placed on <?php echo date('F d, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                    </p>
                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                        <?php 
                        $status_icons = [
                            'pending' => 'fas fa-clock',
                            'processing' => 'fas fa-cog fa-spin',
                            'shipped' => 'fas fa-truck',
                            'delivered' => 'fas fa-check-circle',
                            'cancelled' => 'fas fa-times-circle'
                        ];
                        $icon = $status_icons[strtolower($order['status'])] ?? 'fas fa-info-circle';
                        ?>
                        <i class="<?php echo $icon; ?>"></i> 
                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                    </span>
                </div>
                <div class="col-md-4 text-end">
                    <div class="h2 mb-0">$<?php echo number_format($order['total_amount'], 2); ?></div>
                    <small>Total Amount</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Items -->
            <div class="col-lg-8">
                <h3 class="mb-4">
                    <i class="fas fa-box"></i> Order Items (<?php echo count($order_items); ?>)
                </h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <!-- Product Image -->
                                <div class="col-md-2 col-sm-3 text-center mb-3 mb-md-0">
                                    <?php if (!empty($item['image_url']) && file_exists('../' . $item['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="image-placeholder">
                                            <i class="fas fa-image fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Product Details -->
                                <div class="col-md-6 col-sm-9">
                                    <h5 class="fw-bold mb-2"><?php echo htmlspecialchars($item['name']); ?></h5>
                                    <div class="mb-2">
                                        <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($item['brand']); ?></span>
                                        <span class="badge bg-info me-1"><?php echo htmlspecialchars($item['color']); ?></span>
                                        <span class="badge bg-dark">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['category_name'] ?? 'No Category'); ?>
                                    </p>
                                </div>
                                
                                <!-- Quantity & Price -->
                                <div class="col-md-2 col-6 text-center">
                                    <div class="fw-bold">Qty: <?php echo $item['quantity']; ?></div>
                                    <small class="text-muted">$<?php echo number_format($item['price'], 2); ?> each</small>
                                </div>
                                
                                <div class="col-md-2 col-6 text-center">
                                    <div class="h5 fw-bold text-primary">
                                        $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h4 class="mb-4">
                        <i class="fas fa-file-invoice-dollar"></i> Order Summary
                    </h4>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal:</span>
                        <span class="fw-bold">$<?php echo number_format($order['subtotal'] ?? 0, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>Tax:</span>
                        <span class="fw-bold">$<?php echo number_format($order['tax_amount'] ?? 0, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping:</span>
                        <span class="fw-bold">$<?php echo number_format($order['shipping_amount'] ?? 0, 2); ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <span class="h5">Total:</span>
                        <span class="h4 fw-bold text-primary">$<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                    
                    <div class="mb-4">
                        <strong>Payment Method:</strong><br>
                        <span class="text-muted"><?php echo ucfirst(htmlspecialchars($order['payment_method'] ?? 'N/A')); ?></span>
                    </div>
                    
                    <?php if (!empty($order['shipping_address'])): ?>
                        <div class="mb-4">
                            <strong><i class="fas fa-map-marker-alt"></i> Shipping Address:</strong><br>
                            <span class="text-muted">
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['notes'])): ?>
                        <div class="mb-4">
                            <strong><i class="fas fa-sticky-note"></i> Order Notes:</strong><br>
                            <span class="text-muted">
                                <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid">
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
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
