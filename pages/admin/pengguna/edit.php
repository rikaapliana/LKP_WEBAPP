<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pengguna'; 
$baseURL = '../';

// Ambil ID user dari parameter URL
$id_user = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_user <= 0) {
    $_SESSION['error'] = "ID pengguna tidak valid!";
    header("Location: index.php");
    exit;
}

// Handle AJAX request untuk check duplicate username (exclude current record)
if (isset($_POST['ajax_check_duplicate'])) {
    header('Content-Type: application/json');
    
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $current_id = (int)$_POST['current_id'];
    
    // Cek duplikasi dengan mengecualikan record yang sedang diedit
    $duplicateQuery = "SELECT id_user, username FROM user 
                       WHERE username = '$username' AND id_user != '$current_id'";
    $duplicateResult = mysqli_query($conn, $duplicateQuery);
    
    $response = [
        'duplicate' => mysqli_num_rows($duplicateResult) > 0,
        'count' => mysqli_num_rows($duplicateResult)
    ];
    
    echo json_encode($response);
    exit;
}

// Ambil data user yang akan diedit dengan join ke tabel role
$userQuery = "SELECT u.id_user, u.username, u.role, u.created_at,
              CASE 
                WHEN u.role = 'admin' THEN a.nama
                WHEN u.role = 'instruktur' THEN i.nama
                WHEN u.role = 'siswa' THEN s.nama
                ELSE NULL
              END as nama_lengkap,
              CASE 
                WHEN u.role = 'admin' THEN a.email
                WHEN u.role = 'instruktur' THEN i.email
                WHEN u.role = 'siswa' THEN s.email
                ELSE NULL
              END as email,
              CASE 
                WHEN u.role = 'admin' THEN a.no_hp
                WHEN u.role = 'instruktur' THEN NULL
                WHEN u.role = 'siswa' THEN s.no_hp
                ELSE NULL
              END as no_hp,
              CASE 
                WHEN u.role = 'admin' THEN a.id_admin
                WHEN u.role = 'instruktur' THEN i.id_instruktur
                WHEN u.role = 'siswa' THEN s.id_siswa
                ELSE NULL
              END as role_id
              FROM user u 
              LEFT JOIN admin a ON u.id_user = a.id_user AND u.role = 'admin'
              LEFT JOIN instruktur i ON u.id_user = i.id_user AND u.role = 'instruktur'
              LEFT JOIN siswa s ON u.id_user = s.id_user AND u.role = 'siswa'
              WHERE u.id_user = '$id_user'";
$userResult = mysqli_query($conn, $userQuery);

