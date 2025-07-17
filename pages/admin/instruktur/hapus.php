<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID instruktur tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_instruktur = (int)$_GET['id'];

// Validasi ID instruktur harus berupa angka positif
if ($id_instruktur <= 0) {
    $_SESSION['error'] = "ID instruktur tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data instruktur untuk mendapatkan nama dan file yang akan dihapus
$instrukturQuery = "SELECT * FROM instruktur WHERE id_instruktur = ?";
$stmt = mysqli_prepare($conn, $instrukturQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_instruktur);
mysqli_stmt_execute($stmt);
$instrukturResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($instrukturResult) == 0) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$instruktur = mysqli_fetch_assoc($instrukturResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah instruktur masih terkait dengan data lain
$checkRelations = [];

// Cek apakah instruktur masih mengampu kelas
$kelasQuery = "SELECT COUNT(*) as total FROM kelas WHERE id_instruktur = ?";
$kelasStmt = mysqli_prepare($conn, $kelasQuery);
mysqli_stmt_bind_param($kelasStmt, "i", $id_instruktur);
mysqli_stmt_execute($kelasStmt);
$kelasResult = mysqli_stmt_get_result($kelasStmt);
$kelasData = mysqli_fetch_assoc($kelasResult);
mysqli_stmt_close($kelasStmt);

// Cek apakah instruktur memiliki data jadwal
$jadwalQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_instruktur = ?";
$jadwalStmt = mysqli_prepare($conn, $jadwalQuery);
if ($jadwalStmt) {
    mysqli_stmt_bind_param($jadwalStmt, "i", $id_instruktur);
    mysqli_stmt_execute($jadwalStmt);
    $jadwalResult = mysqli_stmt_get_result($jadwalStmt);
    $jadwalData = mysqli_fetch_assoc($jadwalResult);
    mysqli_stmt_close($jadwalStmt);
} else {
    $jadwalData = ['total' => 0];
}

// Cek apakah instruktur memiliki data materi
$materiQuery = "SELECT COUNT(*) as total FROM materi WHERE id_instruktur = ?";
$materiStmt = mysqli_prepare($conn, $materiQuery);
if ($materiStmt) {
    mysqli_stmt_bind_param($materiStmt, "i", $id_instruktur);
    mysqli_stmt_execute($materiStmt);
    $materiResult = mysqli_stmt_get_result($materiStmt);
    $materiData = mysqli_fetch_assoc($materiResult);
    mysqli_stmt_close($materiStmt);
} else {
    $materiData = ['total' => 0];
}

// Cek apakah instruktur memiliki data absensi
$absensiQuery = "SELECT COUNT(*) as total FROM absensi_instruktur WHERE id_instruktur = ?";
$absensiStmt = mysqli_prepare($conn, $absensiQuery);
if ($absensiStmt) {
    mysqli_stmt_bind_param($absensiStmt, "i", $id_instruktur);
    mysqli_stmt_execute($absensiStmt);
    $absensiResult = mysqli_stmt_get_result($absensiStmt);
    $absensiData = mysqli_fetch_assoc($absensiResult);
    mysqli_stmt_close($absensiStmt);
} else {
    $absensiData = ['total' => 0];
}

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // Array untuk menyimpan informasi kelas yang akan direset
    $resetKelasInfo = [];
    
    // Reset kelas yang diampu oleh instruktur ini
    if ($kelasData['total'] > 0) {
        // Ambil nama kelas yang akan direset
        $getKelasQuery = "SELECT nama_kelas FROM kelas WHERE id_instruktur = ?";
        $getKelasStmt = mysqli_prepare($conn, $getKelasQuery);
        mysqli_stmt_bind_param($getKelasStmt, "i", $id_instruktur);
        mysqli_stmt_execute($getKelasStmt);
        $getKelasResult = mysqli_stmt_get_result($getKelasStmt);
        
        while ($kelasRow = mysqli_fetch_assoc($getKelasResult)) {
            $resetKelasInfo[] = $kelasRow['nama_kelas'];
        }
        mysqli_stmt_close($getKelasStmt);
        
        // Reset id_instruktur di tabel kelas
        $resetKelasQuery = "UPDATE kelas SET id_instruktur = NULL WHERE id_instruktur = ?";
        $resetKelasStmt = mysqli_prepare($conn, $resetKelasQuery);
        mysqli_stmt_bind_param($resetKelasStmt, "i", $id_instruktur);
        
        if (!mysqli_stmt_execute($resetKelasStmt)) {
            throw new Exception("Gagal mereset kelas yang diampu: " . mysqli_stmt_error($resetKelasStmt));
        }
        mysqli_stmt_close($resetKelasStmt);
    }
    
    // Hapus data terkait jika ada (optional - bisa di-comment jika ingin keep data)
    /*
    // Hapus data jadwal
    if ($jadwalData['total'] > 0) {
        $deleteJadwalQuery = "DELETE FROM jadwal WHERE id_instruktur = ?";
        $deleteJadwalStmt = mysqli_prepare($conn, $deleteJadwalQuery);
        mysqli_stmt_bind_param($deleteJadwalStmt, "i", $id_instruktur);
        mysqli_stmt_execute($deleteJadwalStmt);
        mysqli_stmt_close($deleteJadwalStmt);
    }
    
    // Hapus data materi
    if ($materiData['total'] > 0) {
        $deleteMateriQuery = "DELETE FROM materi WHERE id_instruktur = ?";
        $deleteMateriStmt = mysqli_prepare($conn, $deleteMateriQuery);
        mysqli_stmt_bind_param($deleteMateriStmt, "i", $id_instruktur);
        mysqli_stmt_execute($deleteMateriStmt);
        mysqli_stmt_close($deleteMateriStmt);
    }
    
    // Hapus data absensi
    if ($absensiData['total'] > 0) {
        $deleteAbsensiQuery = "DELETE FROM absensi_instruktur WHERE id_instruktur = ?";
        $deleteAbsensiStmt = mysqli_prepare($conn, $deleteAbsensiQuery);
        mysqli_stmt_bind_param($deleteAbsensiStmt, "i", $id_instruktur);
        mysqli_stmt_execute($deleteAbsensiStmt);
        mysqli_stmt_close($deleteAbsensiStmt);
    }
    */

    // Hapus file pas foto jika ada
    $deletedFiles = [];
    $failedFiles = [];

    if (!empty($instruktur['pas_foto'])) {
        $fotoPath = '../../../uploads/pas_foto/' . $instruktur['pas_foto'];
        
        if (file_exists($fotoPath)) {
            if (unlink($fotoPath)) {
                $deletedFiles[] = 'foto profil';
            } else {
                $failedFiles[] = 'foto profil';
            }
        }
    }

    // Jika ada file yang gagal dihapus, log peringatan tapi lanjutkan proses
    if (!empty($failedFiles)) {
        error_log("Gagal menghapus file untuk instruktur ID {$id_instruktur}: " . implode(', ', $failedFiles));
    }

    // Hapus data instruktur dari database menggunakan prepared statement
    $deleteQuery = "DELETE FROM instruktur WHERE id_instruktur = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "i", $id_instruktur);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data instruktur: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $successMessage = "Data instruktur <strong>" . htmlspecialchars($instruktur['nama']) . "</strong>";
    if (!empty($instruktur['nik'])) {
        $successMessage .= " (NIK: " . htmlspecialchars($instruktur['nik']) . ")";
    }
    $successMessage .= " berhasil dihapus!";
    
    // Tambahkan informasi kelas yang direset
    if (!empty($resetKelasInfo)) {
        $successMessage .= "<br><small class='text-info'>Kelas yang direset: " . implode(', ', $resetKelasInfo) . "</small>";
    }
    
    // Tambahkan informasi file yang dihapus
    if (!empty($deletedFiles)) {
        $successMessage .= "<br><small class='text-muted'>File yang dihapus: " . implode(', ', $deletedFiles) . "</small>";
    }
    
    // Tambahkan informasi data terkait
    $relatedData = [];
    if ($jadwalData['total'] > 0) $relatedData[] = $jadwalData['total'] . " jadwal";
    if ($materiData['total'] > 0) $relatedData[] = $materiData['total'] . " materi";
    if ($absensiData['total'] > 0) $relatedData[] = $absensiData['total'] . " absensi";
    
    if (!empty($relatedData)) {
        $successMessage .= "<br><small class='text-warning'>⚠️ Data terkait yang masih ada: " . implode(', ', $relatedData) . " (tidak dihapus)</small>";
    }
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus
    $logMessage = "Data instruktur dihapus - ID: {$id_instruktur}, Nama: {$instruktur['nama']}";
    if (!empty($instruktur['nik'])) {
        $logMessage .= ", NIK: {$instruktur['nik']}";
    }
    if (!empty($resetKelasInfo)) {
        $logMessage .= ", Kelas direset: " . implode(', ', $resetKelasInfo);
    }
    error_log($logMessage);

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    
    // Log error
    error_log("Error menghapus instruktur ID {$id_instruktur}: " . $e->getMessage());
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>