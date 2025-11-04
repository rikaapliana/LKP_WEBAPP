<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

// Memanggil file-file yang dibutuhkan di bagian paling atas
include '../../../includes/db.php';
require_once '../../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Metode request tidak valid';
    header('Location: index.php');
    exit();
}

// Mengambil data dari form dengan aman
$id_pendaftar = filter_input(INPUT_POST, 'id_pendaftar', FILTER_VALIDATE_INT);
$status_pendaftaran = filter_input(INPUT_POST, 'status_pendaftaran', FILTER_SANITIZE_STRING);
$catatan = filter_input(INPUT_POST, 'catatan', FILTER_SANITIZE_STRING);

if (!$id_pendaftar || !$status_pendaftaran) {
    $_SESSION['error'] = 'Data tidak lengkap atau tidak valid.';
    header('Location: index.php');
    exit();
}

$allowed_status = ['Terverifikasi', 'Ditolak'];
if (!in_array($status_pendaftaran, $allowed_status)) {
    $_SESSION['error'] = 'Status yang dikirim tidak valid.';
    header('Location: index.php');
    exit();
}

try {
    // Ambil data pendaftar dari database
    $stmt = $conn->prepare("SELECT * FROM pendaftar WHERE id_pendaftar = ?");
    $stmt->bind_param("i", $id_pendaftar);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Data pendaftar tidak ditemukan.');
    }
    $pendaftar = $result->fetch_assoc();
    
    // Update status pendaftar di database
    $updateStmt = $conn->prepare("UPDATE pendaftar SET status_pendaftaran = ? WHERE id_pendaftar = ?");
    $updateStmt->bind_param("si", $status_pendaftaran, $id_pendaftar);
    
    if ($updateStmt->execute()) {
        
        // Hanya kirim notifikasi jika status pendaftaran adalah 'Ditolak'
        if ($status_pendaftaran === 'Ditolak') {
            
            $penerima_email = $pendaftar['email'];
            $penerima_nama = $pendaftar['nama_pendaftar'];
            $nomor_hp = $pendaftar['no_hp'];

            // 1. Siapkan pesan untuk EMAIL (HTML)
            $subjek_email = "Informasi Status Pendaftaran Anda di LKP Pradata Komputer";
            $isi_email = "
                <h3>Yth. {$penerima_nama},</h3>
                <p>Terima kasih telah mendaftar di <strong>LKP Pradata Komputer</strong>.</p>
                <p>Setelah melalui proses verifikasi, dengan berat hati kami sampaikan bahwa pendaftaran Anda untuk periode ini belum dapat kami proses lebih lanjut dengan status: <strong>DITOLAK</strong>.</p>
            ";
            if (!empty($catatan)) {
                $isi_email .= "<p><b>Catatan dari Admin:</b><br><i>" . htmlspecialchars($catatan) . "</i></p>";
            }
            $isi_email .= "
                <p>Jangan berkecil hati, Anda dapat mencoba mendaftar kembali di gelombang pendaftaran berikutnya.</p>
                <br>
                <p>Hormat kami,</p>
                <p><strong>Admin LKP Pradata Komputer</strong></p>
            ";

            // 2. Siapkan pesan untuk WHATSAPP (Teks Biasa)
            $pesan_wa = "Yth. {$penerima_nama},\n\nTerima kasih telah mendaftar di LKP Pradata Komputer. Setelah melalui proses verifikasi, dengan berat hati kami sampaikan bahwa pendaftaran Anda untuk periode ini berstatus: DITOLAK.";
            if (!empty($catatan)) {
                $pesan_wa .= "\n\nCatatan dari Admin:\n" . $catatan;
            }
            $pesan_wa .= "\n\nHormat kami,\nAdmin LKP Pradata Komputer";
            
            // 3. Kirim kedua notifikasi
            if (!empty($penerima_email)) {
                kirimEmailNotifikasi($penerima_email, $penerima_nama, $subjek_email, $isi_email);
            }
            // Kirim notifikasi WhatsApp jika nomor HP ada
            // Kirim notifikasi WhatsApp jika nomor HP ada
            if (!empty($nomor_hp)) {
                kirimWhatsAppNotifikasi($nomor_hp, $pesan_wa);
            }
        }
        
        $_SESSION['success'] = "Status pendaftar berhasil diubah menjadi '{$status_pendaftaran}'.";
        
    } else {
        $_SESSION['error'] = 'Gagal mengubah status pendaftar.';
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
}

header('Location: index.php');
exit();
?>