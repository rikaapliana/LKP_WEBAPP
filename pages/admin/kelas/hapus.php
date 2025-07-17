<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID kelas tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_kelas = (int)$_GET['id'];

// Validasi ID kelas harus berupa angka positif
if ($id_kelas <= 0) {
    $_SESSION['error'] = "ID kelas tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data kelas untuk mendapatkan nama dan informasi yang akan dihapus
$kelasQuery = "SELECT k.*, g.nama_gelombang, g.tahun, i.nama as nama_instruktur
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
               WHERE k.id_kelas = ?";
$stmt = mysqli_prepare($conn, $kelasQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_kelas);
mysqli_stmt_execute($stmt);
$kelasResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($kelasResult) == 0) {
    $_SESSION['error'] = "Data kelas tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$kelas = mysqli_fetch_assoc($kelasResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah kelas masih terkait dengan data lain
$checkRelations = [];

// Cek apakah kelas masih memiliki siswa
$siswaQuery = "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = ?";
$siswaStmt = mysqli_prepare($conn, $siswaQuery);
mysqli_stmt_bind_param($siswaStmt, "i", $id_kelas);
mysqli_stmt_execute($siswaStmt);
$siswaResult = mysqli_stmt_get_result($siswaStmt);
$siswaData = mysqli_fetch_assoc($siswaResult);
mysqli_stmt_close($siswaStmt);

// Cek apakah kelas memiliki data jadwal
$jadwalQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_kelas = ?";
$jadwalStmt = mysqli_prepare($conn, $jadwalQuery);
if ($jadwalStmt) {
    mysqli_stmt_bind_param($jadwalStmt, "i", $id_kelas);
    mysqli_stmt_execute($jadwalStmt);
    $jadwalResult = mysqli_stmt_get_result($jadwalStmt);
    $jadwalData = mysqli_fetch_assoc($jadwalResult);
    mysqli_stmt_close($jadwalStmt);
} else {
    $jadwalData = ['total' => 0];
}

// Cek apakah kelas memiliki data materi
$materiQuery = "SELECT COUNT(*) as total FROM materi WHERE id_kelas = ?";
$materiStmt = mysqli_prepare($conn, $materiQuery);
if ($materiStmt) {
    mysqli_stmt_bind_param($materiStmt, "i", $id_kelas);
    mysqli_stmt_execute($materiStmt);
    $materiResult = mysqli_stmt_get_result($materiStmt);
    $materiData = mysqli_fetch_assoc($materiResult);
    mysqli_stmt_close($materiStmt);
} else {
    $materiData = ['total' => 0];
}

// Cek apakah kelas memiliki data nilai
$nilaiQuery = "SELECT COUNT(*) as total FROM nilai WHERE id_kelas = ?";
$nilaiStmt = mysqli_prepare($conn, $nilaiQuery);
if ($nilaiStmt) {
    mysqli_stmt_bind_param($nilaiStmt, "i", $id_kelas);
    mysqli_stmt_execute($nilaiStmt);
    $nilaiResult = mysqli_stmt_get_result($nilaiStmt);
    $nilaiData = mysqli_fetch_assoc($nilaiResult);
    mysqli_stmt_close($nilaiStmt);
} else {
    $nilaiData = ['total' => 0];
}

// Cek apakah ada absensi siswa terkait dengan jadwal kelas ini
$absensiQuery = "SELECT COUNT(*) as total FROM absensi_siswa a 
                 JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
                 WHERE j.id_kelas = ?";
$absensiStmt = mysqli_prepare($conn, $absensiQuery);
if ($absensiStmt) {
    mysqli_stmt_bind_param($absensiStmt, "i", $id_kelas);
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
    // Array untuk menyimpan informasi siswa yang akan direset
    $resetSiswaInfo = [];
    
    // Reset siswa yang terdaftar di kelas ini
    if ($siswaData['total'] > 0) {
        // Ambil nama siswa yang akan direset
        $getSiswaQuery = "SELECT nama FROM siswa WHERE id_kelas = ?";
        $getSiswaStmt = mysqli_prepare($conn, $getSiswaQuery);
        mysqli_stmt_bind_param($getSiswaStmt, "i", $id_kelas);
        mysqli_stmt_execute($getSiswaStmt);
        $getSiswaResult = mysqli_stmt_get_result($getSiswaStmt);
        
        while ($siswaRow = mysqli_fetch_assoc($getSiswaResult)) {
            $resetSiswaInfo[] = $siswaRow['nama'];
        }
        mysqli_stmt_close($getSiswaStmt);
        
        // Reset id_kelas di tabel siswa
        $resetSiswaQuery = "UPDATE siswa SET id_kelas = NULL WHERE id_kelas = ?";
        $resetSiswaStmt = mysqli_prepare($conn, $resetSiswaQuery);
        mysqli_stmt_bind_param($resetSiswaStmt, "i", $id_kelas);
        
        if (!mysqli_stmt_execute($resetSiswaStmt)) {
            throw new Exception("Gagal mereset siswa yang terdaftar: " . mysqli_stmt_error($resetSiswaStmt));
        }
        mysqli_stmt_close($resetSiswaStmt);
    }
    
    // Hapus data terkait jika diperlukan (optional - bisa di-comment jika ingin keep data)
    /*
    // Hapus data absensi siswa melalui jadwal
    if ($absensiData['total'] > 0) {
        $deleteAbsensiQuery = "DELETE a FROM absensi_siswa a 
                               JOIN jadwal j ON a.id_jadwal = j.id_jadwal 
                               WHERE j.id_kelas = ?";
        $deleteAbsensiStmt = mysqli_prepare($conn, $deleteAbsensiQuery);
        mysqli_stmt_bind_param($deleteAbsensiStmt, "i", $id_kelas);
        mysqli_stmt_execute($deleteAbsensiStmt);
        mysqli_stmt_close($deleteAbsensiStmt);
    }
    
    // Hapus data jadwal
    if ($jadwalData['total'] > 0) {
        $deleteJadwalQuery = "DELETE FROM jadwal WHERE id_kelas = ?";
        $deleteJadwalStmt = mysqli_prepare($conn, $deleteJadwalQuery);
        mysqli_stmt_bind_param($deleteJadwalStmt, "i", $id_kelas);
        mysqli_stmt_execute($deleteJadwalStmt);
        mysqli_stmt_close($deleteJadwalStmt);
    }
    
    // Hapus data materi
    if ($materiData['total'] > 0) {
        $deleteMateriQuery = "DELETE FROM materi WHERE id_kelas = ?";
        $deleteMateriStmt = mysqli_prepare($conn, $deleteMateriQuery);
        mysqli_stmt_bind_param($deleteMateriStmt, "i", $id_kelas);
        mysqli_stmt_execute($deleteMateriStmt);
        mysqli_stmt_close($deleteMateriStmt);
    }
    
    // Hapus data nilai
    if ($nilaiData['total'] > 0) {
        $deleteNilaiQuery = "DELETE FROM nilai WHERE id_kelas = ?";
        $deleteNilaiStmt = mysqli_prepare($conn, $deleteNilaiQuery);
        mysqli_stmt_bind_param($deleteNilaiStmt, "i", $id_kelas);
        mysqli_stmt_execute($deleteNilaiStmt);
        mysqli_stmt_close($deleteNilaiStmt);
    }
    */

    // Hapus data kelas dari database menggunakan prepared statement
    $deleteQuery = "DELETE FROM kelas WHERE id_kelas = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "i", $id_kelas);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data kelas: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $successMessage = "Data kelas <strong>" . htmlspecialchars($kelas['nama_kelas']) . "</strong>";
    if (!empty($kelas['nama_gelombang'])) {
        $successMessage .= " (Gelombang: " . htmlspecialchars($kelas['nama_gelombang']) . " " . $kelas['tahun'] . ")";
    }
    $successMessage .= " berhasil dihapus!";
    
    // Tambahkan informasi siswa yang direset
    if (!empty($resetSiswaInfo)) {
        $siswaCount = count($resetSiswaInfo);
        $successMessage .= "<br><small class='text-info'>{$siswaCount} siswa direset: " . 
                          (count($resetSiswaInfo) > 3 ? 
                              implode(', ', array_slice($resetSiswaInfo, 0, 3)) . " dan " . (count($resetSiswaInfo) - 3) . " lainnya" : 
                              implode(', ', $resetSiswaInfo)
                          ) . "</small>";
    }
    
    // Tambahkan informasi instruktur yang terdampak
    if (!empty($kelas['nama_instruktur'])) {
        $successMessage .= "<br><small class='text-muted'>Instruktur: " . htmlspecialchars($kelas['nama_instruktur']) . " (tidak terhapus)</small>";
    }
    
    // Tambahkan informasi data terkait yang masih ada
    $relatedData = [];
    if ($jadwalData['total'] > 0) $relatedData[] = $jadwalData['total'] . " jadwal";
    if ($materiData['total'] > 0) $relatedData[] = $materiData['total'] . " materi";
    if ($nilaiData['total'] > 0) $relatedData[] = $nilaiData['total'] . " nilai";
    if ($absensiData['total'] > 0) $relatedData[] = $absensiData['total'] . " absensi";
    
    if (!empty($relatedData)) {
        $successMessage .= "<br><small class='text-warning'>⚠️ Data terkait yang masih ada: " . implode(', ', $relatedData) . " (tidak dihapus)</small>";
    }
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus
    $logMessage = "Data kelas dihapus - ID: {$id_kelas}, Nama: {$kelas['nama_kelas']}";
    if (!empty($kelas['nama_gelombang'])) {
        $logMessage .= ", Gelombang: {$kelas['nama_gelombang']} {$kelas['tahun']}";
    }
    if (!empty($kelas['nama_instruktur'])) {
        $logMessage .= ", Instruktur: {$kelas['nama_instruktur']}";
    }
    if (!empty($resetSiswaInfo)) {
        $logMessage .= ", Siswa direset: " . count($resetSiswaInfo) . " orang";
    }
    error_log($logMessage);

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
    
    // Log error
    error_log("Error menghapus kelas ID {$id_kelas}: " . $e->getMessage());
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>