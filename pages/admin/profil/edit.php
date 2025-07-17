<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'profil'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data admin berdasarkan user_id
$query = "SELECT a.id_admin, a.id_user, a.nama, a.no_hp, a.email, 
                 COALESCE(a.foto, '') as foto, u.username
          FROM admin a 
          JOIN user u ON a.id_user = u.id_user 
          WHERE a.id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

if (!$admin_data) {
    $_SESSION['error'] = "Data admin tidak ditemukan!";
    header("Location: index.php");
    exit;
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama = trim($_POST['nama']);
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    
    // Validasi input
    if (empty($nama) || empty($no_hp) || empty($email) || empty($username)) {
        $_SESSION['error'] = "Semua field harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid!";
    } else {
        try {
            $conn->begin_transaction();
            
            // Handle upload foto
            $foto_name = $admin_data['foto']; // Keep existing foto if no new upload
            
            // Check if user wants to delete current foto
            if (isset($_POST['delete_foto']) && $_POST['delete_foto'] == '1') {
                if (!empty($foto_name) && file_exists('../../../uploads/profile_pictures/' . $foto_name)) {
                    unlink('../../../uploads/profile_pictures/' . $foto_name);
                }
                $foto_name = '';
            }
            
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../../uploads/profile_pictures/';
                
                // Create directory if not exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES['foto']['tmp_name'];
                $file_name = $_FILES['foto']['name'];
                $file_size = $_FILES['foto']['size'];
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
                    $new_filename = time() . '_admin_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Delete old foto if exists
                        if (!empty($admin_data['foto']) && file_exists($upload_dir . $admin_data['foto'])) {
                            unlink($upload_dir . $admin_data['foto']);
                        }
                        $foto_name = $new_filename;
                    } else {
                        throw new Exception("Gagal upload foto!");
                    }
                }
            }
            
            // Update tabel admin
            $update_admin = "UPDATE admin SET nama = ?, no_hp = ?, email = ?, foto = ? WHERE id_user = ?";
            $stmt = $conn->prepare($update_admin);
            $stmt->bind_param("ssssi", $nama, $no_hp, $email, $foto_name, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update tabel admin: " . $stmt->error);
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
  <title>Edit Profil Admin</title>
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
                <h2 class="page-title mb-1">EDIT PROFIL</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Profil</a>
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
          <!-- Main Edit Form -->
          <div class="col-xl-8">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-person-gear me-2"></i>Edit Informasi Profil
                </h5>
              </div>
              
              <div class="p-4">
                <form method="POST" action="" enctype="multipart/form-data" class="no-double-submit-prevention">
                  <!-- Upload Foto Section -->
                  <div class="mb-4">
                    <h6 class="text-muted mb-3">
                      <i class="bi bi-camera me-2"></i>Foto Profil
                    </h6>
                    
                    <div class="row align-items-center">
                      <div class="col-auto">
                        <div class="position-relative">
                          <?php if (!empty($admin_data['foto']) && file_exists('../../../uploads/profile_pictures/' . $admin_data['foto'])): ?>
                            <img src="../../../uploads/profile_pictures/<?= $admin_data['foto'] ?>" 
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
                          <input class="form-control" type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png">
                          <div class="form-text">
                            <small class="text-muted">
                              <i class="bi bi-info-circle me-1"></i>
                              Format: JPG, JPEG, PNG. Maksimal 2MB
                            </small>
                          </div>
                        </div>
                        <?php if (!empty($admin_data['foto'])): ?>
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
                               value="<?= htmlspecialchars($admin_data['nama']) ?>" required />
                        <label for="nama">Nama Lengkap</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="no_hp" name="no_hp" type="tel" 
                               value="<?= htmlspecialchars($admin_data['no_hp']) ?>" required />
                        <label for="no_hp">No. HP</label>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row mb-4">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="email" name="email" type="email" 
                               value="<?= htmlspecialchars($admin_data['email']) ?>" required />
                        <label for="email">Email</label>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="username" name="username" type="text" 
                               value="<?= htmlspecialchars($admin_data['username']) ?>" required />
                        <label for="username">Username</label>
                      </div>
                    </div>
                  </div>

                  <div class="mt-4 mb-0">
                    <button class="btn btn-primary-formal" type="submit" name="update_profil">
                      <i class="bi bi-floppy me-2"></i>Simpan Perubahan
                    </button>
                    <a href="index.php" class="btn btn-secondary-formal ms-2">
                      <i class="bi bi-arrow-left me-2"></i>Batal
                    </a>
                  </div>
                </form>
              </div>
            </div>
          </div>
          
          <!-- Profile Preview Card -->
          <div class="col-xl-4">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-eye me-2"></i>Preview Profil
                </h5>
              </div>
              
              <div class="p-4">
                <div class="text-center mb-4">
                  <?php if (!empty($admin_data['foto']) && file_exists('../../../uploads/profile_pictures/' . $admin_data['foto'])): ?>
                    <img src="../../../uploads/profile_pictures/<?= $admin_data['foto'] ?>" 
                         alt="Foto Profil" 
                         class="rounded-circle mb-3" 
                         style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #e9ecef;">
                  <?php else: ?>
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; border: 4px solid #e9ecef;">
                      <i class="bi bi-person-fill" style="font-size: 3rem;"></i>
                    </div>
                  <?php endif; ?>
                  <h6 class="mb-1"><?= htmlspecialchars($admin_data['nama']) ?></h6>
                  <span class="badge bg-primary fs-6">Administrator</span>
                </div>
                
                <hr>
                
                <div class="row text-center mb-3">
                  <div class="col-12 mb-3">
                    <h6 class="text-muted mb-1">Username</h6>
                    <p class="mb-0 fw-medium"><?= htmlspecialchars($admin_data['username']) ?></p>
                  </div>
                  <div class="col-6">
                    <h6 class="text-muted mb-1">Email</h6>
                    <p class="mb-0 small"><?= htmlspecialchars($admin_data['email']) ?></p>
                  </div>
                  <div class="col-6">
                    <h6 class="text-muted mb-1">No. HP</h6>
                    <p class="mb-0 small"><?= htmlspecialchars($admin_data['no_hp']) ?></p>
                  </div>
                </div>
                
                <hr>
                
                <div class="text-center">
                  <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Preview akan diperbarui setelah data disimpan
                  </small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Preview foto sebelum upload
    document.getElementById('foto').addEventListener('change', function(e) {
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
          document.getElementById('foto').value = '';
          
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
