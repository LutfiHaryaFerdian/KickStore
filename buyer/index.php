<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$category_id = isset($_GET['category']) ? $_GET['category'] : '';
$brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$color = isset($_GET['color']) ? $_GET['color'] : '';
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($category_id) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($brand) {
    $where_conditions[] = "p.brand = ?";
    $params[] = $brand;
}

if ($color) {
    $where_conditions[] = "p.color = ?";
    $params[] = $color;
}

if ($min_price) {
    $where_conditions[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price) {
    $where_conditions[] = "p.price <= ?";
    $params[] = $max_price;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause
          ORDER BY p.created_at DESC
          LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total products for pagination
$count_query = "SELECT COUNT(*) as total FROM products p $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $per_page);

// Get all categories for filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all brands for filter
$brands_query = "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand";
$brands_stmt = $db->prepare($brands_query);
$brands_stmt->execute();
$brands = $brands_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all colors for filter
$colors_query = "SELECT DISTINCT color FROM products WHERE color IS NOT NULL AND color != '' ORDER BY color";
$colors_stmt = $db->prepare($colors_query);
$colors_stmt->execute();
$colors = $colors_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count
$cart_query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$_SESSION['user_id']]);
$cart_count = $cart_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kick Store - Browse Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-card {
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 250px;
            object-fit: cover;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
        }
        .image-placeholder {
            height: 250px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
        }
        .filter-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .filter-section .form-select,
        .filter-section .form-control {
            background-color: rgba(255,255,255,0.9);
            border: none;
            border-radius: 10px;
        }
        .badge-stock {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
            font-size: 0.75rem;
        }
        .price-tag {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        .btn-add-cart {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
            border-radius: 0 0 50px 50px;
        }
        .quantity-selector {
            max-width: 120px;
        }
        .add-to-cart-form {
            display: flex;
            gap: 10px;
            align-items: center;
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
                <form class="d-flex mx-auto" method="GET" action="index.php">
                    <div class="input-group">
                        <input class="form-control" type="search" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if ($cart_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-list"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
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
            <h1 class="display-4 fw-bold mb-3">Find Your Perfect Shoes</h1>
            <p class="lead">Discover our amazing collection of quality footwear</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="container">
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="container">
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="filter-section">
                    <h4 class="mb-4"><i class="fas fa-filter"></i> Filters</h4>
                    <form method="GET" action="index.php">
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Brand</label>
                            <select name="brand" class="form-select">
                                <option value="">All Brands</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['brand']); ?>" <?php echo $brand == $b['brand'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['brand']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Color</label>
                            <select name="color" class="form-select">
                                <option value="">All Colors</option>
                                <?php foreach ($colors as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['color']); ?>" <?php echo $color == $c['color'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['color']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Price Range</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" name="min_price" class="form-control" placeholder="Min $" value="<?php echo $min_price; ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_price" class="form-control" placeholder="Max $" value="<?php echo $max_price; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-light fw-bold">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="index.php" class="btn btn-outline-light">
                                <i class="fas fa-times"></i> Clear All
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold">
                        <i class="fas fa-shoe-prints text-primary"></i> 
                        <?php echo $search ? 'Search Results' : 'All Products'; ?>
                    </h2>
                    <div class="text-muted">
                        <i class="fas fa-box"></i> <?php echo $total_products; ?> products found
                    </div>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-4"></i>
                        <h3>No products found</h3>
                        <p class="text-muted mb-4">Try adjusting your search or filter criteria</p>
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-left"></i> Browse All Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card product-card">
                                    <div class="position-relative">
                                        <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                            <span class="badge bg-warning badge-stock">
                                                <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock']; ?> left!
                                            </span>
                                        <?php elseif ($product['stock'] == 0): ?>
                                            <span class="badge bg-danger badge-stock">
                                                <i class="fas fa-times"></i> Out of Stock
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])): ?>
                                            <img src="../<?php echo $product['image_url']; ?>" class="product-image w-100" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <div class="text-center">
                                                    <i class="fas fa-image fa-3x mb-2"></i>
                                                    <p class="mb-0">No Image Available</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title fw-bold"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($product['brand']); ?></span>
                                            <span class="badge bg-info me-1"><?php echo htmlspecialchars($product['color']); ?></span>
                                            <span class="badge bg-dark">Size: <?php echo htmlspecialchars($product['size']); ?></span>
                                        </div>
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="price-tag">$<?php echo number_format($product['price'], 2); ?></span>
                                            <small class="text-muted">
                                                <i class="fas fa-boxes"></i> <?php echo $product['stock']; ?> in stock
                                            </small>
                                        </div>
                                        
                                        <form method="POST" action="add_to_cart.php" class="add-to-cart-form">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="redirect_url" value="index.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>">
                                            
                                            <?php if ($product['stock'] > 0): ?>
                                                <div class="input-group quantity-selector">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="decreaseQuantity(this)">-</button>
                                                    <input type="number" name="quantity" class="form-control form-control-sm text-center" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="increaseQuantity(this)">+</button>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-add-cart flex-grow-1">
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary w-100" disabled>
                                                    <i class="fas fa-times"></i> Out of Stock
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-5">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?><?php echo !empty($brand) ? '&brand=' . urlencode($brand) : ''; ?><?php echo !empty($color) ? '&color=' . urlencode($color) : ''; ?><?php echo !empty($min_price) ? '&min_price=' . $min_price : ''; ?><?php echo !empty($max_price) ? '&max_price=' . $max_price : ''; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?><?php echo !empty($brand) ? '&brand=' . urlencode($brand) : ''; ?><?php echo !empty($color) ? '&color=' . urlencode($color) : ''; ?><?php echo !empty($min_price) ? '&min_price=' . $min_price : ''; ?><?php echo !empty($max_price) ? '&max_price=' . $max_price : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_id) ? '&category=' . $category_id : ''; ?><?php echo !empty($brand) ? '&brand=' . urlencode($brand) : ''; ?><?php echo !empty($color) ? '&color=' . urlencode($color) : ''; ?><?php echo !empty($min_price) ? '&min_price=' . $min_price : ''; ?><?php echo !empty($max_price) ? '&max_price=' . $max_price : ''; ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
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
    <script>
        function increaseQuantity(button) {
            const input = button.parentElement.querySelector('input[name="quantity"]');
            const max = parseInt(input.getAttribute('max'));
            const current = parseInt(input.value);
            if (current < max) {
                input.value = current + 1;
            }
        }
        
        function decreaseQuantity(button) {
            const input = button.parentElement.querySelector('input[name="quantity"]');
            const current = parseInt(input.value);
            if (current > 1) {
                input.value = current - 1;
            }
        }
    </script>
</body>
</html>
