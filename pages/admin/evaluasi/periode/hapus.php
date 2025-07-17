<?php
session_start();  
require_once '../../../../includes/auth.php';  
requireAdminAuth();

include '../../../../includes/db.php';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID periode tidak valid!";
    header("Location: index.php");
    exit;
}

// Validasi konfirmasi
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Akses tidak valid! Konfirmasi diperlukan.";
    header("Location: index.php");
    exit;
}

$id_periode = (int)$_GET['id'];

// Validasi ID periode harus berupa angka positif
if ($id_periode <= 0) {
    $_SESSION['error'] = "ID periode tidak valid!";
    header("Location: index.php");
    exit;
}

// Ambil data periode untuk mendapatkan detail yang akan dihapus
$periodeQuery = "SELECT pe.*, g.nama_gelombang, g.tahun, g.gelombang_ke 
                 FROM periode_evaluasi pe 
                 LEFT JOIN gelombang g ON pe.id_gelombang = g.id_gelombang 
                 WHERE pe.id_periode = ?";
$stmt = mysqli_prepare($conn, $periodeQuery);

if (!$stmt) {
    $_SESSION['error'] = "Gagal mempersiapkan query: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id_periode);
mysqli_stmt_execute($stmt);
$periodeResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($periodeResult) == 0) {
    $_SESSION['error'] = "Data periode evaluasi tidak ditemukan!";
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

$periode = mysqli_fetch_assoc($periodeResult);
mysqli_stmt_close($stmt);

// Validasi tambahan - cek apakah periode masih memiliki data terkait
$checkRelations = [];

// Cek apakah periode sedang aktif
if ($periode['status'] === 'aktif') {
    $now = time();
    $tanggal_buka = strtotime($periode['tanggal_buka']);
    $tanggal_tutup = strtotime($periode['tanggal_tutup']);
    
    if ($now >= $tanggal_buka && $now <= $tanggal_tutup) {
        $_SESSION['error'] = "Tidak dapat menghapus periode evaluasi yang sedang <strong>berjalan</strong>. Harap ubah status periode atau tunggu hingga periode berakhir.";
        header("Location: index.php");
        exit;
    }
}

// Cek apakah ada evaluasi (responden) yang sudah mengisi
$evaluasiQuery = "SELECT COUNT(*) as total FROM evaluasi WHERE id_periode = ?";
$stmt = mysqli_prepare($conn, $evaluasiQuery);
mysqli_stmt_bind_param($stmt, "i", $id_periode);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$totalEvaluasi = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Cek apakah ada jawaban siswa untuk periode ini
$jawabanQuery = "SELECT COUNT(*) as total 
                 FROM jawaban_evaluasi je 
                 INNER JOIN evaluasi e ON je.id_evaluasi = e.id_evaluasi 
                 WHERE e.id_periode = ?";
$stmt = mysqli_prepare($conn, $jawabanQuery);
mysqli_stmt_bind_param($stmt, "i", $id_periode);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$totalJawaban = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Jika periode sudah memiliki responden/jawaban, berikan peringatan khusus
if ($totalEvaluasi > 0 || $totalJawaban > 0) {
    $message = "Tidak dapat menghapus periode evaluasi ini karena sudah memiliki data:";
    if ($totalEvaluasi > 0) {
        $message .= "<br>• <strong>{$totalEvaluasi} responden</strong> terdaftar";
    }
    if ($totalJawaban > 0) {
        $message .= "<br>• <strong>{$totalJawaban} jawaban</strong> evaluasi";
    }
    $message .= "<br><br>Menghapus periode akan menghapus <strong>semua data evaluasi terkait</strong> secara permanen.";
    $message .= "<br><small class='text-muted'>Jika Anda yakin ingin melanjutkan, ubah status periode menjadi 'selesai' terlebih dahulu.</small>";
    
    $_SESSION['error'] = $message;
    header("Location: index.php");
    exit;
}

// Cek jumlah pertanyaan yang dipilih
$jumlah_pertanyaan = 0;
if (!empty($periode['pertanyaan_terpilih'])) {
    $pertanyaan_data = json_decode($periode['pertanyaan_terpilih'], true);
    $jumlah_pertanyaan = is_array($pertanyaan_data) ? count($pertanyaan_data) : 0;
}

// Mulai transaksi database
mysqli_begin_transaction($conn);

try {
    // Hapus data terkait terlebih dahulu (jika ada)
    
    // 1. Hapus jawaban evaluasi terkait (jika ada, meski sudah dicek di atas)
    if ($totalJawaban > 0) {
        $deleteJawabanQuery = "DELETE je FROM jawaban_evaluasi je 
                               INNER JOIN evaluasi e ON je.id_evaluasi = e.id_evaluasi 
                               WHERE e.id_periode = ?";
        $stmt = mysqli_prepare($conn, $deleteJawabanQuery);
        mysqli_stmt_bind_param($stmt, "i", $id_periode);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal menghapus jawaban evaluasi: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }
    
    // 2. Hapus data evaluasi terkait (jika ada)
    if ($totalEvaluasi > 0) {
        $deleteEvaluasiQuery = "DELETE FROM evaluasi WHERE id_periode = ?";
        $stmt = mysqli_prepare($conn, $deleteEvaluasiQuery);
        mysqli_stmt_bind_param($stmt, "i", $id_periode);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal menghapus data evaluasi: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }
    
    // 3. Hapus data periode evaluasi utama
    $deleteQuery = "DELETE FROM periode_evaluasi WHERE id_periode = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    
    if (!$deleteStmt) {
        throw new Exception("Gagal mempersiapkan query hapus: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($deleteStmt, "i", $id_periode);
    
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new Exception("Gagal menghapus data periode evaluasi: " . mysqli_stmt_error($deleteStmt));
    }
    
    // Cek apakah ada baris yang terhapus
    if (mysqli_stmt_affected_rows($deleteStmt) == 0) {
        throw new Exception("Tidak ada data yang dihapus. Mungkin data sudah tidak ada.");
    }
    
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // Buat pesan sukses dengan detail
    $jenisEvaluasi = $periode['jenis_evaluasi'] == 'per_materi' ? 'Per Materi' : 'Akhir Kursus';
    $materiInfo = $periode['materi_terkait'] ? ' (' . strtoupper($periode['materi_terkait']) . ')' : '';
    $gelombangInfo = $periode['nama_gelombang'] ? $periode['nama_gelombang'] . ' (' . $periode['tahun'] . ')' : 'Gelombang tidak diketahui';
    
    $successMessage = "Periode evaluasi <strong>#{$id_periode}</strong> berhasil dihapus!";
    $successMessage .= "<br><small class='text-muted'>";
    $successMessage .= "<strong>Detail yang dihapus:</strong><br>";
    $successMessage .= "• Nama: " . htmlspecialchars($periode['nama_evaluasi']) . "<br>";
    $successMessage .= "• Jenis: {$jenisEvaluasi}{$materiInfo}<br>";
    $successMessage .= "• Gelombang: {$gelombangInfo}<br>";
    $successMessage .= "• Status: " . ucfirst($periode['status']) . "<br>";
    if ($jumlah_pertanyaan > 0) {
        $successMessage .= "• Pertanyaan terpilih: {$jumlah_pertanyaan} soal<br>";
    }
    if ($totalEvaluasi > 0) {
        $successMessage .= "• Data evaluasi terhapus: {$totalEvaluasi} record<br>";
    }
    if ($totalJawaban > 0) {
        $successMessage .= "• Jawaban terhapus: {$totalJawaban} record<br>";
    }
    $successMessage .= "</small>";
    
    $_SESSION['success'] = $successMessage;
    
    // Log aktivitas hapus untuk audit
    $logMessage = "Periode evaluasi dihapus - ID: {$id_periode}, Nama: {$periode['nama_evaluasi']}, Jenis: {$jenisEvaluasi}";
    if ($periode['materi_terkait']) {
        $logMessage .= ", Materi: {$periode['materi_terkait']}";
    }
    $logMessage .= ", Gelombang: {$gelombangInfo}, Status: {$periode['status']}";
    if ($jumlah_pertanyaan > 0) {
        $logMessage .= ", Pertanyaan: {$jumlah_pertanyaan}";
    }
    if ($totalEvaluasi > 0 || $totalJawaban > 0) {
        $logMessage .= ", Data terhapus: {$totalEvaluasi} evaluasi, {$totalJawaban} jawaban";
    }
    $logMessage .= " | User: " . ($_SESSION['username'] ?? 'Unknown');
    
    error_log($logMessage);

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    
    $_SESSION['error'] = "Gagal menghapus periode evaluasi: " . $e->getMessage();
    
    // Log error untuk debugging
    error_log("Error menghapus periode evaluasi ID {$id_periode}: " . $e->getMessage() . " | User: " . ($_SESSION['username'] ?? 'Unknown'));
}

// Redirect kembali ke halaman index
header("Location: index.php");
exit;
?>