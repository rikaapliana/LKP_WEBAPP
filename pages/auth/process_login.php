<?php
// File: pages/auth/process_login.php

session_start();
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Jika sudah login, redirect
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    // Validasi input
    if (empty($username) || empty($password)) {
        redirect('login.php?error=' . urlencode('Username dan password harus diisi!'));
    }
    
    try {
        // Cari user berdasarkan username
        $stmt = $conn->prepare("SELECT id_user, username, password, role FROM user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verifikasi password
            if (verifyPassword($password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Redirect berdasarkan role
                redirect(getRedirectUrl($user['role']));
                
            } else {
                // Password salah
                redirect('login.php?error=' . urlencode('Username atau password salah!'));
            }
        } else {
            // Username tidak ditemukan
            redirect('login.php?error=' . urlencode('Username atau password salah!'));
        }
        
    } catch (Exception $e) {
        redirect('login.php?error=' . urlencode('Terjadi kesalahan sistem. Silakan coba lagi.'));
    }
    
} else {
    // Jika bukan POST request
    redirect('login.php');
}
?>