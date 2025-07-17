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
if (isset($_GET['registered'])) {
    $success = 'Akun berhasil dibuat! Silakan login.';
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
    
    <!-- CSS -->
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../../assets/css/styles.css" rel="stylesheet" />
    <link href="../../assets/css/fonts.css" rel="stylesheet" />
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .text-link {
            color: #667eea;
            text-decoration: none;
        }
        
        .text-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="login-card">
                        <div class="login-header">
                            <div class="logo">
                                <img src="../../assets/img/favicon.png" alt="Logo" style="width: 50px; height: 50px;">
                            </div>
                            <h3 class="mb-0">Selamat Datang</h3>
                            <p class="mb-0">Login untuk mengakses sistem</p>
                        </div>
                        
                        <div class="login-body">
                            <?php if ($error): ?>
                                <?= showAlert($error, 'danger') ?>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <?= showAlert($success, 'success') ?>
                            <?php endif; ?>
                            
                            <form method="POST" action="process_login.php" id="loginForm">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Masukkan username Anda" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Masukkan password Anda" required>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-login">
                                        <i class="bi bi-box-arrow-in-right me-2"></i>
                                        Masuk
                                    </button>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <small>
                                        <a href="forgot_password.php" class="text-link">Lupa Password?</a>
                                    </small>
                                </div>
                                
                                <hr class="my-4">
                                
                                <div class="text-center">
                                    <small class="text-muted">Belum punya akun?</small><br>
                                    <a href="register.php" class="text-link">Daftar Sekarang</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>