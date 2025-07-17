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
$id_kelas = $_POST['id_kelas'] ?? '';

// Validasi input
if (empty($id_pendaftar) || empty($id_kelas)) {
    $_SESSION['error'] = 'Data tidak lengkap';
    header('Location: index.php');
    exit();
}

try {
    // Mulai transaksi
    mysqli_autocommit($conn, false);
    
    // 1. Ambil data pendaftar
    $pendaftarQuery = "SELECT * FROM pendaftar WHERE id_pendaftar = ? AND status_pendaftaran = 'Terverifikasi'";
    $pendaftarStmt = mysqli_prepare($conn, $pendaftarQuery);
    mysqli_stmt_bind_param($pendaftarStmt, "i", $id_pendaftar);
    mysqli_stmt_execute($pendaftarStmt);
    $pendaftarResult = mysqli_stmt_get_result($pendaftarStmt);
    
    if (mysqli_num_rows($pendaftarResult) === 0) {
        throw new Exception('Data pendaftar tidak ditemukan atau belum terverifikasi');
    }
    
    $pendaftar = mysqli_fetch_assoc($pendaftarResult);
    
    // 2. Validasi kelas dan kapasitas
    $kelasQuery = "SELECT k.*, g.nama_gelombang, 
                   (SELECT COUNT(*) FROM siswa s WHERE s.id_kelas = k.id_kelas AND s.status_aktif = 'aktif') as siswa_terdaftar
                   FROM kelas k 
                   LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
                   WHERE k.id_kelas = ? AND g.status = 'aktif'";
    $kelasStmt = mysqli_prepare($conn, $kelasQuery);
    mysqli_stmt_bind_param($kelasStmt, "i", $id_kelas);
    mysqli_stmt_execute($kelasStmt);
    $kelasResult = mysqli_stmt_get_result($kelasStmt);
    
    if (mysqli_num_rows($kelasResult) === 0) {
        throw new Exception('Kelas tidak ditemukan atau tidak aktif');
    }
    
    $kelas = mysqli_fetch_assoc($kelasResult);
    
    // Cek kapasitas kelas
    if ($kelas['siswa_terdaftar'] >= $kelas['kapasitas']) {
        throw new Exception('Kelas sudah penuh. Silakan pilih kelas lain');
    }
    
    // 3. Cek apakah NIK sudah terdaftar sebagai siswa
    $nikCheckQuery = "SELECT COUNT(*) as count FROM siswa WHERE nik = ?";
    $nikCheckStmt = mysqli_prepare($conn, $nikCheckQuery);
    mysqli_stmt_bind_param($nikCheckStmt, "s", $pendaftar['nik']);
    mysqli_stmt_execute($nikCheckStmt);
    $nikCheckResult = mysqli_stmt_get_result($nikCheckStmt);
    $nikCheck = mysqli_fetch_assoc($nikCheckResult);
    
    if ($nikCheck['count'] > 0) {
        throw new Exception('NIK sudah terdaftar sebagai siswa aktif');
    }
    
    // 4. Generate username dan password
    $username = generateUsername($pendaftar['nama_pendaftar'], $conn);
    $password = generatePassword();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // 5. Buat akun user
    $userQuery = "INSERT INTO user (username, password, role, created_at) VALUES (?, ?, 'siswa', NOW())";
    $userStmt = mysqli_prepare($conn, $userQuery);
    mysqli_stmt_bind_param($userStmt, "ss", $username, $hashedPassword);
    
    if (!mysqli_stmt_execute($userStmt)) {
        throw new Exception('Gagal membuat akun user: ' . mysqli_error($conn));
    }
    
    $id_user = mysqli_insert_id($conn);
    
    // 6. Transfer data ke tabel siswa
    $siswaQuery = "INSERT INTO siswa (
        id_user, id_kelas, nik, nama, tempat_lahir, tanggal_lahir,
        jenis_kelamin, pendidikan_terakhir, no_hp, email, alamat_lengkap,
        pas_foto, ktp, kk, ijazah, status_aktif
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')";
    
    $siswaStmt = mysqli_prepare($conn, $siswaQuery);
    mysqli_stmt_bind_param($siswaStmt, "iisssssssssssss", 
        $id_user, 
        $id_kelas,
        $pendaftar['nik'],
        $pendaftar['nama_pendaftar'],
        $pendaftar['tempat_lahir'],
        $pendaftar['tanggal_lahir'],
        $pendaftar['jenis_kelamin'],
        $pendaftar['pendidikan_terakhir'],
        $pendaftar['no_hp'],
        $pendaftar['email'],
        $pendaftar['alamat_lengkap'],
        $pendaftar['pas_foto'],
        $pendaftar['ktp'],
        $pendaftar['kk'],
        $pendaftar['ijazah']
    );
    
    if (!mysqli_stmt_execute($siswaStmt)) {
        throw new Exception('Gagal menyimpan data siswa: ' . mysqli_error($conn));
    }
    
    $id_siswa = mysqli_insert_id($conn);
    
    // 7. Update status pendaftar menjadi "Diterima"
    $updatePendaftarQuery = "UPDATE pendaftar SET status_pendaftaran = 'Diterima' WHERE id_pendaftar = ?";
    $updatePendaftarStmt = mysqli_prepare($conn, $updatePendaftarQuery);
    mysqli_stmt_bind_param($updatePendaftarStmt, "i", $id_pendaftar);
    
    if (!mysqli_stmt_execute($updatePendaftarStmt)) {
        throw new Exception('Gagal mengupdate status pendaftar: ' . mysqli_error($conn));
    }
    
    // 8. Copy file dari folder pendaftar ke folder siswa (jika diperlukan)
    copyPendaftarFiles($pendaftar);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // 9. Kirim email credentials
    $emailSent = sendWelcomeEmail($pendaftar, $username, $password, $kelas);
    
    // 10. Log aktivitas
    $logMessage = "Transfer berhasil: {$pendaftar['nama_pendaftar']} (NIK: {$pendaftar['nik']}) -> Kelas: {$kelas['nama_kelas']} | Username: {$username}";
    logTransferActivity($logMessage, $emailSent);
    
    $_SESSION['success'] = "Transfer berhasil! {$pendaftar['nama_pendaftar']} telah menjadi siswa aktif di kelas {$kelas['nama_kelas']}. Email credentials telah dikirim.";
    
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Transfer gagal: ' . $e->getMessage();
}

