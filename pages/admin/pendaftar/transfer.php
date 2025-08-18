<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
include '../../../config/email_config.php';

// Tambahan untuk email notification
require_once '../../../vendor/phpmailer/PHPMailer.php';
require_once '../../../vendor/phpmailer/SMTP.php';
require_once '../../../vendor/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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
    
    // 8. Validasi file yang sudah ada
    $fileValidation = validatePendaftarFiles($pendaftar);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // 9. Kirim email credentials
    $emailSent = sendWelcomeEmail($pendaftar, $username, $password, $kelas);
    
    // 10. Log aktivitas
    $logMessage = "Transfer berhasil: {$pendaftar['nama_pendaftar']} (NIK: {$pendaftar['nik']}) -> Kelas: {$kelas['nama_kelas']} | Username: {$username}";
    logTransferActivity($logMessage, $emailSent, $fileValidation);
    
    if ($emailSent) {
        $_SESSION['success'] = "Transfer berhasil! {$pendaftar['nama_pendaftar']} telah menjadi siswa aktif di kelas {$kelas['nama_kelas']}. Email credentials telah dikirim ke {$pendaftar['email']}.";
    } else {
        $_SESSION['success'] = "Transfer berhasil! {$pendaftar['nama_pendaftar']} telah menjadi siswa aktif di kelas {$kelas['nama_kelas']}. PENTING: Email gagal dikirim. Username: {$username}, Password: {$password} - Silakan informasikan secara manual.";
    }
    
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

function generateUsername($nama, $conn) {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nama));
    if (strlen($base) > 10) {
        $base = substr($base, 0, 10);
    }
    $tahun = date('Y');
    $username = $base . '_' . $tahun;
    $counter = 1;
    while (usernameExists($username, $conn)) {
        $username = $base . '_' . $tahun . '_' . $counter;
        $counter++;
        if ($counter > 999) {
            $username = $base . '_' . time();
            break;
        }
    }
    return $username;
}

