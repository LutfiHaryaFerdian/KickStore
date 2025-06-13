<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    $_SESSION['error'] = "Invalid order ID!";
    header("Location: orders.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order details
$order_query = "SELECT o.*, COUNT(oi.id) as total_items, SUM(oi.quantity) as total_quantity
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.id = ? AND o.user_id = ?
                GROUP BY o.id";
$order_stmt = $db->prepare($order_query);
$order_stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found!";
    header("Location: orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
            border-radius: 0 0 50px 50px;
        }
        
        .order-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: #28a745;
            font-size: 3rem;
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
        }
        
        .order-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .btn-action {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Success Section -->
    <div class="success-section">
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="display-4 fw-bold mb-3">Order Placed Successfully!</h1>
            <p class="lead">Thank you for your purchase. Your order has been received and is being processed.</p>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="order-card">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-primary">Order Confirmation</h2>
                        <p class="text-muted">Your order details and next steps</p>
                    </div>
                    
                    <div class="order-info">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-receipt text-primary"></i> Order Number:</strong><br>
                                <span class="h5 text-primary">#<?php echo htmlspecialchars($order['id']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-calendar text-success"></i> Order Date:</strong><br>
                                <?php echo date('F d, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-dollar-sign text-warning"></i> Total Amount:</strong><br>
                                <span class="h5 text-success">$<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-credit-card text-info"></i> Payment Method:</strong><br>
                                <?php echo ucfirst(str_replace(' ', ' ', $order['payment_method'])); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-box text-secondary"></i> Total Items:</strong><br>
                                <?php echo $order['total_items']; ?> products (<?php echo $order['total_quantity']; ?> items)
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="fas fa-info-circle text-primary"></i> Status:</strong><br>
                                <span class="badge bg-warning text-dark fs-6">
                                    <i class="fas fa-clock"></i> <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['shipping_address'])): ?>
                            <hr>
                            <div class="mb-3">
                                <strong><i class="fas fa-map-marker-alt text-danger"></i> Shipping Address:</strong><br>
                                <div class="mt-2 p-3 bg-white rounded">
                                    <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['notes'])): ?>
                            <hr>
                            <div class="mb-3">
                                <strong><i class="fas fa-sticky-note text-warning"></i> Order Notes:</strong><br>
                                <div class="mt-2 p-3 bg-white rounded">
                                    <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> What's Next?</h5>
                        <ul class="mb-0">
                            <li>Your order will be processed within 1-2 business days</li>
                            <li>You can track your order status in "My Orders" section</li>
                            <?php if ($order['payment_method'] == 'Cash on Delivery'): ?>
                                <li>Prepare exact cash amount for delivery</li>
                            <?php else: ?>
                                <li>Complete payment via bank transfer to process your order</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="text-center">
                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-action">
                            <i class="fas fa-eye"></i> View Order Details
                        </a>
                        <a href="orders.php" class="btn btn-success btn-action">
                            <i class="fas fa-list"></i> My Orders
                        </a>
                        <a href="index.php" class="btn btn-outline-primary btn-action">
                            <i class="fas fa-shopping-bag"></i> Continue Shopping
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
