<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query conditions
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_filter) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Sort options
$sort_options = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name_az' => 'p.name ASC',
    'name_za' => 'p.name DESC'
];

$order_clause = "ORDER BY " . ($sort_options[$sort] ?? $sort_options['newest']);

// Get products
$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause 
          $order_clause";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$cat_query = "SELECT * FROM categories ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count
$cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$_SESSION['user_id']]);
$cart_count = $cart_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toko Sepatu Kick - Beranda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%) !important;
            box-shadow: 0 2px 20px rgba(160, 82, 45, 0.3);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .search-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            margin-top: -30px;
            position: relative;
            z-index: 10;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #D2B48C;
            color: #A0522D;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-color: #CD853F;
            color: white;
            transform: translateY(-2px);
        }
        
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(210, 180, 140, 0.3);
        }
        
        .product-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.05);
        }
        
        .product-price {
            color: #A0522D;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .product-brand {
            color: #CD853F;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .badge {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-radius: 10px;
            padding: 5px 10px;
        }
        
        .form-control, .form-select {
            border: 2px solid #F5DEB3;
            border-radius: 10px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #D2B48C;
            box-shadow: 0 0 0 0.2rem rgba(210, 180, 140, 0.25);
        }
        
        .cart-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(210, 180, 140, 0.1);
            margin-bottom: 30px;
        }
        
        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #A0522D;
        }
        
        .no-products i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shoe-prints"></i> Kick Store
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Beranda
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-bag"></i> Pesanan Saya
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                            <?php if ($cart_count > 0): ?>
                                <span class="cart-badge"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4" style="color: #A0522D;">
                        Temukan Sepatu Impian Anda
                    </h1>
                    <p class="lead mb-4" style="color: #8B4513;">
                        Koleksi sepatu premium dengan kualitas terbaik dan desain terkini untuk setiap kesempatan.
                    </p>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-shoe-prints" style="font-size: 8rem; color: #CD853F; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Search Section -->
        <div class="search-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text" style="background: #F5DEB3; border-color: #F5DEB3;">
                            <i class="fas fa-search" style="color: #A0522D;"></i>
                        </span>
                        <input type="text" class="form-control" name="search" placeholder="Cari sepatu..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                        <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                        <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                        <option value="name_az" <?php echo $sort == 'name_az' ? 'selected' : ''; ?>>Nama A-Z</option>
                        <option value="name_za" <?php echo $sort == 'name_za' ? 'selected' : ''; ?>>Nama Z-A</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Products Section -->
        <div class="row mt-4">
            <?php if (empty($products)): ?>
                <div class="col-12">
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h3>Tidak Ada Produk Ditemukan</h3>
                        <p class="text-muted">Coba ubah kata kunci pencarian atau filter Anda.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-refresh"></i> Lihat Semua Produk
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="product-card">
                            <div class="position-relative overflow-hidden">
                                <?php if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])): ?>
                                    <img src="../<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="product-image d-flex align-items-center justify-content-center" style="background: #F5DEB3;">
                                        <i class="fas fa-image fa-3x" style="color: #CD853F;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                    <span class="badge position-absolute top-0 start-0 m-2" style="background: #dc3545;">
                                        Stok Terbatas
                                    </span>
                                <?php elseif ($product['stock'] == 0): ?>
                                    <span class="badge position-absolute top-0 start-0 m-2" style="background: #6c757d;">
                                        Habis
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body p-3">
                                <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
                                <h6 class="card-title fw-bold mb-2"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <p class="card-text text-muted small mb-2">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 80)) . (strlen($product['description']) > 80 ? '...' : ''); ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="product-price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></span>
                                    <small class="text-muted">
                                        <i class="fas fa-tags"></i> <?php echo htmlspecialchars($product['category_name'] ?? 'Tanpa Kategori'); ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-ruler"></i> <?php echo htmlspecialchars($product['size']); ?> | 
                                        <i class="fas fa-palette"></i> <?php echo htmlspecialchars($product['color']); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-boxes"></i> <?php echo $product['stock']; ?> tersisa
                                    </small>
                                </div>
                                
                                <?php if ($product['stock'] > 0): ?>
                                    <form method="POST" action="add_to_cart.php" class="d-grid">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-times"></i> Stok Habis
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4" style="background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%); color: white;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-shoe-prints"></i> Kick Store
                    </h5>
                    <p class="opacity-75">Toko sepatu terpercaya dengan koleksi premium dan pelayanan terbaik.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="opacity-75 mb-0">© 2024 Kick Store. Semua hak dilindungi.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add to cart with animation
        document.querySelectorAll('form[action="add_to_cart.php"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button');
                const originalText = button.innerHTML;
                
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan...';
                button.disabled = true;
                
                // Re-enable after form submission
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 1000);
            });
        });
    </script>
</body>
</html>
