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
                                throw new Exception("Failed to upload image!");
                            }
                        } else {
                            throw new Exception("Invalid file type! Only JPG, JPEG, PNG, and GIF are allowed.");
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
                        $success = "Product added successfully!";
                    } else {
                        throw new Exception("Failed to add product to database!");
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
                        $success = "Product updated successfully!";
                    } else {
                        throw new Exception("Failed to update product!");
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
                        $success = "Product deleted successfully!";
                    } else {
                        throw new Exception("Failed to delete product!");
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
        }
    }
}

// Get all products
$query = "SELECT p.*, c.name as category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for dropdown
$cat_query = "SELECT * FROM categories ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .image-placeholder {
            width: 80px;
            height: 80px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
        .product-image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 8px;
            border: 2px solid #dee2e6;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-shoe-prints"></i> Kick Store Admin</a>
            
            <div class="navbar-nav ms-auto">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-light vh-100">
                <div class="list-group list-group-flush mt-3">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="products.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-bag"></i> Orders
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users"></i> Users
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="container-fluid mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-box"></i> Kelola Produk</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus"></i> Tambah Produk
                        </button>
                    </div>
                    
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

                    <!-- Products Table -->
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Produk</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($products)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                                    <h4>Tidak Ada Produk</h4>
                                    <p class="text-muted">Start by adding your first product to the inventory.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                        <i class="fas fa-plus"></i> Tambah Produk Pertama
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Gambar</th>
                                                <th>ID</th>
                                                <th>Detail Produk</th>
                                                <th>Kategori</th>
                                                <th>Harga</th>
                                                <th>Stok</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($product['image_url']) && file_exists('../' . $product['image_url'])): ?>
                                                            <img src="../<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                                        <?php else: ?>
                                                            <div class="image-placeholder">
                                                                <i class="fas fa-image"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong>#<?php echo $product['id']; ?></strong></td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($product['brand']); ?> | 
                                                                Size: <?php echo htmlspecialchars($product['size']); ?> | 
                                                                Color: <?php echo htmlspecialchars($product['color']); ?>
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?></span>
                                                    </td>
                                                    <td><strong>Rp <?php echo number_format($product['price'], 2); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $product['stock'] > 10 ? 'success' : ($product['stock'] > 0 ? 'warning' : 'danger'); ?>">
                                                            <?php echo $product['stock']; ?> units
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($product['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" title="Edit Product">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Product">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Tambah Produk Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="addProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <!-- Product Image Upload -->
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-image"></i> Gambar Produk</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-2">Click to upload or drag and drop</p>
                                <p class="text-muted small">JPG, JPEG, PNG or GIF (Max 5MB)</p>
                                <input type="file" class="form-control" name="product_image" id="add_product_image" accept="image/*" style="display: none;">
                            </div>
                            <div id="imagePreviewContainer" class="mt-3" style="display: none;">
                                <img id="imagePreview" class="product-image-preview" alt="Preview">
                                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeImage()">
                                    <i class="fas fa-times"></i> Remove Image
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-tag"></i> Nama Produk *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-copyright"></i> Merek *</label>
                                    <input type="text" class="form-control" name="brand" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-align-left"></i> Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Product description (optional)"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-dollar-sign"></i> Harga *</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-boxes"></i> Stok *</label>
                                    <input type="number" min="0" class="form-control" name="stock" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-list"></i> Kategori *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
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
                                    <label class="form-label"><i class="fas fa-ruler"></i> Ukuran *</label>
                                    <input type="text" class="form-control" name="size" placeholder="e.g., 42, 8.5, M" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-palette"></i> Warna *</label>
                                    <input type="text" class="form-control" name="color" placeholder="e.g., Black, White, Red" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Tambah Produk
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
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="editProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <input type="hidden" name="current_image" id="edit_current_image">
                        
                        <!-- Current Image Display -->
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-image"></i> Gambar Produk</label>
                            <div id="currentImageDisplay" class="mb-3">
                                <p class="mb-2">Current Image:</p>
                                <img id="currentImage" class="product-image-preview" style="display: none;" alt="Current Image">
                                <div id="noCurrentImage" class="text-muted" style="display: none;">
                                    <i class="fas fa-image fa-2x"></i><br>No image currently set
                                </div>
                            </div>
                            
                            <div class="upload-area" id="editUploadArea">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                <p class="mb-1">Upload new image (optional)</p>
                                <p class="text-muted small">JPG, JPEG, PNG or GIF (Max 5MB)</p>
                                <input type="file" class="form-control" name="product_image" id="edit_product_image" accept="image/*" style="display: none;">
                            </div>
                            
                            <div id="newImagePreviewContainer" class="mt-3" style="display: none;">
                                <p class="mb-2">New Image Preview:</p>
                                <img id="newImagePreview" class="product-image-preview" alt="New Preview">
                                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeNewImage()">
                                    <i class="fas fa-times"></i> Remove New Image
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-tag"></i> Nama Produk *</label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-copyright"></i> Merek *</label>
                                    <input type="text" class="form-control" name="brand" id="edit_brand" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-align-left"></i> Deskripsi</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-dollar-sign"></i> Harga *</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="price" id="edit_price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-boxes"></i> Stok *</label>
                                    <input type="number" min="0" class="form-control" name="stock" id="edit_stock" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-list"></i> Kategori *</label>
                                    <select class="form-select" name="category_id" id="edit_category_id" required>
                                        <option value="">Select Category</option>
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
                                    <label class="form-label"><i class="fas fa-ruler"></i> Ukuran *</label>
                                    <input type="text" class="form-control" name="size" id="edit_size" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><i class="fas fa-palette"></i> Warna *</label>
                                    <input type="text" class="form-control" name="color" id="edit_color" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Perbarui Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Upload area click handler for add product
        document.getElementById('uploadArea').addEventListener('click', function() {
            document.getElementById('add_product_image').click();
        });
        
        // Upload area click handler for edit product
        document.getElementById('editUploadArea').addEventListener('click', function() {
            document.getElementById('edit_product_image').click();
        });
        
        // Image preview for add product
        document.getElementById('add_product_image').addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                    document.getElementById('uploadArea').style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Image preview for edit product
        document.getElementById('edit_product_image').addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('newImagePreview').src = e.target.result;
                    document.getElementById('newImagePreviewContainer').style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
        
        function removeImage() {
            document.getElementById('add_product_image').value = '';
            document.getElementById('imagePreviewContainer').style.display = 'none';
            document.getElementById('uploadArea').style.display = 'block';
        }
        
        function removeNewImage() {
            document.getElementById('edit_product_image').value = '';
            document.getElementById('newImagePreviewContainer').style.display = 'none';
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
            
            // Display current image
            const currentImage = document.getElementById('currentImage');
            const noCurrentImage = document.getElementById('noCurrentImage');
            
            if (product.image_url) {
                currentImage.src = '../' + product.image_url;
                currentImage.style.display = 'block';
                noCurrentImage.style.display = 'none';
            } else {
                currentImage.style.display = 'none';
                noCurrentImage.style.display = 'block';
            }
            
            // Reset new image preview
            document.getElementById('newImagePreviewContainer').style.display = 'none';
            document.getElementById('edit_product_image').value = '';
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }
        
        // Drag and drop functionality
        ['uploadArea', 'editUploadArea'].forEach(id => {
            const area = document.getElementById(id);
            
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const input = id === 'uploadArea' ? 
                        document.getElementById('add_product_image') : 
                        document.getElementById('edit_product_image');
                    input.files = files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>
</html>
