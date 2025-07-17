<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Metode request tidak valid';
    header('Location: index.php');
    exit();
}

$id_pendaftar = $_POST['id_pendaftar'] ?? '';
$status_pendaftaran = $_POST['status_pendaftaran'] ?? '';
$catatan = $_POST['catatan'] ?? '';

// Validasi input
if (empty($id_pendaftar) || empty($status_pendaftaran)) {
    $_SESSION['error'] = 'Data tidak lengkap';
    header('Location: index.php');
    exit();
}

// Validasi status yang diizinkan
$allowed_status = ['Terverifikasi', 'Ditolak'];
if (!in_array($status_pendaftaran, $allowed_status)) {
    $_SESSION['error'] = 'Status tidak valid';
    header('Location: index.php');
    exit();
}

try {
    // Ambil data pendaftar untuk validasi
    $query = "SELECT * FROM pendaftar WHERE id_pendaftar = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id_pendaftar);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        $_SESSION['error'] = 'Data pendaftar tidak ditemukan';
        header('Location: index.php');
        exit();
    }
    
    $pendaftar = mysqli_fetch_assoc($result);
    
    // Validasi: hanya bisa update jika status masih "Belum di Verifikasi"
    if ($pendaftar['status_pendaftaran'] !== 'Belum di Verifikasi') {
        $_SESSION['error'] = 'Status pendaftar sudah diproses sebelumnya';
        header('Location: index.php');
        exit();
    }
    
    // Update status pendaftar
    $updateQuery = "UPDATE pendaftar SET status_pendaftaran = ? WHERE id_pendaftar = ?";
    $updateStmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "si", $status_pendaftaran, $id_pendaftar);
    
    if (mysqli_stmt_execute($updateStmt)) {
        // Log aktivitas (opsional)
        $log_message = "Status pendaftar {$pendaftar['nama_pendaftar']} (NIK: {$pendaftar['nik']}) diubah menjadi '{$status_pendaftaran}'";
        if (!empty($catatan)) {
            $log_message .= " dengan catatan: {$catatan}";
        }
        
        // Simpan ke file log (opsional)
        $log_file = '../../../uploads/status_update_log.txt';
        $log_entry = date('Y-m-d H:i:s') . " - Admin: " . ($_SESSION['nama_admin'] ?? 'Unknown') . " - " . $log_message . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Kirim email notifikasi jika diperlukan
        if ($status_pendaftaran === 'Terverifikasi') {
            sendVerificationEmail($pendaftar);
        } elseif ($status_pendaftaran === 'Ditolak') {
            sendRejectionEmail($pendaftar, $catatan);
        }
        
        $_SESSION['success'] = "Status pendaftar berhasil diubah menjadi '{$status_pendaftaran}'";
        
    } else {
        $_SESSION['error'] = 'Gagal mengubah status pendaftar: ' . mysqli_error($conn);
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
}

header('Location: index.php');
exit();

// Function untuk mengirim email verifikasi
function sendVerificationEmail($pendaftar) {
    if (empty($pendaftar['email'])) return false;
    
    try {
        // Include email config jika ada
        if (file_exists('../../../config/email_config.php')) {
            include_once '../../../config/email_config.php';
        }
        
        $to = $pendaftar['email'];
        $subject = "Pendaftaran Anda Telah Diverifikasi - LKP Pradata Komputer";
        
        $message = "
        Kepada Yth. {$pendaftar['nama_pendaftar']},
        
        Selamat! Pendaftaran Anda di LKP Pradata Komputer telah berhasil diverifikasi.
        
        DETAIL PENDAFTARAN:
        Nama: {$pendaftar['nama_pendaftar']}
        NIK: {$pendaftar['nik']}
        Email: {$pendaftar['email']}
        Jam Pilihan: {$pendaftar['jam_pilihan']}
        
        LANGKAH SELANJUTNYA:
        Admin akan segera mengatur penempatan kelas Anda. Anda akan menerima email lanjutan berisi informasi akun login dan jadwal kelas.
        
        Terima kasih atas kepercayaan Anda memilih LKP Pradata Komputer.
        
        Hormat kami,
        Tim LKP Pradata Komputer
        Kabupaten Tabalong
        ";
        
        $headers = "From: admin@lkp-pradata.com\r\n";
        $headers .= "Reply-To: admin@lkp-pradata.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Gunakan mail() function atau library email lainnya
        $sent = mail($to, $subject, $message, $headers);
        
        // Log email
        $log_entry = date('Y-m-d H:i:s') . " - Email verifikasi dikirim ke: {$to} - Status: " . ($sent ? 'Berhasil' : 'Gagal') . "\n";
        file_put_contents('../../../uploads/email_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
        
        return $sent;
        
    } catch (Exception $e) {
        error_log("Error sending verification email: " . $e->getMessage());
        return false;
    }
}

// Function untuk mengirim email penolakan
function sendRejectionEmail($pendaftar, $catatan = '') {
    if (empty($pendaftar['email'])) return false;
    
    try {
        $to = $pendaftar['email'];
        $subject = "Informasi Pendaftaran - LKP Pradata Komputer";
        
        $message = "
        Kepada Yth. {$pendaftar['nama_pendaftar']},
        
        Terima kasih telah mendaftar di LKP Pradata Komputer.
        
        Setelah melalui proses review, kami perlu menginformasikan bahwa pendaftaran Anda belum dapat kami proses lebih lanjut pada periode ini.
        ";
        
        if (!empty($catatan)) {
            $message .= "\nCatatan dari Admin:\n{$catatan}\n";
        }
        
        $message .= "
        
        Jangan berkecil hati! Anda dapat mendaftar kembali pada periode pendaftaran berikutnya setelah melengkapi persyaratan yang diperlukan.
        
        Untuk informasi lebih lanjut, silakan hubungi kami melalui kontak yang tersedia.
        
        Terima kasih atas pengertian Anda.
        
        Hormat kami,
        Tim LKP Pradata Komputer
        Kabupaten Tabalong
        ";
        
        $headers = "From: admin@lkp-pradata.com\r\n";
        $headers .= "Reply-To: admin@lkp-pradata.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $sent = mail($to, $subject, $message, $headers);
        
        // Log email
        $log_entry = date('Y-m-d H:i:s') . " - Email penolakan dikirim ke: {$to} - Status: " . ($sent ? 'Berhasil' : 'Gagal') . "\n";
        file_put_contents('../../../uploads/email_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
        
        return $sent;
        
    } catch (Exception $e) {
        error_log("Error sending rejection email: " . $e->getMessage());
        return false;
    }
}
?>