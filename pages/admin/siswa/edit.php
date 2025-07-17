<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'siswa'; 
$baseURL = '../';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID siswa tidak valid!";
    header("Location: index.php");
    exit;
}

$id_siswa = (int)$_GET['id'];

// Ambil data siswa
$siswaQuery = "SELECT s.*, u.username FROM siswa s 
               LEFT JOIN user u ON s.id_user = u.id_user 
               WHERE s.id_siswa = '$id_siswa'";
$siswaResult = mysqli_query($conn, $siswaQuery);

if (mysqli_num_rows($siswaResult) == 0) {
    $_SESSION['error'] = "Data siswa tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$siswa = mysqli_fetch_assoc($siswaResult);

// Ambil data kelas untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE g.status = 'aktif' OR g.status = 'dibuka'
               ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $tempat_lahir = mysqli_real_escape_string($conn, $_POST['tempat_lahir']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $pendidikan_terakhir = $_POST['pendidikan_terakhir'];
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $alamat_lengkap = mysqli_real_escape_string($conn, $_POST['alamat_lengkap']);
    $id_kelas = !empty($_POST['id_kelas']) ? $_POST['id_kelas'] : NULL;
    $status_aktif = $_POST['status_aktif'];
    
    // Handle file uploads
    $pas_foto = $siswa['pas_foto'];
    $ktp_file = $siswa['ktp'];
    $kk_file = $siswa['kk'];
    $ijazah_file = $siswa['ijazah'];
    
    // Handle file deletion
    if (isset($_POST['hapus_pas_foto']) && $_POST['hapus_pas_foto'] == '1') {
        if (!empty($siswa['pas_foto']) && file_exists("../../../uploads/pas_foto/" . $siswa['pas_foto'])) {
            unlink("../../../uploads/pas_foto/" . $siswa['pas_foto']);
        }
        $pas_foto = '';
    }
    
    if (isset($_POST['hapus_ktp']) && $_POST['hapus_ktp'] == '1') {
        if (!empty($siswa['ktp']) && file_exists("../../../uploads/ktp/" . $siswa['ktp'])) {
            unlink("../../../uploads/ktp/" . $siswa['ktp']);
        }
        $ktp_file = '';
    }
    
    if (isset($_POST['hapus_kk']) && $_POST['hapus_kk'] == '1') {
        if (!empty($siswa['kk']) && file_exists("../../../uploads/kk/" . $siswa['kk'])) {
            unlink("../../../uploads/kk/" . $siswa['kk']);
        }
        $kk_file = '';
    }
    
    if (isset($_POST['hapus_ijazah']) && $_POST['hapus_ijazah'] == '1') {
        if (!empty($siswa['ijazah']) && file_exists("../../../uploads/ijazah/" . $siswa['ijazah'])) {
            unlink("../../../uploads/ijazah/" . $siswa['ijazah']);
        }
        $ijazah_file = '';
    }
    
    // Upload pas foto baru
    if (!empty($_FILES['pas_foto']['name'])) {
        // Hapus file lama jika ada
        if (!empty($siswa['pas_foto']) && file_exists("../../../uploads/pas_foto/" . $siswa['pas_foto'])) {
            unlink("../../../uploads/pas_foto/" . $siswa['pas_foto']);
        }
        
        $targetDir = "../../../uploads/pas_foto/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['pas_foto']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $pas_foto = time() . '_' . uniqid() . '.' . $fileExtension;
            if (!move_uploaded_file($_FILES['pas_foto']['tmp_name'], $targetDir . $pas_foto)) {
                $pas_foto = $siswa['pas_foto']; // Kembalikan ke file lama jika gagal
                $error = "Gagal mengupload foto profil.";
            }
        } else {
            $error = "Format foto tidak didukung. Gunakan JPG, JPEG, atau PNG.";
        }
    }
    
    // Upload KTP baru
    if (!empty($_FILES['ktp']['name'])) {
        // Hapus file lama jika ada
        if (!empty($siswa['ktp']) && file_exists("../../../uploads/ktp/" . $siswa['ktp'])) {
            unlink("../../../uploads/ktp/" . $siswa['ktp']);
        }
        
        $targetDir = "../../../uploads/ktp/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['ktp']['name'], PATHINFO_EXTENSION));
        if ($fileExtension == 'pdf') {
            $ktp_file = time() . '_ktp_' . uniqid() . '.pdf';
            if (!move_uploaded_file($_FILES['ktp']['tmp_name'], $targetDir . $ktp_file)) {
                $ktp_file = $siswa['ktp']; // Kembalikan ke file lama jika gagal
                $error = "Gagal mengupload dokumen KTP.";
            }
        } else {
            $error = "Format KTP tidak didukung. Gunakan PDF.";
        }
    }
    
    // Upload KK baru
    if (!empty($_FILES['kk']['name'])) {
        // Hapus file lama jika ada
        if (!empty($siswa['kk']) && file_exists("../../../uploads/kk/" . $siswa['kk'])) {
            unlink("../../../uploads/kk/" . $siswa['kk']);
        }
        
        $targetDir = "../../../uploads/kk/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['kk']['name'], PATHINFO_EXTENSION));
        if ($fileExtension == 'pdf') {
            $kk_file = time() . '_kk_' . uniqid() . '.pdf';
            if (!move_uploaded_file($_FILES['kk']['tmp_name'], $targetDir . $kk_file)) {
                $kk_file = $siswa['kk']; // Kembalikan ke file lama jika gagal
                $error = "Gagal mengupload dokumen Kartu Keluarga.";
            }
        } else {
            $error = "Format Kartu Keluarga tidak didukung. Gunakan PDF.";
        }
    }
    
    // Upload Ijazah baru
    if (!empty($_FILES['ijazah']['name'])) {
        // Hapus file lama jika ada
        if (!empty($siswa['ijazah']) && file_exists("../../../uploads/ijazah/" . $siswa['ijazah'])) {
            unlink("../../../uploads/ijazah/" . $siswa['ijazah']);
        }
        
        $targetDir = "../../../uploads/ijazah/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['ijazah']['name'], PATHINFO_EXTENSION));
        if ($fileExtension == 'pdf') {
            $ijazah_file = time() . '_ijazah_' . uniqid() . '.pdf';
            if (!move_uploaded_file($_FILES['ijazah']['tmp_name'], $targetDir . $ijazah_file)) {
                $ijazah_file = $siswa['ijazah']; // Kembalikan ke file lama jika gagal
                $error = "Gagal mengupload dokumen ijazah.";
            }
        } else {
            $error = "Format ijazah tidak didukung. Gunakan PDF.";
        }
    }
    
    if (!isset($error)) {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update data siswa
            $query = "UPDATE siswa SET 
                      nama = '$nama',
                      tempat_lahir = '$tempat_lahir',
                      tanggal_lahir = '$tanggal_lahir',
                      jenis_kelamin = '$jenis_kelamin',
                      pendidikan_terakhir = '$pendidikan_terakhir',
                      no_hp = '$no_hp',
                      email = '$email',
                      alamat_lengkap = '$alamat_lengkap',
                      id_kelas = " . ($id_kelas ? "'$id_kelas'" : "NULL") . ",
                      pas_foto = '$pas_foto',
                      ktp = '$ktp_file',
                      kk = '$kk_file',
                      ijazah = '$ijazah_file',
                      status_aktif = '$status_aktif'
                      WHERE id_siswa = '$id_siswa'";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception("Gagal memperbarui data siswa: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $_SESSION['success'] = "Data siswa berhasil diperbarui!";
            header("Location: index.php");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Data Siswa</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>
<body>
<div class="d-flex">
  <?php include '../../../includes/sidebar/admin.php'; ?>

  <div class="flex-fill main-content">
    
    <!-- TOP NAVBAR -->
    <nav class="top-navbar">
      <div class="container-fluid px-3 px-md-4">
        <div class="d-flex align-items-center">
          <!-- Left: Hamburger + Page Info -->
          <div class="d-flex align-items-center flex-grow-1">
            <!-- Sidebar Toggle Button -->
            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
              <i class="bi bi-list fs-4"></i>
            </button>
            
            <!-- Page Title & Breadcrumb -->
            <div class="page-info">
              <h2 class="page-title mb-1">EDIT DATA SISWA</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Akademik</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Siswa</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Edit Data</li>
                </ol>
              </nav>
            </div>
          </div>
          
          <!-- Right: Date Info -->
          <div class="d-flex align-items-center">
            <div class="navbar-page-info d-none d-xl-block">
              <small class="text-muted">
                <i class="bi bi-calendar3 me-1"></i>
                <?= date('d M Y') ?>
              </small>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <div class="container-fluid mt-4">
      <!-- Alert Error -->
      <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <?= $error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Main Form Card -->
      <div class="card content-card">
        <div class="section-header">
          <h5 class="mb-0 text-dark">
            <i class="bi bi-person-gear me-2"></i>Form Edit Siswa
          </h5>
          <small class="text-muted">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
        </div>

        <div class="card-body">
          <form action="" method="post" enctype="multipart/form-data" id="formEditSiswa">
            <div class="row">
              
              <!-- Data Pribadi Section -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-person-circle me-2"></i>Data Pribadi
                </h6>
                
                <div class="mb-4">
                  <label class="form-label">NIK</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($siswa['nik']) ?>" readonly style="background-color: #f8f9fa;">
                  <div class="form-text"><small>NIK tidak dapat diubah</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Nama Lengkap</label>
                  <input type="text" name="nama" class="form-control" required 
                         value="<?= htmlspecialchars($siswa['nama']) ?>">
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Tempat Lahir</label>
                      <input type="text" name="tempat_lahir" class="form-control" required 
                             value="<?= htmlspecialchars($siswa['tempat_lahir']) ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Tanggal Lahir</label>
                      <input type="date" name="tanggal_lahir" class="form-control" required
                             value="<?= $siswa['tanggal_lahir'] ?>">
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Jenis Kelamin</label>
                      <select name="jenis_kelamin" class="form-select" required>
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="Laki-Laki" <?= ($siswa['jenis_kelamin'] == 'Laki-Laki') ? 'selected' : '' ?>>Laki-Laki</option>
                        <option value="Perempuan" <?= ($siswa['jenis_kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Pendidikan Terakhir</label>
                      <select name="pendidikan_terakhir" class="form-select" required>
                        <option value="">Pilih Pendidikan</option>
                        <option value="SD" <?= ($siswa['pendidikan_terakhir'] == 'SD') ? 'selected' : '' ?>>SD</option>
                        <option value="SLTP" <?= ($siswa['pendidikan_terakhir'] == 'SLTP') ? 'selected' : '' ?>>SLTP (SMP)</option>
                        <option value="SLTA" <?= ($siswa['pendidikan_terakhir'] == 'SLTA') ? 'selected' : '' ?>>SLTA (SMA/SMK)</option>
                        <option value="D1" <?= ($siswa['pendidikan_terakhir'] == 'D1') ? 'selected' : '' ?>>D1</option>
                        <option value="D2" <?= ($siswa['pendidikan_terakhir'] == 'D2') ? 'selected' : '' ?>>D2</option>
                        <option value="S1" <?= ($siswa['pendidikan_terakhir'] == 'S1') ? 'selected' : '' ?>>S1</option>
                        <option value="S2" <?= ($siswa['pendidikan_terakhir'] == 'S2') ? 'selected' : '' ?>>S2</option>
                        <option value="S3" <?= ($siswa['pendidikan_terakhir'] == 'S3') ? 'selected' : '' ?>>S3</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Alamat Lengkap</label>
                  <textarea name="alamat_lengkap" class="form-control" rows="3" required><?= htmlspecialchars($siswa['alamat_lengkap']) ?></textarea>
                  <div class="form-text"><small>Alamat sesuai KTP</small></div>
                </div>
              </div>

              <!-- Data Kontak & Status -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-telephone me-2"></i>Data Kontak & Status
                </h6>

                <div class="mb-4">
                  <label class="form-label required">Nomor Handphone</label>
                  <input type="tel" name="no_hp" class="form-control" required 
                         value="<?= htmlspecialchars($siswa['no_hp']) ?>">
                  <div class="form-text"><small>Nomor yang dapat dihubungi via WhatsApp</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" 
                         value="<?= htmlspecialchars($siswa['email']) ?>">
                </div>

                <div class="mb-4">
                  <label class="form-label">Kelas Pelatihan</label>
                  <select name="id_kelas" class="form-select">
                    <option value="">Belum ditentukan</option>
                    <?php 
                    mysqli_data_seek($kelasResult, 0); // Reset pointer
                    while($kelas = mysqli_fetch_assoc($kelasResult)): 
                    ?>
                      <option value="<?= $kelas['id_kelas'] ?>" 
                              <?= ($siswa['id_kelas'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                        <?php if($kelas['nama_gelombang']): ?>
                          - <?= htmlspecialchars($kelas['nama_gelombang']) ?>
                        <?php endif; ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div class="form-text"><small>Dapat diatur kemudian</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Status Siswa</label>
                  <select name="status_aktif" class="form-select" required>
                    <option value="">Pilih Status</option>
                    <option value="aktif" <?= ($siswa['status_aktif'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= ($siswa['status_aktif'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Upload Documents Section -->
            <div class="row mt-5">
              <div class="col-12">
                <h6 class="section-title mb-4">
                  <i class="bi bi-cloud-upload me-2"></i>Dokumen Persyaratan
                </h6>
                <p class="text-muted mb-4">Upload ulang dokumen jika diperlukan. File lama akan diganti dengan file baru.</p>
                
                <div class="row">
                  <!-- Pas Foto -->
                  <div class="col-lg-3 col-md-6 mb-4">
                    <div class="border rounded p-3" style="background-color: #f8f9fa;">
                      <h6 class="mb-3">
                        <i class="bi bi-camera me-1"></i>Pas Foto
                      </h6>
                      
                      <?php if(!empty($siswa['pas_foto'])): ?>
                        <div class="current-file mb-3 p-3 border rounded" style="background-color: #f8f9fa;">
                          <h6 class="mb-3">Foto Saat Ini</h6>
                          <div class="text-center mb-3">
                            <img src="../../../uploads/pas_foto/<?= $siswa['pas_foto'] ?>" 
                                 alt="Pas Foto" 
                                 class="img-thumbnail" 
                                 style="max-width: 150px; max-height: 150px; object-fit: cover;">
                          </div>
                          <div class="text-center mb-3">
                            <a href="../../../uploads/pas_foto/<?= $siswa['pas_foto'] ?>" target="_blank" 
                               class="btn btn-outline-primary btn-sm">
                              <i class="bi bi-eye me-1"></i>Lihat Foto
                            </a>
                          </div>
                          <div class="form-check">
                            <input type="checkbox" name="hapus_pas_foto" value="1" class="form-check-input" id="hapus_pas_foto">
                            <label class="form-check-label text-danger" for="hapus_pas_foto">
                              <i class="bi bi-trash me-1"></i>Hapus foto ini
                            </label>
                          </div>
                        </div>
                      <?php endif; ?>
                      
                      <input type="file" name="pas_foto" class="form-control" accept=".jpg,.jpeg,.png">
                      <div class="form-text"><small>Format yang didukung: JPG, JPEG, PNG (Maks 2MB)</small></div>
                    </div>
                  </div>

                  <!-- KTP -->
                  <div class="col-lg-3 col-md-6 mb-4">
                    <div class="border rounded p-3" style="background-color: #f8f9fa;">
                      <h6 class="mb-3">
                        <i class="bi bi-file-pdf me-1"></i>Scan KTP
                      </h6>
                      
                      <?php if(!empty($siswa['ktp'])): ?>
                        <div class="current-file mb-3 p-3 border rounded" style="background-color: #f8f9fa;">
                          <h6 class="mb-3">File KTP Saat Ini</h6>
                          <div class="text-center mb-3">
                            <i class="bi bi-file-pdf text-danger" style="font-size: 3rem;"></i>
                          </div>
                          <div class="text-center mb-3">
                            <a href="../../../uploads/ktp/<?= $siswa['ktp'] ?>" target="_blank" 
                               class="btn btn-outline-primary btn-sm">
                              <i class="bi bi-eye me-1"></i>Lihat File
                            </a>
                          </div>
                          <div class="form-check">
                            <input type="checkbox" name="hapus_ktp" value="1" class="form-check-input" id="hapus_ktp">
                            <label class="form-check-label text-danger" for="hapus_ktp">
                              <i class="bi bi-trash me-1"></i>Hapus file ini
                            </label>
                          </div>
                        </div>
                      <?php endif; ?>
                      
                      <input type="file" name="ktp" class="form-control" accept=".pdf">
                      <div class="form-text"><small>Format yang didukung: PDF (Maks 5MB)</small></div>
                    </div>
                  </div>

                  <!-- KK -->
                  <div class="col-lg-3 col-md-6 mb-4">
                    <div class="border rounded p-3" style="background-color: #f8f9fa;">
                      <h6 class="mb-3">
                        <i class="bi bi-file-pdf me-1"></i>Kartu Keluarga
                      </h6>
                      
                      <?php if(!empty($siswa['kk'])): ?>
                        <div class="current-file mb-3 p-3 border rounded" style="background-color: #f8f9fa;">
                          <h6 class="mb-3">File KK Saat Ini</h6>
                          <div class="text-center mb-3">
                            <i class="bi bi-file-pdf text-info" style="font-size: 3rem;"></i>
                          </div>
                          <div class="text-center mb-3">
                            <a href="../../../uploads/kk/<?= $siswa['kk'] ?>" target="_blank" 
                               class="btn btn-outline-primary btn-sm">
                              <i class="bi bi-eye me-1"></i>Lihat File
                            </a>
                          </div>
                          <div class="form-check">
                            <input type="checkbox" name="hapus_kk" value="1" class="form-check-input" id="hapus_kk">
                            <label class="form-check-label text-danger" for="hapus_kk">
                              <i class="bi bi-trash me-1"></i>Hapus file ini
                            </label>
                          </div>
                        </div>
                      <?php endif; ?>
                      
                      <input type="file" name="kk" class="form-control" accept=".pdf">
                       <div class="form-text"><small>Format yang didukung: PDF (Maks 5MB)</small></div>
                    </div>
                  </div>

                  <!-- Ijazah -->
                  <div class="col-lg-3 col-md-6 mb-4">
                    <div class="border rounded p-3" style="background-color: #f8f9fa;">
                      <h6 class="mb-3">
                        <i class="bi bi-file-pdf me-1"></i>Ijazah
                      </h6>
                      
                      <?php if(!empty($siswa['ijazah'])): ?>
                        <div class="current-file mb-3 p-3 border rounded" style="background-color: #f8f9fa;">
                          <h6 class="mb-3">File Ijazah Saat Ini</h6>
                          <div class="text-center mb-3">
                            <i class="bi bi-file-pdf text-success" style="font-size: 3rem;"></i>
                          </div>
                          <div class="text-center mb-3">
                            <a href="../../../uploads/ijazah/<?= $siswa['ijazah'] ?>" target="_blank" 
                               class="btn btn-outline-primary btn-sm">
                              <i class="bi bi-eye me-1"></i>Lihat File
                            </a>
                          </div>
                          <div class="form-check">
                            <input type="checkbox" name="hapus_ijazah" value="1" class="form-check-input" id="hapus_ijazah">
                            <label class="form-check-label text-danger" for="hapus_ijazah">
                              <i class="bi bi-trash me-1"></i>Hapus file ini
                            </label>
                          </div>
                        </div>
                      <?php endif; ?>
                      
                      <input type="file" name="ijazah" class="form-control" accept=".pdf">
                      <div class="form-text"><small>Format yang didukung: PDF (Maks 5MB)</small></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
             <div class="row mt-5 pt-4 border-top">
              <div class="col-12">
                <div class="d-flex justify-content-end gap-3">
                  <a href="index.php" class="btn btn-kembali px-3">
                   Kembali
                  </a>
                  <button type="submit" class="btn btn-simpan px-4">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="../../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../../assets/js/scripts.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('formEditSiswa');
  
  // Phone number validation - only numbers, max 13 digits
  const phoneInput = document.querySelector('input[name="no_hp"]');
  if (phoneInput) {
    phoneInput.addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
      if (this.value.length > 13) {
        this.value = this.value.slice(0, 13);
      }
    });
  }

  // File size and type validation
  const fileInputs = document.querySelectorAll('input[type="file"]');
  fileInputs.forEach(input => {
    input.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        // Check file size
        const maxSize = this.name === 'pas_foto' ? 2 * 1024 * 1024 : 5 * 1024 * 1024; // 2MB for photo, 5MB for PDF
        
        if (file.size > maxSize) {
          alert(`Ukuran file terlalu besar. Maksimal ${this.name === 'pas_foto' ? '2MB' : '5MB'}`);
          this.value = '';
          return;
        }

        // Check file type
        const allowedTypes = this.name === 'pas_foto' 
          ? ['image/jpeg', 'image/jpg', 'image/png']
          : ['application/pdf'];
        
        if (!allowedTypes.includes(file.type)) {
          alert(`Format file tidak didukung. Gunakan ${this.name === 'pas_foto' ? 'JPG/PNG' : 'PDF'}`);
          this.value = '';
          return;
        }
      }
    });
  });

  // Handle file deletion checkboxes
  const deleteCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="hapus_"]');
  deleteCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      if (this.checked) {
        const confirmDelete = confirm('Apakah Anda yakin ingin menghapus file ini?');
        if (!confirmDelete) {
          this.checked = false;
        }
      }
    });
  });

  // Form submission validation
  form.addEventListener('submit', function(e) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
      if (!field.value.trim()) {
        field.classList.add('is-invalid');
        isValid = false;
      } else {
        field.classList.remove('is-invalid');
      }
    });

    if (!isValid) {
      e.preventDefault();
      alert('Mohon lengkapi semua field yang wajib diisi!');
      return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
  });

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>
</body>
</html>