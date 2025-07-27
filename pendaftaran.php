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
        // Handle file upload sesuai struktur siswa
        $pas_foto = '';
        $ktp = '';
        $kk = '';
        $ijazah = '';
        
        // Definisikan folder upload sesuai struktur siswa
        $upload_folders = [
            'pas_foto' => 'uploads/pas_foto/',
            'ktp' => 'uploads/ktp/',
            'kk' => 'uploads/kk/',
            'ijazah' => 'uploads/ijazah/'
        ];
        
        // Buat folder jika belum ada
        foreach ($upload_folders as $folder) {
            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }
        }
        
        // Definisikan allowed types sesuai dengan sistem siswa
        $allowed_document_types = ['application/pdf']; // Hanya PDF untuk dokumen resmi
        $allowed_image_types = ['image/jpeg', 'image/png', 'image/jpg']; // Gambar untuk pas foto
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Upload pas foto (HANYA GAMBAR)
        if (isset($_FILES['pas_foto']) && $_FILES['pas_foto']['error'] == 0) {
            $file_tmp = $_FILES['pas_foto']['tmp_name'];
            $file_type = $_FILES['pas_foto']['type'];
            $file_size = $_FILES['pas_foto']['size'];
            
            if (in_array($file_type, $allowed_image_types) && $file_size <= $max_size) {
                $extension = strtolower(pathinfo($_FILES['pas_foto']['name'], PATHINFO_EXTENSION));
                $pas_foto = time() . '_' . $nik . '_pasfoto.' . $extension;
                
                if (!move_uploaded_file($file_tmp, $upload_folders['pas_foto'] . $pas_foto)) {
                    $error = "Gagal mengupload pas foto!";
                }
            } else {
                $error = "Pas foto harus berupa gambar JPG/PNG maksimal 5MB!";
            }
        } else {
            $error = "Pas foto wajib diupload!";
        }
        
        // Upload KTP (HANYA PDF)
        if (!isset($error) && isset($_FILES['ktp']) && $_FILES['ktp']['error'] == 0) {
            $file_tmp = $_FILES['ktp']['tmp_name'];
            $file_type = $_FILES['ktp']['type'];
            $file_size = $_FILES['ktp']['size'];
            
            if (in_array($file_type, $allowed_document_types) && $file_size <= $max_size) {
                $ktp = time() . '_' . $nik . '_ktp.pdf';
                
                if (!move_uploaded_file($file_tmp, $upload_folders['ktp'] . $ktp)) {
                    $error = "Gagal mengupload KTP!";
                }
            } else {
                $error = "KTP harus berupa file PDF maksimal 5MB!";
            }
        } else if (!isset($error)) {
            $error = "KTP wajib diupload dalam format PDF!";
        }
        
        // Upload KK (HANYA PDF)
        if (!isset($error) && isset($_FILES['kk']) && $_FILES['kk']['error'] == 0) {
            $file_tmp = $_FILES['kk']['tmp_name'];
            $file_type = $_FILES['kk']['type'];
            $file_size = $_FILES['kk']['size'];
            
            if (in_array($file_type, $allowed_document_types) && $file_size <= $max_size) {
                $kk = time() . '_' . $nik . '_kk.pdf';
                
                if (!move_uploaded_file($file_tmp, $upload_folders['kk'] . $kk)) {
                    $error = "Gagal mengupload Kartu Keluarga!";
                }
            } else {
                $error = "Kartu Keluarga harus berupa file PDF maksimal 5MB!";
            }
        } else if (!isset($error)) {
            $error = "Kartu Keluarga wajib diupload dalam format PDF!";
        }
        
        // Upload Ijazah (HANYA PDF)
        if (!isset($error) && isset($_FILES['ijazah']) && $_FILES['ijazah']['error'] == 0) {
            $file_tmp = $_FILES['ijazah']['tmp_name'];
            $file_type = $_FILES['ijazah']['type'];
            $file_size = $_FILES['ijazah']['size'];
            
            if (in_array($file_type, $allowed_document_types) && $file_size <= $max_size) {
                $ijazah = time() . '_' . $nik . '_ijazah.pdf';
                
                if (!move_uploaded_file($file_tmp, $upload_folders['ijazah'] . $ijazah)) {
                    $error = "Gagal mengupload Ijazah!";
                }
            } else {
                $error = "Ijazah harus berupa file PDF maksimal 5MB!";
            }
        } else if (!isset($error)) {
            $error = "Ijazah wajib diupload dalam format PDF!";
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
                $success = "Pendaftaran berhasil! Berkas akan dicek admin terlebih dahulu. Apabila diterima maka akan mendapatkan email paling lambat 1 minggu sebelum kelas dimulai.";
                $pendaftar_id = mysqli_insert_id($conn);
                
                // Reset form
                unset($_POST);
            } else {
                $error = "Terjadi kesalahan: " . mysqli_error($conn);
                
                // Hapus file yang sudah diupload jika database gagal
                if ($pas_foto && file_exists($upload_folders['pas_foto'] . $pas_foto)) {
                    unlink($upload_folders['pas_foto'] . $pas_foto);
                }
                if ($ktp && file_exists($upload_folders['ktp'] . $ktp)) {
                    unlink($upload_folders['ktp'] . $ktp);
                }
                if ($kk && file_exists($upload_folders['kk'] . $kk)) {
                    unlink($upload_folders['kk'] . $kk);
                }
                if ($ijazah && file_exists($upload_folders['ijazah'] . $ijazah)) {
                    unlink($upload_folders['ijazah'] . $ijazah);
                }
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
            background: white;
            border-radius: 12px;
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }

        .header-content {
            padding: 2rem;
            text-align: left;
            border-top: 4px solid #0c63e4;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }

        .header-subtitle {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0;
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
        
        /* Clean Closed Registration Component - REMOVED */
        
        .container-form {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        @media (max-width: 768px) {
            .registration-header {
                margin-bottom: 1.5rem;
            }

            .header-image {
                height: 150px;
            }

            .header-content {
                padding: 1.5rem;
            }

            .header-title {
                font-size: 1.5rem;
            }
            
            .registration-form {
                padding: 1.5rem;
            }

            .container-form {
                padding: 0 10px;
            }
            
            .closed-registration {
                margin: 1rem;
                padding: 2rem 1.5rem;
                border-radius: 15px;
            }
            
            .closed-registration h3 {
                font-size: 2rem;
            }
            
            .closed-registration .main-icon {
                font-size: 3rem;
            }
            
            .info-section {
                padding: 1.5rem;
            }
            
            .contact-item {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }
            
            .contact-item i {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="container-form">
            <!-- Header -->
            <div class="registration-header mt-4">
                <img src="assets/img/OPENING.jpg" alt="LKP Pradata Komputer" class="header-image">
                <div class="header-content">
                    <h1 class="header-title">Form Pendaftaran LKP Pradata Komputer Kabupaten Tabalong</h1>
                    <p class="header-subtitle">Program Tabalong Smart - Menciptakan SDM Unggul melalui Pelatihan Komputer dan Aplikasi Perkantoran</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= $success ?>
                    <?php if(isset($pendaftar_id)): ?>
                        <br>
                        <strong>Nomor Pendaftaran:</strong> <?= str_pad($pendaftar_id, 6, '0', STR_PAD_LEFT) ?>
                        <br>
                        <small>Silakan catat nomor pendaftaran ini untuk keperluan verifikasi.</small>
                    <?php endif; ?>
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
                        <div class="col-12">
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
                                        <div class="form-text">Nomor Induk Kependudukan sesuai KTP (16 digit angka)</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" name="nama_pendaftar" class="form-control" 
                                               value="<?= isset($_POST['nama_pendaftar']) ? htmlspecialchars($_POST['nama_pendaftar']) : '' ?>" 
                                               required>
                                        <div class="form-text">Nama lengkap sesuai KTP</div>
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
                                        <div class="form-text">Tempat lahir sesuai KTP</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                        <input type="date" name="tanggal_lahir" class="form-control" 
                                               value="<?= isset($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : '' ?>" 
                                               required>
                                        <div class="form-text">Tanggal lahir sesuai KTP</div>
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
                                        <div class="form-text">Pilih jenis kelamin sesuai KTP</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Pendidikan Terakhir <span class="text-danger">*</span></label>
                                        <select name="pendidikan_terakhir" class="form-select" required>
                                            <option value="">Pilih Pendidikan Terakhir</option>
                                            <option value="SD" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'SD' ? 'selected' : '' ?>>SD</option>
                                            <option value="SLTP" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'SLTP' ? 'selected' : '' ?>>SLTP (SMP)</option>
                                            <option value="SLTA" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'SLTA' ? 'selected' : '' ?>>SLTA (SMA/SMK)</option>
                                            <option value="D1" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'D1' ? 'selected' : '' ?>>D1</option>
                                            <option value="D2" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'D2' ? 'selected' : '' ?>>D2</option>
                                            <option value="S1" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'S1' ? 'selected' : '' ?>>S1</option>
                                            <option value="S2" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'S2' ? 'selected' : '' ?>>S2</option>
                                            <option value="S3" <?= isset($_POST['pendidikan_terakhir']) && $_POST['pendidikan_terakhir'] == 'S3' ? 'selected' : '' ?>>S3</option>
                                        </select>
                                        <div class="form-text">Pendidikan formal terakhir yang ditempuh</div>
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
                                        <div class="form-text">Nomor yang dapat dihubungi via WhatsApp (contoh: 08123456789)</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                               required>
                                        <div class="form-text">Email aktif untuk menerima informasi pendaftaran</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea name="alamat_lengkap" class="form-control" rows="3" 
                                          placeholder="Masukkan alamat lengkap beserta RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten" 
                                          required><?= isset($_POST['alamat_lengkap']) ? htmlspecialchars($_POST['alamat_lengkap']) : '' ?></textarea>
                                <div class="form-text">Alamat domisili saat ini (tidak harus sesuai KTP)</div>
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
                                <div class="form-text">Pilih waktu yang sesuai dengan jadwal Anda (dapat disesuaikan kemudian)</div>
                            </div>
                        </div>
                        
                        <!-- Upload Dokumen -->
                        <div class="form-section">
                            <h5><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload Dokumen</h5>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Penting:</strong> 
                                <ul class="mb-0 mt-2">
                                    <li>Pas foto: Format gambar JPG/PNG maksimal 5MB</li>
                                    <li>KTP, Kartu Keluarga, Ijazah: <strong>Harus format PDF maksimal 5MB</strong></li>
                                    <li>Pastikan dokumen terlihat jelas dan dapat dibaca</li>
                                </ul>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Pas Foto <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="pas_foto" class="form-control" 
                                                   accept="image/jpeg,image/png,image/jpg" required>
                                            <div class="mt-2">
                                                <i class="bi bi-camera display-6 text-muted"></i>
                                                <p class="mb-0"><strong>Format: JPG, PNG</strong></p>
                                            </div>
                                        </div>
                                        <div class="form-text">Foto formal terbaru dengan latar belakang putih atau merah</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">KTP <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="ktp" class="form-control" 
                                                   accept="application/pdf" required>
                                            <div class="mt-2">
                                                <i class="bi bi-file-pdf display-6 text-danger"></i>
                                                <p class="mb-0"><strong>Format: PDF</strong></p>
                                            </div>
                                        </div>
                                        <div class="form-text">Scan/foto KTP yang sudah dikonversi ke PDF</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Kartu Keluarga <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="kk" class="form-control" 
                                                   accept="application/pdf" required>
                                            <div class="mt-2">
                                                <i class="bi bi-file-pdf display-6 text-danger"></i>
                                                <p class="mb-0"><strong>Format: PDF</strong></p>
                                            </div>
                                        </div>
                                        <div class="form-text">Scan/foto Kartu Keluarga yang sudah dikonversi ke PDF</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Ijazah Terakhir <span class="text-danger">*</span></label>
                                        <div class="file-upload">
                                            <input type="file" name="ijazah" class="form-control" 
                                                   accept="application/pdf" required>
                                            <div class="mt-2">
                                                <i class="bi bi-file-pdf display-6 text-danger"></i>
                                                <p class="mb-0"><strong>Format: PDF</strong></p>
                                            </div>
                                        </div>
                                        <div class="form-text">Scan/foto ijazah pendidikan terakhir yang sudah dikonversi ke PDF</div>
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
                            <div class="form-text">Dengan mencentang kotak ini, Anda menyetujui syarat dan ketentuan</div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-kembali me-1">
                               Kembali
                            </a>
                            <button type="submit" class="btn btn-kirim-soft btn-lg px-4">
                                <i class="bi bi-send me-1"></i>Daftar Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Alert Pendaftaran Ditutup -->
                <?php 
                // Cek apakah ditutup karena kuota penuh
                $isKuotaPenuh = ($gelombangAktif && $gelombangAktif['kuota_maksimal'] > 0 && $totalPendaftar >= $gelombangAktif['kuota_maksimal']);
                ?>
                
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading">
                        <i class="bi bi-exclamation-triangle me-2"></i>Pendaftaran Ditutup
                    </h4>
                    <p class="mb-3"><?= $pesanTutup ?></p>
                    
                    <?php if ($isKuotaPenuh): ?>
                        <hr>
                        <p class="mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Jangan khawatir!</strong> Kami akan segera membuka pendaftaran untuk gelombang berikutnya. 
                            Pastikan Anda mendaftar lebih awal di gelombang selanjutnya.
                        </p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <strong>Informasi lebih lanjut:</strong><br>
                        <i class="bi bi-telephone me-1"></i> <strong>Telp:</strong> (0526) 2023798<br> 
                        <i class="bi bi-envelope me-1"></i> <strong>Email:</strong>awiekpradata@gmail.com<br>
                        <i class="bi bi-geo-alt me-1"></i> <strong>Alamat:</strong> Jl. Ketimun S. 21 No. 3A Komplek Pertamina. Tanjung - Tabalong - Kalimantan Selatan 71571
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-kembali">
                            Kembali ke Beranda
                        </a>
                        <?php if ($isKuotaPenuh): ?>
                            <button type="button" class="btn btn-success" onclick="alert('Fitur notifikasi gelombang baru akan segera tersedia!')">
                                <i class="bi bi-bell me-1"></i>Beritahu Saya Gelombang Baru
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }, 15000); // 15 detik untuk membaca pesan sukses
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
            if (form) {
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
                            submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Daftar Sekarang';
                            return;
                        }
                    }
                    
                    // Re-enable after 5 seconds (fallback)
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Daftar Sekarang';
                    }, 5000);
                });
            }
            
            // NIK validation - only numbers, max 16 digits
            const nikInput = document.querySelector('input[name="nik"]');
            if (nikInput) {
                nikInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 16) {
                        this.value = this.value.slice(0, 16);
                    }
                });
            }
            
            // Phone number validation - only numbers, max 15 digits
            const phoneInput = document.querySelector('input[name="no_hp"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 15) {
                        this.value = this.value.slice(0, 15);
                    }
                });
            }

            // File input change events for better UX
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const upload = this.closest('.file-upload');
                    const fileName = this.files[0] ? this.files[0].name : '';
                    
                    if (fileName) {
                        upload.style.borderColor = '#28a745';
                        upload.style.backgroundColor = '#f8fff9';
                        
                        // Add file name display
                        let fileNameDisplay = upload.querySelector('.file-name-display');
                        if (!fileNameDisplay) {
                            fileNameDisplay = document.createElement('small');
                            fileNameDisplay.className = 'file-name-display text-success mt-1 d-block';
                            upload.appendChild(fileNameDisplay);
                        }
                        fileNameDisplay.innerHTML = '<i class="bi bi-check-circle me-1"></i>' + fileName;
                    }
                });
            });
        });
    </script>
</body>
</html>