// Kembalikan autocommit
mysqli_autocommit($conn, true);

header('Location: index.php');
exit();

// HELPER FUNCTIONS

/**
 * Generate username unik
 */
function generateUsername($nama, $conn) {
    // Bersihkan nama dari karakter khusus
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nama));
    
    // Batasi panjang base name
    if (strlen($base) > 10) {
        $base = substr($base, 0, 10);
    }
    
    $tahun = date('Y');
    $username = $base . '_' . $tahun;
    
    // Cek apakah username sudah ada
    $counter = 1;
    while (usernameExists($username, $conn)) {
        $username = $base . '_' . $tahun . '_' . $counter;
        $counter++;
        
        // Failsafe: jika counter terlalu tinggi, gunakan timestamp
        if ($counter > 999) {
            $username = $base . '_' . time();
            break;
        }
    }
    
    return $username;
}

/**
 * Cek apakah username sudah ada
 */
function usernameExists($username, $conn) {
    $query = "SELECT COUNT(*) as count FROM user WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] > 0;
}

/**
 * Generate password
 */
function generatePassword() {
    // Opsi 1: Random 8 karakter
    // return bin2hex(random_bytes(4));
    
    // Opsi 2: Pattern dengan tahun
    // return "lkp" . date('Y');
    
    // Opsi 3: Kombinasi yang mudah diingat tapi aman
    $adjectives = ['smart', 'bright', 'quick', 'clever', 'sharp'];
    $numbers = rand(10, 99);
    $adjective = $adjectives[array_rand($adjectives)];
    
    return $adjective . $numbers;
}

