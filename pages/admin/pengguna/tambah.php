<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pengguna'; 
$baseURL = '../';

// Handle AJAX request untuk check duplicate username
if (isset($_POST['ajax_check_duplicate'])) {
    header('Content-Type: application/json');
    
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    
    // Cek duplikasi username
    $duplicateQuery = "SELECT id_user, username FROM user WHERE username = '$username'";
    $duplicateResult = mysqli_query($conn, $duplicateQuery);
    
    $response = [
        'duplicate' => mysqli_num_rows($duplicateResult) > 0,
        'count' => mysqli_num_rows($duplicateResult)
    ];
    
    echo json_encode($response);
    exit;
}

// Ambil data yang belum punya akun user berdasarkan role
function getDataWithoutUser($conn, $role) {
    if ($role == 'admin') {
        $query = "SELECT a.id_admin as id, a.nama, a.email FROM admin a WHERE a.id_user IS NULL ORDER BY a.nama ASC";
    } elseif ($role == 'instruktur') {
        $query = "SELECT i.id_instruktur as id, i.nama, i.email FROM instruktur i WHERE i.id_user IS NULL ORDER BY i.nama ASC";
    } elseif ($role == 'siswa') {
        $query = "SELECT s.id_siswa as id, s.nama, s.email FROM siswa s WHERE s.id_user IS NULL ORDER BY s.nama ASC";
    } else {
        return [];
    }
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_check_duplicate'])) {
    // Sanitize input sesuai struktur database
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $target_id = (int)$_POST['target_id']; // ID dari admin/instruktur/siswa yang dipilih
    
    // Validasi input
    if (empty($username)) {
        $error = "Username tidak boleh kosong!";
    } elseif (strlen($username) < 3) {
        $error = "Username minimal 3 karakter!";
    } elseif (strlen($username) > 20) {
        $error = "Username maksimal 20 karakter!";
    } elseif (!preg_match('/^[a-zA-Z0-9_\s]+$/', $username)) {
        $error = "Username hanya boleh berisi huruf, angka, underscore, dan spasi!";
    } elseif (empty($password)) {
        $error = "Password tidak boleh kosong!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif (!in_array($role, ['admin', 'instruktur', 'siswa'])) {
        $error = "Role tidak valid!";
    } elseif ($target_id <= 0) {
        $error = "Harap pilih " . $role . " yang akan dibuatkan akun!";
    } else {
        // Validasi duplikasi username
        $duplicateQuery = "SELECT id_user, username FROM user WHERE username = '$username'";
        $duplicateResult = mysqli_query($conn, $duplicateQuery);
        
        if (mysqli_num_rows($duplicateResult) > 0) {
            $error = "Username '" . htmlspecialchars($username) . "' sudah digunakan!";
        } else {
            // Validasi apakah target ID ada dan belum punya akun
            if ($role == 'admin') {
                $checkQuery = "SELECT id_admin, nama FROM admin WHERE id_admin = '$target_id' AND id_user IS NULL";
            } elseif ($role == 'instruktur') {
                $checkQuery = "SELECT id_instruktur, nama FROM instruktur WHERE id_instruktur = '$target_id' AND id_user IS NULL";
            } elseif ($role == 'siswa') {
                $checkQuery = "SELECT id_siswa, nama FROM siswa WHERE id_siswa = '$target_id' AND id_user IS NULL";
            }
            
            $checkResult = mysqli_query($conn, $checkQuery);
            
            if (mysqli_num_rows($checkResult) == 0) {
                $error = ucfirst($role) . " tidak ditemukan atau sudah memiliki akun!";
            } else {
                $targetData = mysqli_fetch_assoc($checkResult);
                
                // Begin transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Hash password dengan algoritma yang kuat
                    $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
                    
                    // Insert ke tabel user dulu
                    $queryUser = "INSERT INTO user (username, password, role, created_at) 
                                  VALUES ('$username', '$hashed_password', '$role', NOW())";
                    
                    if (!mysqli_query($conn, $queryUser)) {
                        throw new Exception("Gagal menambahkan data ke tabel user: " . mysqli_error($conn));
                    }
                    
                    // Ambil ID user yang baru dibuat
                    $user_id = mysqli_insert_id($conn);
                    
                    // Update tabel role dengan id_user yang baru
                    if ($role == 'admin') {
                        $updateQuery = "UPDATE admin SET id_user = '$user_id' WHERE id_admin = '$target_id'";
                    } elseif ($role == 'instruktur') {
                        $updateQuery = "UPDATE instruktur SET id_user = '$user_id' WHERE id_instruktur = '$target_id'";
                    } elseif ($role == 'siswa') {
                        $updateQuery = "UPDATE siswa SET id_user = '$user_id' WHERE id_siswa = '$target_id'";
                    }
                    
                    if (!mysqli_query($conn, $updateQuery)) {
                        throw new Exception("Gagal menghubungkan akun dengan data " . $role . ": " . mysqli_error($conn));
                    }
                    
                    // Commit transaction - user dan role sudah terhubung
                    mysqli_commit($conn);
                    
                    $_SESSION['success'] = "Akun berhasil dibuat dan terhubung!<br>" .
                                           "<strong>Username:</strong> " . htmlspecialchars($username) . "<br>" .
                                           "<strong>Nama:</strong> " . htmlspecialchars($targetData['nama']) . "<br>" .
                                           "<strong>Role:</strong> " . ucfirst($role) . "<br>" .
                                           "<small class='text-info'>Akun siap digunakan untuk login</small>";
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
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Akun Pengguna</title>
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
              <h2 class="page-title mb-1">TAMBAH AKUN PENGGUNA</h2>
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
                  <li class="breadcrumb-item active" aria-current="page">Tambah Akun</li>
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
            <i class="bi bi-person-plus-fill me-2"></i>Buatkan Akun Login
          </h5>
          <small class="text-muted">Buat akun login untuk Admin/Instruktur/Siswa yang sudah ada</small>
        </div>

        <div class="card-body">
          <form action="" method="post" id="formTambahPengguna">
            <div class="row justify-content-center">
              <div class="col-lg-8">
                
                <!-- Step 1: Pilih Role & Orang -->
                <h6 class="section-title mb-4">
                  <i class="bi bi-1-circle me-2"></i>Pilih Data yang Akan Dibuatkan Akun
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Role/Peran</label>
                  <select name="role" class="form-select" required id="roleSelect">
                    <option value="">Pilih Role</option>
                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>
                      Admin
                    </option>
                    <option value="instruktur" <?= (isset($_POST['role']) && $_POST['role'] == 'instruktur') ? 'selected' : '' ?>>
                      Instruktur
                    </option>
                    <option value="siswa" <?= (isset($_POST['role']) && $_POST['role'] == 'siswa') ? 'selected' : '' ?>>
                      Siswa
                    </option>
                  </select>
                  <div class="form-text">
                    <small>Pilih kategori pengguna</small>
                  </div>
                </div>

                <div class="mb-4" id="targetSelectContainer" style="display: none;">
                  <label class="form-label required" id="targetSelectLabel">Pilih Orang</label>
                  <select name="target_id" class="form-select" required id="targetSelect">
                    <option value="">Pilih...</option>
                  </select>
                  <div class="form-text">
                    <small id="targetSelectHelp">Pilih orang yang akan dibuatkan akun login</small>
                  </div>
                  <div id="target-info" class="mt-2"></div>
                </div>

                <hr class="my-4">

                <!-- Step 2: Buat Username & Password -->
                <h6 class="section-title mb-4">
                  <i class="bi bi-2-circle me-2"></i>Buat Data Login
                </h6>

                <div class="mb-4">
                  <label class="form-label required">Username</label>
                  <input type="text" name="username" class="form-control" required 
                         minlength="3" maxlength="20"
                         value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                  <div class="form-text">
                    <small>3-20 karakter, boleh berisi huruf, angka, underscore (_), dan spasi</small>
                  </div>
                  <div id="duplicate-feedback" class="invalid-feedback"></div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Password</label>
                      <div class="input-group">
                        <input type="password" name="password" class="form-control" required 
                               minlength="6" id="password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
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
                      <label class="form-label required">Konfirmasi Password</label>
                      <div class="input-group">
                        <input type="password" name="confirm_password" class="form-control" required 
                               minlength="6" id="confirmPassword">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                          <i class="bi bi-eye"></i>
                        </button>
                      </div>
                      <div class="form-text">
                        <small>Ulangi password yang sama</small>
                      </div>
                      <div id="password-match-feedback" class="invalid-feedback"></div>
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
                    <i class="bi bi-check-lg me-1"></i>Buat Akun
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
  const form = document.getElementById('formTambahPengguna');
  const roleSelect = document.getElementById('roleSelect');
  const targetSelectContainer = document.getElementById('targetSelectContainer');
  const targetSelect = document.getElementById('targetSelect');
  const targetSelectLabel = document.getElementById('targetSelectLabel');
  const targetSelectHelp = document.getElementById('targetSelectHelp');
  const targetInfo = document.getElementById('target-info');
  
  // Password visibility toggle
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');
  const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
  const confirmPasswordInput = document.getElementById('confirmPassword');

  togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
  });

  toggleConfirmPassword.addEventListener('click', function() {
    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    confirmPasswordInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
  });

  // Handle role change - load data via AJAX
  roleSelect.addEventListener('change', function() {
    const selectedRole = this.value;
    
    if (selectedRole) {
      targetSelectContainer.style.display = 'block';
      loadTargetData(selectedRole);
    } else {
      targetSelectContainer.style.display = 'none';
      targetSelect.innerHTML = '<option value="">Pilih...</option>';
      targetInfo.innerHTML = '';
    }
  });

  // Handle target selection change
  targetSelect.addEventListener('change', function() {
    const selectedId = this.value;
    const selectedRole = roleSelect.value;
    
    if (selectedId && selectedRole) {
      showTargetInfo(selectedRole, selectedId);
    } else {
      targetInfo.innerHTML = '';
    }
  });

  function loadTargetData(role) {
    // Show loading
    targetSelect.innerHTML = '<option value="">Loading...</option>';
    targetSelect.disabled = true;
    
    // Update labels
    let labelText = 'Pilih ' + role.charAt(0).toUpperCase() + role.slice(1);
    let helpText = 'Pilih ' + role + ' yang akan dibuatkan akun login';
    
    targetSelectLabel.textContent = labelText;
    targetSelectHelp.textContent = helpText;
    
    // AJAX request to get data
    fetch('get_target_data.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'role=' + encodeURIComponent(role)
    })
    .then(response => response.json())
    .then(data => {
      targetSelect.innerHTML = '<option value="">Pilih...</option>';
      targetSelect.disabled = false;
      
      if (data.length === 0) {
        targetSelect.innerHTML = '<option value="">Tidak ada ' + role + ' yang belum punya akun</option>';
        targetSelect.disabled = true;
        targetInfo.innerHTML = '<div class="alert alert-info small"><i class="bi bi-exclamation-triangle me-2"></i>Semua ' + role + ' sudah memiliki akun atau belum ada data ' + role + '.</div>';
      } else {
        data.forEach(item => {
          const option = document.createElement('option');
          option.value = item.id;
          option.textContent = item.nama + (item.email ? ' (' + item.email + ')' : '');
          option.dataset.info = JSON.stringify(item);
          targetSelect.appendChild(option);
        });
        targetInfo.innerHTML = '<div class="alert alert-success small"><i class="bi bi-check-circle me-2"></i>Ditemukan ' + data.length + ' ' + role + ' yang belum memiliki akun.</div>';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      targetSelect.innerHTML = '<option value="">Error loading data</option>';
      targetSelect.disabled = true;
      targetInfo.innerHTML = '<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle me-2"></i>Gagal memuat data.</div>';
    });
  }

  function showTargetInfo(role, targetId) {
    const selectedOption = targetSelect.querySelector(`option[value="${targetId}"]`);
    if (selectedOption && selectedOption.dataset.info) {
      const item = JSON.parse(selectedOption.dataset.info);
      
      const infoHtml = `
        <div class="card bg-light border-0">
          <div class="card-body p-3">
            <h6 class="card-title mb-2">
              <i class="bi bi-person-check text-success me-2"></i>
              ${item.nama}
            </h6>
            <small class="text-muted">
              ${item.email ? '<i class="bi bi-envelope me-1"></i>' + item.email : '<i class="bi bi-envelope-slash me-1"></i>Email belum diatur'}
            </small>
            <div class="mt-2">
              <span class="badge bg-primary">${role.toUpperCase()}</span>
            </div>
          </div>
        </div>
      `;
      targetInfo.innerHTML = infoHtml;
    }
  }

  // Fungsi untuk cek duplikasi username secara real-time
  function checkDuplicateUsername() {
    const username = document.querySelector('input[name="username"]').value.trim();
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
            feedbackDiv.textContent = `Username "${username}" sudah digunakan`;
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
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    const feedbackDiv = document.getElementById('password-match-feedback');
    
    if (confirmPassword.length > 0) {
      if (password !== confirmPassword) {
        confirmPasswordInput.classList.add('is-invalid');
        confirmPasswordInput.setAttribute('data-mismatch', 'true');
        feedbackDiv.textContent = 'Password tidak cocok';
      } else {
        confirmPasswordInput.classList.remove('is-invalid');
        confirmPasswordInput.removeAttribute('data-mismatch');
        feedbackDiv.textContent = '';
      }
    }
  }

  // Event listeners untuk validasi real-time
  const usernameInput = document.querySelector('input[name="username"]');
  usernameInput.addEventListener('blur', checkDuplicateUsername);

  passwordInput.addEventListener('input', checkPasswordMatch);
  confirmPasswordInput.addEventListener('input', checkPasswordMatch);

  // Form submission validation
  form.addEventListener('submit', function(e) {
    const usernameInput = document.querySelector('input[name="username"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    
    // Cek apakah ada duplikasi username
    if (usernameInput.hasAttribute('data-duplicate')) {
        e.preventDefault();
        usernameInput.focus();
        return false;
    }

    // Cek apakah password cocok
    if (confirmPasswordInput.hasAttribute('data-mismatch')) {
        e.preventDefault();
        confirmPasswordInput.focus();
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

    // Validasi password length
    const password = passwordInput.value;
    if (password && password.length < 6) {
      passwordInput.classList.add('is-invalid');
      isValid = false;
      alert('Password minimal 6 karakter!');
    }

    // Validasi password match
    const confirmPassword = confirmPasswordInput.value;
    if (password !== confirmPassword) {
      confirmPasswordInput.classList.add('is-invalid');
      isValid = false;
      alert('Konfirmasi password tidak cocok!');
    }

    // Validasi target selection
    const targetId = targetSelect.value;
    const role = roleSelect.value;
    if (role && !targetId) {
      targetSelect.classList.add('is-invalid');
      isValid = false;
      alert('Harap pilih ' + role + ' yang akan dibuatkan akun!');
    }

    if (!isValid) {
      e.preventDefault();
      alert('Harap lengkapi semua field yang wajib diisi dengan benar!');
      return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Membuat Akun...';
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