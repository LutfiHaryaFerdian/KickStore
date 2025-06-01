<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: index.php");
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
    header("Location: index.php");
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
        body {
            background-color: #f3f4f6;
            color: #1f2937;
        }

        .navbar-dark {
            background-color: #6c757d !important;
        }

        .card {
            background-color: #e5e7eb;
            border: none;
        }

        .btn-primary {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }

        .btn-primary:hover {
            background-color: #1a3575;
            border-color: #1a3575;
        }

        .btn-outline-primary {
            border-color: #1e3a8a;
            color: #1e3a8a;
        }

        .btn-outline-primary:hover {
            background-color: #1e3a8a;
            color: #fff;
        }

        .badge.bg-warning {
            background-color: #f59e0b;
        }

        .lead {
            color: #374151;
        }

        h2.text-success {
            color: #10b981 !important;
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
                <a class="nav-link" href="orders.php"><i class="fas fa-list"></i> My Orders</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle fa-5x text-success"></i>
                        </div>
                        <h2 class="text-success mb-3">Order Placed Successfully!</h2>
                        <p class="lead">Thank you for your order. Your order has been received and is being processed.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Order Details</h5>
                                <p><strong>Order ID:</strong> #<?php echo $order['id']; ?></p>
                                <p><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-warning">Pending</span></p>
                            </div>
                            <div class="col-md-6 text-start">
                                <h5>What's Next?</h5>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Order confirmation email sent</li>
                                    <li><i class="fas fa-clock text-warning"></i> Order processing (1-2 business days)</li>
                                    <li><i class="fas fa-truck text-info"></i> Shipping notification</li>
                                    <li><i class="fas fa-home text-primary"></i> Delivery (3-5 business days)</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="orders.php" class="btn btn-primary me-2">
                                <i class="fas fa-list"></i> View My Orders
                            </a>
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-shopping-bag"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
