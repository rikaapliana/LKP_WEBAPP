<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'pengaturan';
$baseURL = '../../';

// Ambil ID gelombang dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID gelombang tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$id_gelombang = (int)$_GET['id'];

// Ambil data gelombang yang akan diedit
$query = "SELECT * FROM gelombang WHERE id_gelombang = $id_gelombang";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Gelombang tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$gelombang = mysqli_fetch_assoc($result);

// Cek apakah gelombang sedang digunakan (untuk membatasi edit)
$cekKelas = mysqli_query($conn, "SELECT COUNT(*) as total FROM kelas WHERE id_gelombang = $id_gelombang");
$jumlahKelas = mysqli_fetch_assoc($cekKelas)['total'];

$cekPengaturan = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengaturan_pendaftaran WHERE id_gelombang = $id_gelombang");
$jumlahPengaturan = mysqli_fetch_assoc($cekPengaturan)['total'];

$isBeingUsed = ($jumlahKelas > 0 || $jumlahPengaturan > 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $nama_gelombang = mysqli_real_escape_string($conn, $_POST['nama_gelombang']);
    $tahun = (int)$_POST['tahun'];
    $gelombang_ke = (int)$_POST['gelombang_ke'];
    $status = $_POST['status'];
    
    // Validasi input
    $errors = [];
    
    if (empty($nama_gelombang)) {
        $errors[] = "Nama gelombang harus diisi";
    }
    
    if ($tahun < 2020 || $tahun > 2030) {
        $errors[] = "Tahun harus antara 2020-2030";
    }
    
    if ($gelombang_ke < 1 || $gelombang_ke > 10) {
        $errors[] = "Gelombang ke harus antara 1-10";
    }
    
    // Cek duplikasi gelombang (kecuali gelombang yang sedang diedit)
    $checkQuery = "SELECT id_gelombang FROM gelombang 
                   WHERE tahun = $tahun AND gelombang_ke = $gelombang_ke 
                   AND id_gelombang != $id_gelombang";
    $checkResult = mysqli_query($conn, $checkQuery);
    if (mysqli_num_rows($checkResult) > 0) {
        $errors[] = "Gelombang ke-$gelombang_ke tahun $tahun sudah ada!";
    }
    
    // Jika sedang digunakan, batasi perubahan tertentu
    if ($isBeingUsed) {
        if ($tahun != $gelombang['tahun'] || $gelombang_ke != $gelombang['gelombang_ke']) {
            $errors[] = "Tidak dapat mengubah tahun atau nomor gelombang karena sudah digunakan!";
        }
    }
    
    if (empty($errors)) {
        try {
            // Update data gelombang
            $updateQuery = "UPDATE gelombang SET 
                           nama_gelombang = '$nama_gelombang',
                           tahun = $tahun,
                           gelombang_ke = $gelombang_ke,
                           status = '$status'
                           WHERE id_gelombang = $id_gelombang";
            
            if (mysqli_query($conn, $updateQuery)) {
                $_SESSION['success'] = "Gelombang '$nama_gelombang' berhasil diperbarui!";
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Gagal memperbarui gelombang: " . mysqli_error($conn);
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Auto-generate suggestions
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Gelombang - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../../includes/sidebar/admin.php'; ?>

    <div class="flex-fill main-content">
      <!-- TOP NAVBAR -->
      <nav class="top-navbar">
        <div class="container-fluid px-3 px-md-4">
          <div class="d-flex align-items-center">
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <div class="page-info">
                <h2 class="page-title mb-1">EDIT GELOMBANG</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="../index.php">Pengaturan</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Kelola Gelombang</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Edit Gelombang</li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <div class="d-flex align-items-center">
              <div class="navbar-page-info d-none d-md-block">
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
        <!-- Alert Errors -->
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Terjadi kesalahan:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
              <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Warning jika sedang digunakan -->
        <?php if ($isBeingUsed): ?>
          <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Perhatian:</strong> Gelombang ini sedang digunakan oleh <?= $jumlahKelas ?> kelas dan <?= $jumlahPengaturan ?> pengaturan pendaftaran. 
            Beberapa field tidak dapat diubah untuk menjaga konsistensi data.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Main Form Card -->
        <div class="row">
          <!-- Form Edit -->
          <div class="col-lg-8">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-pencil me-2"></i>Form Edit Gelombang Pelatihan
                </h5>
              </div>

              <div class="card-body">
                <form method="POST" id="formEditGelombang">
                  <!-- Nama Gelombang -->
                  <div class="mb-4">
                    <label class="form-label required">Nama Gelombang</label>
                    <input type="text" name="nama_gelombang" class="form-control" required 
                           value="<?= isset($_POST['nama_gelombang']) ? htmlspecialchars($_POST['nama_gelombang']) : htmlspecialchars($gelombang['nama_gelombang']) ?>"
                           placeholder="Contoh: Gelombang 1 Tahun 2025">
                    <div class="form-text">Nama yang akan ditampilkan untuk gelombang ini</div>
                  </div>

                  <!-- Tahun dan Gelombang Ke -->
                  <div class="row">
                    <div class="col-md-6">
                      <div class="mb-4">
                        <label class="form-label required">Tahun</label>
                        <select name="tahun" class="form-select" required <?= $isBeingUsed ? 'disabled' : '' ?>>
                          <option value="">Pilih Tahun</option>
                          <?php for ($year = $currentYear + 2; $year >= 2020; $year--): ?>
                            <option value="<?= $year ?>" 
                                    <?= (isset($_POST['tahun']) ? $_POST['tahun'] : $gelombang['tahun']) == $year ? 'selected' : '' ?>>
                              <?= $year ?>
                            </option>
                          <?php endfor; ?>
                        </select>
                        <?php if ($isBeingUsed): ?>
                          <input type="hidden" name="tahun" value="<?= $gelombang['tahun'] ?>">
                          <div class="form-text text-warning">Tidak dapat diubah karena sedang digunakan</div>
                        <?php else: ?>
                          <div class="form-text">Tahun pelaksanaan gelombang</div>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="mb-4">
                        <label class="form-label required">Gelombang Ke</label>
                        <select name="gelombang_ke" class="form-select" required <?= $isBeingUsed ? 'disabled' : '' ?>>
                          <option value="">Pilih Gelombang Ke</option>
                          <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" 
                                    <?= (isset($_POST['gelombang_ke']) ? $_POST['gelombang_ke'] : $gelombang['gelombang_ke']) == $i ? 'selected' : '' ?>>
                              Gelombang Ke-<?= $i ?>
                            </option>
                          <?php endfor; ?>
                        </select>
                        <?php if ($isBeingUsed): ?>
                          <input type="hidden" name="gelombang_ke" value="<?= $gelombang['gelombang_ke'] ?>">
                          <div class="form-text text-warning">Tidak dapat diubah karena sedang digunakan</div>
                        <?php else: ?>
                          <div class="form-text">Urutan gelombang dalam tahun tersebut</div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <!-- Status -->
                  <div class="mb-4">
                    <label class="form-label required">Status</label>
                    <select name="status" class="form-select" required>
                      <option value="">Pilih Status</option>
                      <option value="aktif" <?= (isset($_POST['status']) ? $_POST['status'] : $gelombang['status']) == 'aktif' ? 'selected' : '' ?>>
                        Aktif - Siap untuk digunakan
                      </option>
                      <option value="dibuka" <?= (isset($_POST['status']) ? $_POST['status'] : $gelombang['status']) == 'dibuka' ? 'selected' : '' ?>>
                        Dibuka - Pendaftaran sedang dibuka
                      </option>
                      <option value="selesai" <?= (isset($_POST['status']) ? $_POST['status'] : $gelombang['status']) == 'selesai' ? 'selected' : '' ?>>
                        Selesai - Gelombang sudah berakhir
                      </option>
                    </select>
                    <div class="form-text">Status gelombang saat ini</div>
                  </div>

                  <!-- Button Action -->
                  <div class="d-flex justify-content-end gap-3 pt-4 mt-4 border-top">
                    <a href="index.php" class="btn btn-kembali px-3">
                      Kembali
                    </a>
                    <button type="submit" class="btn btn-simpan px-4">
                      <i class="bi bi-check-lg me-1"></i>Perbarui
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Info Panel -->
          <div class="col-lg-4">
            <!-- Current Info -->
            <div class="card content-card mb-3">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Info Gelombang Saat Ini
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-12">
                    <strong>Nama:</strong><br>
                    <span class="text-muted"><?= htmlspecialchars($gelombang['nama_gelombang']) ?></span>
                  </div>
                  <div class="col-6">
                    <strong>Tahun:</strong><br>
                    <span class="badge bg-light text-dark"><?= $gelombang['tahun'] ?></span>
                  </div>
                  <div class="col-6">
                    <strong>Gelombang:</strong><br>
                    <span class="badge bg-info">Ke-<?= $gelombang['gelombang_ke'] ?></span>
                  </div>
                  <div class="col-12">
                    <strong>Status:</strong><br>
                    <?php 
                    $statusClass = 'secondary';
                    $statusText = 'Draft';
                    $statusIcon = 'pause-circle';
                    
                    switch($gelombang['status']) {
                      case 'aktif':
                        $statusClass = 'success';
                        $statusText = 'Aktif';
                        $statusIcon = 'play-circle';
                        break;
                      case 'dibuka':
                        $statusClass = 'primary';
                        $statusText = 'Dibuka';
                        $statusIcon = 'door-open';
                        break;
                      case 'selesai':
                        $statusClass = 'secondary';
                        $statusText = 'Selesai';
                        $statusIcon = 'check-circle';
                        break;
                    }
                    ?>
                    <span class="badge bg-<?= $statusClass ?>">
                      <i class="bi bi-<?= $statusIcon ?> me-1"></i><?= $statusText ?>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Usage Info -->
            <?php if ($isBeingUsed): ?>
            <div class="card content-card mb-3">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-graph-up me-2"></i>Penggunaan Data
                </h6>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-6">
                    <strong>Kelas:</strong><br>
                    <span class="badge bg-primary"><?= $jumlahKelas ?> kelas</span>
                  </div>
                  <div class="col-6">
                    <strong>Pengaturan:</strong><br>
                    <span class="badge bg-info"><?= $jumlahPengaturan ?> item</span>
                  </div>
                </div>
                <small class="text-muted mt-2 d-block">
                  Data ini membatasi perubahan tahun dan nomor gelombang
                </small>
              </div>
            </div>
            <?php endif; ?>

            <!-- Tips -->
            <div class="card content-card">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-lightbulb me-2"></i>Tips Edit Gelombang
                </h6>
              </div>
              <div class="card-body">
                <div class="alert alert-info">
                  <ul class="mb-0 small">
                    <li>Nama gelombang dapat diubah kapan saja</li>
                    <li>Status dapat disesuaikan dengan kondisi terkini</li>
                    <?php if ($isBeingUsed): ?>
                      <li class="text-warning">Tahun dan nomor gelombang dikunci karena sudah digunakan</li>
                    <?php else: ?>
                      <li>Pastikan tidak ada duplikasi tahun dan nomor gelombang</li>
                    <?php endif; ?>
                    <li>Perubahan status akan mempengaruhi tampilan sistem</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../../assets/js/scripts.js"></script>
  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formEditGelombang');
    
    // Form validation
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
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Memperbarui...';
    });
  });
  </script>
</body>
</html>