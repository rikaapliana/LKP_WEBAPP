<?php
// File: includes/functions.php

// BARIS TAMBAHAN UNTUK MEMANGGIL PHPMailer
// ==========================================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Pastikan autoload.php dipanggil sebelum fungsi ini
// Path ini relatif terhadap file functions.php di dalam folder 'includes'
require_once __DIR__ . '/../vendor/autoload.php';
// ==========================================================

// Fungsi untuk hash password yang aman
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Fungsi untuk verifikasi password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Fungsi generate token acak untuk remember me dan reset password
function generateToken($length = 50) {
    return bin2hex(random_bytes($length));
}

// Fungsi untuk validasi email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Fungsi untuk redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Fungsi untuk menampilkan pesan alert
function showAlert($message, $type = 'danger') {
    return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

// Fungsi untuk cek NIK format Indonesia (16 digit)
function isValidNIK($nik) {
    return preg_match('/^[0-9]{16}$/', $nik);
}

// Fungsi untuk membersihkan input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk set remember me cookie
function setRememberMeCookie($userId, $token) {
    // Cookie berlaku 30 hari
    $expire = time() + (30 * 24 * 60 * 60);
    setcookie('remember_token', $userId . ':' . $token, $expire, '/', '', false, true);
}

// Fungsi untuk hapus remember me cookie
function clearRememberMeCookie() {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Fungsi untuk validasi remember me
function validateRememberMe($conn) {
    if (isset($_COOKIE['remember_token'])) {
        $tokenParts = explode(':', $_COOKIE['remember_token']);
        
        if (count($tokenParts) == 2) {
            $userId = (int)$tokenParts[0];
            $token = $tokenParts[1];
            
            $stmt = $conn->prepare("SELECT id_user, username, role FROM user WHERE id_user = ? AND remember_token = ?");
            $stmt->bind_param("is", $userId, $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Set session
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                return true;
            } else {
                // Token tidak valid, hapus cookie
                clearRememberMeCookie();
            }
        }
    }
    return false;
}

// Fungsi untuk cek session timeout (30 menit)
function checkSessionTimeout() {
    $timeout = 30 * 60; // 30 menit dalam detik
    
    if (isset($_SESSION['login_time'])) {
        if ((time() - $_SESSION['login_time']) > $timeout) {
            // Session expired
            session_destroy();
            clearRememberMeCookie();
            return false;
        } else {
            // Update login time
            $_SESSION['login_time'] = time();
        }
    }
    return true;
}

// Fungsi untuk mengecek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

// Fungsi untuk mengecek role user
function hasRole($requiredRole) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $requiredRole;
}

// Fungsi untuk mendapatkan URL redirect berdasarkan role
function getRedirectUrl($role) {
    switch ($role) {
        case 'admin':
            return '../admin/dashboard.php';
        case 'instruktur':
            return '../instruktur/dashboard.php';
        case 'siswa':
            return '../siswa/dashboard.php';
        default:
            return '../../index.php';
    }
}

// ===== HELPER FUNCTIONS UNTUK ADMIN USER MANAGEMENT =====

// Fungsi untuk membuat user baru (otomatis hash password)
function createUser($username, $password, $role, $conn) {
    // Validasi input
    if (empty($username) || empty($password) || empty($role)) {
        return ['success' => false, 'message' => 'Semua field harus diisi!'];
    }
    
    // Validasi role
    $validRoles = ['admin', 'instruktur', 'siswa'];
    if (!in_array($role, $validRoles)) {
        return ['success' => false, 'message' => 'Role tidak valid!'];
    }
    
    // Cek apakah username sudah ada
    $checkStmt = $conn->prepare("SELECT id_user FROM user WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Username sudah digunakan!'];
    }
    
    // Hash password dan insert user
    $hashedPassword = hashPassword($password);
    
    $stmt = $conn->prepare("INSERT INTO user (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $username, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'User berhasil dibuat!', 'user_id' => $conn->insert_id];
    } else {
        return ['success' => false, 'message' => 'Gagal membuat user: ' . $conn->error];
    }
}

// Fungsi untuk update password user (otomatis hash)
function updateUserPassword($userId, $newPassword, $conn) {
    // Validasi input
    if (empty($userId) || empty($newPassword)) {
        return ['success' => false, 'message' => 'User ID dan password harus diisi!'];
    }
    
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Password minimal 6 karakter!'];
    }
    
    // Hash password baru dan update
    $hashedPassword = hashPassword($newPassword);
    
    $stmt = $conn->prepare("UPDATE user SET password = ? WHERE id_user = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Password berhasil diupdate!'];
        } else {
            return ['success' => false, 'message' => 'User tidak ditemukan!'];
        }
    } else {
        return ['success' => false, 'message' => 'Gagal update password: ' . $conn->error];
    }
}

