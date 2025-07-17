<?php
session_start();
require_once '../../../../includes/auth.php';
requireAdminAuth();

include '../../../../includes/db.php';
$activePage = 'pengaturan';
$baseURL = '../../';

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
    
    // Cek duplikasi gelombang
    $checkQuery = "SELECT id_gelombang FROM gelombang WHERE tahun = $tahun AND gelombang_ke = $gelombang_ke";
    $checkResult = mysqli_query($conn, $checkQuery);
    if (mysqli_num_rows($checkResult) > 0) {
        $errors[] = "Gelombang ke-$gelombang_ke tahun $tahun sudah ada!";
    }
    
    if (empty($errors)) {
        try {
            // Insert data gelombang baru
            $insertQuery = "INSERT INTO gelombang (nama_gelombang, tahun, gelombang_ke, status) 
                           VALUES ('$nama_gelombang', $tahun, $gelombang_ke, '$status')";
            
            if (mysqli_query($conn, $insertQuery)) {
                $_SESSION['success'] = "Gelombang '$nama_gelombang' berhasil ditambahkan!";
                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Gagal menyimpan gelombang: " . mysqli_error($conn);
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Auto-generate suggestions
$currentYear = date('Y');
$nextYear = $currentYear + 1;

// Cari gelombang terakhir untuk tahun ini
$lastGelombangQuery = "SELECT MAX(gelombang_ke) as last_gelombang FROM gelombang WHERE tahun = $currentYear";
$lastGelombangResult = mysqli_query($conn, $lastGelombangQuery);
$lastGelombang = mysqli_fetch_assoc($lastGelombangResult)['last_gelombang'] ?? 0;
$suggestedGelombang = $lastGelombang + 1;

// Generate suggested name
$suggestedName = "Gelombang $suggestedGelombang Tahun $currentYear";
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Gelombang Baru - LKP Pradata Komputer</title>
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
                <h2 class="page-title mb-1">TAMBAH GELOMBANG BARU</h2>
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
                    <li class="breadcrumb-item active" aria-current="page">Tambah Gelombang</li>
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

        <!-- Info Suggestion -->
        <div class="alert alert-info alert-dismissible fade show" role="alert">
          <i class="bi bi-lightbulb me-2"></i>
          <strong>Saran:</strong> Gelombang terakhir tahun <?= $currentYear ?> adalah ke-<?= $lastGelombang ?>. 
          Disarankan membuat gelombang ke-<?= $suggestedGelombang ?> untuk tahun <?= $currentYear ?>.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

<!-- Main Form Card -->
        <div class="row">
          <!-- Form Tambah -->
          <div class="col-lg-8">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-plus-circle me-2"></i>Form Tambah Gelombang Pelatihan
                </h5>
              </div>

              <div class="card-body">
                <form method="POST" id="formTambahGelombang">
                  <!-- Nama Gelombang -->
                  <div class="mb-4">
                    <label class="form-label required">Nama Gelombang</label>
                    <input type="text" name="nama_gelombang" class="form-control" required 
                           value="<?= isset($_POST['nama_gelombang']) ? htmlspecialchars($_POST['nama_gelombang']) : $suggestedName ?>"
                           placeholder="Contoh: Gelombang 1 Tahun 2025">
                    <div class="form-text">Nama yang akan ditampilkan untuk gelombang ini</div>
                  </div>

                  <!-- Tahun dan Gelombang Ke -->
                  <div class="row">
                    <div class="col-md-6">
                      <div class="mb-4">
                        <label class="form-label required">Tahun</label>
                        <select name="tahun" class="form-select" required>
                          <option value="">Pilih Tahun</option>
                          <?php for ($year = $currentYear; $year <= $currentYear + 2; $year++): ?>
                            <option value="<?= $year ?>" 
                                    <?= (isset($_POST['tahun']) && $_POST['tahun'] == $year) || (!isset($_POST['tahun']) && $year == $currentYear) ? 'selected' : '' ?>>
                              <?= $year ?>
                            </option>
                          <?php endfor; ?>
                          <?php for ($year = $currentYear - 1; $year >= 2020; $year--): ?>
                            <option value="<?= $year ?>" 
                                    <?= (isset($_POST['tahun']) && $_POST['tahun'] == $year) ? 'selected' : '' ?>>
                              <?= $year ?>
                            </option>
                          <?php endfor; ?>
                        </select>
                        <div class="form-text">Tahun pelaksanaan gelombang</div>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="mb-4">
                        <label class="form-label required">Gelombang Ke</label>
                        <select name="gelombang_ke" class="form-select" required>
                          <option value="">Pilih Gelombang Ke</option>
                          <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" 
                                    <?= (isset($_POST['gelombang_ke']) && $_POST['gelombang_ke'] == $i) || (!isset($_POST['gelombang_ke']) && $i == $suggestedGelombang) ? 'selected' : '' ?>>
                              Gelombang Ke-<?= $i ?>
                            </option>
                          <?php endfor; ?>
                        </select>
                        <div class="form-text">Urutan gelombang dalam tahun tersebut</div>
                      </div>
                    </div>
                  </div>

                  <!-- Status Awal -->
                  <div class="mb-4">
                    <label class="form-label required">Status Awal</label>
                    <select name="status" class="form-select" required>
                      <option value="">Pilih Status</option>
                      <option value="aktif" <?= (isset($_POST['status']) && $_POST['status'] == 'aktif') ? 'selected' : 'selected' ?>>
                        Aktif - Siap untuk digunakan
                      </option>
                      <option value="dibuka" <?= (isset($_POST['status']) && $_POST['status'] == 'dibuka') ? 'selected' : '' ?>>
                        Dibuka - Langsung buka pendaftaran
                      </option>
                      <option value="selesai" <?= (isset($_POST['status']) && $_POST['status'] == 'selesai') ? 'selected' : '' ?>>
                        Selesai - Gelombang sudah berakhir
                      </option>
                    </select>
                    <div class="form-text">Status dapat diubah kemudian</div>
                  </div>

                  <!-- Button Action -->
                  <div class="d-flex justify-content-end gap-3 pt-4 mt-4 border-top">
                    <a href="index.php" class="btn btn-kembali px-3">
                      Kembali
                    </a>
                    <button type="submit" class="btn btn-simpan px-4">
                      <i class="bi bi-check-lg me-1"></i>Simpan
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Info Panel -->
          <div class="col-lg-4">
            <div class="card content-card">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Informasi Penting
                </h6>
              </div>
              <div class="card-body">
                <div class="alert alert-info">
                  <h6 class="alert-heading">
                    <i class="bi bi-lightbulb me-2"></i>Tips Tambah Gelombang
                  </h6>
                  <ul class="mb-0 small">
                    <li>Gunakan nama yang jelas dan mudah diidentifikasi</li>
                    <li>Pastikan tidak ada duplikasi gelombang</li>
                    <li>Status "Aktif" biasanya pilihan terbaik untuk gelombang baru</li>
                    <li>Anda dapat mengatur formulir pendaftaran setelah gelombang dibuat</li>
                  </ul>
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
    const form = document.getElementById('formTambahGelombang');
    const namaInput = document.querySelector('input[name="nama_gelombang"]');
    const tahunSelect = document.querySelector('select[name="tahun"]');
    const gelombangSelect = document.querySelector('select[name="gelombang_ke"]');
    const statusSelect = document.querySelector('select[name="status"]');
    
    // Preview elements
    const previewNama = document.getElementById('previewNama');
    const previewTahun = document.getElementById('previewTahun');
    const previewGelombang = document.getElementById('previewGelombang');
    const previewStatus = document.getElementById('previewStatus');
    
    // Auto-generate nama gelombang
    function generateNama() {
      const tahun = tahunSelect.value;
      const gelombang = gelombangSelect.value;
      
      if (tahun && gelombang) {
        const namaGenerated = `Gelombang ${gelombang} Tahun ${tahun}`;
        namaInput.value = namaGenerated;
        updatePreview();
      }
    }
    
    // Update preview
    function updatePreview() {
      const nama = namaInput.value || 'Nama Gelombang';
      const tahun = tahunSelect.value || '<?= $currentYear ?>';
      const gelombang = gelombangSelect.value || '1';
      const status = statusSelect.value || 'aktif';
      
      previewNama.textContent = nama;
      previewTahun.textContent = tahun;
      previewGelombang.textContent = gelombang;
      
      // Update status badge
      previewStatus.className = 'badge';
      switch(status) {
        case 'aktif':
          previewStatus.classList.add('bg-success');
          previewStatus.textContent = 'Aktif';
          break;
        case 'dibuka':
          previewStatus.classList.add('bg-primary');
          previewStatus.textContent = 'Dibuka';
          break;
        case 'selesai':
          previewStatus.classList.add('bg-secondary');
          previewStatus.textContent = 'Selesai';
          break;
        default:
          previewStatus.classList.add('bg-secondary');
          previewStatus.textContent = 'Draft';
      }
    }
    
    // Event listeners
    tahunSelect.addEventListener('change', generateNama);
    gelombangSelect.addEventListener('change', generateNama);
    namaInput.addEventListener('input', updatePreview);
    statusSelect.addEventListener('change', updatePreview);
    
    // Reset button (removed from UI but keep functionality for potential future use)
    // const btnReset = document.getElementById('btnReset');
    // if (btnReset) {
    //   btnReset.addEventListener('click', function() {
    //     if (confirm('Yakin ingin mereset form? Semua data yang diisi akan hilang.')) {
    //       form.reset();
    //       // Restore default values
    //       tahunSelect.value = '<?= $currentYear ?>';
    //       gelombangSelect.value = '<?= $suggestedGelombang ?>';
    //       statusSelect.value = 'aktif';
    //       namaInput.value = '<?= $suggestedName ?>';
    //       updatePreview();
    //     }
    //   });
    // }
    
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
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
    });
    
    // Initialize preview
    updatePreview();
  });
  </script>
</body>
</html>