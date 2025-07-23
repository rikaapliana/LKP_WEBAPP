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
    
    // 8. Validasi file yang sudah ada (tidak perlu copy karena sudah satu folder)
    $fileValidation = validatePendaftarFiles($pendaftar);
    
    // Commit transaksi
    mysqli_commit($conn);
    
    // 9. Kirim email credentials (menggunakan config)
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
 * PERBAIKAN: Validasi file yang sudah ada (tidak perlu copy lagi)
 */
function validatePendaftarFiles($pendaftar) {
    // File sudah berada di folder yang tepat dari awal, hanya validasi
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
    
    // Log hasil validasi
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] File validation untuk {$pendaftar['nama_pendaftar']} (NIK: {$pendaftar['nik']}):\n";
    
    if (!empty($validFiles)) {
        $logEntry .= "  - Valid files: " . implode(', ', $validFiles) . "\n";
    }
    
    if (!empty($missingFiles)) {
        $logEntry .= "  - Missing files: " . implode(', ', $missingFiles) . "\n";
    }
    
    $logEntry .= "  - Status: Files sudah berada di folder yang tepat, tidak perlu copy\n\n";
    
    // Simpan log
    file_put_contents('../../../uploads/file_validation_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
    
    // Return info untuk log aktivitas
    return [
        'valid_count' => count($validFiles),
        'missing_count' => count($missingFiles),
        'status' => empty($missingFiles) ? 'all_files_valid' : 'some_files_missing'
    ];
}

/**
 * Kirim email selamat datang dengan credentials (MENGGUNAKAN CONFIG)
 */
