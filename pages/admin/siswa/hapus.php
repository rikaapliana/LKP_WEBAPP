<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID siswa tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_siswa = (int)$_GET['id'];

// Validasi ID siswa harus berupa angka positif
if ($id_siswa <= 0) {
    $_SESSION['error'] = "ID siswa tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data siswa untuk mendapatkan nama dan file yang akan dihapus
$siswaQuery = "SELECT * FROM siswa WHERE id_siswa = ?";
$stmt = mysqli_prepare($conn, $siswaQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_siswa);
mysqli_stmt_execute($stmt);
$siswaResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($siswaResult) == 0) {
    $_SESSION['error'] = "Data siswa tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$siswa = mysqli_fetch_assoc($siswaResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah siswa masih terkait dengan data lain
$checkRelations = [];

// Cek apakah siswa memiliki data nilai/ujian (jika ada tabel tersebut)
// $nilaiQuery = "SELECT COUNT(*) as total FROM nilai WHERE id_siswa = ?";
// $checkRelations['nilai'] = $nilaiQuery;

// Cek apakah siswa memiliki data absensi (jika ada tabel tersebut)
// $absensiQuery = "SELECT COUNT(*) as total FROM absensi WHERE id_siswa = ?";
// $checkRelations['absensi'] = $absensiQuery;

// Uncomment jika Anda memiliki tabel relasi lain yang perlu dicek

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // Hapus file-file terkait jika ada
    $filesToDelete = [
        [
            'path' => '../../../uploads/pas_foto/',
            'file' => $siswa['pas_foto'],
            'description' => 'foto profil'
        ],
        [
            'path' => '../../../uploads/ktp/',
            'file' => $siswa['ktp'],
            'description' => 'KTP'
        ],
        [
            'path' => '../../../uploads/kk/',
            'file' => $siswa['kk'],
            'description' => 'Kartu Keluarga'
        ],
        [
            'path' => '../../../uploads/ijazah/',
            'file' => $siswa['ijazah'],
            'description' => 'Ijazah'
        ]
    ];

    $deletedFiles = [];
    $failedFiles = [];

    foreach ($filesToDelete as $fileInfo) {
        if (!empty($fileInfo['file'])) {
            $fullPath = $fileInfo['path'] . $fileInfo['file'];
            
            if (file_exists($fullPath)) {
                if (unlink($fullPath)) {
                    $deletedFiles[] = $fileInfo['description'];
                } else {
                    $failedFiles[] = $fileInfo['description'];
                }
            }
        }
    }

    // Jika ada file yang gagal dihapus, log peringatan tapi lanjutkan proses
    if (!empty($failedFiles)) {
        error_log("Gagal menghapus file untuk siswa ID {$id_siswa}: " . implode(', ', $failedFiles));
    }

    // Hapus data siswa dari database menggunakan prepared statement
    $deleteQuery = "DELETE FROM siswa WHERE id_siswa = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "i", $id_siswa);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data siswa: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $successMessage = "Data siswa <strong>" . htmlspecialchars($siswa['nama']) . "</strong> (NIK: " . htmlspecialchars($siswa['nik']) . ") berhasil dihapus!";
    
    if (!empty($deletedFiles)) {
        $successMessage .= "<br><small class='text-muted'>File yang dihapus: " . implode(', ', $deletedFiles) . "</small>";
    }
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus
    error_log("Data siswa dihapus - ID: {$id_siswa}, Nama: {$siswa['nama']}, NIK: {$siswa['nik']}");

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    
    // Log error
    error_log("Error menghapus siswa ID {$id_siswa}: " . $e->getMessage());
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>