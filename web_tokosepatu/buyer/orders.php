<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user orders
$query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
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
        body {
            background-color: #e0e0e0;
        }

        .navbar-dark {
            background-color: #6c757d !important;
        }

        .card {
            background-color: #f5f5f5;
        }

        .card-header {
            background-color: #1a237e;
            color: white;
        }

        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
        }

        .btn-primary:hover {
            background-color: #0d1645;
            border-color: #0d1645;
        }

        .btn-secondary {
            background-color: #546e7a;
            border-color: #546e7a;
        }

        .btn-secondary:hover {
            background-color: #455a64;
            border-color: #455a64;
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
                <a class="nav-link active" href="orders.php"><i class="fas fa-list"></i> My Orders</a>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-list"></i> My Orders</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info">
                <h4>No orders found!</h4>
                <p>You haven't placed any orders yet.</p>
                <a href="index.php" class="btn btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <strong>Order #<?php echo $order['id']; ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php 
                                            echo $order['status'] == 'pending' ? 'warning' : 
                                                ($order['status'] == 'confirmed' ? 'info' : 
                                                ($order['status'] == 'shipped' ? 'primary' : 
                                                ($order['status'] == 'delivered' ? 'success' : 'danger'))); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="badge bg-<?php 
                                            echo $order['payment_status'] == 'paid' ? 'success' : 
                                                ($order['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            Payment: <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>
                                        <p><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Shipping Address:</strong></p>
                                        <p class="text-muted"><?php echo nl2br($order['shipping_address']); ?></p>
                                    </div>
                                </div>
                                <?php if ($order['notes']): ?>
                                    <p><strong>Notes:</strong> <?php echo $order['notes']; ?></p>
                                <?php endif; ?>
                                <div class="text-end">
                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
