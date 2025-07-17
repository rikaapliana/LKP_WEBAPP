<?php
session_start();
include 'includes/db.php';

// Cek gelombang yang sedang dibuka untuk pendaftaran
$gelombangQuery = "SELECT g.*, p.status_pendaftaran, p.kuota_maksimal, p.tanggal_buka, p.tanggal_tutup, p.keterangan
                   FROM gelombang g 
                   INNER JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
                   WHERE p.status_pendaftaran = 'dibuka'
                   AND (p.tanggal_buka IS NULL OR p.tanggal_buka <= NOW())
                   AND (p.tanggal_tutup IS NULL OR p.tanggal_tutup >= NOW())
                   ORDER BY g.tahun DESC, g.gelombang_ke DESC
                   LIMIT 1";

$gelombangResult = mysqli_query($conn, $gelombangQuery);
$gelombangAktif = mysqli_fetch_assoc($gelombangResult);

// Jika tidak ada gelombang aktif, tampilkan pesan
if (!$gelombangAktif) {
    $pendaftaranTutup = true;
    $pesanTutup = "Pendaftaran sedang ditutup. Silakan hubungi admin untuk informasi lebih lanjut.";
} else {
    $pendaftaranTutup = false;
    
    // Cek apakah kuota sudah penuh
    $countPendaftar = mysqli_query($conn, "SELECT COUNT(*) as total FROM pendaftar WHERE id_gelombang = " . $gelombangAktif['id_gelombang']);
    $totalPendaftar = mysqli_fetch_assoc($countPendaftar)['total'];
    
    if ($gelombangAktif['kuota_maksimal'] > 0 && $totalPendaftar >= $gelombangAktif['kuota_maksimal']) {
        $pendaftaranTutup = true;
        $pesanTutup = "Kuota pendaftaran untuk " . $gelombangAktif['nama_gelombang'] . " sudah penuh.";
    }
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendaftaranTutup) {
    // Validasi input
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);
    $nama_pendaftar = mysqli_real_escape_string($conn, $_POST['nama_pendaftar']);
    $tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $pendidikan_terakhir = $_POST['pendidikan_terakhir'];
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $alamat_lengkap = mysqli_real_escape_string($conn, $_POST['alamat_lengkap']);
    $jam_pilihan = $_POST['jam_pilihan'];
    
    // Cek apakah NIK sudah terdaftar di gelombang yang sama
    $cekNIK = mysqli_query($conn, "SELECT id_pendaftar FROM pendaftar WHERE nik = '$nik' AND id_gelombang = " . $gelombangAktif['id_gelombang']);
    
    if (mysqli_num_rows($cekNIK) > 0) {
        $error = "NIK sudah terdaftar di gelombang ini!";
    } else {
        // Handle file upload
        $pas_foto = '';
        $ktp = '';
        $kk = '';
        $ijazah = '';
        
        $upload_dir = 'uploads/pendaftar/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Upload pas foto
        if (isset($_FILES['pas_foto']) && $_FILES['pas_foto']['error'] == 0) {
            $file_tmp = $_FILES['pas_foto']['tmp_name'];
            $file_type = $_FILES['pas_foto']['type'];
            $file_size = $_FILES['pas_foto']['size'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $pas_foto = time() . '_pas_foto_' . uniqid() . '.' . pathinfo($_FILES['pas_foto']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($file_tmp, $upload_dir . $pas_foto);
            } else {
                $error = "File pas foto tidak valid atau terlalu besar!";
            }
        }
        
        // Upload KTP
        if (isset($_FILES['ktp']) && $_FILES['ktp']['error'] == 0) {
            $file_tmp = $_FILES['ktp']['tmp_name'];
            $file_type = $_FILES['ktp']['type'];
            $file_size = $_FILES['ktp']['size'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $ktp = time() . '_ktp_' . uniqid() . '.' . pathinfo($_FILES['ktp']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($file_tmp, $upload_dir . $ktp);
            } else {
                $error = "File KTP tidak valid atau terlalu besar!";
            }
        }
        
        // Upload KK
        if (isset($_FILES['kk']) && $_FILES['kk']['error'] == 0) {
            $file_tmp = $_FILES['kk']['tmp_name'];
            $file_type = $_FILES['kk']['type'];
            $file_size = $_FILES['kk']['size'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $kk = time() . '_kk_' . uniqid() . '.' . pathinfo($_FILES['kk']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($file_tmp, $upload_dir . $kk);
            } else {
                $error = "File KK tidak valid atau terlalu besar!";
            }
        }
        
        // Upload Ijazah
        if (isset($_FILES['ijazah']) && $_FILES['ijazah']['error'] == 0) {
            $file_tmp = $_FILES['ijazah']['tmp_name'];
            $file_type = $_FILES['ijazah']['type'];
            $file_size = $_FILES['ijazah']['size'];
            
            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                $ijazah = time() . '_ijazah_' . uniqid() . '.' . pathinfo($_FILES['ijazah']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($file_tmp, $upload_dir . $ijazah);
            } else {
                $error = "File ijazah tidak valid atau terlalu besar!";
            }
        }
        
        // Insert ke database jika tidak ada error
        if (!isset($error)) {
            $insertQuery = "INSERT INTO pendaftar (
                id_gelombang, nik, nama_pendaftar, tempat_lahir, tanggal_lahir, 
                jenis_kelamin, pendidikan_terakhir, no_hp, email, alamat_lengkap, 
                jam_pilihan, pas_foto, ktp, kk, ijazah, status_pendaftaran
            ) VALUES (
                " . $gelombangAktif['id_gelombang'] . ",
                '$nik', '$nama_pendaftar', '$tempat_lahir', '$tanggal_lahir',
                '$jenis_kelamin', '$pendidikan_terakhir', '$no_hp', '$email', '$alamat_lengkap',
                '$jam_pilihan', '$pas_foto', '$ktp', '$kk', '$ijazah', 'Belum di Verifikasi'
            )";
            
            if (mysqli_query($conn, $insertQuery)) {
                $success = "Pendaftaran berhasil untuk " . $gelombangAktif['nama_gelombang'] . "!";
                $pendaftar_id = mysqli_insert_id($conn);
                
                // Reset form
                unset($_POST);
            } else {
                $error = "Terjadi kesalahan: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - LKP Pradata Komputer</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png"/>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="assets/css/fonts.css" />
    <link rel="stylesheet" href="assets/css/styles.css" />
    
    <style>
        .registration-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .registration-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .gelombang-info {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section h5 {
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .file-upload {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        
        .file-upload.dragover {
            border-color: #007bff;
            background-color: #e7f3ff;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
        }
        
        .progress-step.active::after {
            background: #007bff;
        }
        
        .progress-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
            color: #6c757d;
        }
        
        .progress-step.active .progress-step-circle {
            background: #007bff;
            color: white;
        }
        
        .closed-registration {
            text-align: center;
            padding: 4rem 2rem;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 2rem 0;
        }
        
        .closed-registration i {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .registration-header {
                padding: 2rem 0;
            }
            
            .registration-form {
                padding: 1.5rem;
            }
            
            .progress-steps {
                flex-direction: column;
                gap: 1rem;
            }
            
            .progress-step:not(:last-child)::after {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="registration-header">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center">
                        <h1><i class="bi bi-mortarboard me-3"></i>Pendaftaran LKP Pradata Komputer</h1>
                        <p class="lead mb-0">Bergabunglah dengan program kursus komputer terbaik</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= $success ?>
                    <br>
                    <strong>Nomor Pendaftaran:</strong> <?= str_pad($pendaftar_id, 6, '0', STR_PAD_LEFT) ?>
                    <br>
                    <small>Silakan catat nomor pendaftaran ini untuk keperluan verifikasi.</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$pendaftaranTutup): ?>
                <!-- Informasi Gelombang -->
                <div class="gelombang-info">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1">
                                <i class="bi bi-calendar-event me-2"></i>
                                <?= $gelombangAktif['nama_gelombang'] ?>
                            </h4>
                            <p class="mb-0">
                                <span class="badge bg-primary me-2">Tahun <?= $gelombangAktif['tahun'] ?></span>
                                <span class="badge bg-info me-2">Gelombang ke-<?= $gelombangAktif['gelombang_ke'] ?></span>
                                <span class="badge bg-success">Pendaftaran Dibuka</span>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <?php if ($gelombangAktif['kuota_maksimal'] > 0): ?>
                                <div class="mb-1">
                                    <strong>Kuota Pendaftaran:</strong>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" style="width: <?= ($totalPendaftar / $gelombangAktif['kuota_maksimal']) * 100 ?>%">
                                        <?= $totalPendaftar ?> / <?= $gelombangAktif['kuota_maksimal'] ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($gelombangAktif['keterangan']): ?>
                        <div class="mt-3">
                            <strong>Keterangan:</strong>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($gelombangAktif['keterangan'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($gelombangAktif['tanggal_tutup']): ?>
                        <div class="mt-3">
                            <strong>Batas Pendaftaran:</strong> 
                            <span class="text-danger"><?= date('d F Y, H:i', strtotime($gelombangAktif['tanggal_tutup'])) ?> WIB</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Progress Steps -->
                <div class="progress-steps">
                    <div class="progress-step active">
                        <div class="progress-step-circle">1</div>
                        <div>Data Diri</div>
                    </div>
                    <div class="progress-step">
                        <div class="progress-step-circle">2</div>
                        <div>Dokumen</div>
                    </div>
                    <div class="progress-step">
                        <div class="progress-step-circle">3</div>
                        <div>Konfirmasi</div>
                    </div>
                </div>

                <!-- Form Pendaftaran -->
                <div class="registration-form">
                    <form method="POST" enctype="multipart/form-data" id="registrationForm">
                        <input type="hidden" name="id_gelombang" value="<?= $gelombangAktif['id_gelombang'] ?>">
                        
                        <!-- Data Pribadi -->
                        <div class="form-section">
                            <h5><i class="bi bi-person me-2"></i>Data Pribadi</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">NIK <span class="text-danger">*</span></label>
                                        <input type="text" name="nik" class="form-control" maxlength="16" 
                                               pattern="[0-9]{16}" value="<?= isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : '' ?>" 
                                               required>
                                        <div class="form-text">16 digit angka sesuai KTP</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" name="nama_pendaftar" class="form-control" 
                                               value="<?= isset($_POST['nama_pendaftar']) ? htmlspecialchars($_POST['nama_pendaftar']) : '' ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tempat Lahir <span class="text-danger">*</span></label>
                                        <input type="text" name="tempat_lahir" class="form-control" 
                                               value="<?= isset($_POST['tempat_lahir']) ? htmlspecialchars($_POST['tempat_lahir']) : '' ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                        <input type="date" name="tanggal_lahir" class="form-control" 
                                               value="<?= isset($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : '' ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                        <select name="jenis_kelamin" class="form-select" required>
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <option value="Laki-Laki" <?= isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'Laki-Laki' ? 'selected' : '' ?>>Laki-Laki</option>
                                            <option value="Perempuan" <?= isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Pendidikan Terakhir <span class="text-danger">*</span></label>
                                        <select name="pendidikan_terakhir" class="form-select" required>
                                            <option value="">Pilih Pendidikan Terakhir</option>
                                            <option value="SD" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'SD' ? 'selected' : '' ?>>SD</option>
                                            <option value="SLTP" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'SLTP' ? 'selected' : '' ?>>SLTP</option>
                                            <option value="SLTA" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'SLTA' ? 'selected' : '' ?>>SLTA</option>
                                            <option value="D1" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'D1' ? 'selected' : '' ?>>D1</option>
                                            <option value="D2" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'D2' ? 'selected' : '' ?>>D2</option>
                                            <option value="S1" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'S1' ? 'selected' : '' ?>>S1</option>
                                            <option value="S2" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'S2' ? 'selected' : '' ?>>S2</option>
                                            <option value="S3" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'S3' ? 'selected' : '' ?>>S3</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Kontak -->
                        <div class="form-section">
                            <h5><i class="bi bi-telephone me-2"></i>Informasi Kontak</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nomor HP <span class="text-danger">*</span></label>
                                        <input type="tel" name="no_hp" class="form-control" 
                                               pattern="[0-9]{10,15}" 
                                               value="<?= isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '' ?>" 
                                               required>
                                        <div class="form-text">Contoh: 08123456789</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea name="alamat_lengkap" class="form-control" rows="3" 
                                          placeholder="Masukkan alamat lengkap beserta RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten" 
                                          required><?= isset($_POST['alamat_lengkap']) ? htmlspecialchars($_POST['alamat_lengkap']) : '' ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Pilihan Waktu -->
                        <div class="form-section">
                            <h5><i class="bi bi-clock me-2"></i>Pilihan Waktu Kursus</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Jam Pilihan <span class="text-danger">*</span></label>
                                <select name="jam_pilihan" class="form-select" required>
                                    <option value="">Pilih Jam Kursus</option>
                                    <option value="08.00 - 09.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '08.00 - 09.00' ? 'selected' : '' ?>>08.00 - 09.00</option>
                                    <option value="09.00 - 10.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '09.00 - 10.00' ? 'selected' : '' ?>>09.00 - 10.00</option>
                                    <option value="10.00 - 11.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '10.00 - 11.00' ? 'selected' : '' ?>>10.00 - 11.00</option>
                                    <option value="11.00 - 12.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '11.00 - 12.00' ? 'selected' : '' ?>>11.00 - 12.00</option>
                                    <option value="13.00 - 14.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '13.00 - 14.00' ? 'selected' : '' ?>>13.00 - 14.00</option>
                                    <option value="14.00 - 15.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '14.00 - 15.00' ? 'selected' : '' ?>>14.00 - 15.00</option>
                                    <option value="15.00 - 16.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '15.00 - 16.00' ? 'selected' : '' ?>>15.00 - 16.00</option>
                                    <option value="16.00 - 17.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '16.00 - 17.00' ? 'selected' : '' ?>>16.00 - 17.00</option>
                                    <option value="17.00 - 18.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '17.00 - 18.00' ? 'selected' : '' ?>>17.00 - 18.00</option>
                                    <option value="19.00 - 20.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '19.00 - 20.00' ? 'selected' : '' ?>>19.00 - 20.00</option>
                                    <option value="20.00 - 21.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '20.00 - 21.00' ? 'selected' : '' ?>>20.00 - 21.00</option>
                                    <option value="21.00 - 22.00" <?= isset($_POST['jam_pilihan']) && $_POST['jam_pilihan'] == '21.00 - 22.00' ? 'selected' : '' ?>>21.00 - 22.00</option>
                                </select>
                                <div class="form-text">Pilih waktu yang sesuai dengan jadwal Anda</div>
                            </div>
                        </div>
                        
                        <!-- Upload Dokumen -->
                        <div class="form-section">
                            <h5><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload Dokumen</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Pas Foto <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="pas_foto" class="form-control" 
                                                   accept="image/jpeg,image/png,image/jpg" required>
                                            <div class="mt-2">
                                                <i class="bi bi-camera display-6 text-muted"></i>
                                                <p class="mb-0">Format: JPG, PNG (Max: 5MB)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">KTP <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="ktp" class="form-control" 
                                                   accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                                            <div class="mt-2">
                                                <i class="bi bi-card-text display-6 text-muted"></i>
                                                <p class="mb-0">Format: JPG, PNG, PDF (Max: 5MB)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Kartu Keluarga <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="kk" class="form-control" 
                                                   accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                                            <div class="mt-2">
                                                <i class="bi bi-people display-6 text-muted"></i>
                                                <p class="mb-0">Format: JPG, PNG, PDF (Max: 5MB)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Ijazah Terakhir <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="ijazah" class="form-control" 
                                                   accept="image/jpeg,image/png,image/jpg,application/pdf" required>
                                            <div class="mt-2">
                                                <i class="bi bi-award display-6 text-muted"></i>
                                                <p class="mb-0">Format: JPG, PNG, PDF (Max: 5MB)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Persetujuan -->
                        <div class="form-section">
                            <h5><i class="bi bi-check-square me-2"></i>Persetujuan</h5>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agreement" required>
                                <label class="form-check-label" for="agreement">
                                    Saya menyatakan bahwa data yang saya isi adalah benar dan saya bersedia mengikuti 
                                    semua aturan dan ketentuan yang berlaku di LKP Pradata Komputer.
                                </label>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-send me-2"></i>
                                Daftar ke <?= $gelombangAktif['nama_gelombang'] ?>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Pesan Pendaftaran Tutup -->
                <div class="closed-registration">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <h3>Pendaftaran Ditutup</h3>
                    <p class="lead"><?= $pesanTutup ?></p>
                    <div class="mt-4">
                        <h5>Informasi Lebih Lanjut:</h5>
                        <p>
                            <i class="bi bi-telephone me-2"></i><strong>Telp:</strong> (0517) 123456<br>
                            <i class="bi bi-envelope me-2"></i><strong>Email:</strong> info@lkppradata.com<br>
                            <i class="bi bi-geo-alt me-2"></i><strong>Alamat:</strong> Jl. Contoh No. 123, Kota Anda
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 10000);
                }
            });
            
            // File upload drag and drop
            const fileUploads = document.querySelectorAll('.file-upload');
            fileUploads.forEach(upload => {
                const input = upload.querySelector('input[type="file"]');
                
                upload.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    upload.classList.add('dragover');
                });
                
                upload.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    upload.classList.remove('dragover');
                });
                
                upload.addEventListener('drop', function(e) {
                    e.preventDefault();
                    upload.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        input.files = files;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
            
            // Form validation
            const form = document.getElementById('registrationForm');
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mendaftar...';
                
                // Validate file sizes
                const fileInputs = form.querySelectorAll('input[type="file"]');
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                for (let input of fileInputs) {
                    if (input.files[0] && input.files[0].size > maxSize) {
                        e.preventDefault();
                        alert('File ' + input.name + ' terlalu besar. Maksimal 5MB.');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Daftar ke <?= $gelombangAktif['nama_gelombang'] ?>';
                        return;
                    }
                }
                
                // Re-enable after 5 seconds (fallback)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Daftar ke <?= $gelombangAktif['nama_gelombang'] ?>';
                }, 5000);
            });
            
            // NIK validation
            const nikInput = document.querySelector('input[name="nik"]');
            if (nikInput) {
                nikInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
            
            // Phone number validation
            const phoneInput = document.querySelector('input[name="no_hp"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>