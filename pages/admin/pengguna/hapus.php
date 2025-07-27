<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID pengguna tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_user = (int)$_GET['id'];

// Validasi ID user harus berupa angka positif
if ($id_user <= 0) {
    $_SESSION['error'] = "ID pengguna tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data user untuk mendapatkan informasi yang akan dihapus
$userQuery = "SELECT u.id_user, u.username, u.role, u.created_at,
              CASE 
                WHEN u.role = 'admin' THEN a.nama
                WHEN u.role = 'instruktur' THEN i.nama
                WHEN u.role = 'siswa' THEN s.nama
                ELSE NULL
              END as nama_lengkap,
              CASE 
                WHEN u.role = 'admin' THEN a.email
                WHEN u.role = 'instruktur' THEN i.email
                WHEN u.role = 'siswa' THEN s.email
                ELSE NULL
              END as email,
              CASE 
                WHEN u.role = 'admin' THEN a.id_admin
                WHEN u.role = 'instruktur' THEN i.id_instruktur
                WHEN u.role = 'siswa' THEN s.id_siswa
                ELSE NULL
              END as role_id
              FROM user u 
              LEFT JOIN admin a ON u.id_user = a.id_user AND u.role = 'admin'
              LEFT JOIN instruktur i ON u.id_user = i.id_user AND u.role = 'instruktur'
              LEFT JOIN siswa s ON u.id_user = s.id_user AND u.role = 'siswa'
              WHERE u.id_user = ?";
$stmt = mysqli_prepare($conn, $userQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$userResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($userResult) == 0) {
    $_SESSION['error'] = "Data pengguna tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$user = mysqli_fetch_assoc($userResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah user terkait dengan data sensitif
$checkRelations = [];

// Cek apakah user admin/instruktur/siswa masih terkait dengan data lain
if ($user['role'] == 'instruktur') {
    // Cek apakah instruktur masih mengajar kelas
    $kelasQuery = "SELECT COUNT(*) as total FROM kelas WHERE id_instruktur = ?";
    $kelasStmt = mysqli_prepare($conn, $kelasQuery);
    if ($kelasStmt) {
        mysqli_stmt_bind_param($kelasStmt, "i", $user['role_id']);
        mysqli_stmt_execute($kelasStmt);
        $kelasResult = mysqli_stmt_get_result($kelasStmt);
        $kelasData = mysqli_fetch_assoc($kelasResult);
        mysqli_stmt_close($kelasStmt);
        
        if ($kelasData['total'] > 0) {
            $checkRelations[] = $kelasData['total'] . " kelas yang diampu";
        }
    }
    
    // Cek apakah instruktur punya jadwal mengajar
    $jadwalQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_instruktur = ?";
    $jadwalStmt = mysqli_prepare($conn, $jadwalQuery);
    if ($jadwalStmt) {
        mysqli_stmt_bind_param($jadwalStmt, "i", $user['role_id']);
        mysqli_stmt_execute($jadwalStmt);
        $jadwalResult = mysqli_stmt_get_result($jadwalStmt);
        $jadwalData = mysqli_fetch_assoc($jadwalResult);
        mysqli_stmt_close($jadwalStmt);
        
        if ($jadwalData['total'] > 0) {
            $checkRelations[] = $jadwalData['total'] . " jadwal mengajar";
        }
    }
    
    // Cek apakah instruktur punya data materi
    $materiQuery = "SELECT COUNT(*) as total FROM materi WHERE id_instruktur = ?";
    $materiStmt = mysqli_prepare($conn, $materiQuery);
    if ($materiStmt) {
        mysqli_stmt_bind_param($materiStmt, "i", $user['role_id']);
        mysqli_stmt_execute($materiStmt);
        $materiResult = mysqli_stmt_get_result($materiStmt);
        $materiData = mysqli_fetch_assoc($materiResult);
        mysqli_stmt_close($materiStmt);
        
        if ($materiData['total'] > 0) {
            $checkRelations[] = $materiData['total'] . " materi pembelajaran";
        }
    }
}

if ($user['role'] == 'siswa') {
    // Cek apakah siswa punya data nilai
    $nilaiQuery = "SELECT COUNT(*) as total FROM nilai WHERE id_siswa = ?";
    $nilaiStmt = mysqli_prepare($conn, $nilaiQuery);
    if ($nilaiStmt) {
        mysqli_stmt_bind_param($nilaiStmt, "i", $user['role_id']);
        mysqli_stmt_execute($nilaiStmt);
        $nilaiResult = mysqli_stmt_get_result($nilaiStmt);
        $nilaiData = mysqli_fetch_assoc($nilaiResult);
        mysqli_stmt_close($nilaiStmt);
        
        if ($nilaiData['total'] > 0) {
            $checkRelations[] = $nilaiData['total'] . " data nilai";
        }
    }
    
    // Cek apakah siswa punya data absensi
    $absensiQuery = "SELECT COUNT(*) as total FROM absensi_siswa WHERE id_siswa = ?";
    $absensiStmt = mysqli_prepare($conn, $absensiQuery);
    if ($absensiStmt) {
        mysqli_stmt_bind_param($absensiStmt, "i", $user['role_id']);
        mysqli_stmt_execute($absensiStmt);
        $absensiResult = mysqli_stmt_get_result($absensiStmt);
        $absensiData = mysqli_fetch_assoc($absensiResult);
        mysqli_stmt_close($absensiStmt);
        
        if ($absensiData['total'] > 0) {
            $checkRelations[] = $absensiData['total'] . " data absensi";
        }
    }
}

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // Hapus data user dari database menggunakan prepared statement
    $deleteUserQuery = "DELETE FROM user WHERE id_user = ?";
    $deleteUserStmt = mysqli_prepare($conn, $deleteUserQuery);
    
    if (!$deleteUserStmt) {
        throw new Exception("Gagal mempersiapkan query hapus user: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteUserStmt, "i", $id_user);
    
    if (!mysqli_stmt_execute($deleteUserStmt)) {
        throw new Exception("Gagal menghapus data user: " . mysqli_stmt_error($deleteUserStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteUserStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteUserStmt);
    
    // Reset id_user di tabel role terkait (set NULL)
    if ($user['role'] == 'admin' && $user['role_id']) {
        $resetAdminQuery = "UPDATE admin SET id_user = NULL WHERE id_admin = ?";
        $resetAdminStmt = mysqli_prepare($conn, $resetAdminQuery);
        mysqli_stmt_bind_param($resetAdminStmt, "i", $user['role_id']);
        mysqli_stmt_execute($resetAdminStmt);
        mysqli_stmt_close($resetAdminStmt);
    } elseif ($user['role'] == 'instruktur' && $user['role_id']) {
        $resetInstrukturQuery = "UPDATE instruktur SET id_user = NULL WHERE id_instruktur = ?";
        $resetInstrukturStmt = mysqli_prepare($conn, $resetInstrukturQuery);
        mysqli_stmt_bind_param($resetInstrukturStmt, "i", $user['role_id']);
        mysqli_stmt_execute($resetInstrukturStmt);
        mysqli_stmt_close($resetInstrukturStmt);
    } elseif ($user['role'] == 'siswa' && $user['role_id']) {
        $resetSiswaQuery = "UPDATE siswa SET id_user = NULL WHERE id_siswa = ?";
        $resetSiswaStmt = mysqli_prepare($conn, $resetSiswaQuery);
        mysqli_stmt_bind_param($resetSiswaStmt, "i", $user['role_id']);
        mysqli_stmt_execute($resetSiswaStmt);
        mysqli_stmt_close($resetSiswaStmt);
    }
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $successMessage = "Akun pengguna berhasil dihapus!<br>" .
                      "<strong>Username:</strong> " . htmlspecialchars($user['username']) . "<br>" .
                      "<strong>Nama:</strong> " . htmlspecialchars($user['nama_lengkap'] ?? 'Tidak diatur') . "<br>" .
                      "<strong>Role:</strong> " . ucfirst($user['role']);
    
    // Tambahkan informasi data yang masih tersimpan
    if (!empty($checkRelations)) {
        $successMessage .= "<br><small class='text-info'>Data " . $user['role'] . " masih tersimpan: " . 
                          implode(', ', $checkRelations) . "</small>";
    } else {
        $successMessage .= "<br><small class='text-success'>Semua data terkait sudah bersih</small>";
    }
    
    $successMessage .= "<br><small class='text-muted'>Data " . $user['role'] . " dapat dibuatkan akun baru kapan saja</small>";
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus
    $logMessage = "Akun user dihapus - ID: {$id_user}, Username: {$user['username']}, Role: {$user['role']}";
    if (!empty($user['nama_lengkap'])) {
        $logMessage .= ", Nama: {$user['nama_lengkap']}";
    }
    if (!empty($checkRelations)) {
        $logMessage .= ", Data terkait: " . implode(', ', $checkRelations);
    }
    error_log($logMessage);

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus akun pengguna: " . $e->getMessage();
    
    // Log error
    error_log("Error menghapus user ID {$id_user}: " . $e->getMessage());
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>