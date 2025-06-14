<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    
    $query = "UPDATE orders SET status = ?, payment_status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    if ($stmt->execute([$status, $payment_status, $order_id])) {
        $success = "Order status updated successfully!";
    } else {
        $error = "Failed to update order status!";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($payment_filter) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR o.id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all orders with user info
$query = "SELECT o.*, u.full_name, u.email FROM orders o 
          JOIN users u ON o.user_id = u.id 
          $where_clause
          ORDER BY o.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Admin</title>
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
                    <a href="orders.php" class="list-group-item list-group-item-action active">
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
                    <h2>Kelola Pesanan</h2>
                    
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
                                    <label class="form-label">Status Pesanan</label>
                                    <select name="status" class="form-select">
                                        <option value="">Semua Status</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                        <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Dikirim</option>
                                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Terkirim</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status Pembayaran</label>
                                    <select name="payment" class="form-select">
                                        <option value="">Semua Status Pembayaran</option>
                                        <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Dibayar</option>
                                        <option value="failed" <?php echo $payment_filter == 'failed' ? 'selected' : ''; ?>>Gagal</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Cari</label>
                                    <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan nama pelanggan, email, atau ID pesanan..." value="<?php echo $search; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID Pesanan</th>
                                            <th>Pelanggan</th>
                                            <th>Email</th>
                                            <th>Jumlah</th>
                                            <th>Metode Pembayaran</th>
                                            <th>Status Pesanan</th>
                                            <th>Status Pembayaran</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['id']; ?></td>
                                                <td><?php echo $order['full_name']; ?></td>
                                                <td><?php echo $order['email']; ?></td>
                                                <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                                <td><?php echo $order['payment_method']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $order['status'] == 'pending' ? 'warning' : 
                                                            ($order['status'] == 'confirmed' ? 'info' : 
                                                            ($order['status'] == 'shipped' ? 'primary' : 
                                                            ($order['status'] == 'delivered' ? 'success' : 'danger'))); 
                                                    ?>">
                                                        <?php 
                                                            if($order['status'] == 'pending'){
                                                                echo "Pending";
                                                            } elseif ($order['status'] == 'confirmed'){
                                                                echo "Dikonfirmasi";
                                                            } elseif ($order['status'] == 'shipped'){
                                                                echo "Dikirim";
                                                            } elseif ($order['status'] == 'delivered'){
                                                                echo "Terkirim";
                                                            } else {
                                                                echo "Dibatalkan";
                                                            }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $order['payment_status'] == 'paid' ? 'success' : 
                                                            ($order['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php 
                                                            if($order['payment_status'] == 'paid'){
                                                                echo "Dibayar";
                                                            } elseif ($order['payment_status'] == 'pending'){
                                                                echo "Pending";
                                                            } else {
                                                                echo "Gagal";
                                                            }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-success" onclick="updateOrderStatus(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
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

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Perbarui Status Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="order_id" id="update_order_id">
                        
                        <div class="mb-3">
                            <label class="form-label">ID Pesanan</label>
                            <input type="text" class="form-control" id="display_order_id" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Pelanggan</label>
                            <input type="text" class="form-control" id="display_customer" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status Pesanan</label>
                            <select name="status" class="form-select" id="update_status_select" required>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Dikonfirmasi</option>
                                <option value="shipped">Dikirim</option>
                                <option value="delivered">Terkirim</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status Pembayaran</label>
                            <select name="payment_status" class="form-select" id="update_payment_select" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Dibayar</option>
                                <option value="failed">Gagal</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Perbarui Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateOrderStatus(order) {
            document.getElementById('update_order_id').value = order.id;
            document.getElementById('display_order_id').value = '#' + order.id;
            document.getElementById('display_customer').value = order.full_name;
            document.getElementById('update_status_select').value = order.status;
            document.getElementById('update_payment_select').value = order.payment_status;
            
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }
    </script>
</body>
</html>
