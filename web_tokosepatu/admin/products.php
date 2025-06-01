<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Validate required fields
                    if (empty($_POST['name']) || empty($_POST['price']) || empty($_POST['stock']) || empty($_POST['category_id'])) {
                        throw new Exception("All required fields must be filled!");
                    }
                    
                    // Validate price and stock are numeric
                    if (!is_numeric($_POST['price']) || !is_numeric($_POST['stock'])) {
                        throw new Exception("Price and stock must be numeric values!");
                    }
                    
                    // Check if category exists
                    $cat_check = "SELECT id FROM categories WHERE id = ?";
                    $cat_stmt = $db->prepare($cat_check);
                    $cat_stmt->execute([$_POST['category_id']]);
                    if ($cat_stmt->rowCount() == 0) {
                        throw new Exception("Selected category does not exist!");
                    }
                    
                    $query = "INSERT INTO products (name, description, price, stock, category_id, brand, size, color, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $_POST['name'], 
                        $_POST['description'], 
                        $_POST['price'], 
                        $_POST['stock'],
                        $_POST['category_id'], 
                        $_POST['brand'], 
                        $_POST['size'], 
                        $_POST['color']
                    ]);
                    
                    if ($result) {
                        $success = "Product added successfully! Product ID: " . $db->lastInsertId();
                    } else {
                        throw new Exception("Failed to insert product into database!");
                    }
                } catch (Exception $e) {
                    $error = "Error adding product: " . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    if (empty($_POST['name']) || empty($_POST['price']) || empty($_POST['stock']) || empty($_POST['category_id'])) {
                        throw new Exception("All required fields must be filled!");
                    }
                    
                    $query = "UPDATE products SET name=?, description=?, price=?, stock=?, category_id=?, brand=?, size=?, color=? 
                              WHERE id=?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([
                        $_POST['name'], $_POST['description'], $_POST['price'], $_POST['stock'],
                        $_POST['category_id'], $_POST['brand'], $_POST['size'], $_POST['color'], $_POST['product_id']
                    ]);
                    
                    if ($result) {
                        $success = "Product updated successfully!";
                    } else {
                        throw new Exception("Failed to update product!");
                    }
                } catch (Exception $e) {
                    $error = "Error updating product: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Check if product is in any orders
                    $order_check = "SELECT COUNT(*) as count FROM order_items WHERE product_id = ?";
                    $order_stmt = $db->prepare($order_check);
                    $order_stmt->execute([$_POST['product_id']]);
                    $order_count = $order_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($order_count > 0) {
                        throw new Exception("Cannot delete product. It has been ordered $order_count times.");
                    }
                    
                    $query = "DELETE FROM products WHERE id=?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$_POST['product_id']]);
                    
                    if ($result) {
                        $success = "Product deleted successfully!";
                    } else {
                        throw new Exception("Failed to delete product!");
                    }
                } catch (Exception $e) {
                    $error = "Error deleting product: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all products with category names
try {
    $query = "SELECT p.*, c.name as category_name FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching products: " . $e->getMessage();
    $products = [];
}

// Get categories for dropdown
try {
    $cat_query = "SELECT * FROM categories ORDER BY name";
    $cat_stmt = $db->prepare($cat_query);
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categories)) {
        $warning = "No categories found! Please add categories first before adding products.";
    }
} catch (Exception $e) {
    $error = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                    <a href="products_fixed.php" class="list-group-item list-group-item-action active">
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
                        <h2>Products Management</h2>
                        <?php if (!empty($categories)): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        <?php else: ?>
                            <a href="categories.php" class="btn btn-warning">
                                <i class="fas fa-plus"></i> Add Categories First
                            </a>
                        <?php endif; ?>
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
                    
                    <?php if (isset($warning)): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $warning; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Debug Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6>System Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Categories Available:</strong> 
                                    <span class="badge bg-<?php echo count($categories) > 0 ? 'success' : 'danger'; ?>">
                                        <?php echo count($categories); ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Products Count:</strong> 
                                    <span class="badge bg-info"><?php echo count($products); ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Database:</strong> 
                                    <span class="badge bg-success">Connected</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>User Role:</strong> 
                                    <span class="badge bg-primary"><?php echo $_SESSION['role']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($products)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h5>No Products Found</h5>
                                    <p class="text-muted">Start by adding your first product to the inventory.</p>
                                    <?php if (!empty($categories)): ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="fas fa-plus"></i> Add First Product
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Brand</th>
                                                <th>Category</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Size</th>
                                                <th>Color</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td><?php echo $product['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['brand']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?></td>
                                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $product['stock'] > 10 ? 'success' : ($product['stock'] > 0 ? 'warning' : 'danger'); ?>">
                                                            <?php echo $product['stock']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($product['size']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['color']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $product['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($product['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
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
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Brand <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="brand" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Product description (optional)"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stock <span class="text-danger">*</span></label>
                                    <input type="number" min="0" class="form-control" name="stock" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
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
                                    <label class="form-label">Size <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="size" placeholder="e.g., 42, 8.5, M" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Color <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="color" placeholder="e.g., Black, White, Red" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Product
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
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" id="edit_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Brand <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="brand" id="edit_brand" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="price" id="edit_price" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Stock <span class="text-danger">*</span></label>
                                    <input type="number" min="0" class="form-control" name="stock" id="edit_stock" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
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
                                    <label class="form-label">Size <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="size" id="edit_size" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Color <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="color" id="edit_color" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_brand').value = product.brand;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_category_id').value = product.category_id;
            document.getElementById('edit_size').value = product.size;
            document.getElementById('edit_color').value = product.color;
            
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }

        // Form validation
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const price = parseFloat(document.querySelector('input[name="price"]').value);
            const stock = parseInt(document.querySelector('input[name="stock"]').value);
            
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                return;
            }
            
            if (stock < 0) {
                e.preventDefault();
                alert('Stock cannot be negative');
                return;
            }
        });
    </script>
</body>
</html>