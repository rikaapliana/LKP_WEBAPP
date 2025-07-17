<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'nilai'; 
$baseURL = '../';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID nilai tidak valid!";
    header("Location: index.php");
    exit;
}

$id_nilai = (int)$_GET['id'];

// Ambil data nilai
$nilaiQuery = "SELECT n.*, s.nama as nama_siswa, s.nik, k.nama_kelas, g.nama_gelombang
               FROM nilai n 
               LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
               LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               WHERE n.id_nilai = '$id_nilai'";
$nilaiResult = mysqli_query($conn, $nilaiQuery);

if (mysqli_num_rows($nilaiResult) == 0) {
    $_SESSION['error'] = "Data nilai tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$nilai = mysqli_fetch_assoc($nilaiResult);

// Handle AJAX request untuk check duplicate
if (isset($_POST['ajax_check_duplicate'])) {
    header('Content-Type: application/json');
    
    $id_siswa = mysqli_real_escape_string($conn, $_POST['id_siswa']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    
    // Cek duplikasi dengan join ke tabel siswa (exclude current nilai)
    $duplicateQuery = "SELECT n.id_nilai, s.nama as nama_siswa, k.nama_kelas 
                       FROM nilai n 
                       LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
                       LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
                       WHERE n.id_siswa = '$id_siswa' 
                       AND n.id_kelas = '$id_kelas'
                       AND n.id_nilai != '$id_nilai'";
    
    $duplicateResult = mysqli_query($conn, $duplicateQuery);
    
    $response = [
        'duplicate' => mysqli_num_rows($duplicateResult) > 0,
        'count' => mysqli_num_rows($duplicateResult)
    ];
    
    // Jika ada duplikasi, tambahkan info siswa
    if ($response['duplicate']) {
        $existingNilai = mysqli_fetch_assoc($duplicateResult);
        $response['siswa_nama'] = $existingNilai['nama_siswa'];
        $response['kelas_nama'] = $existingNilai['nama_kelas'];
    }
    
    echo json_encode($response);
    exit;
}

// Ambil data siswa untuk dropdown
$siswaQuery = "SELECT s.*, k.nama_kelas FROM siswa s 
               LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
               WHERE s.status_aktif = 'aktif'
               ORDER BY s.nama ASC";
$siswaResult = mysqli_query($conn, $siswaQuery);

// Ambil data kelas untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang, g.gelombang_ke, g.tahun FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               ORDER BY k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_check_duplicate'])) {
    // Sanitize input
    $id_siswa = mysqli_real_escape_string($conn, $_POST['id_siswa']);
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $nilai_word = !empty($_POST['nilai_word']) ? (int)$_POST['nilai_word'] : NULL;
    $nilai_excel = !empty($_POST['nilai_excel']) ? (int)$_POST['nilai_excel'] : NULL;
    $nilai_ppt = !empty($_POST['nilai_ppt']) ? (int)$_POST['nilai_ppt'] : NULL;
    $nilai_internet = !empty($_POST['nilai_internet']) ? (int)$_POST['nilai_internet'] : NULL;
    $nilai_pengembangan = !empty($_POST['nilai_pengembangan']) ? (int)$_POST['nilai_pengembangan'] : NULL;
    
    // Validasi rentang nilai (0-100)
    $nilaiFields = ['nilai_word', 'nilai_excel', 'nilai_ppt', 'nilai_internet', 'nilai_pengembangan'];
    foreach ($nilaiFields as $field) {
        if (!empty($_POST[$field])) {
            $nilaiValue = (int)$_POST[$field];
            if ($nilaiValue < 0 || $nilaiValue > 100) {
                $error = "Nilai harus berada dalam rentang 0-100!";
                break;
            }
        }
    }
    
    if (!isset($error)) {
        // Validasi siswa exists dan aktif
        $siswaCheck = mysqli_query($conn, "SELECT nama FROM siswa WHERE id_siswa = '$id_siswa' AND status_aktif = 'aktif'");
        if (mysqli_num_rows($siswaCheck) == 0) {
            $error = "Siswa tidak valid atau tidak aktif!";
        } else {
            // Validasi kelas exists
            $kelasCheck = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id_kelas = '$id_kelas'");
            if (mysqli_num_rows($kelasCheck) == 0) {
                $error = "Kelas tidak valid!";
            } else {
                // Validasi duplikasi nilai (id_siswa + id_kelas harus unik, exclude current nilai)
                $duplicateQuery = "SELECT n.id_nilai, s.nama as nama_siswa, k.nama_kelas 
                                   FROM nilai n 
                                   LEFT JOIN siswa s ON n.id_siswa = s.id_siswa
                                   LEFT JOIN kelas k ON n.id_kelas = k.id_kelas
                                   WHERE n.id_siswa = '$id_siswa' 
                                   AND n.id_kelas = '$id_kelas'
                                   AND n.id_nilai != '$id_nilai'";
                
                $duplicateResult = mysqli_query($conn, $duplicateQuery);
                
                if (mysqli_num_rows($duplicateResult) > 0) {
                    $existingNilai = mysqli_fetch_assoc($duplicateResult);
                    $error = "Nilai untuk siswa '" . htmlspecialchars($existingNilai['nama_siswa']) . "' pada kelas '" . htmlspecialchars($existingNilai['nama_kelas']) . "' sudah ada!";
                }
            }
        }
        
        if (!isset($error)) {
            // Hitung rata-rata dari nilai yang diisi
            $nilai_array = array_filter([$nilai_word, $nilai_excel, $nilai_ppt, $nilai_internet, $nilai_pengembangan], function($val) {
                return $val !== NULL;
            });
            
            $rata_rata = NULL;
            $status_kelulusan = NULL;
            
            if (!empty($nilai_array)) {
                $rata_rata = array_sum($nilai_array) / count($nilai_array);
                $status_kelulusan = $rata_rata >= 60 ? 'lulus' : 'tidak lulus';
            }
            
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update data nilai
                $query = "UPDATE nilai SET 
                          id_siswa = '$id_siswa',
                          id_kelas = '$id_kelas',
                          nilai_word = " . ($nilai_word ? "'$nilai_word'" : "NULL") . ",
                          nilai_excel = " . ($nilai_excel ? "'$nilai_excel'" : "NULL") . ",
                          nilai_ppt = " . ($nilai_ppt ? "'$nilai_ppt'" : "NULL") . ",
                          nilai_internet = " . ($nilai_internet ? "'$nilai_internet'" : "NULL") . ",
                          nilai_pengembangan = " . ($nilai_pengembangan ? "'$nilai_pengembangan'" : "NULL") . ",
                          rata_rata = " . ($rata_rata ? "'$rata_rata'" : "NULL") . ",
                          status_kelulusan = " . ($status_kelulusan ? "'$status_kelulusan'" : "NULL") . "
                          WHERE id_nilai = '$id_nilai'";
                
                if (!mysqli_query($conn, $query)) {
                    throw new Exception("Gagal memperbarui data nilai: " . mysqli_error($conn));
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                $_SESSION['success'] = "Data nilai berhasil diperbarui!";
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
  <title>Edit Data Nilai</title>
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
              <h2 class="page-title mb-1">EDIT DATA NILAI</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Akademik</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Nilai</a>
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
            <i class="bi bi-journal-text me-2"></i>Form Edit Nilai
          </h5>
          <small class="text-muted">NIK: <?= $nilai['nik'] ?></small>
        </div>

        <div class="card-body">
          <form action="" method="post" id="formEditNilai">
            <div class="row">
              
              <!-- Bagian 1: Data Siswa & Kelas -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-person-check me-2"></i>Data Siswa & Kelas
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Siswa</label>
                  <select name="id_siswa" class="form-select" required>
                    <option value="">Pilih Siswa</option>
                    <?php 
                    mysqli_data_seek($siswaResult, 0);
                    while($siswa = mysqli_fetch_assoc($siswaResult)): 
                    ?>
                      <option value="<?= $siswa['id_siswa'] ?>" 
                              data-kelas="<?= $siswa['id_kelas'] ?? '' ?>"
                              <?= ($nilai['id_siswa'] == $siswa['id_siswa']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($siswa['nama']) ?> - NIK: <?= htmlspecialchars($siswa['nik']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div class="form-text"><small>Pilih siswa yang akan dinilai</small></div>
                  <div id="duplicate-feedback" class="invalid-feedback"></div>
                </div>

                <div class="mb-4">
                  <label class="form-label required">Kelas</label>
                  <select name="id_kelas" class="form-select" required>
                    <option value="">Pilih Kelas</option>
                    <?php 
                    mysqli_data_seek($kelasResult, 0);
                    while($kelas = mysqli_fetch_assoc($kelasResult)): 
                    ?>
                      <option value="<?= $kelas['id_kelas'] ?>" 
                              <?= ($nilai['id_kelas'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                        <?php if($kelas['nama_gelombang']): ?>
                          - <?= htmlspecialchars($kelas['nama_gelombang']) ?>
                        <?php endif; ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div class="form-text"><small>Pilih kelas untuk penilaian</small></div>
                </div>

                <!-- Current Data Preview -->
                <div class="mt-4 p-3 bg-light rounded">
                  <h6 class="fw-bold mb-3">
                    <i class="bi bi-info-circle me-2"></i>Data Saat Ini
                  </h6>
                  <div class="row">
                    <div class="col-md-6">
                      <strong>Siswa:</strong><br>
                      <span class="text-primary"><?= htmlspecialchars($nilai['nama_siswa']) ?></span><br>
                      <small class="text-muted">NIK: <?= htmlspecialchars($nilai['nik']) ?></small>
                    </div>
                    <div class="col-md-6">
                      <strong>Kelas:</strong><br>
                      <span class="text-primary"><?= htmlspecialchars($nilai['nama_kelas']) ?></span>
                      <?php if($nilai['nama_gelombang']): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($nilai['nama_gelombang']) ?></small>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if($nilai['rata_rata']): ?>
                  <div class="row mt-2">
                    <div class="col-md-6">
                      <strong>Rata-rata Saat Ini:</strong><br>
                      <span class="badge bg-<?= $nilai['rata_rata'] >= 80 ? 'success' : ($nilai['rata_rata'] >= 70 ? 'primary' : ($nilai['rata_rata'] >= 60 ? 'warning' : 'danger')) ?>">
                        <?= number_format($nilai['rata_rata'], 1) ?>
                      </span>
                    </div>
                    <div class="col-md-6">
                      <strong>Status:</strong><br>
                      <span class="badge bg-<?= $nilai['status_kelulusan'] == 'lulus' ? 'success' : 'danger' ?>">
                        <?= ucfirst($nilai['status_kelulusan']) ?>
                      </span>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Bagian 2: Input Nilai -->
              <div class="col-lg-6">
                <h6 class="section-title mb-4">
                  <i class="bi bi-clipboard-data me-2"></i>Edit Nilai
                </h6>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label">
                        <i class="bi bi-file-word text-primary me-1"></i>Nilai Word
                      </label>
                      <input type="number" name="nilai_word" class="form-control text-center" min="0" max="100" 
                             value="<?= $nilai['nilai_word'] ?? '' ?>"
                             placeholder="0-100">
                      <div class="form-text"><small>Nilai Microsoft Word (0-100)</small></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label">
                        <i class="bi bi-file-excel text-success me-1"></i>Nilai Excel
                      </label>
                      <input type="number" name="nilai_excel" class="form-control text-center" min="0" max="100" 
                             value="<?= $nilai['nilai_excel'] ?? '' ?>"
                             placeholder="0-100">
                      <div class="form-text"><small>Nilai Microsoft Excel (0-100)</small></div>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label">
                        <i class="bi bi-file-ppt text-warning me-1"></i>Nilai PowerPoint
                      </label>
                      <input type="number" name="nilai_ppt" class="form-control text-center" min="0" max="100" 
                             value="<?= $nilai['nilai_ppt'] ?? '' ?>"
                             placeholder="0-100">
                      <div class="form-text"><small>Nilai PowerPoint (0-100)</small></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label">
                        <i class="bi bi-globe text-info me-1"></i>Nilai Internet
                      </label>
                      <input type="number" name="nilai_internet" class="form-control text-center" min="0" max="100" 
                             value="<?= $nilai['nilai_internet'] ?? '' ?>"
                             placeholder="0-100">
                      <div class="form-text"><small>Nilai Internet (0-100)</small></div>
                    </div>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label">
                    <i class="bi bi-gear text-secondary me-1"></i>Nilai Softskill
                  </label>
                  <input type="number" name="nilai_pengembangan" class="form-control text-center" min="0" max="100" 
                         value="<?= $nilai['nilai_pengembangan'] ?? '' ?>"
                         placeholder="0-100">
                  <div class="form-text"><small>Nilai Softskill (0-100)</small></div>
                </div>
              </div>
            </div>

            <!-- Bagian 3: Ringkasan Otomatis -->
            <div class="row mt-4">
              <div class="col-12">
                <div class="card border" style="background-color: #f8f9fa;">
                  <div class="card-body">
                    <h6 class="fw-bold mb-3">
                      <i class="bi bi-calculator me-2"></i>Ringkasan Otomatis
                    </h6>
                    <div class="row text-center">
                      <div class="col-md-3">
                        <div class="p-3">
                          <h4 class="mb-1 fw-bold" id="rataRata">-</h4>
                          <small class="text-muted">Rata-rata</small>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="p-3">
                          <h5 class="mb-1 fw-bold" id="kategoriNilai">-</h5>
                          <small class="text-muted">Kategori</small>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="p-3">
                          <h5 class="mb-1 fw-bold" id="statusKelulusan">-</h5>
                          <small class="text-muted">Status Kelulusan</small>
                        </div>
                      </div>
                      <div class="col-md-3">
                        <div class="p-3">
                          <h5 class="mb-1 fw-bold" id="jumlahMapel">-</h5>
                          <small class="text-muted">Mata Pelajaran</small>
                        </div>
                      </div>
                    </div>
                    <div class="text-center mt-3">
                      <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Status kelulusan ditentukan dari rata-rata keseluruhan (minimal 60 untuk lulus)
                      </small>
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
  const form = document.getElementById('formEditNilai');
  const nilaiInputs = form.querySelectorAll('input[type="number"]');
  const siswaSelect = form.querySelector('select[name="id_siswa"]');
  const kelasSelect = form.querySelector('select[name="id_kelas"]');

  siswaSelect.addEventListener('change', function() {
    checkDuplicateNilai();
  });

  kelasSelect.addEventListener('change', function() {
    checkDuplicateNilai();
  });

  // Real-time calculation
  nilaiInputs.forEach(input => {
    input.addEventListener('input', function() {
      // Validasi rentang nilai
      let value = parseInt(this.value);
      if (value > 100) {
        this.value = 100;
      } else if (value < 0) {
        this.value = 0;
      }
      calculateAverage();
    });
  });

  function calculateAverage() {
    const values = [];
    nilaiInputs.forEach(input => {
      if (input.value && input.value !== '') {
        values.push(parseInt(input.value));
      }
    });

    const rataRataEl = document.getElementById('rataRata');
    const kategoriEl = document.getElementById('kategoriNilai');
    const statusEl = document.getElementById('statusKelulusan');
    const jumlahMapelEl = document.getElementById('jumlahMapel');

    if (values.length > 0) {
      const average = values.reduce((a, b) => a + b, 0) / values.length;
      
      // Update rata-rata
      rataRataEl.textContent = average.toFixed(1);
      
      // Update kategori berdasarkan rata-rata keseluruhan (sesuai dengan logika di detail.php)
      let kategori = '';
      let kategoriClass = '';
      if (average >= 85) {
        kategori = 'Sangat Baik';
        kategoriClass = 'text-success';
      } else if (average >= 75) {
        kategori = 'Baik';
        kategoriClass = 'text-primary';
      } else if (average >= 65) {
        kategori = 'Cukup';
        kategoriClass = 'text-info';
      } else if (average >= 60) {
        kategori = 'Kurang Baik';
        kategoriClass = 'text-warning';
      } else {
        kategori = 'Perlu Perbaikan';
        kategoriClass = 'text-danger';
      }
      
      kategoriEl.textContent = kategori;
      kategoriEl.className = 'mb-1 fw-bold ' + kategoriClass;
      
      // Update status kelulusan berdasarkan rata-rata >= 60
      const status = average >= 60 ? 'LULUS' : 'TIDAK LULUS';
      const statusClass = average >= 60 ? 'text-success' : 'text-danger';
      
      statusEl.textContent = status;
      statusEl.className = 'mb-1 fw-bold ' + statusClass;
      
      // Update jumlah mata pelajaran yang sudah dinilai
      jumlahMapelEl.textContent = values.length + '/5';
      jumlahMapelEl.className = 'mb-1 fw-bold text-primary';
    } else {
      rataRataEl.textContent = '-';
      kategoriEl.textContent = '-';
      kategoriEl.className = 'mb-1 fw-bold';
      statusEl.textContent = '-';
      statusEl.className = 'mb-1 fw-bold';
      jumlahMapelEl.textContent = '0/5';
      jumlahMapelEl.className = 'mb-1 fw-bold text-muted';
    }
  }

  // Fungsi untuk cek duplikasi secara real-time
  function checkDuplicateNilai() {
    const idSiswa = siswaSelect.value;
    const idKelas = kelasSelect.value;
    const siswaInput = siswaSelect;
    const feedbackDiv = document.getElementById('duplicate-feedback');
    
    if (!idSiswa || !idKelas) {
        siswaInput.classList.remove('is-invalid');
        siswaInput.removeAttribute('data-duplicate');
        feedbackDiv.textContent = '';
        return;
    }
    
    // Kirim request AJAX untuk cek duplikasi
    const formData = new FormData();
    formData.append('id_siswa', idSiswa);
    formData.append('id_kelas', idKelas);
    formData.append('ajax_check_duplicate', '1');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.duplicate) {
            siswaInput.classList.add('is-invalid');
            siswaInput.setAttribute('data-duplicate', 'true');
            feedbackDiv.textContent = `Nilai untuk siswa "${data.siswa_nama}" pada kelas "${data.kelas_nama}" sudah ada`;
        } else {
            siswaInput.classList.remove('is-invalid');
            siswaInput.removeAttribute('data-duplicate');
            feedbackDiv.textContent = '';
        }
    })
    .catch(error => console.error('Error:', error));
  }

  // Form submission validation
  form.addEventListener('submit', function(e) {
    const siswaInput = document.querySelector('select[name="id_siswa"]');
    
    // Cek apakah ada duplikasi berdasarkan attribute data-duplicate
    if (siswaInput.hasAttribute('data-duplicate')) {
        e.preventDefault();
        alert('Data nilai untuk siswa dan kelas ini sudah ada! Silakan pilih siswa atau kelas yang berbeda.');
        siswaInput.focus();
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

    // Validasi minimal satu nilai harus diisi
    const nilaiValues = Array.from(nilaiInputs).filter(input => input.value && input.value !== '');
    if (nilaiValues.length === 0) {
      isValid = false;
      alert('Minimal satu nilai harus diisi!');
      nilaiInputs[0].focus();
      return false;
    }

    // Validasi rentang nilai
    let nilaiInvalidCount = 0;
    nilaiInputs.forEach(input => {
      if (input.value && input.value !== '') {
        const nilai = parseInt(input.value);
        if (nilai < 0 || nilai > 100) {
          input.classList.add('is-invalid');
          nilaiInvalidCount++;
          isValid = false;
        } else {
          input.classList.remove('is-invalid');
        }
      }
    });

    if (nilaiInvalidCount > 0) {
      alert(`Terdapat ${nilaiInvalidCount} nilai yang tidak valid! Nilai harus berada dalam rentang 0-100.`);
      return false;
    }

    if (!isValid) {
      e.preventDefault();
      alert('Harap lengkapi semua field yang wajib diisi dengan benar!');
      return false;
    }


    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
  });

  // Initialize calculation
  calculateAverage();

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Add real-time validation feedback
  nilaiInputs.forEach(input => {
    input.addEventListener('blur', function() {
      if (this.value && this.value !== '') {
        const nilai = parseInt(this.value);
        if (nilai < 0 || nilai > 100) {
          this.classList.add('is-invalid');
          // Create or update feedback
          let feedback = this.parentNode.querySelector('.invalid-feedback');
          if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            this.parentNode.appendChild(feedback);
          }
          feedback.textContent = 'Nilai harus antara 0-100';
        } else {
          this.classList.remove('is-invalid');
        }
      } else {
        this.classList.remove('is-invalid');
      }
    });
  });

  // Auto-focus next input when current is filled
  nilaiInputs.forEach((input, index) => {
    input.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const nextInput = nilaiInputs[index + 1];
        if (nextInput) {
          nextInput.focus();
        } else {
          // If this is the last input, focus on submit button
          form.querySelector('button[type="submit"]').focus();
        }
      }
    });
  });
});
</script>
</body>
</html>