// Fungsi untuk generate password default
function generateDefaultPassword($role, $name = '') {
    switch ($role) {
        case 'admin':
            return 'admin123';
        case 'instruktur':
            return 'guru123';
        case 'siswa':
            return 'siswa123';
        default:
            return 'default123';
    }
}

// Fungsi untuk validasi kekuatan password
function validatePasswordStrength($password) {
    $strength = 0;
    $feedback = [];
    
    if (strlen($password) >= 6) {
        $strength += 25;
    } else {
        $feedback[] = 'Minimal 6 karakter';
    }
    
    if (preg_match('/[a-z]/', $password)) {
        $strength += 25;
    } else {
        $feedback[] = 'Perlu huruf kecil';
    }
    
    if (preg_match('/[A-Z]/', $password)) {
        $strength += 25;
    } else {
        $feedback[] = 'Perlu huruf besar';
    }
    
    if (preg_match('/[0-9]/', $password)) {
        $strength += 25;
    } else {
        $feedback[] = 'Perlu angka';
    }
    
    $level = 'Lemah';
    if ($strength >= 75) $level = 'Kuat';
    elseif ($strength >= 50) $level = 'Sedang';
    
    return [
        'strength' => $strength,
        'level' => $level,
        'feedback' => $feedback
    ];
}
// =================================================================
// FUNGSI BARU UNTUK MENGIRIM SEMUA EMAIL NOTIFIKASI SECARA TERPUSAT
// =================================================================
/**
 * Fungsi terpusat untuk mengirim semua jenis email notifikasi.
 *
 * @param string $penerima_email Alamat email tujuan.
 * @param string $penerima_nama  Nama penerima.
 * @param string $subjek         Subjek email.
 * @param string $isi_pesan      Isi email dalam format HTML.
 * @return bool                  True jika berhasil, false jika gagal.
 */
function kirimEmailNotifikasi($penerima_email, $penerima_nama, $subjek, $isi_pesan) {

    $mail = new PHPMailer(true);

    try {
        // Konfigurasi Server SMTP (sama seperti di bantuan.php)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Hapus komentar ini jika butuh debug lagi
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'rikaapliana02@gmail.com'; // Email Anda
        $mail->Password   = 'ejit psog kjzn yfhf';      // App Password Anda
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Pengirim dan Penerima
        $mail->setFrom('no-reply@lkp-pradata.com', 'Admin LKP Pradata');
        $mail->addAddress($penerima_email, $penerima_nama);

        // Konten Email
        $mail->isHTML(true);
        $mail->Subject = $subjek;
        $mail->Body    = $isi_pesan;

        $mail->send();
        return true; // Mengembalikan nilai true jika email berhasil dikirim

    } catch (Exception $e) {
        // Jika gagal, bisa dicatat ke file log error untuk diperiksa nanti
        error_log("Gagal kirim email ke {$penerima_email}. Error: {$mail->ErrorInfo}");
        return false; // Mengembalikan nilai false jika gagal
    }
}
    // ... (setelah penutup `}` dari fungsi kirimEmailNotifikasi) ...

/**
 * Fungsi terpusat untuk mengirim notifikasi via WhatsApp menggunakan API Gateway.
 *
 * @param string $target Nomor tujuan, format 08... atau 628...
 * @param string $pesan  Isi pesan teks yang akan dikirim.
 * @return string        Mengembalikan response dari API Gateway.
 */
function kirimWhatsAppNotifikasi($target, $pesan) {
    // ==========================================================
    // GANTI DENGAN API TOKEN DARI PENYEDIA LAYANAN ANDA
    // ==========================================================
    $token = "Y5DtwHNyT7sCyfTcBP8M"; 

    // Memformat nomor HP ke standar internasional (62xxx)
    $formattedTarget = $target;
    if (substr($formattedTarget, 0, 1) == '0') {
        $formattedTarget = '62' . substr($formattedTarget, 1);
    } elseif (substr($formattedTarget, 0, 2) != '62') {
        $formattedTarget = '62' . $formattedTarget;
    }
    // Menghapus karakter selain angka
    $formattedTarget = preg_replace('/[^0-9]/', '', $formattedTarget);

    $curl = curl_init();

    // Menggunakan contoh endpoint dari Fonnte. Sesuaikan jika Anda menggunakan layanan lain.
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array(
        'target' => $formattedTarget,
        'message' => $pesan
      ),
      CURLOPT_HTTPHEADER => array(
        "Authorization: $token"
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    // Anda bisa mencatat response ini ke log untuk debugging
    // error_log("WhatsApp Response: " . $response);
    
    return $response; 

}
?>