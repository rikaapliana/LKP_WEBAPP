<?php
// File: pages/auth/process_manual_reset.php

session_start();
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Jika sudah login, redirect
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

// Rate limiting configuration
$maxAttempts = 3;
$lockoutTime = 300; // 5 menit
$resetAttemptsFile = '../../uploads/reset_attempts.json';

function getRateLimitData() {
    global $resetAttemptsFile;
    if (!file_exists($resetAttemptsFile)) {
        return [];
    }
    return json_decode(file_get_contents($resetAttemptsFile), true) ?: [];
}

function saveRateLimitData($data) {
    global $resetAttemptsFile;
    $dir = dirname($resetAttemptsFile);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($resetAttemptsFile, json_encode($data));
}

function checkRateLimit($username, $ip) {
    global $maxAttempts, $lockoutTime;
    
    $rateLimitData = getRateLimitData();
    $currentTime = time();
    
    // Cleanup expired attempts
    foreach ($rateLimitData as $key => $data) {
        if ($currentTime - $data['last_attempt'] > $lockoutTime) {
            unset($rateLimitData[$key]);
        }
    }
    
    $userKey = $username . '_' . $ip;
    
    if (isset($rateLimitData[$userKey])) {
        $attempts = $rateLimitData[$userKey];
        
        // Check if still in lockout period
        if ($attempts['count'] >= $maxAttempts && 
            ($currentTime - $attempts['last_attempt']) < $lockoutTime) {
            $remainingTime = $lockoutTime - ($currentTime - $attempts['last_attempt']);
            $remainingMinutes = ceil($remainingTime / 60);
            return [
                'allowed' => false,
                'message' => "Terlalu banyak percobaan reset password. Coba lagi dalam {$remainingMinutes} menit."
            ];
        }
        
        // Reset attempts if lockout period has passed
        if (($currentTime - $attempts['last_attempt']) >= $lockoutTime) {
            unset($rateLimitData[$userKey]);
            saveRateLimitData($rateLimitData);
        }
    }
    
    return ['allowed' => true];
}

