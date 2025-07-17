<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID jadwal tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_jadwal = (int)$_GET['id'];

// Validasi ID jadwal harus berupa angka positif
if ($id_jadwal <= 0) {
    $_SESSION['error'] = "ID jadwal tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data jadwal lengkap untuk mendapatkan informasi sebelum dihapus
$jadwalQuery = "SELECT j.*, k.nama_kelas, g.nama_gelombang, g.tahun, i.nama as nama_instruktur
                FROM jadwal j 
                LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
                LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
                WHERE j.id_jadwal = ?";
$stmt = mysqli_prepare($conn, $jadwalQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_jadwal);
mysqli_stmt_execute($stmt);
$jadwalResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($jadwalResult) == 0) {
    $_SESSION['error'] = "Data jadwal tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$jadwal = mysqli_fetch_assoc($jadwalResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah jadwal memiliki data terkait
$checkRelations = [];

// Cek apakah jadwal memiliki data absensi siswa
$absensiQuery = "SELECT COUNT(*) as total FROM absensi_siswa WHERE id_jadwal = ?";
$absensiStmt = mysqli_prepare($conn, $absensiQuery);
if ($absensiStmt) {
    mysqli_stmt_bind_param($absensiStmt, "i", $id_jadwal);
    mysqli_stmt_execute($absensiStmt);
    $absensiResult = mysqli_stmt_get_result($absensiStmt);
    $absensiData = mysqli_fetch_assoc($absensiResult);
    mysqli_stmt_close($absensiStmt);
} else {
    $absensiData = ['total' => 0];
}

// Cek status jadwal berdasarkan tanggal
$tanggalJadwal = strtotime($jadwal['tanggal']);
$today = strtotime(date('Y-m-d'));
$statusJadwal = '';

if ($tanggalJadwal < $today) {
    $statusJadwal = 'selesai';
} elseif ($tanggalJadwal == $today) {
    $statusJadwal = 'berlangsung';
} else {
    $statusJadwal = 'terjadwal';
}

// Peringatan khusus untuk jadwal yang sudah berlangsung atau selesai
if ($statusJadwal !== 'terjadwal' && $absensiData['total'] > 0) {
    $_SESSION['warning'] = "Peringatan: Jadwal ini sudah memiliki data absensi. Penghapusan akan menghilangkan riwayat kehadiran siswa!";
}

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // Array untuk menyimpan informasi yang akan dihapus
    $deletedRelatedData = [];
    
    // Hapus data absensi siswa terkait jadwal ini (opsional - bisa dikomentari jika ingin keep data)
    if ($absensiData['total'] > 0) {
        // Ambil detail absensi sebelum dihapus (untuk log)
        $getAbsensiQuery = "SELECT COUNT(DISTINCT id_siswa) as total_siswa FROM absensi_siswa WHERE id_jadwal = ?";
        $getAbsensiStmt = mysqli_prepare($conn, $getAbsensiQuery);
        mysqli_stmt_bind_param($getAbsensiStmt, "i", $id_jadwal);
        mysqli_stmt_execute($getAbsensiStmt);
        $getAbsensiResult = mysqli_stmt_get_result($getAbsensiStmt);
        $absensiDetail = mysqli_fetch_assoc($getAbsensiResult);
        mysqli_stmt_close($getAbsensiStmt);
        
        // Hapus data absensi
        $deleteAbsensiQuery = "DELETE FROM absensi_siswa WHERE id_jadwal = ?";
        $deleteAbsensiStmt = mysqli_prepare($conn, $deleteAbsensiQuery);
        mysqli_stmt_bind_param($deleteAbsensiStmt, "i", $id_jadwal);
        
        if (!mysqli_stmt_execute($deleteAbsensiStmt)) {
            throw new Exception("Gagal menghapus data absensi: " . mysqli_stmt_error($deleteAbsensiStmt));
        }
        
        $deletedAbsensiCount = mysqli_stmt_affected_rows($deleteAbsensiStmt);
        mysqli_stmt_close($deleteAbsensiStmt);
        
        if ($deletedAbsensiCount > 0) {
            $deletedRelatedData[] = "{$deletedAbsensiCount} data absensi dari {$absensiDetail['total_siswa']} siswa";
        }
    }

    // Hapus data jadwal dari database menggunakan prepared statement
    $deleteQuery = "DELETE FROM jadwal WHERE id_jadwal = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "i", $id_jadwal);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data jadwal: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $successMessage = "Jadwal <strong>" . htmlspecialchars($jadwal['nama_kelas']) . "</strong>";
    
    // Tambahkan informasi gelombang jika ada
    if (!empty($jadwal['nama_gelombang'])) {
        $successMessage .= " - " . htmlspecialchars($jadwal['nama_gelombang']);
        if (!empty($jadwal['tahun'])) {
            $successMessage .= " (" . htmlspecialchars($jadwal['tahun']) . ")";
        }
    }
    
    // Tambahkan tanggal dan waktu
    $successMessage .= "<br><small class='text-muted'>";
    $successMessage .= "Tanggal: " . date('d F Y', strtotime($jadwal['tanggal']));
    $successMessage .= " | Waktu: " . date('H:i', strtotime($jadwal['waktu_mulai'])) . " - " . date('H:i', strtotime($jadwal['waktu_selesai']));
    if (!empty($jadwal['nama_instruktur'])) {
        $successMessage .= " | Instruktur: " . htmlspecialchars($jadwal['nama_instruktur']);
    }
    $successMessage .= "</small><br>";
    $successMessage .= "<strong>Berhasil dihapus!</strong>";
    
    // Tambahkan informasi data terkait yang dihapus
    if (!empty($deletedRelatedData)) {
        $successMessage .= "<br><small class='text-info'>Data terkait yang dihapus: " . implode(', ', $deletedRelatedData) . "</small>";
    }
    
    // Tambahkan status jadwal
    $statusLabels = [
        'terjadwal' => '📅 Jadwal Mendatang',
        'berlangsung' => '⏰ Jadwal Hari Ini', 
        'selesai' => '✅ Jadwal Selesai'
    ];
    
    $successMessage .= "<br><small class='text-muted'>Status: " . $statusLabels[$statusJadwal] . "</small>";
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus
    $logMessage = "Data jadwal dihapus - ID: {$id_jadwal}";
    $logMessage .= ", Kelas: {$jadwal['nama_kelas']}";
    $logMessage .= ", Tanggal: {$jadwal['tanggal']}";
    $logMessage .= ", Waktu: {$jadwal['waktu_mulai']}-{$jadwal['waktu_selesai']}";
    if (!empty($jadwal['nama_instruktur'])) {
        $logMessage .= ", Instruktur: {$jadwal['nama_instruktur']}";
    }
    if (!empty($deletedRelatedData)) {
        $logMessage .= ", Data terkait dihapus: " . implode(', ', $deletedRelatedData);
    }
    $logMessage .= ", Status: {$statusJadwal}";
    error_log($logMessage);

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus jadwal: " . $e->getMessage();
    
    // Log error
    error_log("Error menghapus jadwal ID {$id_jadwal}: " . $e->getMessage());
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>