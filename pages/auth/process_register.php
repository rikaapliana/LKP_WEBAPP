<?php
// File: pages/auth/process_register.php

session_start();
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Jika sudah login, redirect
if (isLoggedIn()) {
    redirect(getRedirectUrl($_SESSION['role']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $role = sanitizeInput($_POST['role']);
    $nik = sanitizeInput($_POST['nik']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $agree = isset($_POST['agree']);
    
    // Validasi input
    if (empty($username) || empty($role) || empty($nik) || empty($email) || empty($password) || empty($confirmPassword)) {
        redirect('register.php?error=' . urlencode('Semua field harus diisi!'));
    }
    
    // Validasi agreement
    if (!$agree) {
        redirect('register.php?error=' . urlencode('Anda harus menyetujui syarat dan ketentuan!'));
    }
    
    // Validasi role
    if (!in_array($role, ['siswa', 'instruktur'])) {
        redirect('register.php?error=' . urlencode('Role tidak valid!'));
    }
    
    // Validasi NIK
    if (!isValidNIK($nik)) {
        redirect('register.php?error=' . urlencode('NIK harus 16 digit angka!'));
    }
    
    // Validasi email
    if (!isValidEmail($email)) {
        redirect('register.php?error=' . urlencode('Format email tidak valid!'));
    }
    
    // Validasi password
    if ($password !== $confirmPassword) {
        redirect('register.php?error=' . urlencode('Password dan konfirmasi password tidak cocok!'));
    }
    
    if (strlen($password) < 6) {
        redirect('register.php?error=' . urlencode('Password minimal 6 karakter!'));
    }
    
    try {
        // Cek apakah NIK dan email cocok dengan data di tabel siswa/instruktur
        if ($role == 'siswa') {
            $stmt = $conn->prepare("SELECT id_siswa, nama FROM siswa WHERE nik = ? AND email = ? AND id_user IS NULL");
        } else {
            $stmt = $conn->prepare("SELECT id_instruktur, nama FROM instruktur WHERE nik = ? AND email = ? AND id_user IS NULL");
        }
        
        $stmt->bind_param("ss", $nik, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            redirect('register.php?error=' . urlencode('NIK dan email tidak cocok dengan data ' . $role . ' yang terdaftar, atau sudah memiliki akun!'));
        }
        
        $userData = $result->fetch_assoc();
        
        // Buat user baru menggunakan helper function (otomatis hash password)
        $createResult = createUser($username, $password, $role, $conn);
        
        if (!$createResult['success']) {
            redirect('register.php?error=' . urlencode($createResult['message']));
        }
        
        $newUserId = $createResult['user_id'];
        
        // Update id_user di tabel siswa/instruktur
        if ($role == 'siswa') {
            $updateStmt = $conn->prepare("UPDATE siswa SET id_user = ? WHERE nik = ? AND email = ?");
        } else {
            $updateStmt = $conn->prepare("UPDATE instruktur SET id_user = ? WHERE nik = ? AND email = ?");
        }
        
        $updateStmt->bind_param("iss", $newUserId, $nik, $email);
        
        if ($updateStmt->execute()) {
            // Log registrasi
            $logFile = '../../uploads/registration_log.txt';
            $logContent = "\n=== NEW USER REGISTRATION ===\n";
            $logContent .= "Waktu: " . date('Y-m-d H:i:s') . "\n";
            $logContent .= "Username: $username\n";
            $logContent .= "Role: $role\n";
            $logContent .= "NIK: $nik\n";
            $logContent .= "Email: $email\n";
            $logContent .= "Nama: " . $userData['nama'] . "\n";
            $logContent .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n";
            $logContent .= "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
            $logContent .= "================================\n";
            file_put_contents($logFile, $logContent, FILE_APPEND);
            
            // Redirect ke login dengan pesan sukses
            redirect('register.php?success=1');
            
        } else {
            // Rollback - hapus user yang sudah dibuat
            $conn->prepare("DELETE FROM user WHERE id_user = ?")->execute([$newUserId]);
            redirect('register.php?error=' . urlencode('Gagal menghubungkan akun dengan data ' . $role . '. Silakan coba lagi.'));
        }
        
    } catch (Exception $e) {
        // Log error untuk debugging
        error_log("Registration error: " . $e->getMessage());
        redirect('register.php?error=' . urlencode('Terjadi kesalahan sistem. Silakan coba lagi.'));
    }
    
} else {
    // Jika bukan POST request
    redirect('register.php');
}
?>