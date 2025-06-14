<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_role':
                $query = "UPDATE users SET role = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$_POST['role'], $_POST['user_id']])) {
                    $success = "User role updated successfully!";
                } else {
                    $error = "Failed to update user role!";
                }
                break;
                
            case 'delete':
                // Check if user has orders
                $check_query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$_POST['user_id']]);
                $order_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($order_count > 0) {
                    $error = "Cannot delete user. They have $order_count orders associated with them.";
                } else {
                    $query = "DELETE FROM users WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$_POST['user_id']])) {
                        $success = "User deleted successfully!";
                    } else {
                        $error = "Failed to delete user!";
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$role_filter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($search) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all users with order count
$query = "SELECT u.*, COUNT(o.id) as order_count 
          FROM users u 
          LEFT JOIN orders o ON u.id = o.user_id 
          $where_clause
          GROUP BY u.id 
          ORDER BY u.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin</title>
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
                    <a href="products.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="orders.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-shopping-bag"></i> Orders
                    </a>
                    <a href="categories.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users"></i> Users
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="container-fluid mt-4">
                    <h2>Kelola Pengguna</h2>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Peran</label>
                                    <select name="role" class="form-select">
                                        <option value="">Semua Peran</option>
                                        <option value="buyer" <?php echo $role_filter == 'buyer' ? 'selected' : ''; ?>>Pembeli</option>
                                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or username..." value="<?php echo $search; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Pengguna</th>
                                            <th>Nama Lengkap</th>
                                            <th>Email</th>
                                            <th>Telepon</th>
                                            <th>Peran</th>
                                            <th>Pesanan</th>
                                            <th>Bergabung</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo $user['username']; ?></td>
                                                <td><?php echo $user['full_name']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td><?php echo $user['phone'] ?: 'N/A'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'primary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $user['order_count']; ?></span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="updateUserRole(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                            <i class="fas fa-user-cog"></i>
                                                        </button>
                                                        
                                                        <?php if ($user['order_count'] == 0): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled title="Cannot delete user with orders">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Current User</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Role Modal -->
    <div class="modal fade" id="updateRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Perbarui Peran Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="user_id" id="update_user_id">
                        
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <input type="text" class="form-control" id="display_user_info" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Peran</label>
                            <select name="role" class="form-select" id="update_role_select" required>
                                <option value="buyer">Pembeli</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> Changing a user's role will affect their access permissions immediately.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Perbarui Peran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateUserRole(user) {
            document.getElementById('update_user_id').value = user.id;
            document.getElementById('display_user_info').value = user.full_name + ' (' + user.email + ')';
            document.getElementById('update_role_select').value = user.role;
            
            new bootstrap.Modal(document.getElementById('updateRoleModal')).show();
        }
    </script>
</body>
</html>
