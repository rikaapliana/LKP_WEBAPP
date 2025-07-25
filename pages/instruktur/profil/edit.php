<?php
session_start();  
require_once '../../../includes/auth.php';  
requireInstrukturAuth();

include '../../../includes/db.php';
$activePage = 'profil'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data instruktur berdasarkan user_id
$query = "SELECT a.id_instruktur, a.id_user, a.nik, a.nama, a.jenis_kelamin, 
                 a.angkatan, a.status_aktif, a.email, COALESCE(a.pas_foto, '') as pas_foto, 
                 u.username, u.created_at
          FROM instruktur a 
          JOIN user u ON a.id_user = u.id_user 
          WHERE a.id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$instruktur_data = $result->fetch_assoc();

if (!$instruktur_data) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit;
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama = trim($_POST['nama']);
    $nik = trim($_POST['nik']);
    $email = trim($_POST['email']);
    $jenis_kelamin = trim($_POST['jenis_kelamin']);
    $angkatan = trim($_POST['angkatan']);
    $username = trim($_POST['username']);
    
    // Validasi input
    if (empty($nama) || empty($nik) || empty($email) || empty($jenis_kelamin) || empty($username)) {
        $_SESSION['error'] = "Field yang wajib diisi tidak boleh kosong!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid!";
    } else {
        try {
            $conn->begin_transaction();
            
            // Handle upload foto
            $foto_name = $instruktur_data['pas_foto']; // Keep existing foto if no new upload
            
            // Check if user wants to delete current foto
            if (isset($_POST['delete_foto']) && $_POST['delete_foto'] == '1') {
                if (!empty($foto_name) && file_exists('../../../uploads/profile_pictures/' . $foto_name)) {
                    unlink('../../../uploads/profile_pictures/' . $foto_name);
                }
                $foto_name = '';
            }
            
            if (isset($_FILES['pas_foto']) && $_FILES['pas_foto']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../../uploads/profile_pictures/';
                
                // Create directory if not exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES['pas_foto']['tmp_name'];
                $file_name = $_FILES['pas_foto']['name'];
                $file_size = $_FILES['pas_foto']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validasi file
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($file_ext, $allowed_ext)) {
                    throw new Exception("Format file harus JPG, JPEG, atau PNG!");
                } elseif ($file_size > $max_size) {
                    throw new Exception("Ukuran file maksimal 2MB!");
                } else {
                    // Generate unique filename
                    $new_filename = time() . '_instruktur_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Delete old foto if exists
                        if (!empty($instruktur_data['pas_foto']) && file_exists($upload_dir . $instruktur_data['pas_foto'])) {
                            unlink($upload_dir . $instruktur_data['pas_foto']);
                        }
                        $foto_name = $new_filename;
                    } else {
                        throw new Exception("Gagal upload foto!");
                    }
                }
            }
            
            // Update tabel instruktur
            $update_instruktur = "UPDATE instruktur SET nama = ?, nik = ?, email = ?, jenis_kelamin = ?, angkatan = ?, pas_foto = ? WHERE id_user = ?";
            $stmt = $conn->prepare($update_instruktur);
            $stmt->bind_param("ssssssi", $nama, $nik, $email, $jenis_kelamin, $angkatan, $foto_name, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update tabel instruktur: " . $stmt->error);
            }
            
            // Update tabel user (hanya username)
            $update_user = "UPDATE user SET username = ? WHERE id_user = ?";
            $stmt = $conn->prepare($update_user);
            $stmt->bind_param("si", $username, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update tabel user: " . $stmt->error);
            }
            
            $conn->commit();
            $_SESSION['success'] = "Profil berhasil diperbarui!";
            
            // Redirect ke halaman profil
            header("Location: index.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profil Instruktur - <?= htmlspecialchars($instruktur_data['nama']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/instruktur.php'; ?>

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
                <h2 class="page-title mb-1">EDIT PROFIL</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="profil.php">Profil</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <!-- Right: Optional Info -->
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
        <!-- Alert Error -->
        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
          <div class="col-xl-12">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-person-gear me-2"></i>Edit Informasi Profil
                </h5>
              </div>
              
              <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data" class="no-double-submit-prevention">
                  <!-- Upload Foto Section -->
                  <div class="mb-4">
                    <h6 class="text-muted mb-3">
                      <i class="bi bi-camera me-2"></i>Foto Profil
                    </h6>
                    
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <div class="position-relative">
                          <?php if (!empty($instruktur_data['pas_foto']) && file_exists('../../../uploads/profile_pictures/' . $instruktur_data['pas_foto'])): ?>
                            <img src="../../../uploads/profile_pictures/<?= $instruktur_data['pas_foto'] ?>" 
                                 alt="Foto Profil" 
                                 class="rounded-circle" 
                                 style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #e9ecef;"
                                 id="previewFoto">
                          <?php else: ?>
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 100px; height: 100px; border: 3px solid #e9ecef;"
                                 id="previewFoto">
                              <i class="bi bi-person-fill" style="font-size: 3rem;"></i>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="col">
                        <div class="mb-3">
                          <input class="form-control" type="file" id="pas_foto" name="pas_foto" accept=".jpg,.jpeg,.png">
                          <div class="form-text">
                            <small class="text-muted">
                              <i class="bi bi-info-circle me-1"></i>
                              Format: JPG, JPEG, PNG. Maksimal 2MB
                            </small>
                          </div>
                        </div>
                        <?php if (!empty($instruktur_data['pas_foto'])): ?>
                          <button type="button" class="btn btn-outline-danger btn-sm" id="hapusFoto">
                            <i class="bi bi-trash me-1"></i>Hapus Foto
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <hr class="my-4">

                  <!-- Form Data Diri -->
                  <h6 class="text-muted mb-3">
                    <i class="bi bi-person-lines-fill me-2"></i>Data Diri
                  </h6>
                  
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="nama" name="nama" type="text" 
                               value="<?= htmlspecialchars($instruktur_data['nama']) ?>" required />
                        <label for="nama">Nama Lengkap *</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="nik" name="nik" type="text" 
                               value="<?= htmlspecialchars($instruktur_data['nik']) ?>" required />
                        <label for="nik">NIK *</label>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="email" name="email" type="email" 
                               value="<?= htmlspecialchars($instruktur_data['email']) ?>" required />
                        <label for="email">Email *</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                          <option value="">Pilih Jenis Kelamin</option>
                          <option value="Laki-Laki" <?= $instruktur_data['jenis_kelamin'] == 'Laki-Laki' ? 'selected' : '' ?>>Laki-Laki</option>
                          <option value="Perempuan" <?= $instruktur_data['jenis_kelamin'] == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                        </select>
                        <label for="jenis_kelamin">Jenis Kelamin *</label>
                      </div>
                    </div>
                  </div>

                  <div class="row mb-4">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="angkatan" name="angkatan" type="text" 
                               value="<?= htmlspecialchars($instruktur_data['angkatan']) ?>" />
                        <label for="angkatan">Angkatan</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="username" name="username" type="text" 
                               value="<?= htmlspecialchars($instruktur_data['username']) ?>" required />
                        <label for="username">Username *</label>
                      </div>
                    </div>
                  </div>

                  <div class="mt-4 mb-0">
                    <button class="btn btn-primary-formal" type="submit" name="update_profil">
                      <i class="bi bi-floppy me-2"></i>Simpan Perubahan
                    </button>
                    <a href="index.php" class="btn btn-secondary-formal ms-2">
                     Batal
                    </a>
                  </div>
                </form>
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
    // Preview foto sebelum upload
    document.getElementById('pas_foto').addEventListener('change', function(e) {
      const file = e.target.files[0];
      const preview = document.getElementById('previewFoto');
      
      if (file) {
        // Validasi file
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!allowedTypes.includes(file.type)) {
          alert('Format file harus JPG, JPEG, atau PNG!');
          this.value = '';
          return;
        }
        
        if (file.size > maxSize) {
          alert('Ukuran file maksimal 2MB!');
          this.value = '';
          return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.innerHTML = `<img src="${e.target.result}" 
                                   alt="Preview" 
                                   class="rounded-circle" 
                                   style="width: 100px; height: 100px; object-fit: cover; border: 3px solid #e9ecef;">`;
        };
        reader.readAsDataURL(file);
      }
    });

    // Hapus foto
    const hapusFotoBtn = document.getElementById('hapusFoto');
    if (hapusFotoBtn) {
      hapusFotoBtn.addEventListener('click', function() {
        if (confirm('Yakin ingin menghapus foto profil?')) {
          // Reset preview to default
          const preview = document.getElementById('previewFoto');
          preview.innerHTML = `<div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                   style="width: 100px; height: 100px; border: 3px solid #e9ecef;">
                                 <i class="bi bi-person-fill" style="font-size: 3rem;"></i>
                               </div>`;
          
          // Clear file input
          document.getElementById('pas_foto').value = '';
          
          // Add hidden input to mark foto for deletion
          const form = document.querySelector('form');
          let deleteInput = document.getElementById('deleteFoto');
          if (!deleteInput) {
            deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_foto';
            deleteInput.id = 'deleteFoto';
            deleteInput.value = '1';
            form.appendChild(deleteInput);
          }
          
          // Hide delete button
          this.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>