<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'instruktur'; 
$baseURL = '../';

// Ambil data kelas yang aktif untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang, g.tahun 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE g.status = 'aktif' OR g.status = 'dibuka'
               ORDER BY g.tahun DESC, k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input sesuai struktur database
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $jenis_kelamin = $_POST['jenis_kelamin']; // Enum: 'Laki-Laki', 'Perempuan'
    $angkatan = mysqli_real_escape_string($conn, $_POST['angkatan']);
    $kelas_diampu = isset($_POST['kelas_diampu']) ? $_POST['kelas_diampu'] : []; // Array kelas yang dipilih
    
    // Validasi NIK unik
    $nikCheck = mysqli_query($conn, "SELECT id_instruktur FROM instruktur WHERE nik = '$nik'");
    if (mysqli_num_rows($nikCheck) > 0) {
        $error = "NIK sudah terdaftar! Gunakan NIK yang berbeda.";
    } else {
        // Handle file upload pas foto
        $pas_foto = '';
        
        // Upload pas foto
        if (!empty($_FILES['pas_foto']['name'])) {
            $targetDir = "../../../uploads/pas_foto/";
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            
            $fileExtension = strtolower(pathinfo($_FILES['pas_foto']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $pas_foto = time() . '_instruktur_' . uniqid() . '.' . $fileExtension;
                if (!move_uploaded_file($_FILES['pas_foto']['tmp_name'], $targetDir . $pas_foto)) {
                    $pas_foto = '';
                }
            } else {
                $error = "Format file foto tidak didukung. Gunakan JPG, JPEG, atau PNG.";
            }
        }
        
        if (!isset($error)) {
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert ke database sesuai struktur tabel instruktur
                $query = "INSERT INTO instruktur (nik, nama, jenis_kelamin, angkatan, pas_foto) 
                          VALUES ('$nik', '$nama', '$jenis_kelamin', '$angkatan', '$pas_foto')";
                
                if (mysqli_query($conn, $query)) {
                    $id_instruktur = mysqli_insert_id($conn);
                    
                    // Update kelas yang dipilih dengan id_instruktur
                    if (!empty($kelas_diampu)) {
                        foreach ($kelas_diampu as $id_kelas) {
                            $id_kelas = mysqli_real_escape_string($conn, $id_kelas);
                            
                            // Debug: cek apakah kelas ada
                            $checkKelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id_kelas = '$id_kelas'");
                            if (mysqli_num_rows($checkKelas) == 0) {
                                throw new Exception("Kelas dengan ID $id_kelas tidak ditemukan");
                            }
                            
                            $updateKelas = "UPDATE kelas SET id_instruktur = '$id_instruktur' WHERE id_kelas = '$id_kelas'";
                            if (!mysqli_query($conn, $updateKelas)) {
                                throw new Exception("Gagal mengupdate kelas ID $id_kelas: " . mysqli_error($conn));
                            }
                        }
                    }
                    
                    // Commit transaction
                    mysqli_commit($conn);
                    
                    $_SESSION['success'] = "Data instruktur berhasil ditambahkan" . (!empty($kelas_diampu) ? " dengan " . count($kelas_diampu) . " kelas yang diampu!" : "!");
                    header("Location: index.php");
                    exit;
                } else {
                    throw new Exception("Gagal menambahkan data instruktur: " . mysqli_error($conn));
                }
            } catch (Exception $e) {
                // Rollback transaction
                mysqli_rollback($conn);
                $error = $e->getMessage();
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
  <title>Tambah Data Instruktur</title>
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
              <h2 class="page-title mb-1">TAMBAH DATA INSTRUKTUR</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Master</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Instruktur</a>
                  </li>
                  <li class="breadcrumb-item active" aria-current="page">Tambah Data</li>
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
            <i class="bi bi-person-plus me-2"></i>Form Tambah Instruktur
          </h5>
        </div>

        <div class="card-body">
          <form action="" method="post" enctype="multipart/form-data" id="formTambahInstruktur">
            <div class="row">
              
              <!-- Data Instruktur Section -->
              <div class="col-lg-8">
                <h6 class="section-title mb-4">
                  <i class="bi bi-person-circle me-2"></i>Data Instruktur
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">NIK</label>
                  <input type="text" name="nik" class="form-control" required 
                         pattern="[0-9]{16}" title="NIK harus 16 digit angka"
                         value="<?= isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : '' ?>">
                  <div class="form-text"><small>Masukkan 16 digit NIK sesuai KTP</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Nama Lengkap</label>
                  <input type="text" name="nama" class="form-control" required 
                         value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>">
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Jenis Kelamin</label>
                      <select name="jenis_kelamin" class="form-select" required>
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="Laki-Laki" <?= (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'Laki-Laki') ? 'selected' : '' ?>>Laki-Laki</option>
                        <option value="Perempuan" <?= (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Angkatan</label>
                      <input type="text" name="angkatan" class="form-control" required 
                             value="<?= isset($_POST['angkatan']) ? htmlspecialchars($_POST['angkatan']) : '' ?>">
                      <div class="form-text"><small>Contoh : Gelombang 1 Tahun 2021</small></div>
                    </div>
                  </div>
                </div>

                <!-- Kelas yang Diampu -->
                <div class="mb-4">
                  <label class="form-label">
                    <i class="bi bi-building me-1"></i>Kelas yang Diampu
                  </label>
                  
                  <div class="border rounded p-3" style="background-color: #f8f9fa;">
                    <!-- Search Box -->
                    <div class="mb-3">
                      <input type="text" 
                             class="form-control form-control-sm" 
                             id="kelasSearchInput" 
                             placeholder="Cari kelas...">
                    </div>
                    
                    <!-- Kelas List dalam scrollable area -->
                    <div style="max-height: 280px; overflow-y: auto;" class="border rounded p-3 bg-white">
                      <div class="row g-3">
                        <?php if (mysqli_num_rows($kelasResult) > 0): ?>
                          <?php mysqli_data_seek($kelasResult, 0); ?>
                          <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                            <div class="col-md-6">
                              <div class="kelas-item" 
                                   data-search="<?= strtolower(htmlspecialchars($kelas['nama_kelas'] . ' ' . $kelas['nama_gelombang'])) ?>">
                                <div class="form-check p-2 border rounded h-100">
                                  <input class="form-check-input" 
                                         type="checkbox" 
                                         value="<?= $kelas['id_kelas'] ?>" 
                                         id="kelas_<?= $kelas['id_kelas'] ?>"
                                         name="kelas_diampu[]"
                                         <?= (isset($_POST['kelas_diampu']) && in_array($kelas['id_kelas'], $_POST['kelas_diampu'])) ? 'checked' : '' ?>
                                         onchange="updateKelasCount()">
                                  <label class="form-check-label w-100" for="kelas_<?= $kelas['id_kelas'] ?>">
                                    <div class="fw-medium"><?= htmlspecialchars($kelas['nama_kelas']) ?></div>
                                    <?php if($kelas['nama_gelombang']): ?>
                                      <small class="text-muted"><?= htmlspecialchars($kelas['nama_gelombang']) ?> (<?= $kelas['tahun'] ?>)</small>
                                    <?php endif; ?>
                                  </label>
                                </div>
                              </div>
                            </div>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <div class="col-12 text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Tidak ada kelas yang sedang aktif
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <!-- Controls -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                      <small class="text-muted">
                        <i class="bi bi-check-circle me-1"></i>
                        <span id="selectedCount">0</span> kelas dipilih
                      </small>
                      <button type="button" class="btn-formal btn-batal" onclick="deselectAll()">
                        Batalkan Semua
                      </button>
                    </div>
                  </div>
                  
                  <div class="form-text mt-2">
                    Pilih kelas yang akan diampu oleh instruktur ini
                  </div>
                </div>
              </div>

              <!-- Upload Foto Section -->
              <div class="col-lg-4">
                <h6 class="section-title mb-4">
                  <i class="bi bi-camera me-2"></i>Foto Profil
                </h6>
                
                <!-- Upload New Photo -->
                <div class="mb-4">
                  <label class="form-label">
                    <i class="bi bi-image me-1"></i>Upload Foto Profil
                  </label>
                  <input type="file" name="pas_foto" class="form-control" accept=".jpg,.jpeg,.png">
                  <div class="form-text"><small>Format yang didukung: JPG, JPEG, PNG (Maks 2MB)</small></div>
                </div>
                
                <!-- Preview Area -->
                <div class="text-center">
                  <div id="imagePreview" class="d-none mb-3">
                    <h6 class="mb-2">Preview Foto</h6>
                    <img id="previewImg" src="" alt="Preview" class="img-thumbnail mb-2" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                    <div>
                      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePreview()">
                        <i class="bi bi-trash me-1"></i>Hapus Preview
                      </button>
                    </div>
                  </div>
                  <div id="placeholderPreview" class="preview-placeholder border rounded p-4 text-muted">
                    <i class="bi bi-person-fill fs-1 d-block mb-2"></i>
                    <small>Preview foto akan tampil di sini</small>
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
  const form = document.getElementById('formTambahInstruktur');
  
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

  // Photo preview functionality
  const photoInput = document.querySelector('input[name="pas_foto"]');
  const imagePreview = document.getElementById('imagePreview');
  const previewImg = document.getElementById('previewImg');
  const placeholderPreview = document.getElementById('placeholderPreview');

  if (photoInput) {
    photoInput.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        // Check file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
          alert('Ukuran file terlalu besar. Maksimal 2MB');
          this.value = '';
          return;
        }

        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
          alert('Format file tidak didukung. Gunakan JPG, JPEG, atau PNG');
          this.value = '';
          return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImg.src = e.target.result;
          imagePreview.classList.remove('d-none');
          placeholderPreview.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Remove preview function
  window.removePreview = function() {
    photoInput.value = '';
    imagePreview.classList.add('d-none');
    placeholderPreview.style.display = 'block';
    previewImg.src = '';
  };

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

    // Validate NIK length
    const nik = nikInput.value;
    if (nik.length !== 16) {
      nikInput.classList.add('is-invalid');
      isValid = false;
      alert('NIK harus tepat 16 digit!');
    }

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

  // Initialize search
  const searchInput = document.getElementById('kelasSearchInput');
  const kelasItems = document.querySelectorAll('.kelas-item');
  
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      
      kelasItems.forEach(item => {
        const searchData = item.dataset.search;
        const parentCol = item.closest('.col-md-6');
        if (searchData.includes(searchTerm)) {
          parentCol.style.display = 'block';
        } else {
          parentCol.style.display = 'none';
        }
      });
    });
  }

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});

// Kelas functions
function deselectAll() {
  const checkboxes = document.querySelectorAll('input[name="kelas_diampu[]"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = false;
  });
  updateKelasCount();
}

function updateKelasCount() {
  const checked = document.querySelectorAll('input[name="kelas_diampu[]"]:checked');
  document.getElementById('selectedCount').textContent = checked.length;
}
</script>
</body>
</html>