function usernameExists($username, $conn) {
    $query = "SELECT COUNT(*) as count FROM user WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

function generatePassword() {
    $adjectives = ['smart', 'bright', 'quick', 'clever', 'sharp'];
    $numbers = rand(10, 99);
    $adjective = $adjectives[array_rand($adjectives)];
    return $adjective . $numbers;
}

function validatePendaftarFiles($pendaftar) {
    $fileFields = ['pas_foto', 'ktp', 'kk', 'ijazah'];
    $missingFiles = [];
    $validFiles = [];
    foreach ($fileFields as $field) {
        if (!empty($pendaftar[$field])) {
            $filePath = "../../../uploads/{$field}/{$pendaftar[$field]}";
            if (file_exists($filePath)) {
                $validFiles[] = $field . ': ' . $pendaftar[$field];
            } else {
                $missingFiles[] = $field . ': ' . $pendaftar[$field];
            }
        } else {
            $missingFiles[] = $field . ': (kosong)';
        }
    }
    return [
        'valid_count' => count($validFiles),
        'missing_count' => count($missingFiles),
        'status' => empty($missingFiles) ? 'all_files_valid' : 'some_files_missing'
    ];
}

function sendWelcomeEmail($pendaftar, $username, $password, $kelas) {
    if (empty($pendaftar['email'])) return false;
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_ENCRYPTION == 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($pendaftar['email'], $pendaftar['nama_pendaftar']);
        
        $mail->isHTML(true);
        $mail->Subject = 'Selamat! Anda Diterima di ' . COMPANY_NAME;
        $mail->Body = generateWelcomeEmailHTML($pendaftar, $username, $password, $kelas);
        $mail->AltBody = generateWelcomeEmailText($pendaftar, $username, $password, $kelas);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        $logEntry = date('Y-m-d H:i:s') . " - Email welcome GAGAL dikirim ke: {$pendaftar['email']} - Error: {$e->getMessage()}\n";
        file_put_contents('../../../uploads/email_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        return false;
    }
}

/**
 * PERBAIKAN: Generate HTML email template yang lebih rapi dan jelas
 */
function generateWelcomeEmailHTML($pendaftar, $username, $password, $kelas) {
    // Ambil nama depan untuk sapaan yang lebih personal
    $firstName = explode(' ', trim($pendaftar['nama_pendaftar']))[0];
    
    // URL Login dari config
    $loginUrl = defined('LOGIN_URL') ? LOGIN_URL : '#'; // Fallback jika konstanta tidak ada

    return "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Selamat Datang di " . COMPANY_NAME . "</title>
        <style>
            body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; }
            .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
            .header { background-color: #0056b3; color: #ffffff; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; color: #333333; line-height: 1.6; }
            .content h2 { color: #0056b3; font-size: 20px; }
            .content p { margin: 0 0 15px 0; }
            .credentials { background-color: #f4f7f6; border: 1px dashed #cccccc; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; }
            .credentials .label { font-size: 14px; color: #555555; margin-bottom: 5px; }
            .credentials .value { font-size: 20px; font-weight: bold; color: #d9534f; background-color: #ffffff; padding: 8px 15px; border-radius: 5px; display: inline-block; letter-spacing: 1px; }
            .cta-button { text-align: center; margin: 30px 0; }
            .cta-button a { background-color: #28a745; color: #ffffff; padding: 14px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; }
            .info-table { width: 100%; margin-bottom: 20px; }
            .info-table td { padding: 8px 0; border-bottom: 1px solid #eeeeee; }
            .info-table td:first-child { font-weight: bold; width: 120px; }
            .warning { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
            .footer { background-color: #f4f7f6; color: #888888; text-align: center; padding: 20px; font-size: 12px; }
            .footer a { color: #0056b3; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>Pendaftaran Berhasil!</h1>
            </div>
            <div class='content'>
                <h2>Selamat Bergabung, " . htmlspecialchars($firstName) . "!</h2>
                <p>Kami dengan senang hati menginformasikan bahwa Anda telah resmi diterima sebagai siswa di <strong>" . COMPANY_NAME . "</strong>. Selamat datang di keluarga besar kami!</p>
                
                <table class='info-table'>
                    <tr>
                        <td>Nama Lengkap</td>
                        <td>: " . htmlspecialchars($pendaftar['nama_pendaftar']) . "</td>
                    </tr>
                    <tr>
                        <td>Kelas</td>
                        <td>: " . htmlspecialchars($kelas['nama_kelas']) . "</td>
                    </tr>
                    <tr>
                        <td>Gelombang</td>
                        <td>: " . htmlspecialchars($kelas['nama_gelombang']) . "</td>
                    </tr>
                </table>

                <p>Untuk mengakses materi, jadwal, dan informasi lainnya, silakan gunakan detail login di bawah ini:</p>
                
                <div class='credentials'>
                    <div class='label'>Username Anda:</div>
                    <div class='value'>" . htmlspecialchars($username) . "</div>
                    <br>
                    <div class='label'>Password Sementara Anda:</div>
                    <div class='value'>" . htmlspecialchars($password) . "</div>
                </div>

                <div class='cta-button'>
                    <a href='" . $loginUrl . "'>Masuk ke Portal Siswa</a>
                </div>

                <div class='warning'>
                    <strong>Penting:</strong> Untuk keamanan, mohon segera ganti password Anda setelah berhasil login untuk pertama kali.
                </div>

                <p>Jika Anda memiliki pertanyaan, jangan ragu untuk menghubungi kami. Kami siap membantu Anda memulai perjalanan belajar ini.</p>
                <p>Salam hangat,<br><strong>Tim " . COMPANY_NAME . "</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . COMPANY_NAME . ". Semua Hak Cipta Dilindungi.</p>
                <p>Email ini dibuat secara otomatis. Mohon tidak membalas email ini.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * PERBAIKAN: Generate text email template yang disesuaikan
 */
function generateWelcomeEmailText($pendaftar, $username, $password, $kelas) {
    $loginUrl = defined('LOGIN_URL') ? LOGIN_URL : '#';
    
    return "
==================================================
PENDAFTARAN BERHASIL - SELAMAT DATANG!
==================================================

Halo " . htmlspecialchars($pendaftar['nama_pendaftar']) . ",

Selamat! Anda telah resmi diterima sebagai siswa di " . COMPANY_NAME . ".

Berikut adalah detail pendaftaran Anda:
--------------------------------------------------
- Nama Lengkap: " . htmlspecialchars($pendaftar['nama_pendaftar']) . "
- Kelas         : " . htmlspecialchars($kelas['nama_kelas']) . "
- Gelombang     : " . htmlspecialchars($kelas['nama_gelombang']) . "

AKUN PORTAL SISWA ANDA:
--------------------------------------------------
Gunakan informasi berikut untuk masuk ke portal siswa.

- Username: " . htmlspecialchars($username) . "
- Password: " . htmlspecialchars($password) . "

- LINK LOGIN: " . $loginUrl . "

[PENTING]
Untuk keamanan, mohon segera ganti password Anda setelah berhasil login untuk pertama kali.

--------------------------------------------------
Jika ada pertanyaan, silakan hubungi kami.

Terima kasih dan selamat belajar!

Salam hangat,
Tim " . COMPANY_NAME . "

(Email ini dibuat secara otomatis, mohon tidak dibalas)
    ";
}

function logTransferActivity($message, $emailSent, $fileValidation) {
    $logFile = '../../../uploads/transfer_log.txt';
    $adminName = $_SESSION['nama_admin'] ?? 'Unknown Admin';
    $timestamp = date('Y-m-d H:i:s');
    $emailStatus = $emailSent ? 'Email: Sent' : 'Email: Failed';
    $fileStatus = "Files: {$fileValidation['valid_count']} valid, {$fileValidation['missing_count']} missing";
    
    $logEntry = "[{$timestamp}] Admin: {$adminName} | {$message} | {$emailStatus} | {$fileStatus}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>