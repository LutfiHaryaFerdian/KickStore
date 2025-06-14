<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header("Location: ../auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {

        if (!empty($full_name) && !empty($email)) {
            $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$full_name, $email, $phone, $address, $_SESSION['user_id']]);
            
            $_SESSION['full_name'] = $full_name;
            $success = "Profil berhasil diperbarui!";
        }
        

        if (!empty($current_password) && !empty($new_password)) {

            $pass_query = "SELECT password FROM users WHERE id = ?";
            $pass_stmt = $db->prepare($pass_query);
            $pass_stmt->execute([$_SESSION['user_id']]);
            $user_data = $pass_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $user_data['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $pass_update_query = "UPDATE users SET password = ? WHERE id = ?";
                    $pass_update_stmt = $db->prepare($pass_update_query);
                    $pass_update_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    
                    $success = "Profil dan kata sandi berhasil diperbarui!";
                } else {
                    $error = "Konfirmasi kata sandi tidak cocok!";
                }
            } else {
                $error = "Kata sandi saat ini salah!";
            }
        }
        
    } catch (Exception $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}


$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);


$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_spent
    FROM orders WHERE user_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Toko Sepatu Kick</title>
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
        
        .profile-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            padding: 30px;
            margin: 30px 0;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            color: #A0522D;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2.5rem;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.3);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            text-align: center;
            margin-bottom: 20px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
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
        
        .section-title {
            color: #A0522D;
            border-bottom: 2px solid #F5DEB3;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item a {
            color: #A0522D;
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: #CD853F;
        }
        
        .password-section {
            background: #FEFEFE;
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
        }
    </style>
</head>
<body>

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
                        <a class="nav-link" href="index.php">
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
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Keranjang
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user-edit"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">

        <nav aria-label="breadcrumb" class="mt-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Beranda</a></li>
                <li class="breadcrumb-item active">Profil Saya</li>
            </ol>
        </nav>

        <div class="row">

            <div class="col-lg-4">
                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4 class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                        <small class="text-muted">
                            Bergabung sejak <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </small>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="stats-card">
                                <h4 class="fw-bold"><?php echo $stats['total_orders']; ?></h4>
                                <small>Total Pesanan</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-card">
                                <h4 class="fw-bold">Rp <?php echo number_format($stats['total_spent'] ?? 0, 0, ',', '.'); ?></h4>
                                <small>Total Belanja</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            

            <div class="col-lg-8">
                <div class="profile-container">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <h4 class="section-title">
                            <i class="fas fa-user-edit"></i> Informasi Profil
                        </h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap *</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Contoh: 08123456789">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <small class="text-muted">Username tidak dapat diubah</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="address" rows="3" placeholder="Masukkan alamat lengkap Anda..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        

                        <div class="password-section">
                            <h5 class="section-title">
                                <i class="fas fa-lock"></i> Ubah Kata Sandi
                            </h5>
                            <p class="text-muted small mb-3">Kosongkan jika tidak ingin mengubah kata sandi</p>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Kata Sandi Saat Ini</label>
                                    <input type="password" class="form-control" name="current_password" placeholder="Masukkan kata sandi saat ini">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Kata Sandi Baru</label>
                                    <input type="password" class="form-control" name="new_password" placeholder="Masukkan kata sandi baru">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Konfirmasi Kata Sandi</label>
                                    <input type="password" class="form-control" name="confirm_password" placeholder="Konfirmasi kata sandi baru">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        document.querySelector('form').addEventListener('submit', function(e) {
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            

            if (currentPassword || newPassword || confirmPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Masukkan kata sandi saat ini');
                    return;
                }
                if (!newPassword) {
                    e.preventDefault();
                    alert('Masukkan kata sandi baru');
                    return;
                }
                if (!confirmPassword) {
                    e.preventDefault();
                    alert('Konfirmasi kata sandi baru');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Konfirmasi kata sandi tidak cocok');
                    return;
                }
                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('Kata sandi baru minimal 6 karakter');
                    return;
                }
            }
        });
    </script>
</body>
</html>
