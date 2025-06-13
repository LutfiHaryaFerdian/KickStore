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

// Get all orders for the user
$query = "SELECT o.*, 
                 COUNT(oi.id) as total_items,
                 SUM(oi.quantity) as total_quantity
          FROM orders o
          LEFT JOIN order_items oi ON o.id = oi.order_id
          WHERE o.user_id = ?
          GROUP BY o.id
          ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 20px;
        }
        
        .order-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #74c0fc;
        }
        
        .status-shipped {
            background: #d4edda;
            color: #155724;
            border: 1px solid #81c784;
        }
        
        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #4dd0e1;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            border-radius: 0 0 50px 50px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-details {
            flex-grow: 1;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-view-details {
            background: white;
            color: #667eea;
            border: 2px solid white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-view-details:hover {
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .order-info {
                text-align: center;
            }
            
            .order-actions {
                justify-content: center;
                width: 100%;
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
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
                            <i class="fas fa-list"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
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
                <i class="fas fa-list"></i> My Orders
            </h1>
            <p class="lead">Track and manage your shoe orders</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="container">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <?php if (empty($orders)): ?>
            <!-- Empty Orders -->
            <div class="empty-orders">
                <i class="fas fa-shopping-bag fa-5x text-muted mb-4"></i>
                <h2 class="fw-bold mb-3">No Orders Yet</h2>
                <p class="text-muted mb-4">You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold">
                    <i class="fas fa-history"></i> Order History (<?php echo count($orders); ?>)
                </h3>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-plus"></i> Continue Shopping
                </a>
            </div>
            
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-details">
                                <h5 class="mb-2 fw-bold">
                                    <i class="fas fa-receipt"></i> Order #<?php echo htmlspecialchars($order['id']); ?>
                                </h5>
                                <div class="mb-2">
                                    <small>
                                        <i class="fas fa-calendar"></i> 
                                        Placed on <?php echo date('M d, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                                    </small>
                                </div>
                                <div>
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
                            </div>
                            
                            <div class="order-actions">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-view-details">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-summary">
                        <div class="row">
                            <div class="col-md-3 col-6 text-center mb-3 mb-md-0">
                                <div class="fw-bold text-primary">Total Amount</div>
                                <div class="h5 mb-0">$<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3 mb-md-0">
                                <div class="fw-bold text-success">Items</div>
                                <div class="h5 mb-0"><?php echo $order['total_items'] ?? 0; ?> products</div>
                            </div>
                            <div class="col-md-3 col-6 text-center mb-3 mb-md-0">
                                <div class="fw-bold text-info">Quantity</div>
                                <div class="h5 mb-0"><?php echo $order['total_quantity'] ?? 0; ?> items</div>
                            </div>
                            <div class="col-md-3 col-6 text-center">
                                <div class="fw-bold text-warning">Payment</div>
                                <div class="h5 mb-0"><?php echo ucfirst(htmlspecialchars($order['payment_method'] ?? 'N/A')); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['shipping_address'])): ?>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><i class="fas fa-map-marker-alt"></i> Shipping Address:</strong>
                                    <div class="text-muted">
                                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                    </div>
                                </div>
                                <?php if (!empty($order['notes'])): ?>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-sticky-note"></i> Order Notes:</strong>
                                        <div class="text-muted">
                                            <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Order Actions based on status -->
                        <?php if ($order['status'] == 'pending'): ?>
                            <hr>
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Your order is being processed. You will receive a confirmation email shortly.
                                </small>
                            </div>
                        <?php elseif ($order['status'] == 'processing'): ?>
                            <hr>
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-cog"></i> 
                                    Your order is being prepared for shipment.
                                </small>
                            </div>
                        <?php elseif ($order['status'] == 'shipped'): ?>
                            <hr>
                            <div class="text-center">
                                <small class="text-success">
                                    <i class="fas fa-truck"></i> 
                                    Your order has been shipped and is on its way!
                                </small>
                            </div>
                        <?php elseif ($order['status'] == 'delivered'): ?>
                            <hr>
                            <div class="text-center">
                                <small class="text-success">
                                    <i class="fas fa-check-circle"></i> 
                                    Order delivered successfully. Thank you for shopping with us!
                                </small>
                            </div>
                        <?php elseif ($order['status'] == 'cancelled'): ?>
                            <hr>
                            <div class="text-center">
                                <small class="text-danger">
                                    <i class="fas fa-times-circle"></i> 
                                    This order has been cancelled.
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
