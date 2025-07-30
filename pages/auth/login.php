<?php
// File: pages/auth/login.php

session_start();
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Jika sudah login, redirect ke dashboard sesuai role
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

// Cek remember me
validateRememberMe($conn);
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

$error = '';
$success = '';

// Cek parameter URL untuk pesan
if (isset($_GET['expired'])) {
    $error = 'Sesi Anda telah berakhir. Silakan login kembali.';
}
if (isset($_GET['reset'])) {
    $success = 'Password berhasil direset! Silakan login dengan password baru.';
}
if (isset($_GET['logout'])) {
    $success = 'Anda berhasil logout.';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'invalid_credentials') {
        $error = 'Username atau password yang Anda masukkan salah.';
    } elseif ($_GET['error'] == 'user_not_found') {
        $error = 'Username tidak ditemukan dalam sistem.';
    } elseif ($_GET['error'] == 'account_inactive') {
        $error = 'Akun Anda tidak aktif. Hubungi administrator.';
    } else {
        $error = 'Username atau password yang Anda masukkan salah. Silakan coba lagi.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Login - LKP Webapp</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon.png"/>
    
    <!-- CSS -->
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../../assets/css/bootstrap-icons.css" rel="stylesheet" />
    <link href="../../assets/css/styles.css" rel="stylesheet" />
    <link href="../../assets/css/fonts.css" rel="stylesheet" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 900px;
        }
        
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            display: flex;
            min-height: 600px;
            border: 1px solid #e9ecef;
        }
        
        /* Left Side - Informational */
        .card-left {
            flex: 1;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 70%, #2868A3 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .card-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .card-left::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 200px;
            height: 200px;
            border: 2px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }
        
        .info-content {
            position: relative;
            z-index: 2;
            text-align: left;
        }
        
        /* Header Section */
        .info-header {
            margin-bottom: 40px;
        }
        
        .system-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .institution-name {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        
        .institution-subtitle {
            font-size: 14px;
            opacity: 0.85;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .institution-description {
            font-size: 13px;
            opacity: 0.75;
            line-height: 1.4;
            font-weight: 400;
        }
        
        /* Mission Section */
        .mission-section {
            margin-bottom: 40px;
        }
        
        .mission-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        
        .mission-text {
            font-size: 14px;
            line-height: 1.5;
            opacity: 0.8;
        }
        
        /* Features Section */
        .features-section {
            margin-bottom: 40px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(5px);
        }
        
        .feature-icon {
            width: 35px;
            height: 35px;
            background: rgba(134, 157, 226, 0.9);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #01080fff;
            flex-shrink: 0;
        }
        
        .feature-text {
            font-size: 12px;
            font-weight: 500;
            line-height: 1.3;
        }
        
        /* Stats Section */
        .stats-section {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 30px;
        }
        
        .stats-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            opacity: 0.9;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            text-align: center;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px 15px;
            backdrop-filter: blur(5px);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
            display: block;
        }
        
        .stat-label {
            font-size: 11px;
            opacity: 0.8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Right Side - Login Form */
        .card-right {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #4A90E2;
        }
        
        .logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        .login-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            font-size: 14px;
            color: #6c757d;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background-color: #fff;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4A90E2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
        }
        
        .password-toggle:hover {
            color: #4A90E2;
            background-color: #f8f9fa;
        }
        
        .forgot-password {
            text-align: right;
            margin-bottom: 30px;
        }
        
        .forgot-link {
            color: #4A90E2;
            text-decoration: none;
            font-size: 13px;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border: none;
            color: white;
            padding: 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #357ABD 0%, #2868A3 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 40px;
            color: #6c757d;
            font-size: 12px;
        }
        
        /* Alert Styles */
        .alert {
            border: none;
            border-radius: 8px;
            margin-bottom: 24px;
            padding: 12px 16px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d1edff;
            color: #0c5460;
            border-left: 4px solid #0dcaf0;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .alert .bi {
            margin-right: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 450px;
                min-height: auto;
            }
            
            .card-left {
                padding: 40px 30px;
                text-align: center;
            }
            
            .info-content {
                text-align: center;
            }
            
            .institution-name {
                font-size: 28px;
            }
            
            .features-grid,
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .card-right {
                padding: 40px 30px;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 10px;
            }
            
            .card-left,
            .card-right {
                padding: 30px 20px;
            }
            
            .institution-name {
                font-size: 24px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Left Side - Information -->
            <div class="card-left">
                <div class="info-content">
                    <!-- Header Section -->
                    <div class="info-header">
                        <div class="system-badge">
                            <i class="bi bi-shield-lock me-1"></i>
                            Portal Internal
                        </div>
                        
                        <h1 class="institution-name">
                            Lembaga Kursus<br>
                            dan Pelatihan
                        </h1>
                        
                        <div class="institution-subtitle">
                            Program Tabalong Smart
                        </div>
                        
                        <div class="institution-description">
                            Sistem khusus untuk anggota terdaftar LKP.<br>
                            Akses terbatas untuk siswa dan staff resmi lembaga.
                        </div>
                    </div>
                    
                    <!-- Mission Section -->
                    <div class="mission-section">
                        <div class="mission-title">Misi Kami</div>
                        <div class="mission-text">
                            Membangun Sumber Daya Manusia yang Unggul melalui program pelatihan berkualitas tinggi
                        </div>
                    </div>
                    
                    <!-- Features Section -->
                    <div class="features-section">
                        <div class="features-grid">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-mortarboard"></i>
                                </div>
                                <div class="feature-text">
                                    Program<br>Berkualitas
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="feature-text">
                                    Instruktur<br>Berpengalaman
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Section -->
                    <div class="stats-section">
                        <div class="stats-title">Pencapaian Kami</div>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-number">10K+</span>
                                <span class="stat-label">Alumni</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">25+</span>
                                <span class="stat-label">Instruktur</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">15+</span>
                                <span class="stat-label">Program</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="card-right">
                <div class="login-header">
                    <div class="logo">
                        <img src="../../assets/img/favicon.png" alt="Logo LKP">
                    </div>
                    <h2 class="login-title">Selamat Datang</h2>
                    <p class="login-subtitle">Silakan login untuk mengakses sistem</p>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="process_login.php" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="bi bi-person me-1"></i>Username
                        </label>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Masukkan username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password
                        </label>
                        <div class="password-wrapper">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Masukkan password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="forgot_password.php" class="forgot-link">Lupa Password?</a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Login
                    </button>
                </form>
                
                <div class="footer-text">
                    &copy; <?= date('Y') ?> LKP Pradata Komputer Tabalong | Developed by Rika Apliana
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
        
        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            
            // Disable button and show loading
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Memproses...';
        });
        
        // Auto hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>