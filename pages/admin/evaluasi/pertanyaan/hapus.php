<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php'; // Pastikan ini menginisialisasi koneksi $conn

// 1. Validasi Input Awal
// Pastikan ID dan konfirmasi dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Akses tidak valid. ID pertanyaan tidak ditemukan.";
    header("Location: index.php");
    exit;
}

// Konfirmasi diperlukan sebagai lapisan keamanan kedua untuk mencegah penghapusan tidak sengaja
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'delete') {
    $_SESSION['error'] = "Konfirmasi penghapusan diperlukan. Silakan coba lagi.";
    header("Location: index.php");
    exit;
}

$id_pertanyaan = (int)$_GET['id'];

// Pastikan ID adalah angka positif
if ($id_pertanyaan <= 0) {
    $_SESSION['error'] = "ID pertanyaan tidak valid!";
    header("Location: index.php");
    exit;
}

// 2. Ambil Detail Pertanyaan
// Langkah ini penting untuk memastikan pertanyaan ada dan untuk logging nanti
$stmt = $conn->prepare("SELECT pertanyaan, aspek_dinilai FROM pertanyaan_evaluasi WHERE id_pertanyaan = ?");
$stmt->bind_param("i", $id_pertanyaan);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data pertanyaan dengan ID #{$id_pertanyaan} tidak ditemukan!";
    $stmt->close();
    header("Location: index.php");
    exit;
}
$pertanyaan = $result->fetch_assoc();
$stmt->close();


// 3. Validasi Keterkaitan Data (Paling Penting)
// A. Cek apakah pertanyaan sudah memiliki jawaban dari siswa
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM jawaban_evaluasi WHERE id_pertanyaan = ?");
$stmt->bind_param("i", $id_pertanyaan);
$stmt->execute();
$totalJawaban = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

if ($totalJawaban > 0) {
    // BLOKIR PENGHAPUSAN jika sudah ada jawaban.
    $_SESSION['error'] = "<strong>Gagal Menghapus!</strong> Pertanyaan #{$id_pertanyaan} tidak dapat dihapus karena sudah memiliki <strong>{$totalJawaban} jawaban siswa</strong>. Menghapus ini akan merusak data evaluasi.";
    header("Location: index.php");
    exit;
}

// B. Cek apakah pertanyaan terikat pada sebuah periode evaluasi
// Query ini mengasumsikan `pertanyaan_terpilih` adalah string berisi ID yang dipisahkan koma (e.g., "1,5,22")
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM periode_evaluasi WHERE FIND_IN_SET(?, pertanyaan_terpilih) > 0");
$stmt->bind_param("i", $id_pertanyaan);
$stmt->execute();
$totalPeriode = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

if ($totalPeriode > 0) {
    // BLOKIR PENGHAPUSAN jika pertanyaan masih terdaftar di periode.
    $_SESSION['error'] = "<strong>Gagal Menghapus!</strong> Pertanyaan #{$id_pertanyaan} tidak dapat dihapus karena terdaftar dalam <strong>{$totalPeriode} periode evaluasi</strong>. Hapus pertanyaan dari daftar periode evaluasi terlebih dahulu.";
    header("Location: index.php");
    exit;
}


// 4. Proses Hapus Permanen
// Jika semua validasi lolos, lanjutkan dengan penghapusan
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM pertanyaan_evaluasi WHERE id_pertanyaan = ?");
    if (!$stmt) {
        throw new Exception("Gagal mempersiapkan statement hapus: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id_pertanyaan);
    
    if (!$stmt->execute()) {
        throw new Exception("Eksekusi query hapus gagal: " . $stmt->error);
    }
    
    // Pastikan ada baris yang benar-benar terhapus
    if ($stmt->affected_rows === 0) {
        throw new Exception("Tidak ada baris yang terhapus. Data mungkin sudah tidak ada.");
    }

    $stmt->close();
    
    // Jika semua berhasil, commit transaksi
    $conn->commit();
    
    // Set pesan sukses
    $_SESSION['success'] = "Pertanyaan <strong>#{$id_pertanyaan}</strong> ('" . htmlspecialchars(substr($pertanyaan['pertanyaan'], 0, 50)) . "...') berhasil <strong>dihapus permanen</strong>.";

    // Logging (opsional tapi sangat direkomendasikan)
    $logMessage = "ADMIN ACTIVITY: Pertanyaan evaluasi dihapus permanen. ID: {$id_pertanyaan}, Aspek: " . $pertanyaan['aspek_dinilai'];
    error_log($logMessage);

} catch (Exception $e) {
    // Jika ada error, batalkan semua perubahan
    $conn->rollback();
    $_SESSION['error'] = "Terjadi kesalahan saat menghapus data: " . $e->getMessage();
    error_log("Error Hapus Pertanyaan ID {$id_pertanyaan}: " . $e->getMessage());
}

// 5. Redirect kembali ke halaman utama
header("Location: index.php");
exit;
?>