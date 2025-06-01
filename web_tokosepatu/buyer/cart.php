<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get cart items
$query = "SELECT c.*, p.name, p.price, p.stock, p.brand, p.size, p.color 
          FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .navbar {
            background-color: #6c757d;
        }

        .nav-link, .navbar-brand {
            color: white !important;
        }

        .card {
            background-color: #f1f1f1;
            border: 1px solid #dee2e6;
        }

        .card-header {
            background-color: #1e3a8a;
            color: #fff;
        }

        .btn-primary {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }

        .btn-primary:hover {
            background-color: #16387a;
            border-color: #16387a;
        }

        .btn-outline-danger {
            border-color: #6c757d;
            color: #6c757d;
        }

        .btn-outline-danger:hover {
            background-color: #6c757d;
            color: #fff;
        }

        .nav-link.active {
            font-weight: bold;
            text-decoration: underline;
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
                <a class="nav-link active" href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a class="nav-link" href="orders.php"><i class="fas fa-list"></i> My Orders</a>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-shopping-cart"></i> Shopping Cart</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <div class="alert alert-info">
                <h4>Your cart is empty!</h4>
                <p>Start shopping to add items to your cart.</p>
                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="row align-items-center border-bottom py-3">
                                    <div class="col-md-2">
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 80px;">
                                            <i class="fas fa-shoe-prints fa-2x text-muted"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($item['brand']); ?> | Size: <?php echo htmlspecialchars($item['size']); ?> | Color: <?php echo htmlspecialchars($item['color']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>$<?php echo number_format($item['price'], 2); ?></strong>
                                    </div>
                                    <div class="col-md-2">
                                        <form method="POST" action="update_cart.php" class="d-inline">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                            <input type="number" name="quantity" class="form-control form-control-sm" 
                                                   value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>"
                                                   onchange="this.form.submit()">
                                        </form>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                                            <form method="POST" action="remove_from_cart.php" class="d-inline">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span>Subtotal:</span>
                                <strong>$<?php echo number_format($total, 2); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Shipping:</span>
                                <span>Free</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total:</strong>
                                <strong class="text-primary">$<?php echo number_format($total, 2); ?></strong>
                            </div>
                            <a href="checkout.php" class="btn btn-primary w-100">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
