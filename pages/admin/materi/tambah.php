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
    
    $duplicateQuery = "SELECT m.id_materi, m.judul, k.nama_kelas 
                       FROM materi m 
                       JOIN kelas k ON m.id_kelas = k.id_kelas 
                       WHERE m.judul = '$judul' AND m.id_kelas = '$id_kelas'";
    
    $duplicateResult = mysqli_query($conn, $duplicateQuery);
    $response = ['duplicate' => mysqli_num_rows($duplicateResult) > 0];
    
    if ($response['duplicate']) {
        $existingMateri = mysqli_fetch_assoc($duplicateResult);
        $response['kelas_nama'] = $existingMateri['nama_kelas'];
    }
    
    echo json_encode($response);
    exit;
}

// Ambil data kelas aktif
$kelasQuery = "SELECT k.*, g.nama_gelombang
               FROM kelas k 
               JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE g.status = 'aktif'
               ORDER BY k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);
$kelasAktifCount = mysqli_num_rows($kelasResult);

// Ambil data instruktur
$instrukturQuery = "SELECT id_instruktur, nama FROM instruktur ORDER BY nama ASC";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_check_duplicate'])) {
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $id_kelas = !empty($_POST['id_kelas']) ? mysqli_real_escape_string($conn, $_POST['id_kelas']) : NULL;
    $id_instruktur = !empty($_POST['id_instruktur']) ? mysqli_real_escape_string($conn, $_POST['id_instruktur']) : NULL;
    $tambah_ke_semua_kelas = isset($_POST['tambah_ke_semua_kelas']);
    
    // Handle file upload
    $file_materi = null;
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $uploadDir = '../../../uploads/materi/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = $_FILES['file_materi']['name'];
        $fileSize = $_FILES['file_materi']['size'];
        $fileTmp = $_FILES['file_materi']['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xlsx', 'xls'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = "Format file tidak diizinkan!";
        } elseif ($fileSize > $maxFileSize) {
            $error = "Ukuran file maksimal 10MB.";
        } else {
            $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $fileName);
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $file_materi = $newFileName;
            } else {
                $error = "Gagal mengunggah file!";
            }
        }
    }
    
    if (!isset($error)) {
        // Tentukan target kelas
        $target_kelas = [];
        
        if ($tambah_ke_semua_kelas) {
            // Ambil semua kelas aktif dan insert ke masing-masing
            $kelasAktifQuery = "SELECT id_kelas, nama_kelas FROM kelas k 
                                JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                                WHERE g.status = 'aktif'";
            $result = mysqli_query($conn, $kelasAktifQuery);
            
            if (mysqli_num_rows($result) == 0) {
                $error = "Tidak ada kelas aktif!";
            } else {
                while ($kelas = mysqli_fetch_assoc($result)) {
                    $target_kelas[] = $kelas;
                }
            }
        } elseif ($id_kelas) {
            $kelasCheck = mysqli_query($conn, "SELECT id_kelas, nama_kelas FROM kelas k 
                                               JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
                                               WHERE k.id_kelas = '$id_kelas' AND g.status = 'aktif'");
            if (mysqli_num_rows($kelasCheck) == 0) {
                $error = "Kelas tidak valid!";
            } else {
                $target_kelas[] = mysqli_fetch_assoc($kelasCheck);
            }
        } else {
            $target_kelas[] = null;
        }
        
        // Validasi instruktur
        if ($id_instruktur) {
            $instrukturCheck = mysqli_query($conn, "SELECT nama FROM instruktur WHERE id_instruktur = '$id_instruktur'");
            if (mysqli_num_rows($instrukturCheck) == 0) {
                $error = "Instruktur tidak valid!";
            }
        }
        
        // Cek duplikasi
        if (!isset($error)) {
            $duplicate_classes = [];
            foreach ($target_kelas as $kelas) {
                if ($kelas) {
                    $duplicateQuery = "SELECT COUNT(*) as count FROM materi 
                                       WHERE judul = '$judul' AND id_kelas = '{$kelas['id_kelas']}'";
                    $result = mysqli_query($conn, $duplicateQuery);
                    $count = mysqli_fetch_assoc($result)['count'];
                    
                    if ($count > 0) {
                        $duplicate_classes[] = $kelas['nama_kelas'];
                    }
                }
            }
            
            if (!empty($duplicate_classes)) {
                $error = "Materi sudah ada pada kelas: " . implode(', ', $duplicate_classes);
            }
        }
        
        // Insert data
        if (!isset($error)) {
            mysqli_begin_transaction($conn);
            
            try {
                $success_count = 0;
                
                foreach ($target_kelas as $kelas) {
                    $kelas_id = $kelas ? "'{$kelas['id_kelas']}'" : "NULL";
                    $instruktur_id = $id_instruktur ? "'$id_instruktur'" : "NULL";
                    $file_value = $file_materi ? "'$file_materi'" : "NULL";
                    
                    $query = "INSERT INTO materi (judul, deskripsi, id_kelas, id_instruktur, file_materi) 
                              VALUES ('$judul', '$deskripsi', $kelas_id, $instruktur_id, $file_value)";
                    
                    if (mysqli_query($conn, $query)) {
                        $success_count++;
                    }
                }
                
                if ($success_count > 0) {
                    mysqli_commit($conn);
                    
                    if ($tambah_ke_semua_kelas) {
                        $_SESSION['success'] = "Materi berhasil ditambahkan ke $success_count kelas aktif.";
                    } else {
                        $_SESSION['success'] = "Materi berhasil ditambahkan!";
                    }
                    
                    header("Location: index.php");
                    exit;
                } else {
                    throw new Exception("Gagal menambahkan materi!");
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
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
          <div class="d-flex align-items-center flex-grow-1">
            <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
              <i class="bi bi-list fs-4"></i>
            </button>
            
            <div class="page-info">
              <h2 class="page-title mb-1">TAMBAH DATA MATERI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                  <li class="breadcrumb-item"><a href="#">Data Master</a></li>
                  <li class="breadcrumb-item"><a href="index.php">Data Materi</a></li>
                  <li class="breadcrumb-item active">Tambah Data</li>
                </ol>
              </nav>
            </div>
          </div>
          
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

      <!-- Form Card -->
      <div class="card content-card">
        <div class="section-header">
          <h5 class="mb-0 text-dark">
            <i class="bi bi-journal-plus me-2"></i>Form Tambah Materi
          </h5>
        </div>

        <div class="card-body">
          <form action="" method="post" enctype="multipart/form-data" id="formTambahMateri">
            
            <div class="row justify-content-center">
              <div class="col-lg-8 col-xl-7">
                <div class="mb-3">
                  <label class="form-label required">Judul Materi</label>
                  <input type="text" name="judul" class="form-control" required 
                         value="<?= isset($_POST['judul']) ? htmlspecialchars($_POST['judul']) : '' ?>"
                         placeholder="Contoh: Pengenalan Microsoft Word">
                  <div id="duplicate-feedback" class="invalid-feedback"></div>
                </div>

                <div class="mb-3">
                  <label class="form-label required">Deskripsi</label>
                  <textarea name="deskripsi" class="form-control" rows="2" required 
                            placeholder="Deskripsi singkat materi"><?= isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '' ?></textarea>
                </div>

                <!-- Target Kelas - Dipindah ke atas -->
                <div class="mb-3">
                  <label class="form-label">Target Kelas</label>
                  
                  <div class="mb-2">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="tambah_ke_semua_kelas" 
                             id="checkboxSemua" value="1"
                             <?= isset($_POST['tambah_ke_semua_kelas']) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="checkboxSemua">
                        Tambah ke semua kelas aktif (<?= $kelasAktifCount ?> kelas)
                      </label>
                    </div>
                  </div>
                  
                  <select name="id_kelas" class="form-select" id="selectKelas">
                    <option value="">Pilih kelas tertentu atau kosongkan untuk materi umum</option>
                    <?php 
                    mysqli_data_seek($kelasResult, 0);
                    while($kelas = mysqli_fetch_assoc($kelasResult)): 
                    ?>
                      <option value="<?= $kelas['id_kelas'] ?>" 
                              <?= (isset($_POST['id_kelas']) && $_POST['id_kelas'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                        <?= $kelas['nama_gelombang'] ? ' - ' . htmlspecialchars($kelas['nama_gelombang']) : '' ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  
                  <div id="previewKelas" class="mt-1 d-none">
                    <small class="text-success">
                      <i class="bi bi-check-circle me-1"></i>
                      Materi akan ditambahkan ke semua kelas aktif
                    </small>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Instruktur</label>
                      <select name="id_instruktur" class="form-select">
                        <option value="">Pilih Instruktur</option>
                        <?php while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                          <option value="<?= $instruktur['id_instruktur'] ?>" 
                                  <?= (isset($_POST['id_instruktur']) && $_POST['id_instruktur'] == $instruktur['id_instruktur']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($instruktur['nama']) ?>
                          </option>
                        <?php endwhile; ?>
                      </select>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">File Materi</label>
                      <input type="file" name="file_materi" class="form-control" 
                             accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                      <small class="text-muted">PDF, DOC, PPT, XLS (Max: 10MB)</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
              <a href="index.php" class="btn btn-kembali">Kembali</a>
              <button type="submit" class="btn btn-simpan">
                <i class="bi bi-check-lg me-1"></i>Simpan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../../assets/js/scripts.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const checkboxSemua = document.getElementById('checkboxSemua');
  const selectKelas = document.getElementById('selectKelas');
  const previewKelas = document.getElementById('previewKelas');

  // Toggle kelas
  checkboxSemua.addEventListener('change', function() {
    if (this.checked) {
      selectKelas.disabled = true;
      selectKelas.value = '';
      selectKelas.classList.add('bg-light');
      previewKelas.classList.remove('d-none');
    } else {
      selectKelas.disabled = false;
      selectKelas.classList.remove('bg-light');
      previewKelas.classList.add('d-none');
    }
  });

  // Form validation
  document.getElementById('formTambahMateri').addEventListener('submit', function(e) {
    // Basic required field validation
    const requiredFields = this.querySelectorAll('[required]');
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
      return;
    }

    // Show loading state immediately
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
  });

  // Initialize
  if (checkboxSemua.checked) {
    checkboxSemua.dispatchEvent(new Event('change'));
  }
});
</script>
</body>
</html>