if (mysqli_num_rows($userResult) == 0) {
    $_SESSION['error'] = "Data pengguna tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$userData = mysqli_fetch_assoc($userResult);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_check_duplicate'])) {
    // Sanitize input
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    $change_password = isset($_POST['change_password']) ? true : false;
    
    // Validasi input
    if (empty($username)) {
        $error = "Username tidak boleh kosong!";
    } elseif (strlen($username) < 3) {
        $error = "Username minimal 3 karakter!";
    } elseif (strlen($username) > 20) {
        $error = "Username maksimal 20 karakter!";
    } elseif (!preg_match('/^[a-zA-Z0-9_\s]+$/', $username)) {
        $error = "Username hanya boleh berisi huruf, angka, underscore, dan spasi!";
    } elseif ($change_password && empty($new_password)) {
        $error = "Password baru tidak boleh kosong!";
    } elseif ($change_password && strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } elseif ($change_password && $new_password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Validasi duplikasi username (exclude current record)
        $duplicateQuery = "SELECT id_user, username FROM user 
                           WHERE username = '$username' AND id_user != '$id_user'";
        $duplicateResult = mysqli_query($conn, $duplicateQuery);
        
        if (mysqli_num_rows($duplicateResult) > 0) {
            $error = "Username '" . htmlspecialchars($username) . "' sudah digunakan oleh pengguna lain!";
        } else {
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update username di tabel user
                if ($change_password) {
                    // Hash password baru
                    $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
                    $queryUser = "UPDATE user SET 
                                  username = '$username',
                                  password = '$hashed_password'
                                  WHERE id_user = '$id_user'";
                } else {
                    $queryUser = "UPDATE user SET 
                                  username = '$username'
                                  WHERE id_user = '$id_user'";
                }
                
                if (!mysqli_query($conn, $queryUser)) {
                    throw new Exception("Gagal memperbarui data user: " . mysqli_error($conn));
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                $successMessage = "Data pengguna berhasil diperbarui!<br>" .
                                  "<strong>Username:</strong> " . htmlspecialchars($username);
                if ($change_password) {
                    $successMessage .= "<br><small class='text-info'>Password juga telah diperbarui</small>";
                }
                
                $_SESSION['success'] = $successMessage;
                header("Location: index.php");
                exit;
                
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
  <title>Edit Data Pengguna</title>
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
              <h2 class="page-title mb-1">EDIT DATA PENGGUNA</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Master</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Pengguna</a>
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
            <i class="bi bi-person-gear me-2"></i>Form Edit Pengguna
          </h5>
          <small class="text-muted">
            Username: <?= htmlspecialchars($userData['username']) ?> | 
            Role: <?= ucfirst($userData['role']) ?> | 
            Nama: <?= htmlspecialchars($userData['nama_lengkap'] ?? 'Belum diatur') ?>
          </small>
        </div>

        <div class="card-body">
          <form action="" method="post" id="formEditPengguna">
            <input type="hidden" name="current_id" value="<?= $id_user ?>">
            
            <div class="row justify-content-center">
              <div class="col-lg-8">
                
                <!-- Info User -->
                <div class="alert alert-light border">
                  <div class="row align-items-center">
                    <div class="col-md-8">
                      <h6 class="mb-1">
                        <i class="bi bi-person-circle text-primary me-2"></i>
                        <?= htmlspecialchars($userData['nama_lengkap'] ?? 'Nama belum diatur') ?>
                      </h6>
                      <small class="text-muted">
                        <span class="badge bg-<?= $userData['role'] == 'admin' ? 'danger' : ($userData['role'] == 'instruktur' ? 'primary' : 'success') ?> me-2">
                          <?= ucfirst($userData['role']) ?>
                        </span>
                        <?= $userData['email'] ? htmlspecialchars($userData['email']) : 'Email belum diatur' ?>
                      </small>
                    </div>
                    <div class="col-md-4 text-md-end">
                      <small class="text-muted">
                        <i class="bi bi-calendar-plus me-1"></i>
                        Bergabung: <?= date('d/m/Y', strtotime($userData['created_at'])) ?>
                      </small>
                    </div>
                  </div>
                </div>

                <h6 class="section-title mb-4">
                  <i class="bi bi-key me-2"></i>Data Login
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Username</label>
                  <input type="text" name="username" class="form-control" required 
                         minlength="3" maxlength="20"
                         value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : htmlspecialchars($userData['username']) ?>">
                  <div class="form-text">
                    <small>3-20 karakter, boleh berisi huruf, angka, underscore (_), dan spasi</small>
                  </div>
                  <div id="duplicate-feedback" class="invalid-feedback"></div>
                </div>

                <hr class="my-4">

                <h6 class="section-title mb-4">
                  <i class="bi bi-shield-lock me-2"></i>Ubah Password (Opsional)
                </h6>

                <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="changePasswordCheck" name="change_password">
                    <label class="form-check-label" for="changePasswordCheck">
                      <strong>Ubah Password</strong>
                    </label>
                  </div>
                  <div class="form-text">
                    <small>Centang jika ingin mengubah password pengguna</small>
                  </div>
                </div>

                <div id="passwordSection" style="display: none;">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="mb-4">
                        <label class="form-label">Password Baru</label>
                        <div class="input-group">
                          <input type="password" name="new_password" class="form-control" 
                                 minlength="6" id="newPassword">
                          <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                            <i class="bi bi-eye"></i>
                          </button>
                        </div>
                        <div class="form-text">
                          <small>Minimal 6 karakter</small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="mb-4">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <div class="input-group">
                          <input type="password" name="confirm_password" class="form-control" 
                                 minlength="6" id="confirmNewPassword">
                          <button class="btn btn-outline-secondary" type="button" id="toggleConfirmNewPassword">
                            <i class="bi bi-eye"></i>
                          </button>
                        </div>
                        <div class="form-text">
                          <small>Ulangi password baru yang sama</small>
                        </div>
                        <div id="password-match-feedback" class="invalid-feedback"></div>
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
  const form = document.getElementById('formEditPengguna');
  const changePasswordCheck = document.getElementById('changePasswordCheck');
  const passwordSection = document.getElementById('passwordSection');
  const newPasswordInput = document.getElementById('newPassword');
  const confirmNewPasswordInput = document.getElementById('confirmNewPassword');
  
  // Password visibility toggles
  const toggleNewPassword = document.getElementById('toggleNewPassword');
  const toggleConfirmNewPassword = document.getElementById('toggleConfirmNewPassword');

  toggleNewPassword.addEventListener('click', function() {
    const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    newPasswordInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
  });

  toggleConfirmNewPassword.addEventListener('click', function() {
    const type = confirmNewPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    confirmNewPasswordInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
  });

  // Show/hide password section
  changePasswordCheck.addEventListener('change', function() {
    if (this.checked) {
      passwordSection.style.display = 'block';
      newPasswordInput.required = true;
      confirmNewPasswordInput.required = true;
    } else {
      passwordSection.style.display = 'none';
      newPasswordInput.required = false;
      confirmNewPasswordInput.required = false;
      newPasswordInput.value = '';
      confirmNewPasswordInput.value = '';
      newPasswordInput.classList.remove('is-invalid');
      confirmNewPasswordInput.classList.remove('is-invalid');
    }
  });

  // Fungsi untuk cek duplikasi username secara real-time
  function checkDuplicateUsername() {
    const username = document.querySelector('input[name="username"]').value.trim();
    const currentId = document.querySelector('input[name="current_id"]').value;
    const usernameInput = document.querySelector('input[name="username"]');
    const feedbackDiv = document.getElementById('duplicate-feedback');
    
    if (!username || username.length < 3) {
        usernameInput.classList.remove('is-invalid');
        usernameInput.removeAttribute('data-duplicate');
        feedbackDiv.textContent = '';
        return;
    }
    
    // Kirim request AJAX untuk cek duplikasi
    const formData = new FormData();
    formData.append('username', username);
    formData.append('current_id', currentId);
    formData.append('ajax_check_duplicate', '1');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.duplicate) {
            usernameInput.classList.add('is-invalid');
            usernameInput.setAttribute('data-duplicate', 'true');
            feedbackDiv.textContent = `Username "${username}" sudah digunakan oleh pengguna lain`;
        } else {
            usernameInput.classList.remove('is-invalid');
            usernameInput.removeAttribute('data-duplicate');
            feedbackDiv.textContent = '';
        }
    })
    .catch(error => console.error('Error:', error));
  }

  // Fungsi untuk validasi kecocokan password
  function checkPasswordMatch() {
    const newPassword = newPasswordInput.value;
    const confirmPassword = confirmNewPasswordInput.value;
    const feedbackDiv = document.getElementById('password-match-feedback');
    
    if (changePasswordCheck.checked && confirmPassword.length > 0) {
      if (newPassword !== confirmPassword) {
        confirmNewPasswordInput.classList.add('is-invalid');
        confirmNewPasswordInput.setAttribute('data-mismatch', 'true');
        feedbackDiv.textContent = 'Password tidak cocok';
      } else {
        confirmNewPasswordInput.classList.remove('is-invalid');
        confirmNewPasswordInput.removeAttribute('data-mismatch');
        feedbackDiv.textContent = '';
      }
    }
  }

  // Event listeners untuk validasi real-time
  const usernameInput = document.querySelector('input[name="username"]');
  usernameInput.addEventListener('blur', checkDuplicateUsername);

  newPasswordInput.addEventListener('input', checkPasswordMatch);
  confirmNewPasswordInput.addEventListener('input', checkPasswordMatch);

  // Form submission validation
  form.addEventListener('submit', function(e) {
    const usernameInput = document.querySelector('input[name="username"]');
    
    // Cek apakah ada duplikasi username
    if (usernameInput.hasAttribute('data-duplicate')) {
        e.preventDefault();
        usernameInput.focus();
        return false;
    }

    // Cek apakah password cocok jika diubah
    if (changePasswordCheck.checked && confirmNewPasswordInput.hasAttribute('data-mismatch')) {
        e.preventDefault();
        confirmNewPasswordInput.focus();
        return false;
    }

    // Validasi field required
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

    // Validasi username pattern
    const username = usernameInput.value.trim();
    const usernamePattern = /^[a-zA-Z0-9_\s]+$/;
    if (username && !usernamePattern.test(username)) {
      usernameInput.classList.add('is-invalid');
      isValid = false;
      alert('Username hanya boleh berisi huruf, angka, underscore (_), dan spasi!');
    }

    // Validasi password jika diubah
    if (changePasswordCheck.checked) {
      const newPassword = newPasswordInput.value;
      const confirmPassword = confirmNewPasswordInput.value;
      
      if (newPassword.length < 6) {
        newPasswordInput.classList.add('is-invalid');
        isValid = false;
        alert('Password baru minimal 6 karakter!');
      }
      
      if (newPassword !== confirmPassword) {
        confirmNewPasswordInput.classList.add('is-invalid');
        isValid = false;
        alert('Konfirmasi password tidak cocok!');
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