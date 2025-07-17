<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'materi'; 
$baseURL = '../';

// Handle AJAX request untuk check duplicate
if (isset($_POST['ajax_check_duplicate'])) {
    header('Content-Type: application/json');
    
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    
    // Cek duplikasi dengan join ke tabel kelas untuk mendapatkan nama kelas
    $duplicateQuery = "SELECT m.id_materi, m.judul, k.nama_kelas 
                       FROM materi m 
                       JOIN kelas k ON m.id_kelas = k.id_kelas 
                       WHERE m.judul = '$judul' 
                       AND m.id_kelas = '$id_kelas'";
    
    $duplicateResult = mysqli_query($conn, $duplicateQuery);
    
    $response = [
        'duplicate' => mysqli_num_rows($duplicateResult) > 0,
        'count' => mysqli_num_rows($duplicateResult)
    ];
    
    // Jika ada duplikasi, tambahkan info kelas
    if ($response['duplicate']) {
        $existingMateri = mysqli_fetch_assoc($duplicateResult);
        $response['kelas_nama'] = $existingMateri['nama_kelas'];
    }
    
    echo json_encode($response);
    exit;
}

// Ambil data kelas untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               ORDER BY g.nama_gelombang ASC, k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Ambil data instruktur untuk dropdown
$instrukturQuery = "SELECT id_instruktur, nama FROM instruktur ORDER BY nama ASC";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_check_duplicate'])) {
    // Sanitize input sesuai struktur database
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $id_kelas = !empty($_POST['id_kelas']) ? mysqli_real_escape_string($conn, $_POST['id_kelas']) : NULL;
    $id_instruktur = !empty($_POST['id_instruktur']) ? mysqli_real_escape_string($conn, $_POST['id_instruktur']) : NULL;
    
    // Handle file upload
    $file_materi = null;
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $uploadDir = '../../../uploads/materi/';
        
        // Buat direktori jika belum ada
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = $_FILES['file_materi']['name'];
        $fileSize = $_FILES['file_materi']['size'];
        $fileTmp = $_FILES['file_materi']['tmp_name'];
        $fileType = $_FILES['file_materi']['type'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validasi file
        $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xlsx', 'xls'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = "Format file tidak diizinkan! Hanya file PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX yang diperbolehkan.";
        } elseif ($fileSize > $maxFileSize) {
            $error = "Ukuran file terlalu besar! Maksimal 10MB.";
        } else {
            // Generate unique filename
            $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $fileName);
            $uploadPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmp, $uploadPath)) {
                $file_materi = $newFileName;
            } else {
                $error = "Gagal mengunggah file!";
            }
        }
    }
    
    if (!isset($error)) {
        // Validasi kelas jika dipilih
        if ($id_kelas) {
            $kelasCheck = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id_kelas = '$id_kelas'");
            if (mysqli_num_rows($kelasCheck) == 0) {
                $error = "Kelas tidak valid!";
            }
        }
        
        // Validasi instruktur jika dipilih
        if ($id_instruktur) {
            $instrukturCheck = mysqli_query($conn, "SELECT nama FROM instruktur WHERE id_instruktur = '$id_instruktur'");
            if (mysqli_num_rows($instrukturCheck) == 0) {
                $error = "Instruktur tidak valid!";
            }
        }
        
        // Validasi duplikasi materi (judul + id_kelas harus unik jika kelas dipilih)
        if (!isset($error) && $id_kelas) {
            $duplicateQuery = "SELECT m.id_materi, m.judul, k.nama_kelas 
                               FROM materi m 
                               JOIN kelas k ON m.id_kelas = k.id_kelas 
                               WHERE m.judul = '$judul' 
                               AND m.id_kelas = '$id_kelas'";
            
            $duplicateResult = mysqli_query($conn, $duplicateQuery);
            
            if (mysqli_num_rows($duplicateResult) > 0) {
                $existingMateri = mysqli_fetch_assoc($duplicateResult);
                $error = "Materi '" . htmlspecialchars($judul) . "' sudah ada pada kelas '" . htmlspecialchars($existingMateri['nama_kelas']) . "'!";
            }
        }
        
        if (!isset($error)) {
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert ke database sesuai struktur tabel materi
                $query = "INSERT INTO materi (judul, deskripsi, id_kelas, id_instruktur, file_materi) 
                          VALUES ('$judul', '$deskripsi', " . 
                          ($id_kelas ? "'$id_kelas'" : "NULL") . ", " .
                          ($id_instruktur ? "'$id_instruktur'" : "NULL") . ", " .
                          ($file_materi ? "'$file_materi'" : "NULL") . ")";
                
                if (mysqli_query($conn, $query)) {
                    // Commit transaction
                    mysqli_commit($conn);
                    
                    $_SESSION['success'] = "Data materi berhasil ditambahkan!";
                    header("Location: index.php");
                    exit;
                } else {
                    throw new Exception("Gagal menambahkan data materi: " . mysqli_error($conn));
                }
            } catch (Exception $e) {
                // Rollback transaction
                mysqli_rollback($conn);
                
                // Hapus file yang sudah diupload jika ada error
                if ($file_materi && file_exists($uploadDir . $file_materi)) {
                    unlink($uploadDir . $file_materi);
                }
                
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
  <title>Tambah Data Materi</title>
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
              <h2 class="page-title mb-1">TAMBAH DATA MATERI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Master</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Materi</a>
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
            <i class="bi bi-journal-plus me-2"></i>Form Tambah Materi
          </h5>
        </div>

        <div class="card-body">
          <form action="" method="post" enctype="multipart/form-data" id="formTambahMateri">
            <div class="row">
              
              <!-- Bagian 1: Informasi Materi -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-journal-bookmark me-2"></i>Informasi Materi
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Judul Materi</label>
                  <input type="text" name="judul" class="form-control" required 
                         value="<?= isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : '' ?>"
                         placeholder="Masukkan judul materi">
                  <div class="form-text"><small>Contoh: Pengenalan Microsoft Word</small></div>
                  <div id="duplicate-feedback" class="invalid-feedback"></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Deskripsi</label>
                  <textarea name="deskripsi" class="form-control" rows="4" required 
                            placeholder="Masukkan deskripsi materi"><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
                  <div class="form-text"><small>Jelaskan secara singkat tentang materi ini</small></div>
                </div>
              </div>

              <!-- Bagian 2: Pengaturan Kelas & Instruktur -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-gear me-2"></i>Pengaturan Kelas & Instruktur
                </h6>

                <div class="mb-4">
                  <label class="form-label">
                    <i class="bi bi-building me-1"></i>Kelas (Opsional)
                  </label>
                  <select name="id_kelas" class="form-select">
                    <option value="">Pilih Kelas</option>
                    <?php if (mysqli_num_rows($kelasResult) > 0): ?>
                      <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                        <option value="<?= $kelas['id_kelas'] ?>" 
                                <?= (isset($_POST['id_kelas']) && $_POST['id_kelas'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($kelas['nama_kelas']) ?>
                          <?= $kelas['nama_gelombang'] ? ' - ' . htmlspecialchars($kelas['nama_gelombang']) : '' ?>
                        </option>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <option value="" disabled>Tidak ada kelas tersedia</option>
                    <?php endif; ?>
                  </select>
                  <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    <small>Pilih kelas untuk materi spesifik atau kosongkan untuk materi umum</small>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label">
                    <i class="bi bi-person-check me-1"></i>Instruktur (Opsional)
                  </label>
                  <select name="id_instruktur" class="form-select">
                    <option value="">Pilih Instruktur</option>
                    <?php if (mysqli_num_rows($instrukturResult) > 0): ?>
                      <?php while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                        <option value="<?= $instruktur['id_instruktur'] ?>" 
                                <?= (isset($_POST['id_instruktur']) && $_POST['id_instruktur'] == $instruktur['id_instruktur']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($instruktur['nama']) ?>
                        </option>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                  <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    <small>Instruktur yang bertanggung jawab terhadap materi ini</small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bagian 3: Upload File Materi -->
            <div class="row mt-5">
              <div class="col-12">
                <h6 class="section-title mb-4">
                  <i class="bi bi-cloud-upload me-2"></i>File Materi
                </h6>
                <p class="text-muted mb-4">Upload file materi pembelajaran. File bersifat opsional dan dapat dilengkapi kemudian.</p>
                
                <div class="row justify-content-center">
                  <div class="col-lg-4 col-md-6">
                    <div class="border rounded p-3" style="background-color: #f8f9fa;">
                      <div class="text-center mb-2">
                        <i class="bi bi-file-earmark-arrow-up fs-2 text-muted"></i>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">
                          <i class="bi bi-file-earmark-arrow-up me-1"></i>File Materi (Opsional)
                        </label>
                        <input type="file" name="file_materi" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                      </div>
                      <div class="form-text text-center">
                        <i class="bi bi-info-circle me-1"></i>
                        <small>Format: PDF, DOC, PPT, XLS<br>Maksimal: 10MB</small>
                      </div>
                      <div id="file-preview" class="mt-3 d-none">
                        <div class="alert alert-info">
                          <i class="bi bi-file-earmark me-2"></i>
                          <span id="file-name"></span>
                          <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile()">
                            <i class="bi bi-x"></i>
                          </button>
                        </div>
                      </div>
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
  const form = document.getElementById('formTambahMateri');
  const fileInput = document.querySelector('input[name="file_materi"]');
  const filePreview = document.getElementById('file-preview');
  const fileName = document.getElementById('file-name');

  // File preview functionality
  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      const file = this.files[0];
      fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
      filePreview.classList.remove('d-none');
    } else {
      filePreview.classList.add('d-none');
    }
  });

  // Remove file function
  window.removeFile = function() {
    fileInput.value = '';
    filePreview.classList.add('d-none');
  };

  // Format file size
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Fungsi untuk cek duplikasi secara real-time
  function checkDuplicateMateri() {
    const judul = document.querySelector('input[name="judul"]').value.trim();
    const idKelas = document.querySelector('select[name="id_kelas"]').value;
    const judulInput = document.querySelector('input[name="judul"]');
    const feedbackDiv = document.getElementById('duplicate-feedback');
    
    // Reset jika tidak ada kelas yang dipilih
    if (!judul || !idKelas) {
        judulInput.classList.remove('is-invalid');
        judulInput.removeAttribute('data-duplicate');
        feedbackDiv.textContent = '';
        return;
    }
    
    // Kirim request AJAX untuk cek duplikasi
    const formData = new FormData();
    formData.append('judul', judul);
    formData.append('id_kelas', idKelas);
    formData.append('ajax_check_duplicate', '1');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.duplicate) {
            judulInput.classList.add('is-invalid');
            judulInput.setAttribute('data-duplicate', 'true');
            judulInput.setAttribute('data-kelas-nama', data.kelas_nama);
            feedbackDiv.textContent = `Materi "${judul}" sudah ada pada kelas "${data.kelas_nama}"`;
        } else {
            judulInput.classList.remove('is-invalid');
            judulInput.removeAttribute('data-duplicate');
            judulInput.removeAttribute('data-kelas-nama');
            feedbackDiv.textContent = '';
        }
    })
    .catch(error => console.error('Error:', error));
  }

  // Event listeners untuk validasi real-time
  const judulInput = document.querySelector('input[name="judul"]');
  const kelasSelect = document.querySelector('select[name="id_kelas"]');

  judulInput.addEventListener('blur', checkDuplicateMateri);
  kelasSelect.addEventListener('change', checkDuplicateMateri);

  // Form submission validation
  form.addEventListener('submit', function(e) {
    const judulInput = document.querySelector('input[name="judul"]');
    
    // Cek apakah ada duplikasi berdasarkan attribute data-duplicate
    if (judulInput.hasAttribute('data-duplicate')) {
        e.preventDefault();
        judulInput.focus();
        return false;
    }

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

    // Validate file if uploaded
    if (fileInput.files && fileInput.files[0]) {
      const file = fileInput.files[0];
      const allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
      const fileExtension = file.name.split('.').pop().toLowerCase();
      const maxFileSize = 10 * 1024 * 1024; // 10MB

      if (!allowedExtensions.includes(fileExtension)) {
        fileInput.classList.add('is-invalid');
        isValid = false;
        alert('Format file tidak diizinkan! Hanya file PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX yang diperbolehkan.');
      } else if (file.size > maxFileSize) {
        fileInput.classList.add('is-invalid');
        isValid = false;
        alert('Ukuran file terlalu besar! Maksimal 10MB.');
      } else {
        fileInput.classList.remove('is-invalid');
      }
    }

    if (!isValid) {
      e.preventDefault();
      alert('Harap lengkapi semua field yang wajib diisi dengan benar!');
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