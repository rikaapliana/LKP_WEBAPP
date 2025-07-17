<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'kelas'; 
$baseURL = '../';

// Handle AJAX request untuk check duplicate
if (isset($_POST['ajax_check_duplicate'])) {
    header('Content-Type: application/json');
    
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $id_gelombang = mysqli_real_escape_string($conn, $_POST['id_gelombang']);
    
    // Cek duplikasi dengan join ke tabel gelombang untuk mendapatkan nama gelombang
    $duplicateQuery = "SELECT k.id_kelas, k.nama_kelas, g.nama_gelombang 
                       FROM kelas k 
                       JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
                       WHERE k.nama_kelas = '$nama_kelas' 
                       AND k.id_gelombang = '$id_gelombang'";
    
    $duplicateResult = mysqli_query($conn, $duplicateQuery);
    
    $response = [
        'duplicate' => mysqli_num_rows($duplicateResult) > 0,
        'count' => mysqli_num_rows($duplicateResult)
    ];
    
    // Jika ada duplikasi, tambahkan info gelombang
    if ($response['duplicate']) {
        $existingClass = mysqli_fetch_assoc($duplicateResult);
        $response['gelombang_nama'] = $existingClass['nama_gelombang'];
    }
    
    echo json_encode($response);
    exit;
}

// Ambil data gelombang yang aktif untuk dropdown
$gelombangQuery = "SELECT * FROM gelombang 
                   WHERE status = 'aktif' OR status = 'dibuka'
                   ORDER BY tahun DESC, gelombang_ke ASC";
$gelombangResult = mysqli_query($conn, $gelombangQuery);

