<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID materi tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_materi = (int)$_GET['id'];

// Validasi ID materi harus berupa angka positif
if ($id_materi <= 0) {
    $_SESSION['error'] = "ID materi tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data materi untuk mendapatkan judul dan file yang akan dihapus
$materiQuery = "SELECT m.*, k.nama_kelas, i.nama as nama_instruktur 
                FROM materi m 
                LEFT JOIN kelas k ON m.id_kelas = k.id_kelas
                LEFT JOIN instruktur i ON m.id_instruktur = i.id_instruktur
                WHERE m.id_materi = ?";
$stmt = mysqli_prepare($conn, $materiQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_materi);
mysqli_stmt_execute($stmt);
$materiResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($materiResult) == 0) {
    $_SESSION['error'] = "Data materi tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$materi = mysqli_fetch_assoc($materiResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah materi masih terkait dengan data lain
$checkRelations = [];

// Cek apakah materi memiliki data terkait lainnya (jika ada tabel tersebut)
// Contoh: evaluasi materi, progress siswa, dll.
// $evaluasiQuery = "SELECT COUNT(*) as total FROM evaluasi_materi WHERE id_materi = ?";
// $checkRelations['evaluasi'] = $evaluasiQuery;

// $progressQuery = "SELECT COUNT(*) as total FROM progress_siswa WHERE id_materi = ?";
// $checkRelations['progress'] = $progressQuery;

// Uncomment dan sesuaikan jika Anda memiliki tabel relasi lain yang perlu dicek

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // Hapus file materi jika ada
    $filesToDelete = [];
    $deletedFiles = [];
    $failedFiles = [];

    if (!empty($materi['file_materi'])) {
        $filesToDelete[] = [
            'path' => '../../../uploads/materi/',
            'file' => $materi['file_materi'],
            'description' => 'file materi'
        ];
    }

    // Proses penghapusan file
    foreach ($filesToDelete as $fileInfo) {
        $fullPath = $fileInfo['path'] . $fileInfo['file'];
        
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                $deletedFiles[] = $fileInfo['description'];
            } else {
                $failedFiles[] = $fileInfo['description'];
            }
        }
    }

    // Jika ada file yang gagal dihapus, log peringatan tapi lanjutkan proses
    if (!empty($failedFiles)) {
        error_log("Gagal menghapus file untuk materi ID {$id_materi}: " . implode(', ', $failedFiles));
    }

    // Hapus data materi dari database menggunakan prepared statement
    $deleteQuery = "DELETE FROM materi WHERE id_materi = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "i", $id_materi);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data materi: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $successMessage = "Materi <strong>" . htmlspecialchars($materi['judul']) . "</strong> (ID: " . $materi['id_materi'] . ") berhasil dihapus!";
    
    // Tambahkan info kelas jika ada
    if (!empty($materi['nama_kelas'])) {
        $successMessage .= "<br><small class='text-muted'>Kelas: " . htmlspecialchars($materi['nama_kelas']) . "</small>";
    }
    
    // Tambahkan info instruktur jika ada
    if (!empty($materi['nama_instruktur'])) {
        $successMessage .= "<br><small class='text-muted'>Instruktur: " . htmlspecialchars($materi['nama_instruktur']) . "</small>";
    }
    
    // Tambahkan info file yang dihapus
    if (!empty($deletedFiles)) {
        $successMessage .= "<br><small class='text-muted'>File yang dihapus: " . implode(', ', $deletedFiles) . "</small>";
    }
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus
    error_log("Data materi dihapus - ID: {$id_materi}, Judul: {$materi['judul']}, Kelas: " . ($materi['nama_kelas'] ?? 'Umum') . ", Instruktur: " . ($materi['nama_instruktur'] ?? 'Tidak ada'));

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    
    // Log error
    error_log("Error menghapus materi ID {$id_materi}: " . $e->getMessage());
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>