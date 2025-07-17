<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';

$id = $_GET['id'] ?? '';
$confirm = $_GET['confirm'] ?? '';

// Validasi input
if (empty($id) || !is_numeric($id)) {
    $_SESSION['error'] = 'ID gelombang tidak valid';
    header('Location: index.php');
    exit();
}

// Jika belum konfirmasi, redirect kembali
if ($confirm !== 'delete') {
    $_SESSION['error'] = 'Konfirmasi hapus diperlukan';
    header('Location: index.php');
    exit();
}

try {
    // Ambil data gelombang untuk validasi dan log
    $query = "SELECT * FROM gelombang WHERE id_gelombang = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        $_SESSION['error'] = 'Data gelombang tidak ditemukan';
        header('Location: index.php');
        exit();
    }
    
    $gelombang = mysqli_fetch_assoc($result);
    
    // Cek apakah gelombang sedang digunakan
    $cekKelas = mysqli_query($conn, "SELECT COUNT(*) as total FROM kelas WHERE id_gelombang = $id");
    $jumlahKelas = mysqli_fetch_assoc($cekKelas)['total'];
    
    $cekPengaturan = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengaturan_pendaftaran WHERE id_gelombang = $id");
    $jumlahPengaturan = mysqli_fetch_assoc($cekPengaturan)['total'];
    
    $cekEvaluasi = mysqli_query($conn, "SELECT COUNT(*) as total FROM periode_evaluasi WHERE id_gelombang = $id");
    $jumlahEvaluasi = mysqli_fetch_assoc($cekEvaluasi)['total'];
    
    // Validasi: tidak bisa hapus jika masih digunakan
    if ($jumlahKelas > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus gelombang '{$gelombang['nama_gelombang']}' karena masih digunakan oleh $jumlahKelas kelas";
        header('Location: index.php');
        exit();
    }
    
    if ($jumlahPengaturan > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus gelombang '{$gelombang['nama_gelombang']}' karena sudah memiliki pengaturan pendaftaran";
        header('Location: index.php');
        exit();
    }
    
    if ($jumlahEvaluasi > 0) {
        $_SESSION['error'] = "Tidak dapat menghapus gelombang '{$gelombang['nama_gelombang']}' karena sudah memiliki periode evaluasi";
        header('Location: index.php');
        exit();
    }
    
    // Validasi: tidak bisa hapus jika status masih aktif atau dibuka
    if ($gelombang['status'] === 'aktif' || $gelombang['status'] === 'dibuka') {
        $_SESSION['error'] = "Tidak dapat menghapus gelombang dengan status '{$gelombang['status']}'. Ubah status menjadi 'selesai' terlebih dahulu";
        header('Location: index.php');
        exit();
    }
    
    // Mulai transaksi
    mysqli_autocommit($conn, false);
    
    // Hapus data dari database
    $deleteQuery = "DELETE FROM gelombang WHERE id_gelombang = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    mysqli_stmt_bind_param($deleteStmt, "i", $id);
    
    if (mysqli_stmt_execute($deleteStmt)) {
        // Commit transaksi
        mysqli_commit($conn);
        
        // Log aktivitas penghapusan
        $logMessage = "Hapus gelombang: {$gelombang['nama_gelombang']} (Tahun: {$gelombang['tahun']}, Gelombang ke-{$gelombang['gelombang_ke']}) - Status: {$gelombang['status']}";
        logDeleteActivity($logMessage);
        
        $_SESSION['success'] = "Gelombang '{$gelombang['nama_gelombang']}' berhasil dihapus";
        
    } else {
        // Rollback jika gagal
        mysqli_rollback($conn);
        $_SESSION['error'] = 'Gagal menghapus gelombang: ' . mysqli_error($conn);
    }
    
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
}

// Kembalikan autocommit
mysqli_autocommit($conn, true);

header('Location: index.php');
exit();

/**
 * Log aktivitas penghapusan
 */
function logDeleteActivity($message) {
    $logFile = '../../../../uploads/delete_log.txt';
    $adminName = $_SESSION['nama_admin'] ?? 'Unknown Admin';
    $timestamp = date('Y-m-d H:i:s');
    
    $logEntry = "[{$timestamp}] Admin: {$adminName} | {$message}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>