// Ambil data instruktur untuk dropdown (optional)
$instrukturQuery = "SELECT id_instruktur, nama FROM instruktur ORDER BY nama ASC";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_check_duplicate'])) {
    // Sanitize input sesuai struktur database
    $nama_kelas = mysqli_real_escape_string($conn, $_POST['nama_kelas']);
    $id_gelombang = mysqli_real_escape_string($conn, $_POST['id_gelombang']);
    $kapasitas = (int)$_POST['kapasitas'];
    $id_instruktur = !empty($_POST['id_instruktur']) ? mysqli_real_escape_string($conn, $_POST['id_instruktur']) : NULL;
    
    // Validasi kapasitas harus tepat 10
    if ($kapasitas != 10) {
        $error = "Kapasitas harus tepat 10 siswa!";
    } else {
        // Validasi gelombang exists dan aktif
        $gelombangCheck = mysqli_query($conn, "SELECT nama_gelombang FROM gelombang WHERE id_gelombang = '$id_gelombang' AND (status = 'aktif' OR status = 'dibuka')");
        if (mysqli_num_rows($gelombangCheck) == 0) {
            $error = "Gelombang tidak valid atau tidak aktif!";
        } else {
            // Validasi instruktur jika dipilih
            if ($id_instruktur) {
                $instrukturCheck = mysqli_query($conn, "SELECT nama FROM instruktur WHERE id_instruktur = '$id_instruktur'");
                if (mysqli_num_rows($instrukturCheck) == 0) {
                    $error = "Instruktur tidak valid!";
                }
            }
            
            // Validasi duplikasi kelas (nama_kelas + id_gelombang harus unik)
            if (!isset($error)) {
                $duplicateQuery = "SELECT k.id_kelas, k.nama_kelas, g.nama_gelombang 
                                   FROM kelas k 
                                   JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
                                   WHERE k.nama_kelas = '$nama_kelas' 
                                   AND k.id_gelombang = '$id_gelombang'";
                
                $duplicateResult = mysqli_query($conn, $duplicateQuery);
                
                if (mysqli_num_rows($duplicateResult) > 0) {
                    $existingClass = mysqli_fetch_assoc($duplicateResult);
                    $error = "Kelas '" . htmlspecialchars($nama_kelas) . "' sudah ada pada '" . htmlspecialchars($existingClass['nama_gelombang']) . "'!";
                }
            }
            
            if (!isset($error)) {
                // Begin transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Insert ke database sesuai struktur tabel kelas
                    $query = "INSERT INTO kelas (nama_kelas, id_gelombang, kapasitas, id_instruktur) 
                              VALUES ('$nama_kelas', '$id_gelombang', '$kapasitas', " . 
                              ($id_instruktur ? "'$id_instruktur'" : "NULL") . ")";
                    
                    if (mysqli_query($conn, $query)) {
                        // Commit transaction
                        mysqli_commit($conn);
                        
                        $_SESSION['success'] = "Data kelas berhasil ditambahkan!";
                        header("Location: index.php");
                        exit;
                    } else {
                        throw new Exception("Gagal menambahkan data kelas: " . mysqli_error($conn));
                    }
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
  <title>Tambah Data Kelas</title>
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
              <h2 class="page-title mb-1">TAMBAH DATA KELAS</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Master</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Kelas</a>
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
            <i class="bi bi-building-add me-2"></i>Form Tambah Kelas
          </h5>
        </div>

        <div class="card-body">
          <form action="" method="post" id="formTambahKelas">
            <div class="row justify-content-center">
              <div class="col-lg-8">
                <h6 class="section-title mb-4">
                  <i class="bi bi-building me-2"></i>Data Kelas
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Nama Kelas</label>
                  <input type="text" name="nama_kelas" class="form-control" required 
                         value="<?= isset($_POST['nama_kelas']) ? htmlspecialchars($_POST['nama_kelas']) : '' ?>">
                  <div class="form-text"><small>Contoh: 08.00 - 09.00 A</small></div>
                  <div id="duplicate-feedback" class="invalid-feedback"></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Gelombang</label>
                  <select name="id_gelombang" class="form-select" required>
                    <option value="">Pilih Gelombang</option>
                    <?php if (mysqli_num_rows($gelombangResult) > 0): ?>
                      <?php while($gelombang = mysqli_fetch_assoc($gelombangResult)): ?>
                        <option value="<?= $gelombang['id_gelombang'] ?>" 
                                <?= (isset($_POST['id_gelombang']) && $_POST['id_gelombang'] == $gelombang['id_gelombang']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($gelombang['nama_gelombang']) ?> (<?= $gelombang['tahun'] ?>)
                        </option>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <option value="" disabled>Tidak ada gelombang aktif</option>
                    <?php endif; ?>
                  </select>
                  <div class="form-text"><small>Pilih gelombang untuk kelas ini</small></div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Kapasitas</label>
                      <input type="number" name="kapasitas" class="form-control" required 
                             value="10" readonly style="background-color: #f8f9fa;">
                      <div class="form-text"><small>Kapasitas tetap 10 siswa per kelas</small></div>
                    </div>
                  </div>
                  <div class="col-md-6">
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
                        <small>Instruktur dapat dipilih sekarang atau diatur kemudian</small>
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
  const form = document.getElementById('formTambahKelas');
  
  // Kapasitas validation - tetap 10
  const kapasitasInput = document.querySelector('input[name="kapasitas"]');
  if (kapasitasInput) {
    // Set value to 10 and make readonly
    kapasitasInput.value = 10;
    kapasitasInput.readOnly = true;
  }

  // Fungsi untuk cek duplikasi secara real-time
  function checkDuplicateClass() {
    const namaKelas = document.querySelector('input[name="nama_kelas"]').value.trim();
    const idGelombang = document.querySelector('select[name="id_gelombang"]').value;
    const namaKelasInput = document.querySelector('input[name="nama_kelas"]');
    const feedbackDiv = document.getElementById('duplicate-feedback');
    
    if (!namaKelas || !idGelombang) {
        namaKelasInput.classList.remove('is-invalid');
        namaKelasInput.removeAttribute('data-duplicate');
        feedbackDiv.textContent = '';
        return;
    }
    
    // Kirim request AJAX untuk cek duplikasi
    const formData = new FormData();
    formData.append('nama_kelas', namaKelas);
    formData.append('id_gelombang', idGelombang);
    formData.append('ajax_check_duplicate', '1');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.duplicate) {
            namaKelasInput.classList.add('is-invalid');
            namaKelasInput.setAttribute('data-duplicate', 'true');
            namaKelasInput.setAttribute('data-gelombang-nama', data.gelombang_nama);
            feedbackDiv.textContent = `Kelas "${namaKelas}" sudah ada pada "${data.gelombang_nama}"`;
        } else {
            namaKelasInput.classList.remove('is-invalid');
            namaKelasInput.removeAttribute('data-duplicate');
            namaKelasInput.removeAttribute('data-gelombang-nama');
            feedbackDiv.textContent = '';
        }
    })
    .catch(error => console.error('Error:', error));
  }

  // Event listeners untuk validasi real-time
  const namaKelasInput = document.querySelector('input[name="nama_kelas"]');
  const gelombangSelect = document.querySelector('select[name="id_gelombang"]');

  namaKelasInput.addEventListener('blur', checkDuplicateClass);
  gelombangSelect.addEventListener('change', checkDuplicateClass);

  // Form submission validation
  form.addEventListener('submit', function(e) {
    const namaKelasInput = document.querySelector('input[name="nama_kelas"]');
    
    // Cek apakah ada duplikasi berdasarkan attribute data-duplicate
    if (namaKelasInput.hasAttribute('data-duplicate')) {
        e.preventDefault();
        namaKelasInput.focus();
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

    // Validate kapasitas harus 10
    const kapasitas = parseInt(kapasitasInput.value);
    if (kapasitas !== 10) {
      kapasitasInput.classList.add('is-invalid');
      isValid = false;
      alert('Kapasitas harus tepat 10 siswa!');
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