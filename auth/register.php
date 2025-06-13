<?php
session_start();

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../buyer/index.php");
    }
    exit();
}

require_once '../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Debug: tampilkan data yang diterima
    error_log("Registration attempt - Full Name: $full_name, Username: $username, Email: $email");
    
    // Validation
    if (empty($full_name)) {
        $error = "Full name is required";
    } elseif (empty($username)) {
        $error = "Username is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } elseif (empty($confirm_password)) {
        $error = "Please confirm your password";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if username already exists (using email field for username)
            $check_query = "SELECT id FROM users WHERE email = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$username]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Username is already taken";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $insert_query = "INSERT INTO users (username, email, password, full_name, phone, address, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'buyer', NOW())";
                $insert_stmt = $db->prepare($insert_query);

                if ($insert_stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address])) {
                    $success = "Registration successful! You can now login.";
                } else {
                    $error = "Registration failed!";
                }
            }
        } catch (Exception $e) {
            $error = "Registration failed: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Kick Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            position: relative;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="shoe-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M10 2C12 2 14 4 14 6C14 8 12 10 10 10C8 10 6 8 6 6C6 4 8 2 10 2Z" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23shoe-pattern)"/></svg>') repeat;
            animation: float 20s ease-in-out infinite;
            z-index: 1;
        }
        
        .register-header > * {
            position: relative;
            z-index: 2;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        .brand-logo {
            font-size: 3rem;
            margin-bottom: 15px;
            background: white;
            color: #667eea;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
            margin: 0 auto 15px;
        }
        
        .register-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 15px 20px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            z-index: 10;
        }
        
        .form-control.with-icon {
            padding-left: 50px;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .back-home:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }
        
        .login-link {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 576px) {
            .register-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .register-header {
                padding: 30px 20px 20px;
            }
            
            .register-body {
                padding: 30px 20px;
            }
            
            .brand-logo {
                font-size: 2.5rem;
                width: 60px;
                height: 60px;
            }
        }

        .min-vh-100 {
            min-height: 100vh !important;
        }
    </style>
</head>
<body>
    <a href="../index.php" class="back-home">
        <i class="fas fa-arrow-left me-2"></i> Back to Home
    </a>
    
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="register-container">
            <!-- Header Section -->
            <div class="register-header">
                <div class="brand-logo">
                    <i class="fas fa-shoe-prints"></i>
                </div>
                <h2 class="fw-bold mb-2">Join Kick Store!</h2>
                <p class="mb-0 opacity-75">Create your account to get started</p>
            </div>
            
            <!-- Body Section -->
            <div class="register-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-3 text-center">
                            <a href="login.php" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-sign-in-alt me-1"></i> Login Now
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="register.php" id="registerForm">
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control with-icon" name="full_name" placeholder="Full Name *" required>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" class="form-control with-icon" name="username" placeholder="Username *" required>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="text" class="form-control with-icon" name="email" placeholder="Email Address (Optional)">
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="text" class="form-control with-icon" name="password" placeholder="Password *" required>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="text" class="form-control with-icon" name="confirm_password" placeholder="Confirm Password *" required>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="text" class="form-control with-icon" name="phone" placeholder="Phone Number (Optional)">
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt input-icon"></i>
                        <textarea class="form-control with-icon" name="address" rows="3" placeholder="Address (Optional)" style="resize: none;"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i>
                        Create Account
                    </button>
                </form>
                
                <div class="login-link">
                    <p class="text-muted mb-0">
                        Already have an account? 
                        <a href="login.php">Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add interactive effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                const icon = this.parentElement.querySelector('.input-icon');
                if (icon) {
                    icon.style.color = '#667eea';
                }
            });
            
            input.addEventListener('blur', function() {
                const icon = this.parentElement.querySelector('.input-icon');
                if (icon) {
                    icon.style.color = '#adb5bd';
                }
            });
        });
        
        // Simplified form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            const terms = document.getElementById('terms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Please accept the Terms of Service and Privacy Policy');
                return;
            }
            
            // Debug: log form submission
            console.log('Form submitted successfully');
        });
        
        // Real-time password confirmation
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
    </script>
</body>
</html>
