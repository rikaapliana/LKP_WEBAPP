<?php
// File: pages/auth/forgot_password.php

session_start();
require_once '../../includes/functions.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
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
        
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 500px;
            width: 100%;
        }
        
        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .reset-body {
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
        
        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
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
        
        .text-link {
            color: #667eea;
            text-decoration: none;
        }
        
        .text-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
        }
        
        .strength-meter {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="reset-card">
                        <div class="reset-header">
                            <div class="logo">
                                <img src="../../assets/img/logo.png" alt="Logo" style="width: 50px; height: 50px;">
                            </div>
                            <h3 class="mb-0">Reset Password</h3>
                            <p class="mb-0">Atur ulang password Anda</p>
                        </div>
                        
                        <div class="reset-body">
                            <?php if ($message): ?>
                                <?= showAlert($message, $messageType) ?>
                            <?php endif; ?>
                            
                            <div class="info-box">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Masukkan username, email yang terdaftar, dan password baru Anda.
                                </small>
                            </div>
                            
                            <form method="POST" action="process_manual_reset.php" id="resetForm">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Masukkan username Anda" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Masukkan email terdaftar" required>
                                    <small class="text-muted">Email yang terdaftar di akun Anda</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Masukkan password baru" required minlength="6">
                                    <div class="password-strength">
                                        <div class="strength-meter">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                        <small class="text-muted" id="strengthText">Minimal 6 karakter</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Ulangi password baru" required>
                                    <small class="text-muted" id="matchText"></small>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-reset" id="submitBtn" disabled>
                                        <i class="bi bi-shield-check me-2"></i>
                                        Reset Password
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <small>
                                    <a href="login.php" class="text-link">
                                        <i class="bi bi-arrow-left me-1"></i>
                                        Kembali ke Login
                                    </a>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
                return false;
            }
            
            if (password === confirm) {
                matchText.textContent = '✓ Password cocok';
                matchText.style.color = '#28a745';
                return true;
            } else {
                matchText.textContent = '✗ Password tidak cocok';
                matchText.style.color = '#dc3545';
                return false;
            }
        }
        
        function updateSubmitButton() {
            const strength = checkPasswordStrength(passwordInput.value);
            const match = checkPasswordMatch();
            const minLength = passwordInput.value.length >= 6;
            
            submitBtn.disabled = !(strength >= 25 && match && minLength);
        }
        
        passwordInput.addEventListener('input', updateSubmitButton);
        confirmInput.addEventListener('input', updateSubmitButton);
    </script>
</body>
</html>