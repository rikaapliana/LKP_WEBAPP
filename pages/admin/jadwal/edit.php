<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Cek parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID jadwal tidak valid!";
    header("Location: index.php");
    exit;
}

$id_jadwal = (int)$_GET['id'];

// Ambil data jadwal
$jadwalQuery = "SELECT j.*, k.nama_kelas, k.id_gelombang, g.nama_gelombang, g.tahun, i.nama as nama_instruktur
                FROM jadwal j 
                LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
                LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
                WHERE j.id_jadwal = '$id_jadwal'";
$jadwalResult = mysqli_query($conn, $jadwalQuery);

if (mysqli_num_rows($jadwalResult) == 0) {
    $_SESSION['error'] = "Data jadwal tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$jadwal = mysqli_fetch_assoc($jadwalResult);

// Ambil data kelas yang aktif untuk dropdown
$kelasQuery = "SELECT k.*, g.nama_gelombang, g.tahun 
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE g.status = 'aktif' OR g.status = 'dibuka'
               ORDER BY g.tahun DESC, k.nama_kelas ASC";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Ambil data instruktur untuk dropdown
$instrukturQuery = "SELECT * FROM instruktur ORDER BY nama ASC";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $id_kelas = mysqli_real_escape_string($conn, $_POST['id_kelas']);
    $id_instruktur = $_POST['id_instruktur'] ? mysqli_real_escape_string($conn, $_POST['id_instruktur']) : NULL;
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
    
    // Validasi waktu
    if (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
        $error = "Waktu selesai harus lebih besar dari waktu mulai!";
    }
    
    // Cek konflik jadwal (kecuali dengan jadwal ini sendiri)
    if (!isset($error)) {
        $conflictQuery = "SELECT j.*, k.nama_kelas 
                         FROM jadwal j 
                         LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
                         WHERE j.id_jadwal != '$id_jadwal'
                         AND j.tanggal = '$tanggal' 
                         AND ((j.waktu_mulai <= '$waktu_mulai' AND j.waktu_selesai > '$waktu_mulai') 
                              OR (j.waktu_mulai < '$waktu_selesai' AND j.waktu_selesai >= '$waktu_selesai')
                              OR (j.waktu_mulai >= '$waktu_mulai' AND j.waktu_selesai <= '$waktu_selesai'))";
        
        if ($id_instruktur) {
            $conflictQuery .= " AND j.id_instruktur = '$id_instruktur'";
        }
        
        $conflictResult = mysqli_query($conn, $conflictQuery);
        if (mysqli_num_rows($conflictResult) > 0) {
            $conflict = mysqli_fetch_assoc($conflictResult);
            $error = "Jadwal bentrok dengan kelas " . $conflict['nama_kelas'] . " pada " . date('H:i', strtotime($conflict['waktu_mulai'])) . " - " . date('H:i', strtotime($conflict['waktu_selesai']));
        }
    }
    
    if (!isset($error)) {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update data jadwal
            $instrukturValue = $id_instruktur ? "'$id_instruktur'" : "NULL";
            
            $query = "UPDATE jadwal SET 
                      id_kelas = '$id_kelas',
                      id_instruktur = $instrukturValue,
                      tanggal = '$tanggal',
                      waktu_mulai = '$waktu_mulai',
                      waktu_selesai = '$waktu_selesai'
                      WHERE id_jadwal = '$id_jadwal'";
            
            if (!mysqli_query($conn, $query)) {
                throw new Exception("Gagal memperbarui data jadwal: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $_SESSION['success'] = "Data jadwal berhasil diperbarui!";
            header("Location: detail.php?id=" . $id_jadwal);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Data Jadwal</title>
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
              <h2 class="page-title mb-1">EDIT DATA JADWAL</h2>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb page-breadcrumb mb-0">
                  <li class="breadcrumb-item">
                    <a href="../dashboard.php">Dashboard</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="#">Data Akademik</a>
                  </li>
                  <li class="breadcrumb-item">
                    <a href="index.php">Data Jadwal</a>
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
            <i class="bi bi-calendar-week me-2"></i>Form Edit Jadwal
          </h5>
          <small class="text-muted">Kelas: <?= htmlspecialchars($jadwal['nama_kelas']) ?></small>
        </div>

        <div class="card-body">
          <form action="" method="post" id="formEditJadwal">
            <div class="row justify-content-center">
              <div class="col-lg-8">
                <h6 class="section-title mb-4">
                  <i class="bi bi-calendar-event me-2"></i>Data Jadwal
                </h6>
                
                <div class="mb-4">
                  <label class="form-label required">Kelas</label>
                  <select name="id_kelas" class="form-select" required>
                    <option value="">Pilih Kelas</option>
                    <?php mysqli_data_seek($kelasResult, 0); ?>
                    <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                      <option value="<?= $kelas['id_kelas'] ?>" 
                              <?= ($jadwal['id_kelas'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                        <?php if($kelas['nama_gelombang']): ?>
                          - <?= htmlspecialchars($kelas['nama_gelombang']) ?> (<?= $kelas['tahun'] ?>)
                        <?php endif; ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div class="mb-4">
                  <label class="form-label">Instruktur Pengajar</label>
                  <select name="id_instruktur" class="form-select">
                    <option value="">Belum ditentukan</option>
                    <?php mysqli_data_seek($instrukturResult, 0); ?>
                    <?php while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                      <option value="<?= $instruktur['id_instruktur'] ?>" 
                              <?= ($jadwal['id_instruktur'] == $instruktur['id_instruktur']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($instruktur['nama']) ?>
                        <?php if($instruktur['nik']): ?>
                          - <?= htmlspecialchars($instruktur['nik']) ?>
                        <?php endif; ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                  <div class="form-text"><small>Instruktur yang akan mengajar pada jadwal ini</small></div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-4">
                      <label class="form-label required">Tanggal Pelaksanaan</label>
                      <input type="date" name="tanggal" class="form-control" required 
                             value="<?= htmlspecialchars($jadwal['tanggal']) ?>">
                      <div class="form-text"><small>Tanggal pelaksanaan pelatihan</small></div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="mb-4">
                      <label class="form-label required">Waktu Mulai</label>
                      <input type="time" name="waktu_mulai" class="form-control" required 
                             value="<?= htmlspecialchars($jadwal['waktu_mulai']) ?>">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="mb-4">
                      <label class="form-label required">Waktu Selesai</label>
                      <input type="time" name="waktu_selesai" class="form-control" required 
                             value="<?= htmlspecialchars($jadwal['waktu_selesai']) ?>">
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
  const form = document.getElementById('formEditJadwal');
  
  // Form submission validation
  form.addEventListener('submit', function(e) {
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

    // Additional time validation
    const waktuMulai = document.querySelector('input[name="waktu_mulai"]').value;
    const waktuSelesai = document.querySelector('input[name="waktu_selesai"]').value;
    
    if (waktuMulai && waktuSelesai && waktuSelesai <= waktuMulai) {
      alert('Waktu selesai harus lebih besar dari waktu mulai!');
      isValid = false;
    }

    if (!isValid) {
      e.preventDefault();
      alert('Mohon lengkapi semua field yang wajib diisi!');
      return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
  });

  // Real-time validation for time fields
  const waktuMulaiInput = document.querySelector('input[name="waktu_mulai"]');
  const waktuSelesaiInput = document.querySelector('input[name="waktu_selesai"]');
  
  function validateTime() {
    if (waktuMulaiInput.value && waktuSelesaiInput.value) {
      if (waktuSelesaiInput.value <= waktuMulaiInput.value) {
        waktuSelesaiInput.setCustomValidity('Waktu selesai harus lebih besar dari waktu mulai');
      } else {
        waktuSelesaiInput.setCustomValidity('');
      }
    }
  }
  
  waktuMulaiInput.addEventListener('change', validateTime);
  waktuSelesaiInput.addEventListener('change', validateTime);

  // Initialize tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>
</body>
</html>