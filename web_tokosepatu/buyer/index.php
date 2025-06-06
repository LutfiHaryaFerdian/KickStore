<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get all products
$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.status = 'active' ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$cat_query = "SELECT * FROM categories";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Kick Store - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light text-dark" style="background-color: #e5e7eb;">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #6c757d;">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fas fa-shoe-prints"></i> Kick Store</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link text-white" href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
            <a class="nav-link text-white" href="orders.php"><i class="fas fa-list"></i> My Orders</a>
            <div class="dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
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
        <!-- Hero Section -->
        <div class="jumbotron p-5 rounded mb-4" style="background-color: #1e3a8a; color: #fff;">
            <h1 class="display-4">Welcome to Kick Store!</h1>
            <p class="lead">Find the perfect shoes for every occasion</p>
        </div>

        <!-- Filter Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card" style="background-color: #f3f4f6;">
                    <div class="card-body">
                        <h5 class="mb-3">Filter Products</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search products..." 
                                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary" style="background-color:#1e3a8a; border:none; color:#fff;">
                                    Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm" style="background-color: #f9fafb;">
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                            <i class="fas fa-shoe-prints fa-3x text-muted"></i>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title text-dark"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text text-dark"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                            <p class="text-muted">
                                <small>Brand: <?php echo htmlspecialchars($product['brand']); ?> | Size: <?php echo htmlspecialchars($product['size']); ?> | Color: <?php echo htmlspecialchars($product['color']); ?></small>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 text-dark">$<?php echo number_format($product['price'], 2); ?></span>
                                <span class="badge bg-secondary"><?php echo $product['stock']; ?> in stock</span>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top">
                            <?php if ($product['stock'] > 0): ?>
                                <form method="POST" action="add_to_cart.php">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <div class="input-group mb-2">
                                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                        <button type="submit" class="btn btn-primary" style="background-color:#1e3a8a; border:none; color:#fff;">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
