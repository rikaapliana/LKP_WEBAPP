<?php
// File: pages/auth/forgot_password.php

session_start();
require_once '../../includes/functions.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'danger';

// Cek parameter URL untuk pesan
if (isset($_GET['success'])) {
    $message = 'Password berhasil direset! Silakan login dengan password baru.';
    $messageType = 'success';
}
if (isset($_GET['error'])) {
    $message = urldecode($_GET['error']);
    $messageType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Reset Password - LKP Webapp</title>
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
        
        .reset-container {
            width: 100%;
            max-width: 650px;
        }
        
        .reset-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            border: 1px solid #e9ecef;
            padding: 50px 40px;
            position: relative;
        }
        
        /* Background decoration - selaras dengan login */
        .reset-card::before {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border-radius: 50%;
            opacity: 0.05;
        }
        
        .reset-card::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: -40px;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
            border-radius: 50%;
            opacity: 0.03;
        }
        
        .card-content {
            position: relative;
            z-index: 2;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 35px;
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
            box-shadow: 0 4px 15px rgba(74, 144, 226, 0.2);
        }
        
        .logo img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        .reset-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .reset-subtitle {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .system-info {
            background: rgba(74, 144, 226, 0.05);
            border-left: 4px solid #4A90E2;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: left;
        }
        
        .system-info h6 {
            color: #4A90E2;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .system-info small {
            color: #495057;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
        
        .btn-reset {
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
            margin-bottom: 25px;
        }
        
        .btn-reset:hover {
            background: linear-gradient(135deg, #357ABD 0%, #2868A3 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-reset:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-to-login {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .back-link {
            color: #4A90E2;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .footer-text {
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        
        /* Alert Styles - selaras dengan login */
        .alert {
            border: none;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        /* Info Box - selaras dengan theme */
        .info-box {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }
        
        /* Password Strength - warna selaras */
        .password-strength {
            margin-top: 8px;
        }
        
        .strength-meter {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-text {
            font-size: 12px;
            font-weight: 500;
        }
        
        .match-text {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 500;
        }
        
        /* Color classes untuk strength */
        .strength-weak { color: #dc3545; }
        .strength-fair { color: #fd7e14; }
        .strength-good { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .match-success { color: #28a745; }
        .match-error { color: #dc3545; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .reset-container {
                max-width: 500px;
            }
            
            .reset-card {
                padding: 35px 25px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .reset-title {
                font-size: 22px;
            }
        }
        
        @media (max-width: 480px) {
            .reset-container {
                padding: 10px;
                max-width: 400px;
            }
            
            .reset-card {
                padding: 25px 20px;
            }
            
            .reset-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="card-content">
                <div class="reset-header">
                    <div class="logo">
                        <img src="../../assets/img/favicon.png" alt="Logo LKP">
                    </div>
                    <h2 class="reset-title">Reset Password</h2>
                    <p class="reset-subtitle">Atur ulang password akun Anda</p>
                    
                    <div class="system-info">
                        <h6><i class="bi bi-shield-lock me-1"></i>Portal Internal LKP</h6>
                        <small>Sistem khusus untuk anggota terdaftar LKP. Akses terbatas hanya untuk siswa dan staff yang memiliki akun resmi lembaga.</small>
                    </div>
                </div>
                
                <!-- Alert Messages -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>" role="alert">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>
                
                <div class="info-box">
                    <i class="bi bi-info-circle me-1"></i>
                    Masukkan username, email yang terdaftar, dan password baru Anda untuk melakukan reset password.
                </div>
                
                <!-- Reset Form -->
                <form method="POST" action="process_manual_reset.php" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="bi bi-person me-1"></i>Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Masukkan username" required autofocus>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope me-1"></i>Email
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Email terdaftar" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock me-1"></i>Password Baru
                            </label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Password baru" required minlength="6">
                                <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                                    <i class="bi bi-eye" id="toggleIcon1"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <small class="strength-text" id="strengthText">Minimal 6 karakter</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="bi bi-lock-fill me-1"></i>Konfirmasi Password
                            </label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Ulangi password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                    <i class="bi bi-eye" id="toggleIcon2"></i>
                                </button>
                            </div>
                            <small class="match-text" id="matchText"></small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-reset" id="submitBtn" disabled>
                        <i class="bi bi-shield-check me-2"></i>
                        Reset Password
                    </button>
                </form>
                
                <div class="back-to-login">
                    <a href="login.php" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>
                        Kembali ke Login
                    </a>
                </div>
                
                <div class="footer-text">
                    &copy; <?= date('Y') ?> LKP Pradata Komputer Tabalong
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
        
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const matchText = document.getElementById('matchText');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkPasswordStrength(password) {
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            
            if (strength < 25) {
                text = 'Terlalu lemah';
                color = '#dc3545';
            } else if (strength < 50) {
                text = 'Lemah';
                color = '#fd7e14';
            } else if (strength < 75) {
                text = 'Sedang';
                color = '#ffc107';
            } else {
                text = 'Kuat';
                color = '#28a745';
            }
            
            strengthFill.style.width = strength + '%';
            strengthFill.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
            
            return strength;
        }
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm === '') {
                matchText.textContent = '';
                matchText.className = 'match-text';
                return false;
            }
            
            if (password === confirm) {
                matchText.textContent = '✓ Password cocok';
                matchText.className = 'match-text match-success';
                return true;
            } else {
                matchText.textContent = '✗ Password tidak cocok';
                matchText.className = 'match-text match-error';
                return false;
            }
        }
        
        function updateSubmitButton() {
            const strength = checkPasswordStrength(passwordInput.value);
            const match = checkPasswordMatch();
            const minLength = passwordInput.value.length >= 6;
            const hasUsername = document.getElementById('username').value.trim() !== '';
            const hasEmail = document.getElementById('email').value.trim() !== '';
            
            submitBtn.disabled = !(strength >= 25 && match && minLength && hasUsername && hasEmail);
        }
        
        // Event listeners
        passwordInput.addEventListener('input', updateSubmitButton);
        confirmInput.addEventListener('input', updateSubmitButton);
        document.getElementById('username').addEventListener('input', updateSubmitButton);
        document.getElementById('email').addEventListener('input', updateSubmitButton);
        
        // Handle form submission
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Memproses...';
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