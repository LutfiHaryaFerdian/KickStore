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
$query = "SELECT c.*, p.name, p.price, p.stock 
          FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Get user info
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Process order
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipping_address = $_POST['shipping_address'];
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    
    try {
        $db->beginTransaction();
        
        // Create order
        $order_query = "INSERT INTO orders (user_id, total_amount, shipping_address, payment_method, notes) 
                        VALUES (?, ?, ?, ?, ?)";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->execute([$_SESSION['user_id'], $total, $shipping_address, $payment_method, $notes]);
        $order_id = $db->lastInsertId();
        
        // Add order items and update stock
        foreach ($cart_items as $item) {
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                           VALUES (?, ?, ?, ?)";
            $item_stmt = $db->prepare($item_query);
            $item_stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
            
            $stock_query = "UPDATE products SET stock = stock - ? WHERE id = ?";
            $stock_stmt = $db->prepare($stock_query);
            $stock_stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Clear cart
        $clear_query = "DELETE FROM cart WHERE user_id = ?";
        $clear_stmt = $db->prepare($clear_query);
        $clear_stmt->execute([$_SESSION['user_id']]);
        
        $db->commit();
        
        $_SESSION['message'] = "Order placed successfully!";
        header("Location: order_success.php?order_id=" . $order_id);
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Failed to place order. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f1f1f1;
        }

        .navbar-dark {
            background-color: #6c757d !important;
        }

        .card {
            background-color: #e5e7eb;
            border: none;
        }

        .card-header {
            background-color: #374151;
            color: #ffffff;
        }

        .form-control,
        .form-check-input {
            background-color: #f9fafb;
            border-color: #cbd5e1;
            color: #111827;
        }

        .btn-success {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
        }

        .btn-success:hover {
            background-color: #1a3575;
            border-color: #1a3575;
        }

        .text-primary {
            color: #1e3a8a !important;
        }

        .nav-link {
            color: #d1d5db !important;
        }

        .nav-link:hover {
            color: #ffffff !important;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
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
        <h2><i class="fas fa-credit-card"></i> Checkout</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <form method="POST">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Shipping Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" value="<?php echo $user['full_name']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" value="<?php echo $user['email']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" value="<?php echo $user['phone']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="shipping_address" class="form-label">Shipping Address</label>
                                <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required><?php echo $user['address']; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="cod" value="Cash on Delivery" checked>
                                <label class="form-check-label" for="cod">
                                    <i class="fas fa-money-bill"></i> Cash on Delivery
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="bank_transfer" value="Bank Transfer">
                                <label class="form-check-label" for="bank_transfer">
                                    <i class="fas fa-university"></i> Bank Transfer
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="Credit Card">
                                <label class="form-check-label" for="credit_card">
                                    <i class="fas fa-credit-card"></i> Credit Card
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Order Notes (Optional)</h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any special instructions for your order..."></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-check"></i> Place Order
                    </button>
                </form>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo $item['name']; ?> x<?php echo $item['quantity']; ?></span>
                                <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span>Free</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span>$0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong class="text-primary">$<?php echo number_format($total, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