function recordAttempt($username, $ip, $success = false) {
    global $maxAttempts;
    
    $rateLimitData = getRateLimitData();
    $userKey = $username . '_' . $ip;
    $currentTime = time();
    
    if ($success) {
        // Remove rate limit data on successful reset
        unset($rateLimitData[$userKey]);
    } else {
        // Increment failed attempts
        if (!isset($rateLimitData[$userKey])) {
            $rateLimitData[$userKey] = ['count' => 0, 'last_attempt' => 0];
        }
        
        $rateLimitData[$userKey]['count']++;
        $rateLimitData[$userKey]['last_attempt'] = $currentTime;
    }
    
    saveRateLimitData($rateLimitData);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect('forgot_password.php?error=' . urlencode('Token keamanan tidak valid. Silakan coba lagi.'));
    }
    
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $clientIP = $_SERVER['REMOTE_ADDR'];
    
    // Validasi input
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        recordAttempt($username, $clientIP, false);
        redirect('forgot_password.php?error=' . urlencode('Semua field harus diisi!'));
    }
    
    // Check rate limiting
    $rateLimitCheck = checkRateLimit($username, $clientIP);
    if (!$rateLimitCheck['allowed']) {
        redirect('forgot_password.php?error=' . urlencode($rateLimitCheck['message']));
    }
    
    // Validasi email format
    if (!isValidEmail($email)) {
        recordAttempt($username, $clientIP, false);
        redirect('forgot_password.php?error=' . urlencode('Format email tidak valid!'));
    }
    
    // Validasi password match
    if ($password !== $confirmPassword) {
        recordAttempt($username, $clientIP, false);
        redirect('forgot_password.php?error=' . urlencode('Password dan konfirmasi password tidak cocok!'));
    }
    
    // Validasi panjang password
    if (strlen($password) < 6) {
        recordAttempt($username, $clientIP, false);
        redirect('forgot_password.php?error=' . urlencode('Password minimal 6 karakter!'));
    }
    
    try {
        // Cari user berdasarkan username dan email dari tabel terkait
        $stmt = $conn->prepare("
            SELECT u.id_user, u.username, u.role,
                   COALESCE(a.email, i.email, s.email) as user_email,
                   COALESCE(a.nama, i.nama, s.nama) as nama
            FROM user u
            LEFT JOIN admin a ON u.id_user = a.id_user
            LEFT JOIN instruktur i ON u.id_user = i.id_user  
            LEFT JOIN siswa s ON u.id_user = s.id_user
            WHERE u.username = ? AND COALESCE(a.email, i.email, s.email) = ?
        ");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Cek apakah role diizinkan untuk reset password manual
            $allowedRoles = ['siswa', 'instruktur'];
            if (!in_array($user['role'], $allowedRoles)) {
                recordAttempt($username, $clientIP, false);
                redirect('forgot_password.php?error=' . urlencode('Reset password manual hanya diizinkan untuk siswa dan instruktur!'));
            }
            
            // Hash password baru
            $hashedPassword = hashPassword($password);
            
            // Update password
            $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE id_user = ?");
            $updateStmt->bind_param("si", $hashedPassword, $user['id_user']);
            
            if ($updateStmt->execute()) {
                // Record successful attempt
                recordAttempt($username, $clientIP, true);
                
                // Log aktivitas reset password
                $logFile = '../../uploads/password_reset_log.txt';
                $logDir = dirname($logFile);
                
                // Buat direktori jika belum ada
                if (!file_exists($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                $logContent = "\n=== MANUAL PASSWORD RESET ===\n";
                $logContent .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
                $logContent .= "Username: " . $user['username'] . "\n";
                $logContent .= "Email: " . $user['user_email'] . "\n";
                $logContent .= "Nama: " . $user['nama'] . "\n";
                $logContent .= "Role: " . $user['role'] . "\n";
                $logContent .= "IP Address: " . $clientIP . "\n";
                $logContent .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
                $logContent .= "Status: SUCCESS\n";
                $logContent .= "==============================\n";
                
                file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
                
                // Generate new CSRF token for next request
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                // Redirect ke login dengan pesan sukses
                redirect('login.php?reset=1');
                
            } else {
                recordAttempt($username, $clientIP, false);
                
                // Log failed database update
                $logFile = '../../uploads/password_reset_log.txt';
                $logContent = "\n=== MANUAL PASSWORD RESET FAILED ===\n";
                $logContent .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
                $logContent .= "Username: " . $username . "\n";
                $logContent .= "Email: " . $email . "\n";
                $logContent .= "IP Address: " . $clientIP . "\n";
                $logContent .= "Error: Database update failed\n";
                $logContent .= "====================================\n";
                file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
                
                redirect('forgot_password.php?error=' . urlencode('Gagal mengupdate password. Silakan coba lagi.'));
            }
            
        } else {
            // Username dan email tidak cocok - record attempt
            recordAttempt($username, $clientIP, false);
            
            // Log failed attempt
            $logFile = '../../uploads/password_reset_log.txt';
            $logContent = "\n=== MANUAL PASSWORD RESET FAILED ===\n";
            $logContent .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
            $logContent .= "Username: " . $username . "\n";
            $logContent .= "Email: " . $email . "\n";
            $logContent .= "IP Address: " . $clientIP . "\n";
            $logContent .= "Error: Username and email mismatch\n";
            $logContent .= "====================================\n";
            file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
            
            redirect('forgot_password.php?error=' . urlencode('Username dan email tidak cocok atau tidak terdaftar!'));
        }
        
    } catch (Exception $e) {
        // Record failed attempt
        recordAttempt($username, $clientIP, false);
        
        // Log error untuk debugging
        error_log("Manual reset password error: " . $e->getMessage());
        
        $logFile = '../../uploads/password_reset_log.txt';
        $logContent = "\n=== MANUAL PASSWORD RESET ERROR ===\n";
        $logContent .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "Username: " . $username . "\n";
        $logContent .= "Email: " . $email . "\n";
        $logContent .= "IP Address: " . $clientIP . "\n";
        $logContent .= "Error: " . $e->getMessage() . "\n";
        $logContent .= "===================================\n";
        file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
        
        redirect('forgot_password.php?error=' . urlencode('Terjadi kesalahan sistem. Silakan coba lagi.'));
    }
    
} else {
    // Jika bukan POST request
    redirect('forgot_password.php');
}
?>