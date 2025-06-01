<?php
session_start();

// Redirect based on user role if logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: buyer/index.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kick Store - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #374151 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-icon {
            font-size: 3rem;
            color: #1e3a8a;
        }
        .navbar-dark {
            background-color: #374151 !important;
        }
        .btn-primary-custom {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
            color: white;
        }
        .btn-primary-custom:hover {
            background-color: #1e40af;
            border-color: #1e40af;
        }
        .btn-secondary-custom {
            background-color: #6b7280;
            border-color: #6b7280;
            color: white;
        }
        .btn-secondary-custom:hover {
            background-color: #4b5563;
            border-color: #4b5563;
        }
        .text-primary-custom {
            color: #1e3a8a !important;
        }
        .bg-secondary-custom {
            background-color: #6b7280 !important;
        }
        .card-category:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.15);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-shoe-prints"></i> Kick Store</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="auth/login.php">Login</a>
                <a class="nav-link" href="auth/register.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Welcome to Kick Store</h1>
            <p class="lead mb-5">Find the perfect shoes for every occasion. Quality, comfort, and style in every step.</p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <a href="auth/register.php" class="btn btn-secondary-custom btn-lg me-3">
                        <i class="fas fa-user-plus"></i> Get Started
                    </a>
                    <a href="auth/login.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-shoe-prints feature-icon mb-3"></i>
                            <h5>Wide Selection</h5>
                            <p class="text-muted">Choose from hundreds of shoes from top brands. Sneakers, formal shoes, boots, and more.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-shipping-fast feature-icon mb-3"></i>
                            <h5>Fast Delivery</h5>
                            <p class="text-muted">Quick and reliable shipping. Get your shoes delivered to your doorstep in 3-5 business days.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-shield-alt feature-icon mb-3"></i>
                            <h5>Secure Shopping</h5>
                            <p class="text-muted">Shop with confidence. Secure payment processing and buyer protection guaranteed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Categories -->
    <section class="py-5" style="background-color: #f8f9fa;">
        <div class="container">
            <h2 class="text-center mb-5 text-primary-custom">Popular Categories</h2>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card text-center card-category">
                        <div class="card-body">
                            <i class="fas fa-running fa-3x text-primary-custom mb-3"></i>
                            <h5>Sneakers</h5>
                            <p class="text-muted">Casual and sport sneakers for everyday wear</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center card-category">
                        <div class="card-body">
                            <i class="fas fa-user-tie fa-3x text-primary-custom mb-3"></i>
                            <h5>Formal</h5>
                            <p class="text-muted">Professional dress shoes for business</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center card-category">
                        <div class="card-body">
                            <i class="fas fa-hiking fa-3x text-primary-custom mb-3"></i>
                            <h5>Boots</h5>
                            <p class="text-muted">Durable boots for work and outdoor activities</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center card-category">
                        <div class="card-body">
                            <i class="fas fa-sun fa-3x text-primary-custom mb-3"></i>
                            <h5>Sandals</h5>
                            <p class="text-muted">Comfortable sandals for summer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5">
        <div class="container text-center">
            <h2 class="mb-4 text-primary-custom">Ready to Find Your Perfect Shoes?</h2>
            <p class="lead mb-4">Join thousands of satisfied customers who trust us for their footwear needs.</p>
            <a href="auth/register.php" class="btn btn-primary-custom btn-lg">
                <i class="fas fa-user-plus"></i> Create Account Now
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-secondary-custom text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-shoe-prints"></i> Kick Store</h5>
                    <p class="text-light">Your trusted partner for quality footwear since 2024.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-light">© 2024 Kick Store. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>