<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';

$id = $_GET['id'] ?? '';
$confirm = $_GET['confirm'] ?? '';

// Validasi input
if (empty($id) || !is_numeric($id)) {
    $_SESSION['error'] = 'ID pendaftar tidak valid';
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
    // Ambil data pendaftar untuk validasi dan log
    $query = "SELECT * FROM pendaftar WHERE id_pendaftar = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        $_SESSION['error'] = 'Data pendaftar tidak ditemukan';
        header('Location: index.php');
        exit();
    }
    
    $pendaftar = mysqli_fetch_assoc($result);
    
    // Validasi: tidak bisa hapus jika sudah diterima (sudah jadi siswa)
    if ($pendaftar['status_pendaftaran'] === 'Diterima') {
        $_SESSION['error'] = 'Tidak dapat menghapus pendaftar yang sudah diterima sebagai siswa';
        header('Location: index.php');
        exit();
    }
    
    // Mulai transaksi
    mysqli_autocommit($conn, false);
    
    // Hapus file-file yang terkait
    $fileFields = ['pas_foto', 'ktp', 'kk', 'ijazah'];
    $folderMap = [
        'pas_foto' => 'pas_foto_pendaftar',
        'ktp' => 'ktp_pendaftar', 
        'kk' => 'kk_pendaftar',
        'ijazah' => 'ijazah_pendaftar'
    ];
    
    $deletedFiles = [];
    foreach ($fileFields as $field) {
        if (!empty($pendaftar[$field])) {
            $folder = $folderMap[$field];
            $filePath = "../../../uploads/{$folder}/{$pendaftar[$field]}";
            
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedFiles[] = $pendaftar[$field];
                }
            }
        }
    }
    
    // Hapus data dari database
    $deleteQuery = "DELETE FROM pendaftar WHERE id_pendaftar = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    mysqli_stmt_bind_param($deleteStmt, "i", $id);
    
    if (mysqli_stmt_execute($deleteStmt)) {
        // Commit transaksi
        mysqli_commit($conn);
        
        // Log aktivitas penghapusan
        $logMessage = "Hapus pendaftar: {$pendaftar['nama_pendaftar']} (NIK: {$pendaftar['nik']}) - Status: {$pendaftar['status_pendaftaran']}";
        if (!empty($deletedFiles)) {
            $logMessage .= " - Files deleted: " . implode(', ', $deletedFiles);
        }
        logDeleteActivity($logMessage);
        
        $_SESSION['success'] = "Data pendaftar '{$pendaftar['nama_pendaftar']}' berhasil dihapus";
        
    } else {
        // Rollback jika gagal
        mysqli_rollback($conn);
        $_SESSION['error'] = 'Gagal menghapus data pendaftar: ' . mysqli_error($conn);
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
    $logFile = '../../../uploads/delete_log.txt';
    $adminName = $_SESSION['nama_admin'] ?? 'Unknown Admin';
    $timestamp = date('Y-m-d H:i:s');
    
    $logEntry = "[{$timestamp}] Admin: {$adminName} | {$message}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>