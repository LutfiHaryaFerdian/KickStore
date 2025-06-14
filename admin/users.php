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
                    $success = "Peran pengguna berhasil diperbarui!";
                } else {
                    $error = "Gagal memperbarui peran pengguna!";
                }
                break;
                
            case 'delete':
                // Check if user has orders
                $check_query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$_POST['user_id']]);
                $order_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($order_count > 0) {
                    $error = "Tidak dapat menghapus pengguna. Terdapat $order_count pesanan yang terkait dengan pengguna ini.";
                } else {
                    $query = "DELETE FROM users WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$_POST['user_id']])) {
                        $success = "Pengguna berhasil dihapus!";
                    } else {
                        $error = "Gagal menghapus pengguna!";
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

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'buyer' THEN 1 ELSE 0 END) as total_buyers,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins
    FROM users";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin Kick Store</title>
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
            padding: 30px;
            margin: 30px 0;
        }
        
        .page-header {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: #A0522D;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 20px;
            color: #A0522D;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .filter-card {
            background: #FEFEFE;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .users-table-container {
            background: white;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .table-header {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #F5DEB3;
            color: #A0522D;
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #F5DEB3;
        }
        
        .table tbody tr:hover {
            background-color: rgba(245, 222, 179, 0.1);
        }
        
        .role-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .role-admin {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .role-buyer {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        
        .order-count-badge {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
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
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            border-radius: 10px;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
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
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            color: #A0522D;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
            color: #721c24;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #A0522D;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 10px;
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
                        <a class="nav-link" href="products.php">
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
                        <a class="nav-link active" href="users.php">
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
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="fw-bold mb-2">
                            <i class="fas fa-users"></i> Kelola Pengguna
                        </h2>
                        <p class="mb-0">Pantau dan kelola semua pengguna sistem</p>
                    </div>
                    <div class="col-md-4">
                        <!-- Statistics Cards -->
                        <div class="row">
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                                    <div>Total</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo number_format($stats['total_buyers']); ?></div>
                                    <div>Pembeli</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo number_format($stats['total_admins']); ?></div>
                                    <div>Admin</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-filter"></i> Peran
                        </label>
                        <select name="role" class="form-select">
                            <option value="">Semua Peran</option>
                            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="buyer" <?php echo $role_filter == 'buyer' ? 'selected' : ''; ?>>Pembeli</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-search"></i> Cari Pengguna
                        </label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Nama lengkap, email, atau username..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h4>Tidak Ada Pengguna</h4>
                    <p>Belum ada pengguna yang sesuai dengan filter yang dipilih</p>
                </div>
            <?php else: ?>
                <div class="users-table-container">
                    <div class="table-header">
                        <i class="fas fa-list"></i> Daftar Pengguna (<?php echo count($users); ?> pengguna)
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Peran</th>
                                    <th>Pesanan</th>
                                    <th>Bergabung</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-warning text-dark ms-1">Anda</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($user['username']); ?></code>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <i class="fas fa-<?php echo $user['role'] == 'admin' ? 'user-shield' : 'user'; ?>"></i>
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['order_count'] > 0): ?>
                                                <span class="order-count-badge">
                                                    <?php echo $user['order_count']; ?> pesanan
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($user['created_at'])); ?><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="updateUserRole(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                            title="Ubah Peran">
                                                        <i class="fas fa-user-cog"></i>
                                                    </button>
                                                    
                                                    <?php if ($user['order_count'] == 0): ?>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus Pengguna">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" disabled 
                                                                title="Tidak dapat menghapus pengguna yang memiliki pesanan">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">Akun Anda</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Role Modal -->
    <div class="modal fade" id="updateRoleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-user-cog"></i> Ubah Peran Pengguna
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="user_id" id="update_user_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Peringatan:</strong> Mengubah peran pengguna akan mempengaruhi akses mereka ke sistem.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-user"></i> Nama Pengguna
                            </label>
                            <input type="text" class="form-control" id="display_user_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-envelope"></i> Email
                            </label>
                            <input type="text" class="form-control" id="display_user_email" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-user-tag"></i> Peran Baru
                            </label>
                            <select name="role" class="form-select" id="update_role_select" required>
                                <option value="buyer">Pembeli</option>
                                <option value="admin">Admin</option>
                            </select>
                            <div class="form-text">
                                <strong>Pembeli:</strong> Dapat berbelanja dan mengelola pesanan mereka<br>
                                <strong>Admin:</strong> Dapat mengakses panel admin dan mengelola sistem
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Ubah Peran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateUserRole(user) {
            document.getElementById('update_user_id').value = user.id;
            document.getElementById('display_user_name').value = user.full_name;
            document.getElementById('display_user_email').value = user.email;
            document.getElementById('update_role_select').value = user.role;
            
            new bootstrap.Modal(document.getElementById('updateRoleModal')).show();
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
