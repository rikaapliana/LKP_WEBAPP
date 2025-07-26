<?php
session_start();  
require_once '../../../includes/auth.php';  
requireSiswaAuth();

include '../../../includes/db.php';

$user_id = $_SESSION['user_id'];

// Proses ubah password
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_lama = trim($_POST['password_lama'] ?? '');
    $password_baru = trim($_POST['password_baru'] ?? '');
    $konfirmasi_password = trim($_POST['konfirmasi_password'] ?? '');
    
    // Validasi input password
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $_SESSION['error'] = "Semua field password wajib diisi!";
    } elseif (strlen($password_baru) < 6) {
        $_SESSION['error'] = "Password baru minimal 6 karakter!";
    } elseif ($password_baru !== $konfirmasi_password) {
        $_SESSION['error'] = "Konfirmasi password tidak sama!";
    } else {
        try {
            // Cek password lama dari database
            $stmt = $conn->prepare("SELECT password FROM user WHERE id_user = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if (!$user_data) {
                $_SESSION['error'] = "Data user tidak ditemukan!";
            } elseif (!password_verify($password_lama, $user_data['password'])) {
                $_SESSION['error'] = "Password lama tidak benar!";
            } else {
                // Hash password baru
                $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                
                // Update password di database
                $update_stmt = $conn->prepare("UPDATE user SET password = ? WHERE id_user = ?");
                $update_stmt->bind_param("si", $password_hash, $user_id);
                
                if ($update_stmt->execute()) {
                    // Verifikasi password ter-update dengan benar
                    $verify_stmt = $conn->prepare("SELECT password FROM user WHERE id_user = ?");
                    $verify_stmt->bind_param("i", $user_id);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $updated_user = $verify_result->fetch_assoc();
                    
                    if ($updated_user && password_verify($password_baru, $updated_user['password'])) {
                        $_SESSION['success'] = "Password berhasil diubah! Silakan gunakan password baru untuk login selanjutnya.";
                        header("Location: index.php");
                        exit();
                    } else {
                        $_SESSION['error'] = "Password tidak ter-update dengan benar! Silakan coba lagi.";
                    }
                } else {
                    $_SESSION['error'] = "Gagal mengubah password! Error: " . $update_stmt->error;
                }
            }
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
} else {
    $_SESSION['error'] = "Metode request tidak valid!";
}

// Redirect kembali ke halaman edit
header("Location: edit.php");
exit();
?>