/**
 * Copy files dari folder pendaftar ke folder siswa
 */
function copyPendaftarFiles($pendaftar) {
    $fileFields = ['pas_foto', 'ktp', 'kk', 'ijazah'];
    $folderMap = [
        'pas_foto' => ['pas_foto_pendaftar', 'pas_foto'],
        'ktp' => ['ktp_pendaftar', 'ktp'],
        'kk' => ['kk_pendaftar', 'kk'],
        'ijazah' => ['ijazah_pendaftar', 'ijazah']
    ];
    
    foreach ($fileFields as $field) {
        if (!empty($pendaftar[$field])) {
            $sourceFolder = $folderMap[$field][0];
            $targetFolder = $folderMap[$field][1];
            
            $sourcePath = "../../../uploads/{$sourceFolder}/{$pendaftar[$field]}";
            $targetPath = "../../../uploads/{$targetFolder}/{$pendaftar[$field]}";
            
            // Copy file jika source ada dan target folder exists
            if (file_exists($sourcePath) && is_dir("../../../uploads/{$targetFolder}")) {
                copy($sourcePath, $targetPath);
            }
        }
    }
}

/**
 * Kirim email selamat datang dengan credentials
 */
function sendWelcomeEmail($pendaftar, $username, $password, $kelas) {
    if (empty($pendaftar['email'])) return false;
    
    try {
        $to = $pendaftar['email'];
        $subject = "Selamat! Anda Diterima di LKP Pradata Komputer";
        
        $loginUrl = "http://" . $_SERVER['HTTP_HOST'] . "/lkp_webapp/pages/auth/login.php";
        
        $message = "
Kepada Yth. {$pendaftar['nama_pendaftar']},

🎉 SELAMAT! Anda telah resmi diterima sebagai siswa di LKP Pradata Komputer! 🎉

INFORMASI AKUN LOGIN:
👤 Username: {$username}
🔐 Password: {$password}
🌐 Login URL: {$loginUrl}

INFORMASI KELAS:
📚 Kelas: {$kelas['nama_kelas']}
🌊 Gelombang: {$kelas['nama_gelombang']}

LANGKAH SELANJUTNYA:
1. Login menggunakan username dan password di atas
2. Lihat jadwal kelas Anda di dashboard
3. Download materi pembelajaran yang tersedia
4. Ikuti evaluasi berkala sesuai jadwal

⚠️ PENTING:
- Untuk keamanan, silakan ganti password setelah login pertama
- Simpan informasi login ini dengan baik
- Jangan bagikan akun Anda kepada orang lain

Selamat bergabung dan semoga sukses dalam pembelajaran!

Hormat kami,
Tim LKP Pradata Komputer
Kabupaten Tabalong

📧 Email: admin@lkp-pradata.com
📱 Website: lkp-pradata.com
        ";
        
        $headers = "From: admin@lkp-pradata.com\r\n";
        $headers .= "Reply-To: admin@lkp-pradata.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $sent = mail($to, $subject, $message, $headers);
        
        return $sent;
        
    } catch (Exception $e) {
        error_log("Error sending welcome email: " . $e->getMessage());
        return false;
    }
}

/**
 * Log aktivitas transfer
 */
function logTransferActivity($message, $emailSent) {
    $logFile = '../../../uploads/transfer_log.txt';
    $adminName = $_SESSION['nama_admin'] ?? 'Unknown Admin';
    $timestamp = date('Y-m-d H:i:s');
    $emailStatus = $emailSent ? 'Email: Sent' : 'Email: Failed';
    
    $logEntry = "[{$timestamp}] Admin: {$adminName} | {$message} | {$emailStatus}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>