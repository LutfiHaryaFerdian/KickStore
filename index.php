<?php
session_start();

// Redirect if user is already logged in
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
    <title>Toko Sepatu Kick - Koleksi Sepatu Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        .hero-section {
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), 
                        url("uploads/dbimage/db.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(210, 180, 140, 0.8) 0%, rgba(160, 82, 45, 0.8) 100%);
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero-section h1 {
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            margin-bottom: 1.5rem;
        }
        
        .hero-section .lead {
            font-size: 1.4rem;
            font-weight: 300;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            margin-bottom: 2.5rem;
        }
        
        .btn-secondary-custom {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(210, 180, 140, 0.3);
            color: white;
        }
        
        .btn-secondary-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(210, 180, 140, 0.4);
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            color: white;
        }
        
        .btn-outline-light {
            border: 2px solid white;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: #A0522D;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }
        
        .features-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #FEFEFE 0%, #F5DEB3 100%);
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(210, 180, 140, 0.2);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(210, 180, 140, 0.3);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(210, 180, 140, 0.2);
        }
        
        .navbar .btn-primary {
            background: linear-gradient(135deg, #D2B48C 0%, #CD853F 100%);
            border: none;
            color: white;
        }
        
        .navbar .btn-primary:hover {
            background: linear-gradient(135deg, #CD853F 0%, #A0522D 100%);
            color: white;
        }
        
        .scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-section {
                background-attachment: scroll;
                min-height: 80vh;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .hero-section .lead {
                font-size: 1.1rem;
            }
            
            .btn-secondary-custom,
            .btn-outline-light {
                padding: 12px 25px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php" style="color: #A0522D;">
                <i class="fas fa-shoe-prints"></i> Kick Store
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home" style="color: #A0522D;">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features" style="color: #A0522D;">Fitur</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php" style="color: #A0522D;">
                            <i class="fas fa-sign-in-alt"></i> Masuk
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white px-3 ms-2" href="auth/register.php">
                            <i class="fas fa-user-plus"></i> Daftar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container text-center hero-content">
            <h1 class="display-4 mb-4">Selamat Datang di Toko Sepatu Kick</h1>
            <p class="lead mb-5">Temukan sepatu yang sempurna untuk setiap kesempatan. Kualitas, kenyamanan, dan gaya di setiap langkah.</p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <a href="auth/register.php" class="btn btn-secondary-custom btn-lg me-3">
                        <i class="fas fa-user-plus"></i> Mulai Belanja
                    </a>
                    <a href="auth/login.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="scroll-indicator">
            <i class="fas fa-chevron-down text-white" style="font-size: 1.5rem;"></i>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5 fw-bold mb-3" style="color: #A0522D;">Mengapa Memilih Toko Sepatu Kick?</h2>
                    <p class="lead text-muted">Rasakan pengalaman terbaik berbelanja sepatu</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-gem"></i>
                        </div>
                        <h4 class="fw-bold mb-3" style="color: #A0522D;">Kualitas Premium</h4>
                        <p class="text-muted">Koleksi sepatu berkualitas tinggi yang dikurasi dengan cermat dari merek-merek terpercaya di seluruh dunia.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <h4 class="fw-bold mb-3" style="color: #A0522D;">Pengiriman Cepat</h4>
                        <p class="text-muted">Pengiriman cepat dan andal untuk mendapatkan sepatu impian Anda langsung ke depan pintu Anda.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4 class="fw-bold mb-3" style="color: #A0522D;">Kepuasan Pelanggan</h4>
                        <p class="text-muted">Jaminan kepuasan 100% dengan pengembalian mudah dan layanan pelanggan yang luar biasa.</p>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h4 class="fw-bold mb-3" style="color: #A0522D;">Harga Terbaik</h4>
                        <p class="text-muted">Harga yang kompetitif dengan diskon reguler dan penawaran khusus untuk pelanggan setia kami.</p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4 class="fw-bold mb-3" style="color: #A0522D;">Belanja Mudah</h4>
                        <p class="text-muted">Antarmuka yang ramah pengguna dengan opsi pencarian dan filter lanjutan untuk menemukan apa yang Anda butuhkan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5" style="background: linear-gradient(135deg, #A0522D 0%, #8B4513 100%); color: white;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-shoe-prints"></i> Kick Store
                    </h5>
                    <p class="text-light opacity-75">Mitra terpercaya untuk sepatu premium. Melangkah dengan gaya, kenyamanan, dan kualitas.</p>
                </div>
                <div class="col-md-6 text-end">
                    <h6 class="fw-bold mb-3">Tautan Cepat</h6>
                    <div class="d-flex flex-column align-items-end">
                        <a href="auth/login.php" class="text-light text-decoration-none mb-2 opacity-75">Masuk</a>
                        <a href="auth/register.php" class="text-light text-decoration-none mb-2 opacity-75">Daftar</a>
                        <a href="#features" class="text-light text-decoration-none opacity-75">Fitur</a>
                    </div>
                    <p class="text-light opacity-75 mt-4">© 2024 Kick Store. Hak cipta dilindungi.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });
    </script>
</body>
</html>
