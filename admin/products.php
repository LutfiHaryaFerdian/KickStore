<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Handle image upload
                    $image_url = '';
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                        $allowed = array('jpg', 'jpeg', 'png', 'gif');
                        $filename = $_FILES['product_image']['name'];
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        if (in_array(strtolower($ext), $allowed)) {
                            $new_filename = uniqid() . '_' . time() . '.' . $ext;
                            $destination = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
                                $image_url = 'uploads/products/' . $new_filename;
                            } else {
                                throw new Exception("Gagal mengupload gambar!");
                            }
                        } else {
                            throw new Exception("Tipe file tidak valid! Hanya JPG, JPEG, PNG, dan GIF yang diizinkan.");
                        }
                    }
                    
                    $query = "INSERT INTO products (name, description, price, stock, category_id, brand, size, color, image_url, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $_POST['name'], $_POST['description'], $_POST['price'], $_POST['stock'],
                        $_POST['category_id'], $_POST['brand'], $_POST['size'], $_POST['color'], $image_url
                    ]);
                    
                    if ($result) {
                        $success = "Produk berhasil ditambahkan!";
                    } else {
                        throw new Exception("Gagal menambahkan produk ke database!");
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    // Handle image upload for update
                    $image_url = $_POST['current_image'] ?? '';
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                        $allowed = array('jpg', 'jpeg', 'png', 'gif');
                        $filename = $_FILES['product_image']['name'];
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        
                        if (in_array(strtolower($ext), $allowed)) {
                            $new_filename = uniqid() . '_' . time() . '.' . $ext;
                            $destination = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
                                // Delete old image if exists
                                if (!empty($_POST['current_image']) && file_exists('../' . $_POST['current_image'])) {
                                    @unlink('../' . $_POST['current_image']);
                                }
                                $image_url = 'uploads/products/' . $new_filename;
                            }
                        }
                    }
                    
                    $query = "UPDATE products SET name=?, description=?, price=?, stock=?, category_id=?, brand=?, size=?, color=?, image_url=? 
                              WHERE id=?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $_POST['name'], $_POST['description'], $_POST['price'], $_POST['stock'],
                        $_POST['category_id'], $_POST['brand'], $_POST['size'], $_POST['color'], $image_url, $_POST['product_id']
                    ]);
                    
                    if ($result) {
                        $success = "Produk berhasil diperbarui!";
                    } else {
                        throw new Exception("Gagal memperbarui produk!");
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Get image URL before deleting
                    $img_query = "SELECT image_url FROM products WHERE id=?";
                    $img_stmt = $db->prepare($img_query);
                    $img_stmt->execute([$_POST['product_id']]);
                    $product = $img_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete the product
                    $query = "DELETE FROM products WHERE id=?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$_POST['product_id']]);
                    
                    if ($result) {
                        // Delete the image file if exists
                        if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])) {
                            @unlink('../' . $product['image_url']);
                        }
                        $success = "Produk berhasil dihapus!";
                    } else {
                        throw new Exception("Gagal menghapus produk!");
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with filters
$where_conditions = [];
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

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all products
$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$cat_query = "SELECT * FROM categories ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%) !important;
            box-shadow: 0 2px 20px rgba(160, 82, 45, 0.3);
        }
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            margin: 30px 0;
            overflow: hidden;
        }
        
        .page-header {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            padding: 30px;
            color: #A0522D;
        }
        
        .filter-card {
            background: white;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .product-card {
            background: white;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .product-card:hover {
            border-color: #D2B48C;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.3);
            transform: translateY(-5px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
        }
        
        .image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #A0522D;
            font-size: 3rem;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-title {
            color: #A0522D;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .product-brand {
            color: #8B4513;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .product-price {
            color: #CD853F;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stock-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .stock-high {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .stock-medium {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .stock-low {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
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
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-color: #CD853F;
            color: white;
        }
        
        .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-outline-danger:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .form-control, .form-select {
            border: 2px solid #F5DEB3;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #D2B48C;
            box-shadow: 0 0 0 0.2rem rgba(210, 180, 140, 0.25);
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(210, 180, 140, 0.3);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        
        .upload-area {
            border: 2px dashed #D2B48C;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: #CD853F;
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
        }
        
        .product-image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px solid #D2B48C;
            margin-top: 15px;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8B4513;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
                padding: 15px;
            }
            
            .main-container {
                margin: 15px;
                border-radius: 15px;
            }
            
            .page-header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-shoe-prints"></i> Kick Store Admin
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">
                            <i class="fas fa-box"></i> Produk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-shopping-bag"></i> Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags"></i> Kategori
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Pengguna
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="main-container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold mb-2">Kelola Produk</h2>
                        <p class="mb-0">Tambah, edit, dan kelola semua produk sepatu Anda</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Cari Produk</label>
                            <input type="text" name="search" class="form-control" placeholder="Nama produk, merek, atau deskripsi..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Kategori</label>
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
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Products Grid -->
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h4>Belum Ada Produk</h4>
                        <p class="text-muted">Mulai dengan menambahkan produk pertama Anda ke inventori.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Tambah Produk Pertama
                        </button>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <?php if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])): ?>
                                    <img src="../<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                <?php else: ?>
                                    <div class="image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-info">
                                    <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></p>
                                    <div class="product-price">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="stock-badge <?php echo $product['stock'] > 10 ? 'stock-high' : ($product['stock'] > 0 ? 'stock-medium' : 'stock-low'); ?>">
                                            Stok: <?php echo $product['stock']; ?>
                                        </span>
                                        <small class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Tanpa Kategori'); ?></small>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary btn-sm flex-fill" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus"></i> Tambah Produk Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="addProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <!-- Product Image Upload -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><i class="fas fa-image"></i> Gambar Produk</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-2">Klik untuk upload atau drag & drop</p>
                                <p class="text-muted small">JPG, JPEG, PNG atau GIF (Maks 5MB)</p>
                                <input type="file" class="form-control" name="product_image" id="add_product_image" accept="image/*" style="display: none;">
                            </div>
                            <div id="imagePreviewContainer" class="mt-3" style="display: none;">
                                <img id="imagePreview" class="product-image-preview" alt="Preview">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeImage()">
                                    <i class="fas fa-times"></i> Hapus Gambar
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-tag"></i> Nama Produk *</label>
                                    <input type="text" class="form-control" name="name" placeholder="Contoh: Nike Air Max 270" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-copyright"></i> Merek *</label>
                                    <input type="text" class="form-control" name="brand" placeholder="Contoh: Nike, Adidas, Converse" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i class="fas fa-align-left"></i> Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Deskripsi detail produk (opsional)"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-money-bill-wave"></i> Harga *</label>
                                    <input type="number" step="1000" min="0" class="form-control" name="price" placeholder="250000" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-boxes"></i> Stok *</label>
                                    <input type="number" min="0" class="form-control" name="stock" placeholder="50" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-list"></i> Kategori *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-ruler"></i> Ukuran *</label>
                                    <input type="text" class="form-control" name="size" placeholder="Contoh: 40, 41, 42" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-palette"></i> Warna *</label>
                                    <input type="text" class="form-control" name="color" placeholder="Contoh: Hitam, Putih, Merah" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit Produk</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="editProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <input type="hidden" name="current_image" id="edit_current_image">
                        
                        <!-- Current Image Display -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold"><i class="fas fa-image"></i> Gambar Produk</label>
                            <div id="currentImageContainer" class="mb-3">
                                <img id="currentImage" class="product-image-preview" alt="Current Image" style="display: none;">
                                <div id="noCurrentImage" class="text-muted" style="display: none;">
                                    <i class="fas fa-image fa-2x"></i>
                                    <p>Tidak ada gambar</p>
                                </div>
                            </div>
                            
                            <div class="upload-area" id="editUploadArea">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                <p class="mb-1">Klik untuk upload gambar baru</p>
                                <p class="text-muted small">JPG, JPEG, PNG atau GIF (Maks 5MB)</p>
                                <input type="file" class="form-control" name="product_image" id="edit_product_image" accept="image/*" style="display: none;">
                            </div>
                            <div id="editImagePreviewContainer" class="mt-3" style="display: none;">
                                <img id="editImagePreview" class="product-image-preview" alt="Preview">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removeEditImage()">
                                    <i class="fas fa-times"></i> Hapus Gambar Baru
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-tag"></i> Nama Produk *</label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-copyright"></i> Merek *</label>
                                    <input type="text" class="form-control" name="brand" id="edit_brand" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i class="fas fa-align-left"></i> Deskripsi</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-money-bill-wave"></i> Harga *</label>
                                    <input type="number" step="1000" min="0" class="form-control" name="price" id="edit_price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-boxes"></i> Stok *</label>
                                    <input type="number" min="0" class="form-control" name="stock" id="edit_stock" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-list"></i> Kategori *</label>
                                    <select class="form-select" name="category_id" id="edit_category_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-ruler"></i> Ukuran *</label>
                                    <input type="text" class="form-control" name="size" id="edit_size" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold"><i class="fas fa-palette"></i> Warna *</label>
                                    <input type="text" class="form-control" name="color" id="edit_color" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image upload handling for add modal
        document.getElementById('uploadArea').addEventListener('click', function() {
            document.getElementById('add_product_image').click();
        });
        
        document.getElementById('add_product_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Image upload handling for edit modal
        document.getElementById('editUploadArea').addEventListener('click', function() {
            document.getElementById('edit_product_image').click();
        });
        
        document.getElementById('edit_product_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('editImagePreview').src = e.target.result;
                    document.getElementById('editImagePreviewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        function removeImage() {
            document.getElementById('add_product_image').value = '';
            document.getElementById('imagePreviewContainer').style.display = 'none';
        }
        
        function removeEditImage() {
            document.getElementById('edit_product_image').value = '';
            document.getElementById('editImagePreviewContainer').style.display = 'none';
        }
        
        function editProduct(product) {
            // Fill form fields
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_brand').value = product.brand;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_size').value = product.size;
            document.getElementById('edit_color').value = product.color;
            document.getElementById('edit_current_image').value = product.image_url || '';
            
            // Handle current image display
            if (product.image_url) {
                document.getElementById('currentImage').src = '../' + product.image_url;
                document.getElementById('currentImage').style.display = 'block';
                document.getElementById('noCurrentImage').style.display = 'none';
            } else {
                document.getElementById('currentImage').style.display = 'none';
                document.getElementById('noCurrentImage').style.display = 'block';
            }
            
            // Reset new image preview
            document.getElementById('editImagePreviewContainer').style.display = 'none';
            document.getElementById('edit_product_image').value = '';
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }
        
        // Form submission with loading states
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            submitBtn.disabled = true;
            
            // Re-enable if there's an error (page doesn't redirect)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        document.getElementById('editProductForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memperbarui...';
            submitBtn.disabled = true;
            
            // Re-enable if there's an error (page doesn't redirect)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        // Drag and drop functionality
        ['uploadArea', 'editUploadArea'].forEach(id => {
            const area = document.getElementById(id);
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                area.addEventListener(eventName, preventDefaults, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                area.addEventListener(eventName, () => area.classList.add('dragover'), false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                area.addEventListener(eventName, () => area.classList.remove('dragover'), false);
            });
            
            area.addEventListener('drop', handleDrop, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                const fileInput = e.target.closest('.modal').querySelector('input[type="file"]');
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
