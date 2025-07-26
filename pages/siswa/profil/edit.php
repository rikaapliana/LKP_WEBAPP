<?php
session_start();  
require_once '../../../includes/auth.php';  
requireSiswaAuth();

include '../../../includes/db.php';
$activePage = 'profil-saya'; 
$baseURL = '../';

$user_id = $_SESSION['user_id'];

// Ambil data siswa berdasarkan user_id
$query = "SELECT s.*, u.username, u.created_at
          FROM siswa s 
          JOIN user u ON s.id_user = u.id_user 
          WHERE s.id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$siswa_data = $result->fetch_assoc();

if (!$siswa_data) {
    $_SESSION['error'] = "Data siswa tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit;
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $username = trim($_POST['username']);
    $no_hp = trim($_POST['no_hp']);
    $email = trim($_POST['email']);
    $alamat_lengkap = trim($_POST['alamat_lengkap']);
    
    // Validasi input
    if (empty($username) || empty($no_hp) || empty($email)) {
        $_SESSION['error'] = "Username, No. HP dan Email wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid!";
    } elseif (strlen($username) < 3) {
        $_SESSION['error'] = "Username minimal 3 karakter!";
    } else {
        // Cek username sudah dipakai user lain atau belum
        $stmt = $conn->prepare("SELECT id_user FROM user WHERE username = ? AND id_user != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $existing_user = $stmt->get_result()->fetch_assoc();
        
        if ($existing_user) {
            $_SESSION['error'] = "Username sudah digunakan, pilih username lain!";
        } else {
            try {
                $conn->begin_transaction();
                
                // Update tabel user (username) TERLEBIH DAHULU
                $update_user = "UPDATE user SET username = ? WHERE id_user = ?";
                $stmt = $conn->prepare($update_user);
                $stmt->bind_param("si", $username, $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Gagal update username: " . $stmt->error);
                }
                
                // Cek apakah username benar ter-update
                $stmt = $conn->prepare("SELECT username FROM user WHERE id_user = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $check_username = $stmt->get_result()->fetch_assoc();
                
                if ($check_username['username'] !== $username) {
                    throw new Exception("Username tidak ter-update dengan benar!");
                }
                
                // Handle upload foto
                $foto_name = $siswa_data['pas_foto']; // Keep existing foto if no new upload
                
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
                        // Generate unique filename for siswa
                        $new_filename = time() . '_siswa_' . $siswa_data['id_siswa'] . '_' . uniqid() . '.' . $file_ext;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            // Delete old foto if exists
                            if (!empty($siswa_data['pas_foto']) && file_exists($upload_dir . $siswa_data['pas_foto'])) {
                                unlink($upload_dir . $siswa_data['pas_foto']);
                            }
                            $foto_name = $new_filename;
                        } else {
                            throw new Exception("Gagal upload foto!");
                        }
                    }
                }
                
                // Update tabel siswa
                $update_siswa = "UPDATE siswa SET no_hp = ?, email = ?, alamat_lengkap = ?, pas_foto = ? WHERE id_user = ?";
                $stmt = $conn->prepare($update_siswa);
                $stmt->bind_param("ssssi", $no_hp, $email, $alamat_lengkap, $foto_name, $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Gagal update profil siswa: " . $stmt->error);
                }
                
                $conn->commit();
                $_SESSION['success'] = "Profil dan username berhasil diperbarui!";
                
                // Redirect ke halaman profil
                header("Location: index.php");
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Gagal memperbarui profil: " . $e->getMessage();
            }
        }
    }
}

