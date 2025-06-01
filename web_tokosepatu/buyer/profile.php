<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user info
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        
        // Check if email is already taken by another user
        $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$email, $_SESSION['user_id']]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Email is already taken by another user!";
        } else {
            $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            
            if ($update_stmt->execute([$full_name, $email, $phone, $address, $_SESSION['user_id']])) {
                $_SESSION['full_name'] = $full_name;
                $success = "Profile updated successfully!";
                // Refresh user data
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Failed to update profile!";
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $password_error = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $password_error = "Password must be at least 6 characters long!";
        } elseif (!password_verify($current_password, $user['password'])) {
            $password_error = "Current password is incorrect!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_query = "UPDATE users SET password = ? WHERE id = ?";
            $password_stmt = $db->prepare($password_query);
            
            if ($password_stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $password_success = "Password changed successfully!";
            } else {
                $password_error = "Failed to change password!";
            }
        }
    }
}

// Get user statistics
$stats_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_spent,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders
                FROM orders WHERE user_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Profile - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #e0e0e0;
        }

        .navbar-dark {
            background-color: #6c757d !important;
        }

        .card {
            background-color: #f5f5f5;
        }

        .card-header {
            background-color: #1a237e;
            color: white;
        }

        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
        }

        .btn-primary:hover {
            background-color: #0d1645;
            border-color: #0d1645;
        }

        .btn-warning {
            background-color: #ffa000;
            border-color: #ffa000;
            color: #212121;
        }

        .btn-warning:hover {
            background-color: #cc8400;
            border-color: #cc8400;
            color: #212121;
        }

        .text-muted {
            color: #616161 !important;
        }

        .badge.bg-primary {
            background-color: #1a237e !important;
        }

        .badge.bg-success {
            background-color: #2e7d32 !important;
        }

        .badge.bg-info {
            background-color: #0288d1 !important;
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
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-user"></i> My Profile</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <!-- Profile Summary -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-circle fa-5x text-muted"></i>
                        </div>
                        <h5><?php echo htmlspecialchars($user['full_name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h5>My Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-12 mb-3">
                                <h4 class="text-primary"><?php echo $stats['total_orders']; ?></h4>
                                <small class="text-muted">Total Orders</small>
                            </div>
                            <div class="col-12 mb-3">
                                <h4 class="text-success">$<?php echo number_format($stats['total_spent'], 2); ?></h4>
                                <small class="text-muted">Total Spent</small>
                            </div>
                            <div class="col-12">
                                <h4 class="text-info"><?php echo $stats['delivered_orders']; ?></h4>
                                <small class="text-muted">Delivered Orders</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Profile Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" novalidate>
                            <input type="hidden" name="update_profile" value="1" />
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly />
                                        <small class="text-muted">Username cannot be changed</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required />
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required />
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" />
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header">
                        <h5>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($password_success)): ?>
                            <div class="alert alert-success"><?php echo $password_success; ?></div>
                        <?php endif; ?>
                        <?php if (isset($password_error)): ?>
                            <div class="alert alert-danger"><?php echo $password_error; ?></div>
                        <?php endif; ?>
                        <form method="POST" novalidate>
                            <input type="hidden" name="change_password" value="1" />
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required />
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required />
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required />
                            </div>

                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