function sendWelcomeEmail($pendaftar, $username, $password, $kelas) {
    if (empty($pendaftar['email'])) return false;
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings - MENGGUNAKAN CONFIG
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_ENCRYPTION == 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($pendaftar['email'], $pendaftar['nama_pendaftar']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Selamat! Anda Diterima di ' . COMPANY_NAME;
        $mail->Body = generateWelcomeEmailHTML($pendaftar, $username, $password, $kelas);
        $mail->AltBody = generateWelcomeEmailText($pendaftar, $username, $password, $kelas);
        
        $mail->send();
        
        // Log sukses
        $logEntry = date('Y-m-d H:i:s') . " - Email welcome berhasil dikirim ke: {$pendaftar['email']} (Username: {$username})\n";
        file_put_contents('../../../uploads/email_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        
        return true;
        
    } catch (Exception $e) {
        // Log error
        $logEntry = date('Y-m-d H:i:s') . " - Email welcome GAGAL dikirim ke: {$pendaftar['email']} - Error: {$e->getMessage()}\n";
        file_put_contents('../../../uploads/email_log.txt', $logEntry, FILE_APPEND | LOCK_EX);
        
        return false;
    }
}

/**
 * Generate HTML email template (MENGGUNAKAN CONFIG)
 */
function generateWelcomeEmailHTML($pendaftar, $username, $password, $kelas) {
    $currentDate = date('d F Y');
    $currentTime = date('H:i');
    
    return "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Selamat Datang di " . COMPANY_NAME . "</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 14px;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #007bff;
            }
            .header h1 {
                color: #007bff;
                margin: 0;
                font-size: 28px;
            }
            .header p {
                color: #666;
                margin: 5px 0 0 0;
            }
            .success-message {
                background: #d4edda;
                color: #155724;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                text-align: center;
                font-weight: bold;
                border: 1px solid #c3e6cb;
            }
            .content {
                margin: 20px 0;
            }
            .content p {
                margin-bottom: 15px;
            }
            .info-table {
                width: 100%;
                margin: 20px 0;
                border-collapse: collapse;
            }
            .info-table td {
                padding: 10px;
                border-bottom: 1px solid #eee;
                vertical-align: top;
            }
            .info-table td:first-child {
                font-weight: bold;
                width: 120px;
                color: #555;
            }
            .credentials {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 5px;
                margin: 25px 0;
                border: 1px solid #dee2e6;
            }
            .credentials h3 {
                margin: 0 0 15px 0;
                color: #333;
                font-size: 18px;
            }
            .credential-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 12px 0;
                padding: 10px;
                background: white;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .credential-label {
                font-weight: bold;
                color: #555;
            }
            .credential-value {
                font-family: 'Courier New', monospace;
                font-weight: bold;
                color: #007bff;
                background: #e9ecef;
                padding: 4px 8px;
                border-radius: 3px;
            }
            .login-button {
                text-align: center;
                margin: 25px 0;
            }
            .login-button a {
                display: inline-block;
                background: #007bff;
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                font-size: 16px;
            }
            .warning {
                background: #fff3cd;
                color: #856404;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
                border: 1px solid #ffeaa7;
            }
            .warning strong {
                color: #856404;
            }
            .steps {
                margin: 20px 0;
            }
            .steps ol {
                padding-left: 20px;
            }
            .steps li {
                margin: 8px 0;
            }
            .contact {
                margin: 30px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            .contact h3 {
                margin: 0 0 15px 0;
                color: #333;
            }
            .contact p {
                margin: 5px 0;
            }
            .footer {
                text-align: center;
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #dee2e6;
                color: #6c757d;
                font-size: 13px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Selamat Datang!</h1>
                <p>LKP PRADATA KOMPUTER TABALONG</p>
            </div>
            
            <div class='success-message'>
                ✅ PENDAFTARAN ANDA DITERIMA
            </div>
            
            <div class='content'>
                <p>Halo <strong>{$pendaftar['nama_pendaftar']}</strong>,</p>
                
                <p>Selamat! Kami dengan senang hati menginformasikan bahwa pendaftaran Anda di <strong>LKP PRADATA KOMPUTER TABALONG</strong> telah <strong>DITERIMA</strong> dan Anda resmi menjadi siswa kami.</p>
                
                <table class='info-table'>
                    <tr>
                        <td>Kelas</td>
                        <td>{$kelas['nama_kelas']}</td>
                    </tr>
                    <tr>
                        <td>Gelombang</td>
                        <td>{$kelas['nama_gelombang']}</td>
                    </tr>
                    <tr>
                        <td>Kapasitas</td>
                        <td>{$kelas['kapasitas']} siswa</td>
                    </tr>
                </table>
                
                <p>Untuk mengakses sistem pembelajaran online, berikut adalah informasi login Anda:</p>
                
                <div class='credentials'>
                    <h3>🔐 Informasi Login</h3>
                    <div class='credential-row'>
                        <span class='credential-label'>Username:</span>
                        <span class='credential-value'>{$username}</span>
                    </div>
                    <div class='credential-row'>
                        <span class='credential-label'>Password:</span>
                        <span class='credential-value'>{$password}</span>
                    </div>
                </div>
                
                <div class='login-button'>
                    <a href='" . LOGIN_URL . "'>LOGIN KE SISTEM</a>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Penting:</strong> Simpan informasi login ini dengan baik dan jangan bagikan kepada siapa pun. Segera ubah password setelah login pertama kali untuk keamanan akun Anda.
                </div>
                
                <div class='steps'>
                    <h3>📚 Langkah Selanjutnya:</h3>
                    <ol>
                        <li>Login ke sistem menggunakan username dan password di atas</li>
                        <li>Lengkapi profil Anda di sistem</li>
                        <li>Cek jadwal pembelajaran di dashboard</li>
                        <li>Unduh materi pembelajaran yang tersedia</li>
                        <li>Ikuti evaluasi dan ujian sesuai jadwal</li>
                    </ol>
                </div>
                
                <div class='contact'>
                    <h3>📞 Butuh Bantuan?</h3>
                    <p><strong>Email:</strong> " . COMPANY_EMAIL . "</p>
                    <p><strong>Telepon:</strong> " . COMPANY_PHONE . "</p>
                    <p><strong>WhatsApp:</strong> " . COMPANY_WHATSAPP . "</p>
                    <p><strong>Alamat:</strong> " . COMPANY_ADDRESS . "</p>
                </div>
                
                <p>Terima kasih telah mempercayai LKP PRADATA KOMPUTER TABALONG untuk mengembangkan kemampuan komputer Anda. Kami berkomitmen memberikan pelayanan terbaik untuk kesuksesan pembelajaran Anda.</p>
                
                <p>Selamat bergabung dan semoga sukses! 🚀</p>
                
                <p>Salam hangat,<br>
                <strong>TIM LKP PRADATA KOMPUTER TABALONG</strong></p>
            </div>
            
            <div class='footer'>
                <p>Email ini dikirim secara otomatis pada {$currentDate} pukul {$currentTime} WIB</p>
                <p>" . EMAIL_FOOTER_TEXT . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Generate text email template (MENGGUNAKAN CONFIG)
 */
function generateWelcomeEmailText($pendaftar, $username, $password, $kelas) {
    $currentDate = date('d F Y');
    $currentTime = date('H:i');
    
    return "
SELAMAT DATANG DI " . COMPANY_NAME . "

Kepada Yth,
{$pendaftar['nama_pendaftar']}

Selamat! Pendaftaran Anda di " . COMPANY_NAME . " telah DITERIMA.

INFORMASI KELAS:
- Kelas: {$kelas['nama_kelas']}
- Gelombang: {$kelas['nama_gelombang']}
- Kapasitas: {$kelas['kapasitas']} siswa

INFORMASI LOGIN:
- Username: {$username}
- Password: {$password}
- Login URL: " . LOGIN_URL . "

PENTING:
- Simpan informasi login ini dengan baik
- Jangan bagikan kepada siapa pun
- Segera ubah password setelah login pertama

LANGKAH SELANJUTNYA:
1. Login ke sistem menggunakan kredensial di atas
2. Lengkapi profil Anda di sistem
3. Cek jadwal pembelajaran di dashboard
4. Unduh materi pembelajaran yang tersedia
5. Ikuti evaluasi dan ujian sesuai jadwal

KONTAK:
- Email: " . COMPANY_EMAIL . "
- Telepon: " . COMPANY_PHONE . "
- WhatsApp: " . COMPANY_WHATSAPP . "
- Alamat: " . COMPANY_ADDRESS . "

Terima kasih telah mempercayai " . COMPANY_NAME . ".

Salam hangat,
Tim " . COMPANY_NAME . "

---
Email dikirim pada {$currentDate} pukul {$currentTime} WIB
" . EMAIL_FOOTER_TEXT . "
    ";
}

/**
 * Log aktivitas transfer dengan info file validation
 */
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