// HAPUS BAGIAN PROSES UBAH PASSWORD - PINDAH KE FILE TERPISAH
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profil - <?= htmlspecialchars($siswa_data['nama']) ?></title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/siswa.php'; ?>

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
                      <a href="index.php">Profil Saya</a>
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
          <!-- Form Edit Profil -->
          <div class="col-xl-8">
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-person-gear me-2"></i>Edit Informasi Profil
                </h5>
                <small class="text-muted">Data yang dapat diubah</small>
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
                          <?php if (!empty($siswa_data['pas_foto']) && file_exists('../../../uploads/profile_pictures/' . $siswa_data['pas_foto'])): ?>
                            <img src="../../../uploads/profile_pictures/<?= $siswa_data['pas_foto'] ?>" 
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
                        <?php if (!empty($siswa_data['pas_foto'])): ?>
                          <button type="button" class="btn btn-outline-danger btn-sm" id="hapusFoto">
                            <i class="bi bi-trash me-1"></i>Hapus Foto
                          </button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <hr class="my-4">

                  <!-- Form Data Kontak -->
                  <h6 class="text-muted mb-3">
                    <i class="bi bi-person-lines-fill me-2"></i>Data Akun & Kontak
                  </h6>
                  
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="username" name="username" type="text" 
                               value="<?= htmlspecialchars($siswa_data['username']) ?>" required />
                        <label for="username">Username *</label>
                      </div>
                      <div class="form-text">
                        <small class="text-muted">Username untuk login ke sistem</small>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="no_hp" name="no_hp" type="text" 
                               value="<?= htmlspecialchars($siswa_data['no_hp'] ?? '') ?>" required />
                        <label for="no_hp">No. HP *</label>
                      </div>
                    </div>
                  </div>

                  <div class="row mb-3">
                    <div class="col-md-6">
                      <div class="form-floating">
                        <input class="form-control" id="email" name="email" type="email" 
                               value="<?= htmlspecialchars($siswa_data['email'] ?? '') ?>" required />
                        <label for="email">Email *</label>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row mb-4">
                    <div class="col-12">
                      <div class="form-floating">
                        <textarea class="form-control" id="alamat_lengkap" name="alamat_lengkap" 
                                  style="height: 100px"><?= htmlspecialchars($siswa_data['alamat_lengkap'] ?? '') ?></textarea>
                        <label for="alamat_lengkap">Alamat Lengkap</label>
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

          <!-- Form Ubah Password -->
          <div class="col-xl-4">
            <div class="card content-card mb-4">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-key me-2"></i>Ubah Password
                </h5>
                <small class="text-muted">Keamanan akun</small>
              </div>
              
              <div class="card-body p-4">
                <form method="POST" action="update_password.php">
                  <div class="mb-3">
                    <div class="form-floating position-relative">
                      <input class="form-control" id="password_lama" name="password_lama" type="password" required />
                      <label for="password_lama">Password Lama *</label>
                      <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 p-0" 
                              onclick="togglePassword('password_lama', this)" style="z-index: 10;">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>
                  
                  <div class="mb-3">
                    <div class="form-floating position-relative">
                      <input class="form-control" id="password_baru" name="password_baru" type="password" required />
                      <label for="password_baru">Password Baru *</label>
                      <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 p-0" 
                              onclick="togglePassword('password_baru', this)" style="z-index: 10;">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                    <div class="form-text">
                      <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                  </div>
                  
                  <div class="mb-4">
                    <div class="form-floating position-relative">
                      <input class="form-control" id="konfirmasi_password" name="konfirmasi_password" type="password" required />
                      <label for="konfirmasi_password">Konfirmasi Password *</label>
                      <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y me-2 p-0" 
                              onclick="togglePassword('konfirmasi_password', this)" style="z-index: 10;">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>

                  <div class="mt-4 mb-0">
                    <button class="btn btn-warning w-100" type="submit" name="ubah_password">
                      <i class="bi bi-shield-lock me-2"></i>Ubah Password
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- Info Data Tidak Dapat Diubah -->
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Informasi
                </h5>
              </div>
              
              <div class="card-body p-4">
                <div class="alert alert-info mb-3">
                  <i class="bi bi-exclamation-circle me-2"></i>
                  <strong>Data yang tidak dapat diubah:</strong>
                </div>
                
                <ul class="list-unstyled mb-0">
                  <li class="mb-2">
                    <i class="bi bi-lock text-muted me-2"></i>
                    <small class="text-muted">NIK & Nama Lengkap</small>
                  </li>
                  <li class="mb-2">
                    <i class="bi bi-lock text-muted me-2"></i>
                    <small class="text-muted">Tanggal Lahir</small>
                  </li>
                  <li class="mb-2">
                    <i class="bi bi-lock text-muted me-2"></i>
                    <small class="text-muted">Pendidikan Terakhir</small>
                  </li>
                  <li class="mb-2">
                    <i class="bi bi-lock text-muted me-2"></i>
                    <small class="text-muted">Kelas & Gelombang</small>
                  </li>
                </ul>
                
                <hr class="my-3">
                
                <div class="text-center">
                  <small class="text-muted">
                    <i class="bi bi-shield-check me-1"></i>
                    Data ini dilindungi sistem
                  </small>
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

    // Validasi konfirmasi password
    document.getElementById('konfirmasi_password').addEventListener('input', function() {
      const passwordBaru = document.getElementById('password_baru').value;
      const konfirmasi = this.value;
      
      if (konfirmasi && passwordBaru !== konfirmasi) {
        this.setCustomValidity('Konfirmasi password tidak sama');
        this.classList.add('is-invalid');
      } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
      }
    });

    // Check password strength
    document.getElementById('password_baru').addEventListener('input', function() {
      const password = this.value;
      
      if (password.length > 0 && password.length < 6) {
        this.setCustomValidity('Password minimal 6 karakter');
        this.classList.add('is-invalid');
      } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
      }
    });

    // Toggle password visibility
    function togglePassword(fieldId, button) {
      const field = document.getElementById(fieldId);
      const icon = button.querySelector('i');
      
      if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    }
  </script>
</body>
</html>