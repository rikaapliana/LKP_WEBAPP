<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'materi'; 
$baseURL = '../';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID materi tidak valid!";
    header("Location: index.php");
    exit;
}

$id_materi = (int)$_GET['id'];

// Ambil data materi
$materiQuery = "SELECT m.*, k.nama_kelas, g.nama_gelombang, i.nama as nama_instruktur
               FROM materi m 
               LEFT JOIN kelas k ON m.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               LEFT JOIN instruktur i ON m.id_instruktur = i.id_instruktur
               WHERE m.id_materi = '$id_materi'";
$materiResult = mysqli_query($conn, $materiQuery);

if (mysqli_num_rows($materiResult) == 0) {
    $_SESSION['error'] = "Data materi tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$materi = mysqli_fetch_assoc($materiResult);

// Ambil data kelas aktif untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang
               FROM kelas k 
               JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE g.status = 'aktif'
               ORDER BY k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Ambil data instruktur
$instrukturQuery = "SELECT id_instruktur, nama FROM instruktur ORDER BY nama ASC";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $id_kelas = !empty($_POST['id_kelas']) ? mysqli_real_escape_string($conn, $_POST['id_kelas']) : NULL;
    $id_instruktur = !empty($_POST['id_instruktur']) ? mysqli_real_escape_string($conn, $_POST['id_instruktur']) : NULL;
    
    // Handle file
    $file_materi = $materi['file_materi'];
    
    // Handle file deletion
    if (isset($_POST['hapus_file_materi']) && $_POST['hapus_file_materi'] == '1') {
        if (!empty($materi['file_materi']) && file_exists("../../../uploads/materi/" . $materi['file_materi'])) {
            unlink("../../../uploads/materi/" . $materi['file_materi']);
        }
        $file_materi = '';
    }
    
    // Upload file baru
    if (!empty($_FILES['file_materi']['name'])) {
        if (!empty($materi['file_materi']) && file_exists("../../../uploads/materi/" . $materi['file_materi'])) {
            unlink("../../../uploads/materi/" . $materi['file_materi']);
        }
        
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
                $file_materi = $materi['file_materi'];
                $error = "Gagal mengunggah file!";
            }
        }
    }
    
    if (!isset($error)) {
        // Validasi kelas aktif jika dipilih
        if ($id_kelas) {
            $kelasCheck = mysqli_query($conn, "SELECT nama_kelas FROM kelas k 
                                               JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
                                               WHERE k.id_kelas = '$id_kelas' AND g.status = 'aktif'");
            if (mysqli_num_rows($kelasCheck) == 0) {
                $error = "Kelas tidak valid atau tidak aktif!";
            }
        }
        
        // Validasi instruktur
        if ($id_instruktur) {
            $instrukturCheck = mysqli_query($conn, "SELECT nama FROM instruktur WHERE id_instruktur = '$id_instruktur'");
            if (mysqli_num_rows($instrukturCheck) == 0) {
                $error = "Instruktur tidak valid!";
            }
        }
        
        // Validasi duplikasi (exclude current materi)
        if (!isset($error) && $id_kelas) {
            $duplicateQuery = "SELECT COUNT(*) as count FROM materi 
                               WHERE judul = '$judul' AND id_kelas = '$id_kelas' AND id_materi != '$id_materi'";
            $result = mysqli_query($conn, $duplicateQuery);
            $count = mysqli_fetch_assoc($result)['count'];
            
            if ($count > 0) {
                $error = "Materi dengan judul yang sama sudah ada pada kelas ini!";
            }
        }
        
        if (!isset($error)) {
            mysqli_begin_transaction($conn);
            
            try {
                $query = "UPDATE materi SET 
                          judul = '$judul',
                          deskripsi = '$deskripsi',
                          id_kelas = " . ($id_kelas ? "'$id_kelas'" : "NULL") . ",
                          id_instruktur = " . ($id_instruktur ? "'$id_instruktur'" : "NULL") . ",
                          file_materi = " . ($file_materi ? "'$file_materi'" : "NULL") . "
                          WHERE id_materi = '$id_materi'";
                
                if (!mysqli_query($conn, $query)) {
                    throw new Exception("Gagal memperbarui data materi: " . mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                $_SESSION['success'] = "Data materi berhasil diperbarui!";
                header("Location: index.php");
                exit;
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                
                if ($file_materi && $file_materi != $materi['file_materi'] && file_exists($uploadDir . $file_materi)) {
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
  <title>Edit Data Materi</title>
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
              <h2 class="page-title mb-1">EDIT DATA MATERI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                  <li class="breadcrumb-item"><a href="#">Data Master</a></li>
                  <li class="breadcrumb-item"><a href="index.php">Data Materi</a></li>
                  <li class="breadcrumb-item active">Edit Data</li>
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
            <i class="bi bi-journal-text me-2"></i>Form Edit Materi
          </h5>
          <small class="text-muted">ID: <?= $materi['id_materi'] ?></small>
        </div>

        <div class="card-body">
          <form action="" method="post" enctype="multipart/form-data" id="formEditMateri">
            
            <div class="row justify-content-center">
              <div class="col-lg-8 col-xl-7">
                <div class="mb-3">
                  <label class="form-label required">Judul Materi</label>
                  <input type="text" name="judul" class="form-control" required 
                         value="<?= htmlspecialchars($materi['judul']) ?>"
                         placeholder="Contoh: Pengenalan Microsoft Word">
                </div>

                <div class="mb-3">
                  <label class="form-label required">Deskripsi</label>
                  <textarea name="deskripsi" class="form-control" rows="2" required 
                            placeholder="Deskripsi singkat materi"><?= htmlspecialchars($materi['deskripsi']) ?></textarea>
                  <small class="text-muted">Deskripsi singkat maksimal 2-3 kalimat</small>
                </div>

                <!-- Target Kelas -->
                <div class="mb-3">
                  <label class="form-label">Target Kelas</label>
                  <select name="id_kelas" class="form-select">
                    <option value="">Materi Umum (Tanpa Kelas)</option>
                    <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                      <option value="<?= $kelas['id_kelas'] ?>" 
                              <?= ($materi['id_kelas'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                        <?= $kelas['nama_gelombang'] ? ' - ' . htmlspecialchars($kelas['nama_gelombang']) : '' ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <small class="text-muted">Hanya kelas aktif yang ditampilkan</small>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">Instruktur</label>
                      <select name="id_instruktur" class="form-select">
                        <option value="">Pilih Instruktur</option>
                        <?php while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                          <option value="<?= $instruktur['id_instruktur'] ?>" 
                                  <?= ($materi['id_instruktur'] == $instruktur['id_instruktur']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($instruktur['nama']) ?>
                          </option>
                        <?php endwhile; ?>
                      </select>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">File Materi Baru</label>
                      <input type="file" name="file_materi" class="form-control" 
                             accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                      <small class="text-muted">PDF, DOC, PPT, XLS (Max: 10MB)</small>
                    </div>
                  </div>
                </div>

                <!-- File Saat Ini -->
                <?php if(!empty($materi['file_materi'])): ?>
                <div class="mb-3">
                  <label class="form-label">File Saat Ini</label>
                  <div class="border rounded p-3 bg-light">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center">
                        <?php
                        $fileExt = strtolower(pathinfo($materi['file_materi'], PATHINFO_EXTENSION));
                        $iconClass = 'bi-file-earmark';
                        $iconColor = 'text-secondary';
                        
                        if ($fileExt == 'pdf') {
                          $iconClass = 'bi-file-pdf'; $iconColor = 'text-danger';
                        } elseif (in_array($fileExt, ['doc', 'docx'])) {
                          $iconClass = 'bi-file-word'; $iconColor = 'text-primary';
                        } elseif (in_array($fileExt, ['ppt', 'pptx'])) {
                          $iconClass = 'bi-file-ppt'; $iconColor = 'text-warning';
                        } elseif (in_array($fileExt, ['xls', 'xlsx'])) {
                          $iconClass = 'bi-file-excel'; $iconColor = 'text-success';
                        }
                        ?>
                        <i class="bi <?= $iconClass ?> <?= $iconColor ?> fs-4 me-2"></i>
                        <span><?= htmlspecialchars($materi['file_materi']) ?></span>
                      </div>
                      <div class="d-flex gap-2">
                        <a href="../../../uploads/materi/<?= $materi['file_materi'] ?>" target="_blank" 
                           class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-download me-1"></i>Unduh
                        </a>
                      </div>
                    </div>
                    <div class="form-check mt-2">
                      <input type="checkbox" name="hapus_file_materi" value="1" class="form-check-input" id="hapus_file_materi">
                      <label class="form-check-label text-danger" for="hapus_file_materi">
                        <i class="bi bi-trash me-1"></i>Hapus file ini
                      </label>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
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
  const form = document.getElementById('formEditMateri');

  // Handle file deletion checkbox
  const deleteCheckbox = document.getElementById('hapus_file_materi');
  if (deleteCheckbox) {
    deleteCheckbox.addEventListener('change', function() {
      if (this.checked && !confirm('Yakin hapus file ini?')) {
        this.checked = false;
      }
    });
  }

  // Form validation
  form.addEventListener('submit', function(e) {
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

    // File validation
    const fileInput = document.querySelector('input[name="file_materi"]');
    if (fileInput.files && fileInput.files[0]) {
      const file = fileInput.files[0];
      const allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
      const fileExtension = file.name.split('.').pop().toLowerCase();
      const maxFileSize = 10 * 1024 * 1024; // 10MB

      if (!allowedExtensions.includes(fileExtension)) {
        fileInput.classList.add('is-invalid');
        isValid = false;
        alert('Format file tidak diizinkan!');
      } else if (file.size > maxFileSize) {
        fileInput.classList.add('is-invalid');
        isValid = false;
        alert('Ukuran file maksimal 10MB!');
      } else {
        fileInput.classList.remove('is-invalid');
      }
    }

    if (!isValid) {
      e.preventDefault();
      return;
    }

    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
  });
});
</script>
</body>
</html>