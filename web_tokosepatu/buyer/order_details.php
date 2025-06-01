<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header("Location: orders.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.brand, p.size, p.color 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_query);
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #e0e0e0;
        }

        .navbar-dark {
            background-color: #6c757d !important;
        }

        .card-header {
            background-color: #1a237e;
            color: white;
        }

        .card {
            background-color: #f5f5f5;
        }

        .btn-secondary {
            background-color: #546e7a;
            border-color: #546e7a;
        }

        .btn-secondary:hover {
            background-color: #455a64;
            border-color: #455a64;
        }

        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
        }

        .btn-primary:hover {
            background-color: #0d1645;
            border-color: #0d1645;
        }

        .bg-light {
            background-color: #cfd8dc !important;
        }

        .text-muted {
            color: #616161 !important;
        }

        .badge.bg-warning {
            background-color: #ffa000 !important;
            color: #212121;
        }

        .badge.bg-info {
            background-color: #0288d1 !important;
        }

        .badge.bg-primary {
            background-color: #1a237e !important;
        }

        .badge.bg-success {
            background-color: #2e7d32 !important;
        }

        .badge.bg-danger {
            background-color: #c62828 !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-shoe-prints"></i> Kick Store</a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a>
                <a class="nav-link" href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a class="nav-link" href="orders.php"><i class="fas fa-list"></i> My Orders</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-receipt"></i> Order Details #<?php echo $order['id']; ?></h2>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Order Items -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Order Items</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($order_items as $item): ?>
                            <div class="row align-items-center border-bottom py-3">
                                <div class="col-md-2">
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 80px;">
                                        <i class="fas fa-shoe-prints fa-2x text-muted"></i>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <h6><?php echo $item['name']; ?></h6>
                                    <small class="text-muted">
                                        <?php echo $item['brand']; ?> | Size: <?php echo $item['size']; ?> | Color: <?php echo $item['color']; ?>
                                    </small>
                                </div>
                                <div class="col-md-2">
                                    <span>Qty: <?php echo $item['quantity']; ?></span>
                                </div>
                                <div class="col-md-2">
                                    <span>$<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                <div class="col-md-1">
                                    <strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="row mt-3">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between">
                                    <strong>Total: $<?php echo number_format($order['total_amount'], 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Order Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Order Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Order ID:</strong> #<?php echo $order['id']; ?></p>
                        <p><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $order['status'] == 'pending' ? 'warning' : 
                                    ($order['status'] == 'confirmed' ? 'info' : 
                                    ($order['status'] == 'shipped' ? 'primary' : 
                                    ($order['status'] == 'delivered' ? 'success' : 'danger'))); 
                            ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </p>
                        <p><strong>Payment Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $order['payment_status'] == 'paid' ? 'success' : 
                                    ($order['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </p>
                        <p><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>
                        <p><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="card">
                    <div class="card-header">
                        <h5>Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Shipping Address:</strong></p>
                        <p class="text-muted"><?php echo nl2br($order['shipping_address']); ?></p>
                        
                        <?php if ($order['notes']): ?>
                            <p><strong>Order Notes:</strong></p>
                            <p class="text-muted"><?php echo nl2br($order['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
