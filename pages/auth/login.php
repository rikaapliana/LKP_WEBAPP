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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: 
                linear-gradient(135deg, rgba(122, 116, 116, 0.17) 0%, rgba(128, 122, 122, 0.17) 100%),
                url('../../assets/img/background.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 30%, rgba(139, 95, 191, 0.08) 0%, transparent 60%),
                radial-gradient(circle at 70% 70%, rgba(99, 102, 241, 0.06) 0%, transparent 60%);
            pointer-events: none;
        }
        
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
        }
        
        .card-header {
            background: linear-gradient(135deg, #8B5FBF 0%, #6366F1 100%);
            height: 120px;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 60%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }
        
        .card-header::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: calc(100% + 100px);
            height: 100px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            transform: rotate(-2deg);
        }
        
        .card-body {
            padding: 2rem 2.5rem;
            position: relative;
            z-index: 2;
        }
        
        .logo-container {
            position: relative;
            z-index: 3;
            margin-top: -60px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .logo {
            width: 90px;
            height: 90px;
            margin-bottom: 1rem;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .welcome-subtitle {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .form-label {
            color: #4a5568;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: #f8fafc;
            color: #2d3748;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8B5FBF;
            background: white;
            box-shadow: 0 0 0 3px rgba(139, 95, 191, 0.1);
            transform: translateY(-1px);
        }
        
        .form-control::placeholder {
            color: #a0aec0;
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
            color: #a0aec0;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .password-toggle:hover {
            color: #8B5FBF;
            background: rgba(139, 95, 191, 0.1);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            margin-right: 0.5rem;
            accent-color: #8B5FBF;
        }
        
        .form-check-label {
            color: #4a5568;
        }
        
        .forgot-link {
            color: #8B5FBF;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-link:hover {
            color: #6A4C93;
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #8B5FBF 0%, #6A4C93 100%);
            border: none;
            color: white;
            padding: 0.875rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(139, 95, 191, 0.4);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .btn-login.loading .btn-text {
            opacity: 0;
        }
        
        .btn-login.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: #a0aec0;
            font-size: 0.8rem;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 0.5rem;
            }
            
            .login-card {
                padding: 2rem 1.5rem;
            }
            
            .logo {
                width: 70px;
                height: 70px;
            }
            
            .welcome-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Card Header dengan Gradient -->
            <div class="card-header"></div>
            
            <!-- Card Body -->
            <div class="card-body">
                <div class="logo-container">
                    <img src="../../assets/img/favicon.png" alt="Logo" class="logo">
                    <h2 class="welcome-title">Selamat Datang</h2>
                    <p class="welcome-subtitle">Login untuk mengakses sistem</p>
                </div>
            
            <form method="POST" action="process_login.php" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="bi bi-person me-1"></i>Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Masukkan username Anda" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i>Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Masukkan password Anda" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <a href="forgot_password.php" class="forgot-link">Lupa Password?</a>
                </div>
                
                <button type="submit" class="btn-login">
                    <span class="btn-text">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Login
                    </span>
                </button>
            </form>
            
            <div class="footer-text">
                &copy; <?= date('Y') ?> LKP Webapp. All rights reserved.
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
            const submitBtn = this.querySelector('.btn-login');
            
            // Add loading state
            submitBtn.classList.add('loading');
            
            // Disable button to prevent double submission
            submitBtn.disabled = true;
        });
        
        // Show alerts with SweetAlert2
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($error): ?>
                Swal.fire({
                    title: 'Login Gagal!',
                    text: '<?= htmlspecialchars($error) ?>',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#dc3545',
                    background: 'rgba(255, 255, 255, 0.95)',
                    backdrop: 'rgba(0, 0, 0, 0.4)'
                });
            <?php endif; ?>
            
            <?php if ($success): ?>
                Swal.fire({
                    title: 'Berhasil!',
                    text: '<?= htmlspecialchars($success) ?>',
                    icon: 'success',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#8B5FBF',
                    background: 'rgba(255, 255, 255, 0.95)',
                    backdrop: 'rgba(0, 0, 0, 0.4)'
                });
            <?php endif; ?>
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                const form = document.getElementById('loginForm');
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                
                if (username && password) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>