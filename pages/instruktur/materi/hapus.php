<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth(); // Hanya instruktur yang bisa akses

include '../../../includes/db.php';

// Ambil ID instruktur yang sedang login
$stmt = $conn->prepare("SELECT id_instruktur, nama FROM instruktur WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

if (!$instrukturData) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

$id_instruktur = $instrukturData['id_instruktur'];
$nama_instruktur = $instrukturData['nama'];

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

// Ambil data materi - hanya yang milik kelas yang diampu instruktur ini
$materiQuery = "SELECT m.*, k.nama_kelas 
                FROM materi m 
                LEFT JOIN kelas k ON m.id_kelas = k.id_kelas
                WHERE m.id_materi = ? AND k.id_instruktur = ?";
$stmt = mysqli_prepare($conn, $materiQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "ii", $id_materi, $id_instruktur);
mysqli_stmt_execute($stmt);
$materiResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($materiResult) == 0) {
    $_SESSION['error'] = "Data materi tidak ditemukan atau bukan milik kelas yang Anda ampu!";
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
        error_log("Gagal menghapus file untuk materi ID {$id_materi} oleh instruktur {$nama_instruktur}: " . implode(', ', $failedFiles));
    }

    // Hapus data materi dari database - dengan validasi instruktur
    $deleteQuery = "DELETE m FROM materi m 
                    JOIN kelas k ON m.id_kelas = k.id_kelas 
                    WHERE m.id_materi = ? AND k.id_instruktur = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "ii", $id_materi, $id_instruktur);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data materi: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada atau bukan milik kelas yang Anda ampu.");
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
    
    // Tambahkan info instruktur
    $successMessage .= "<br><small class='text-muted'>Instruktur: " . htmlspecialchars($nama_instruktur) . "</small>";
    
    // Tambahkan info file yang dihapus
    if (!empty($deletedFiles)) {
        $successMessage .= "<br><small class='text-muted'>File yang dihapus: " . implode(', ', $deletedFiles) . "</small>";
    }
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus dengan info instruktur
    error_log("Data materi dihapus oleh instruktur - ID Materi: {$id_materi}, Judul: {$materi['judul']}, Kelas: " . ($materi['nama_kelas'] ?? 'Umum') . ", Instruktur: {$nama_instruktur} (ID: {$id_instruktur})");

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    
    // Log error dengan info instruktur
    error_log("Error menghapus materi ID {$id_materi} oleh instruktur {$nama_instruktur} (ID: {$id_instruktur}): " . $e->getMessage());
}

// Redirect kembali ke halaman index dengan filter kelas jika ada
$redirect_url = "index.php";
if (!empty($materi['id_kelas'])) {
    $redirect_url .= "?kelas=" . $materi['id_kelas'];
}
header("Location: " . $redirect_url);
exit;
?>