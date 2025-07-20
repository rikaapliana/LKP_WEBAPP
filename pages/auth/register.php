<?php
// File: pages/auth/register.php

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
    $message = 'Akun berhasil dibuat! Silakan login dengan akun Anda.';
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
    <title>Daftar Akun - LKP Webapp</title>
    
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
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 600px;
            width: 100%;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .register-body {
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
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
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
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="register-card">
                        <div class="register-header">
                            <div class="logo">
                                <img src="../../assets/img/favicon.png" alt="Logo" style="width: 50px; height: 50px;">
                            </div>
                            <h3 class="mb-0">Daftar Akun</h3>
                            <p class="mb-0">Buat akun baru untuk mengakses sistem</p>
                        </div>
                        
                        <div class="register-body">
                            <?php if ($message): ?>
                                <?= showAlert($message, $messageType) ?>
                            <?php endif; ?>
                            
                            <div class="info-box">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Catatan:</strong> Registrasi hanya untuk siswa dan instruktur yang sudah terdaftar di sistem. 
                                    Gunakan NIK dan email yang sesuai dengan data di LKP.
                                </small>
                            </div>
                            
                            <form method="POST" action="process_register.php" id="registerForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username" 
                                                   placeholder="Masukkan username" required minlength="4">
                                            <small class="text-muted">Minimal 4 karakter, unik</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="role" class="form-label">Daftar Sebagai <span class="text-danger">*</span></label>
                                            <select class="form-control" id="role" name="role" required>
                                                <option value="">Pilih Role</option>
                                                <option value="siswa">Siswa</option>
                                                <option value="instruktur">Instruktur</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nik" class="form-label">NIK <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="nik" name="nik" 
                                                   placeholder="16 digit NIK" required maxlength="16" pattern="[0-9]{16}">
                                            <small class="text-muted">NIK harus sesuai data di LKP</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="Email terdaftar" required>
                                            <small class="text-muted">Email harus sesuai data di LKP</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Masukkan password" required minlength="6">
                                            <div class="password-strength">
                                                <div class="strength-meter">
                                                    <div class="strength-fill" id="strengthFill"></div>
                                                </div>
                                                <small class="text-muted" id="strengthText">Minimal 6 karakter</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                   placeholder="Ulangi password" required>
                                            <small class="text-muted" id="matchText"></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="agree" name="agree" required>
                                        <label class="form-check-label" for="agree">
                                            Saya setuju dengan <a href="#" class="text-link">syarat dan ketentuan</a> yang berlaku
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-register" id="submitBtn" disabled>
                                        <i class="bi bi-person-plus me-2"></i>
                                        Daftar Akun
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <small>
                                    Sudah punya akun? 
                                    <a href="login.php" class="text-link">Login di sini</a>
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
        const agreeCheck = document.getElementById('agree');
        
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
            const agreed = agreeCheck.checked;
            
            submitBtn.disabled = !(strength >= 25 && match && minLength && agreed);
        }
        
        passwordInput.addEventListener('input', updateSubmitButton);
        confirmInput.addEventListener('input', updateSubmitButton);
        agreeCheck.addEventListener('change', updateSubmitButton);
        
        // NIK validation
        document.getElementById('nik').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 16) {
                this.value = this.value.slice(0, 16);
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
        });
    </script>
</body>
</html>