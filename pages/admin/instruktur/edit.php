<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'instruktur'; 
$baseURL = '../';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID instruktur tidak valid!";
    header("Location: index.php");
    exit;
}

$id_instruktur = (int)$_GET['id'];

// Ambil data instruktur
$instrukturQuery = "SELECT i.*, u.username FROM instruktur i 
                    LEFT JOIN user u ON i.id_user = u.id_user 
                    WHERE i.id_instruktur = '$id_instruktur'";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

if (mysqli_num_rows($instrukturResult) == 0) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$instruktur = mysqli_fetch_assoc($instrukturResult);

// Ambil data kelas yang sedang aktif untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang, g.tahun 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE g.status = 'aktif' OR g.status = 'dibuka'
               ORDER BY g.tahun DESC, k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Ambil kelas yang saat ini diampu oleh instruktur
$kelasInstrukturQuery = "SELECT id_kelas FROM kelas WHERE id_instruktur = '$id_instruktur'";
$kelasInstrukturResult = mysqli_query($conn, $kelasInstrukturQuery);
$kelasInstrukturArray = [];
while ($row = mysqli_fetch_assoc($kelasInstrukturResult)) {
    $kelasInstrukturArray[] = $row['id_kelas'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $angkatan = mysqli_real_escape_string($conn, $_POST['angkatan']);
    $kelas_diampu = isset($_POST['kelas_diampu']) ? $_POST['kelas_diampu'] : [];
    
    // Handle file upload pas foto
    $pas_foto = $instruktur['pas_foto'];
    
    // Handle file deletion
    if (isset($_POST['hapus_pas_foto']) && $_POST['hapus_pas_foto'] == '1') {
        if (!empty($instruktur['pas_foto']) && file_exists("../../../uploads/pas_foto/" . $instruktur['pas_foto'])) {
            unlink("../../../uploads/pas_foto/" . $instruktur['pas_foto']);
        }
        $pas_foto = '';
    }
    
    // Upload pas foto baru
    if (!empty($_FILES['pas_foto']['name'])) {
        // Hapus file lama jika ada
        if (!empty($instruktur['pas_foto']) && file_exists("../../../uploads/pas_foto/" . $instruktur['pas_foto'])) {
            unlink("../../../uploads/pas_foto/" . $instruktur['pas_foto']);
        }
        
        $targetDir = "../../../uploads/pas_foto/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileExtension = strtolower(pathinfo($_FILES['pas_foto']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $pas_foto = time() . '_instruktur_' . uniqid() . '.' . $fileExtension;
            if (!move_uploaded_file($_FILES['pas_foto']['tmp_name'], $targetDir . $pas_foto)) {
                $pas_foto = $instruktur['pas_foto']; // Kembalikan ke file lama jika gagal
                $error = "Gagal mengupload foto. File tidak valid.";
            }
        } else {
            $error = "Format file foto tidak didukung. Gunakan JPG, JPEG, atau PNG.";
        }
    }
    
    if (!isset($error)) {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update data instruktur
            $query = "UPDATE instruktur SET 
                      nama = '$nama',
                      jenis_kelamin = '$jenis_kelamin',
                      angkatan = '$angkatan',
                      pas_foto = '$pas_foto'
                      WHERE id_instruktur = '$id_instruktur'";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception("Gagal memperbarui data instruktur: " . mysqli_error($conn));
            }
            
            // Reset semua kelas yang sebelumnya diampu oleh instruktur ini
            $resetKelasQuery = "UPDATE kelas SET id_instruktur = NULL WHERE id_instruktur = '$id_instruktur'";
            if (!mysqli_query($conn, $resetKelasQuery)) {
                throw new Exception("Gagal mereset kelas lama: " . mysqli_error($conn));
            }
            
            // Update kelas yang dipilih dengan id_instruktur
            if (!empty($kelas_diampu)) {
                foreach ($kelas_diampu as $id_kelas) {
                    $id_kelas = mysqli_real_escape_string($conn, $id_kelas);
                    
                    // Cek apakah kelas ada
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
            
            $_SESSION['success'] = "Data instruktur berhasil diperbarui" . (!empty($kelas_diampu) ? " dengan " . count($kelas_diampu) . " kelas yang diampu!" : "!");
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
  <title>Edit Data Instruktur</title>
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
              <h2 class="page-title mb-1">EDIT DATA INSTRUKTUR</h2>
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
            <i class="bi bi-person-gear me-2"></i>Form Edit Instruktur
          </h5>
          <small class="text-muted">NIK: <?= htmlspecialchars($instruktur['nik']) ?></small>
        </div>

        <div class="card-body">
          <form action="" method="post" enctype="multipart/form-data" id="formEditInstruktur">
            <div class="row">
              
              <!-- Data Instruktur Section -->
              <div class="col-lg-8">
                <h6 class="section-title mb-4">
                  <i class="bi bi-person-circle me-2"></i>Data Instruktur
                </h6>
                
                <div class="mb-4">
                  <label class="form-label">NIK</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($instruktur['nik']) ?>" readonly style="background-color: #f8f9fa;">
                  <div class="form-text"><small>NIK tidak dapat diubah</small></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Nama Lengkap</label>
                  <input type="text" name="nama" class="form-control" required 
                         value="<?= htmlspecialchars($instruktur['nama']) ?>">
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Jenis Kelamin</label>
                      <select name="jenis_kelamin" class="form-select" required>
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="Laki-Laki" <?= ($instruktur['jenis_kelamin'] == 'Laki-Laki') ? 'selected' : '' ?>>Laki-Laki</option>
                        <option value="Perempuan" <?= ($instruktur['jenis_kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Angkatan</label>
                      <input type="text" name="angkatan" class="form-control" required 
                             placeholder="Contoh: Angkatan 2024"
                             value="<?= htmlspecialchars($instruktur['angkatan']) ?>">
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
                                         <?= in_array($kelas['id_kelas'], $kelasInstrukturArray) ? 'checked' : '' ?>
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
                        <span id="selectedCount"><?= count($kelasInstrukturArray) ?></span> kelas dipilih
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
                
                <!-- Current Photo -->
                <?php if (!empty($instruktur['pas_foto'])): ?>
                  <div class="current-file mb-4 p-3 border rounded" style="background-color: #f8f9fa;">
                    <h6 class="mb-3">Foto Saat Ini</h6>
                    
                    <div class="text-center mb-3">
                      <img src="../../../uploads/pas_foto/<?= $instruktur['pas_foto'] ?>" 
                           alt="Foto Instruktur" 
                           class="img-thumbnail" 
                           style="max-width: 200px; max-height: 200px; object-fit: cover;">
                    </div>
                    
                    <div class="text-center mb-3">
                      <a href="../../../uploads/pas_foto/<?= $instruktur['pas_foto'] ?>" target="_blank" 
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
                
                <!-- Upload New Photo -->
                <div class="mb-4">
                  <label class="form-label">
                    <i class="bi bi-image me-1"></i><?= !empty($instruktur['pas_foto']) ? 'Upload Foto Baru' : 'Upload Foto Profil' ?>
                  </label>
                  <input type="file" name="pas_foto" class="form-control" accept=".jpg,.jpeg,.png">
                  <div class="form-text"><small>Format yang didukung: JPG, JPEG, PNG (Maks 2MB)</small></div>
                </div>
                
                <!-- Preview Area -->
                <div class="text-center">
                  <div id="imagePreview" class="d-none mb-3">
                    <h6 class="mb-2">Preview Foto Baru</h6>
                    <img id="previewImg" src="" alt="Preview" class="img-thumbnail mb-2" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                    <div>
                      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removePreview()">
                        <i class="bi bi-trash me-1"></i>Hapus Preview
                      </button>
                    </div>
                  </div>
                  <div id="placeholderPreview" class="preview-placeholder border rounded p-4 text-muted" <?= !empty($instruktur['pas_foto']) ? 'style="display: none;"' : '' ?>>
                    <i class="bi bi-person-fill fs-1 d-block mb-2"></i>
                    <small>Preview foto baru akan tampil di sini</small>
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
  const form = document.getElementById('formEditInstruktur');
  
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

  // Handle file deletion checkbox
  const deleteCheckbox = document.getElementById('hapus_pas_foto');
  if (deleteCheckbox) {
    deleteCheckbox.addEventListener('change', function() {
      if (this.checked) {
        const confirmDelete = confirm('Apakah Anda yakin ingin menghapus foto ini?');
        if (!confirmDelete) {
          this.checked = false;
        }
      }
    });
  }

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