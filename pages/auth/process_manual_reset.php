<?php
// File: pages/auth/process_manual_reset.php

session_start();
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Jika sudah login, redirect
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validasi input
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        redirect('forgot_password.php?error=' . urlencode('Semua field harus diisi!'));
    }
    
    // Validasi email format
    if (!isValidEmail($email)) {
        redirect('forgot_password.php?error=' . urlencode('Format email tidak valid!'));
    }
    
    // Validasi password match
    if ($password !== $confirmPassword) {
        redirect('forgot_password.php?error=' . urlencode('Password dan konfirmasi password tidak cocok!'));
    }
    
    // Validasi panjang password
    if (strlen($password) < 6) {
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
            
            // Hash password baru
            $hashedPassword = hashPassword($password);
            
            // Update password
            $updateStmt = $conn->prepare("UPDATE user SET password = ? WHERE id_user = ?");
            $updateStmt->bind_param("si", $hashedPassword, $user['id_user']);
            
            if ($updateStmt->execute()) {
                // Log aktivitas reset password
                $logFile = '../../uploads/password_reset_log.txt';
                $logContent = "\n=== MANUAL PASSWORD RESET ===\n";
                $logContent .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
                $logContent .= "Username: " . $user['username'] . "\n";
                $logContent .= "Email: " . $user['user_email'] . "\n";
                $logContent .= "Role: " . $user['role'] . "\n";
                $logContent .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n";
                $logContent .= "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
                $logContent .= "==============================\n";
                file_put_contents($logFile, $logContent, FILE_APPEND);
                
                // Redirect ke login dengan pesan sukses
                redirect('forgot_password.php?success=1');
                
            } else {
                redirect('forgot_password.php?error=' . urlencode('Gagal mengupdate password. Silakan coba lagi.'));
            }
            
        } else {
            // Username dan email tidak cocok
            redirect('forgot_password.php?error=' . urlencode('Username dan email tidak cocok atau tidak terdaftar!'));
        }
        
    } catch (Exception $e) {
        // Log error untuk debugging
        error_log("Manual reset password error: " . $e->getMessage());
        redirect('forgot_password.php?error=' . urlencode('Terjadi kesalahan sistem. Silakan coba lagi.'));
    }
    
} else {
    // Jika bukan POST request
    redirect('forgot_password.php');
}
?>