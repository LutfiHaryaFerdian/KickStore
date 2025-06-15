<?php
session_start();
require_once '../config/database.php';


if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../buyer/index.php");
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    

    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = "Semua field harus diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        

        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username atau email sudah digunakan!";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO users (full_name, username, email, password, role, created_at) VALUES (?, ?, ?, ?, 'buyer', NOW())";
            $insert_stmt = $db->prepare($insert_query);
            
            if ($insert_stmt->execute([$full_name, $username, $email, $hashed_password])) {
                $success = "Pendaftaran berhasil! Silakan login dengan akun Anda.";
            } else {
                $error = "Terjadi kesalahan saat mendaftar. Silakan coba lagi.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Toko Sepatu Kick</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #F5DEB3 0%, #D2B48C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(210, 180, 140, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            margin: 20px;
        }
        
        .register-left {
            background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .register-right {
            padding: 60px 40px;
        }
        
        .brand-logo {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #F5DEB3;
        }
        
        .form-control {
            border: 2px solid #F5DEB3;
            border-radius: 15px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #FEFEFE;
        }
        
        .form-control:focus {
            border-color: #D2B48C;
            box-shadow: 0 0 0 0.2rem rgba(210, 180, 140, 0.25);
            background: white;
        }
        
        .input-group-text {
            background: #F5DEB3;
            border: 2px solid #F5DEB3;
            border-right: none;
            color: #A0522D;
            border-radius: 15px 0 0 15px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 15px 15px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(210, 180, 140, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid #D2B48C;
            color: #A0522D;
            border-radius: 15px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-color: #CD853F;
            color: white;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .register-title {
            color: #A0522D;
            font-weight: 700;
            margin-bottom: 30px;
        }
        
        .welcome-text {
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #F5DEB3;
        }
        
        .divider span {
            background: white;
            padding: 0 20px;
            color: #A0522D;
            font-weight: 600;
        }
        
        .features {
            margin-top: 30px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .feature-item i {
            color: #F5DEB3;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        .btn-outline-secondary {
            border: 2px solid #8B4513;
            color: #8B4513;
            border-radius: 15px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            border-color: #8B4513;
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .register-left {
                padding: 40px 30px;
            }
            
            .register-right {
                padding: 40px 30px;
            }
            
            .brand-logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="row g-0">

            <div class="col-lg-5">
                <div class="register-left h-100">
                    <div>
                        <div class="brand-logo">
                            <i class="fas fa-shoe-prints"></i>
                        </div>
                        <h2 class="fw-bold mb-4">Bergabung dengan Kick Store</h2>
                        <p class="welcome-text mb-4">
                            Daftarkan diri Anda dan nikmati pengalaman berbelanja sepatu premium dengan koleksi terlengkap dan kualitas terbaik.
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="mt-2 mb-0 small">Dipercaya oleh ribuan pelanggan</p>
                    </div>
                </div>
            </div>
            

            <div class="col-lg-7">
                <div class="register-right">
                    <h3 class="register-title text-center">Buat Akun Baru</h3>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold" style="color: #A0522D;">Nama Lengkap</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" name="full_name" placeholder="Nama lengkap Anda" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold" style="color: #A0522D;">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-at"></i>
                                    </span>
                                    <input type="text" class="form-control" name="username" placeholder="Username unik" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="color: #A0522D;">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" name="email" placeholder="alamat@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold" style="color: #A0522D;">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="password" placeholder="Minimal 6 karakter" id="password" required>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-semibold" style="color: #A0522D;">Konfirmasi Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="confirm_password" placeholder="Ulangi password" id="confirmPassword" required>
                                </div>
                                <div class="password-match" id="passwordMatch"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mb-4">
                            <i class="fas fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </form>
                    
                    <div class="divider">
                        <span>atau</span>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-3" style="color: #A0522D;">Sudah punya akun?</p>
                        <a href="login.php" class="btn btn-outline-primary">
                            <i class="fas fa-sign-in-alt"></i> Masuk ke Akun
                        </a>
                    </div>

                    <div class="text-center mt-4">
                        <a href="../index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength++;
            else feedback.push('minimal 6 karakter');
            
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            let strengthText = '';
            let strengthClass = '';
            
            if (strength <= 2) {
                strengthText = 'Lemah';
                strengthClass = 'strength-weak';
            } else if (strength <= 3) {
                strengthText = 'Sedang';
                strengthClass = 'strength-medium';
            } else {
                strengthText = 'Kuat';
                strengthClass = 'strength-strong';
            }
            
            strengthDiv.innerHTML = `<span class="${strengthClass}">Kekuatan password: ${strengthText}</span>`;
        });
        

        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span class="strength-strong">Password cocok</span>';
            } else {
                matchDiv.innerHTML = '<span class="strength-weak">Password tidak cocok</span>';
            }
        });
        

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mendaftar...';
            submitBtn.disabled = true;
            

            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    </script>
</body>
</html>
