<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kelas_id = mysqli_real_escape_string($conn, $_POST['kelas_id']);
    $instruktur_id = mysqli_real_escape_string($conn, $_POST['instruktur_id']);
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal']);
    $waktu_mulai = mysqli_real_escape_string($conn, $_POST['waktu_mulai']);
    $waktu_selesai = mysqli_real_escape_string($conn, $_POST['waktu_selesai']);
    
    // Validasi input
    $errors = [];
    
    if (empty($kelas_id)) $errors[] = "Kelas harus dipilih";
    if (empty($instruktur_id)) $errors[] = "Instruktur harus dipilih";
    if (empty($tanggal)) $errors[] = "Tanggal harus diisi";
    if (empty($waktu_mulai)) $errors[] = "Waktu mulai harus dipilih";
    if (empty($waktu_selesai)) $errors[] = "Waktu selesai harus dipilih";
    
    // Validasi tanggal tidak boleh masa lalu
    if (!empty($tanggal) && strtotime($tanggal) < strtotime(date('Y-m-d'))) {
        $errors[] = "Tanggal tidak boleh masa lalu";
    }
    
    // Validasi tidak boleh weekend (Sabtu=6, Minggu=0)
    if (!empty($tanggal)) {
        $dayOfWeek = date('w', strtotime($tanggal)); // 0=Sunday, 6=Saturday
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            $namaHari = ($dayOfWeek == 0) ? 'Minggu' : 'Sabtu';
            $errors[] = "Jadwal tidak dapat dibuat pada hari $namaHari. Silahkan pilih hari Senin-Jumat";
        }
    }
    
    // Validasi waktu selesai harus lebih besar dari waktu mulai
    if (!empty($waktu_mulai) && !empty($waktu_selesai)) {
        if (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
            $errors[] = "Waktu selesai harus lebih besar dari waktu mulai";
        }
    }
    
    // Cek duplikasi jadwal (kelas, tanggal, waktu yang sama)
    if (empty($errors)) {
        $checkQuery = "SELECT id_jadwal FROM jadwal 
                      WHERE id_kelas = '$kelas_id' 
                      AND tanggal = '$tanggal' 
                      AND (
                          (waktu_mulai <= '$waktu_mulai' AND waktu_selesai > '$waktu_mulai') OR
                          (waktu_mulai < '$waktu_selesai' AND waktu_selesai >= '$waktu_selesai') OR
                          (waktu_mulai >= '$waktu_mulai' AND waktu_selesai <= '$waktu_selesai')
                      )";
        $checkResult = mysqli_query($conn, $checkQuery);
        if (mysqli_num_rows($checkResult) > 0) {
            $errors[] = "Jadwal untuk kelas ini pada tanggal dan waktu tersebut sudah ada";
        }
    }
    
    // Cek konflik instruktur (instruktur yang sama pada waktu yang sama)
    if (empty($errors)) {
        $checkInstrukturQuery = "SELECT j.id_jadwal, k.nama_kelas FROM jadwal j
                                LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
                                WHERE j.id_instruktur = '$instruktur_id' 
                                AND j.tanggal = '$tanggal' 
                                AND (
                                    (j.waktu_mulai <= '$waktu_mulai' AND j.waktu_selesai > '$waktu_mulai') OR
                                    (j.waktu_mulai < '$waktu_selesai' AND j.waktu_selesai >= '$waktu_selesai') OR
                                    (j.waktu_mulai >= '$waktu_mulai' AND j.waktu_selesai <= '$waktu_selesai')
                                )";
        $checkInstrukturResult = mysqli_query($conn, $checkInstrukturQuery);
        if (mysqli_num_rows($checkInstrukturResult) > 0) {
            $konflik = mysqli_fetch_assoc($checkInstrukturResult);
            $errors[] = "Instruktur sudah memiliki jadwal mengajar di kelas " . $konflik['nama_kelas'] . " pada waktu tersebut";
        }
    }
    
    if (empty($errors)) {
        // Pastikan format time
        if (strlen($waktu_mulai) == 5) $waktu_mulai .= ':00';
        if (strlen($waktu_selesai) == 5) $waktu_selesai .= ':00';
        
        $query = "INSERT INTO jadwal (id_kelas, id_instruktur, tanggal, waktu_mulai, waktu_selesai) 
                 VALUES ('$kelas_id', '$instruktur_id', '$tanggal', '$waktu_mulai', '$waktu_selesai')";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Jadwal berhasil ditambahkan!";
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Gagal menyimpan data: " . mysqli_error($conn);
        }
    }
    
    // Store errors in session to display
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Get data for form
$kelasQuery = "SELECT k.*, g.nama_gelombang FROM kelas k LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

$instrukturQuery = "SELECT * FROM instruktur ORDER BY nama";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

// Get current date for default
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Jadwal Manual</title>
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
                <h2 class="page-title mb-1">TAMBAH JADWAL</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Akademik</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="index.php">Data Jadwal</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah Manual</li>
                  </ol>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <div class="container-fluid mt-4">
        <!-- Alert Success -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

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
          <!-- Form Tambah -->
          <div class="col-lg-8">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-calendar-plus me-2"></i>Tambah Jadwal Manual
                </h5>
              </div>

              <div class="card-body">
                <form method="POST" id="formTambahJadwal">
                  <div class="row g-3">
                    <!-- Pilih Kelas -->
                    <div class="col-md-6">
                      <label class="form-label required">Kelas</label>
                      <select name="kelas_id" class="form-select" required>
                        <option value="">Pilih Kelas</option>
                        <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                          <option value="<?= $kelas['id_kelas'] ?>" 
                                  <?= (isset($_POST['kelas_id']) && $_POST['kelas_id'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kelas['nama_kelas']) ?>
                            <?php if($kelas['nama_gelombang']): ?>
                              (<?= htmlspecialchars($kelas['nama_gelombang']) ?>)
                            <?php endif; ?>
                          </option>
                        <?php endwhile; ?>
                      </select>
                      <div class="form-text"><small>Pilih kelas yang akan dijadwalkan</small></div>
                    </div>

                    <!-- Pilih Instruktur -->
                    <div class="col-md-6">
                      <label class="form-label required">Instruktur</label>
                      <select name="instruktur_id" class="form-select" required>
                        <option value="">Pilih Instruktur</option>
                        <?php while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                          <option value="<?= $instruktur['id_instruktur'] ?>"
                                  <?= (isset($_POST['instruktur_id']) && $_POST['instruktur_id'] == $instruktur['id_instruktur']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($instruktur['nama']) ?>
                          </option>
                        <?php endwhile; ?>
                      </select>
                      <div class="form-text"><small>Pilih instruktur yang akan mengajar</small></div>
                    </div>

                    <!-- Tanggal -->
                    <div class="col-md-6">
                      <label class="form-label required">Tanggal</label>
                      <input type="date" name="tanggal" class="form-control" 
                             value="<?= $_POST['tanggal'] ?? $tomorrow ?>" 
                             min="<?= $today ?>" required>
                      <div class="form-text"><small>Tanggal pelaksanaan jadwal (Senin-Jumat)</small></div>
                    </div>

                    <!-- Hari (Auto-filled) -->
                    <div class="col-md-6">
                      <label class="form-label">Hari</label>
                      <input type="text" class="form-control" id="hariOtomatis" readonly>
                      <div class="form-text"><small>Hari akan terisi otomatis</small></div>
                    </div>

                    <!-- Waktu Mulai -->
                    <div class="col-md-6">
                      <label class="form-label required">Waktu Mulai</label>
                      <select name="waktu_mulai" class="form-select" required>
                        <option value="">Pilih Waktu</option>
                        <option value="08:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '08:00:00') ? 'selected' : '' ?>>08:00</option>
                        <option value="09:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '09:00:00') ? 'selected' : '' ?>>09:00</option>
                        <option value="10:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '10:00:00') ? 'selected' : '' ?>>10:00</option>
                        <option value="11:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '11:00:00') ? 'selected' : '' ?>>11:00</option>
                        <option value="13:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '13:00:00') ? 'selected' : '' ?>>13:00</option>
                        <option value="14:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '14:00:00') ? 'selected' : '' ?>>14:00</option>
                        <option value="15:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '15:00:00') ? 'selected' : '' ?>>15:00</option>
                        <option value="16:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '16:00:00') ? 'selected' : '' ?>>16:00</option>
                        <option value="17:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '17:00:00') ? 'selected' : '' ?>>17:00</option>
                        <option value="19:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '19:00:00') ? 'selected' : '' ?>>19:00</option>
                        <option value="20:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '20:00:00') ? 'selected' : '' ?>>20:00</option>
                        <option value="21:00:00" <?= (isset($_POST['waktu_mulai']) && $_POST['waktu_mulai'] == '21:00:00') ? 'selected' : '' ?>>21:00</option>
                      </select>
                      <div class="form-text"><small>Waktu dimulainya pembelajaran</small></div>
                    </div>

                    <!-- Waktu Selesai -->
                    <div class="col-md-6">
                      <label class="form-label required">Waktu Selesai</label>
                      <select name="waktu_selesai" class="form-select" required>
                        <option value="">Pilih Waktu</option>
                        <option value="09:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '09:00:00') ? 'selected' : '' ?>>09:00</option>
                        <option value="10:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '10:00:00') ? 'selected' : '' ?>>10:00</option>
                        <option value="11:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '11:00:00') ? 'selected' : '' ?>>11:00</option>
                        <option value="12:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '12:00:00') ? 'selected' : '' ?>>12:00</option>
                        <option value="14:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '14:00:00') ? 'selected' : '' ?>>14:00</option>
                        <option value="15:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '15:00:00') ? 'selected' : '' ?>>15:00</option>
                        <option value="16:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '16:00:00') ? 'selected' : '' ?>>16:00</option>
                        <option value="17:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '17:00:00') ? 'selected' : '' ?>>17:00</option>
                        <option value="18:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '18:00:00') ? 'selected' : '' ?>>18:00</option>
                        <option value="20:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '20:00:00') ? 'selected' : '' ?>>20:00</option>
                        <option value="21:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '21:00:00') ? 'selected' : '' ?>>21:00</option>
                        <option value="22:00:00" <?= (isset($_POST['waktu_selesai']) && $_POST['waktu_selesai'] == '22:00:00') ? 'selected' : '' ?>>22:00</option>
                      </select>
                      <div class="form-text"><small>Waktu berakhirnya pembelajaran</small></div>
                    </div>

                    <!-- Preview Info -->
                    <div class="col-12">
                      <div class="alert alert-light border" id="previewInfo" style="display: none;">
                        <h6 class="alert-heading">
                          <i class="bi bi-info-circle me-2"></i>Preview Jadwal
                        </h6>
                        <div id="previewContent"></div>
                      </div>
                    </div>

                    <!-- Button -->                 
                   <div class="d-flex justify-content-end gap-3 pt-4 mt-4 border-top">
                      <a href="index.php" class="btn btn-kembali px-3">
                        Kembali
                        </a>
                        <button type="submit" class="btn btn-simpan px-4">
                          <i class="bi bi-check-lg me-1"></i>Simpan
                        </button>
                    </div>

                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Info Panel -->
          <div class="col-lg-4">
            <div class="card content-card">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Panduan Tambah Jadwal
                </h6>
              </div>
              <div class="card-body">
                <div class="alert alert-info">
                  <h6 class="alert-heading">
                    <i class="bi bi-lightbulb me-2"></i>Tips Tambah Jadwal
                  </h6>
                  <ul class="mb-0 small">
                    <li>Pastikan kelas dan instruktur sudah tersedia</li>
                    <li>Tanggal tidak boleh masa lalu</li>
                    <li><strong>Jadwal hanya dapat dibuat pada hari Senin-Jumat</strong></li>
                    <li>Waktu selesai harus lebih besar dari waktu mulai</li>
                    <li>Sistem akan cek konflik jadwal otomatis</li>
                    <li>Satu instruktur tidak bisa mengajar di 2 tempat bersamaan</li>
                  </ul>
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
  document.addEventListener('DOMContentLoaded', function() {
    const tanggalInput = document.querySelector('input[name="tanggal"]');
    const hariOtomatis = document.getElementById('hariOtomatis');
    const waktuMulai = document.querySelector('select[name="waktu_mulai"]');
    const waktuSelesai = document.querySelector('select[name="waktu_selesai"]');
    const kelasSelect = document.querySelector('select[name="kelas_id"]');
    const instrukturSelect = document.querySelector('select[name="instruktur_id"]');
    const previewInfo = document.getElementById('previewInfo');
    const previewContent = document.getElementById('previewContent');
    const btnSubmit = document.getElementById('btnSubmit');
    const form = document.getElementById('formTambahJadwal');
    
    // Nama hari dalam bahasa Indonesia
    const namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    
    // Update hari otomatis berdasarkan tanggal
    function updateHari() {
      if (tanggalInput.value) {
        const tanggal = new Date(tanggalInput.value + 'T00:00:00');
        const dayIndex = tanggal.getDay(); // 0=Sunday, 6=Saturday
        const hari = namaHari[dayIndex];
        hariOtomatis.value = hari;
        
        // Cek apakah weekend
        const isWeekend = (dayIndex === 0 || dayIndex === 6); // 0=Sunday, 6=Saturday
        
        if (isWeekend) {
          // Disable submit untuk weekend
          hariOtomatis.classList.add('border-danger', 'text-danger');
          hariOtomatis.classList.remove('border-success', 'border-warning');
          btnSubmit.disabled = true;
          btnSubmit.innerHTML = '<i class="bi bi-x-circle me-2"></i>Tidak Dapat Menyimpan (Weekend)';
        } else {
          // Hari kerja - normal
          hariOtomatis.classList.add('border-success');
          hariOtomatis.classList.remove('border-danger', 'border-warning', 'text-danger');
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = '<i class="bi bi-save me-2"></i>Simpan Jadwal';
        }
      } else {
        hariOtomatis.value = '';
        hariOtomatis.classList.remove('border-warning', 'border-success', 'border-danger', 'text-danger');
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="bi bi-save me-2"></i>Simpan Jadwal';
      }
      updatePreview();
    }
    
    // Auto-set waktu selesai berdasarkan waktu mulai
    function updateWaktuSelesai() {
      const mulai = waktuMulai.value;
      if (mulai) {
        const [jam, menit, detik] = mulai.split(':');
        const jamSelesai = (parseInt(jam) + 1).toString().padStart(2, '0');
        const waktuSelesaiValue = `${jamSelesai}:${menit}:${detik}`;
        
        // Set value waktu selesai jika belum ada
        if (!waktuSelesai.value) {
          waktuSelesai.value = waktuSelesaiValue;
        }
      }
      updatePreview();
    }
    
    // Update preview info
    function updatePreview() {
      const kelas = kelasSelect.options[kelasSelect.selectedIndex]?.text || '';
      const instruktur = instrukturSelect.options[instrukturSelect.selectedIndex]?.text || '';
      const tanggal = tanggalInput.value;
      const hari = hariOtomatis.value;
      const mulai = waktuMulai.value;
      const selesai = waktuSelesai.value;
      
      if (kelas && instruktur && tanggal && mulai && selesai && !btnSubmit.disabled) {
        const tanggalFormat = new Date(tanggal + 'T00:00:00').toLocaleDateString('id-ID', {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
        
        const waktuMulaiFormat = mulai.substring(0, 5);
        const waktuSelesaiFormat = selesai.substring(0, 5);
        
        previewContent.innerHTML = `
          <div class="row g-2">
            <div class="col-md-6">
              <strong>Kelas:</strong><br>
              <span class="text-primary">${kelas}</span>
            </div>
            <div class="col-md-6">
              <strong>Instruktur:</strong><br>
              <span class="text-success">${instruktur}</span>
            </div>
            <div class="col-md-6">
              <strong>Tanggal:</strong><br>
              <span class="text-info">${tanggalFormat}</span>
            </div>
            <div class="col-md-6">
              <strong>Waktu:</strong><br>
              <span class="badge bg-warning text-dark">${waktuMulaiFormat} - ${waktuSelesaiFormat}</span>
            </div>
          </div>
        `;
        previewInfo.style.display = 'block';
      } else {
        previewInfo.style.display = 'none';
      }
    }
    
    // Event listeners
    if (tanggalInput) {
      tanggalInput.addEventListener('change', updateHari);
      // Set initial hari if tanggal already filled
      if (tanggalInput.value) updateHari();
    }
    
    if (waktuMulai) {
      waktuMulai.addEventListener('change', updateWaktuSelesai);
    }
    
    if (waktuSelesai) {
      waktuSelesai.addEventListener('change', updatePreview);
    }
    
    if (kelasSelect) {
      kelasSelect.addEventListener('change', updatePreview);
    }
    
    if (instrukturSelect) {
      instrukturSelect.addEventListener('change', updatePreview);
    }
    
    // Form validation
    if (form) {
      form.addEventListener('submit', function(e) {
        const tanggal = new Date(tanggalInput.value + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Cek tanggal masa lalu
        if (tanggal < today) {
          e.preventDefault();
          alert('Tanggal tidak boleh masa lalu!');
          tanggalInput.focus();
          return false;
        }
        
        // Cek weekend (0=Sunday, 6=Saturday)
        const dayOfWeek = tanggal.getDay();
        if (dayOfWeek === 0 || dayOfWeek === 6) {
          e.preventDefault();
          const namaHariWeekend = (dayOfWeek === 0) ? 'Minggu' : 'Sabtu';
          alert(`Jadwal tidak dapat dibuat pada hari ${namaHariWeekend}. Silahkan pilih hari Senin-Jumat!`);
          tanggalInput.focus();
          return false;
        }
        
        // Cek waktu selesai > waktu mulai
        if (waktuMulai.value && waktuSelesai.value) {
          const mulai = new Date(`2000-01-01 ${waktuMulai.value}`);
          const selesai = new Date(`2000-01-01 ${waktuSelesai.value}`);
          
          if (selesai <= mulai) {
            e.preventDefault();
            alert('Waktu selesai harus lebih besar dari waktu mulai!');
            waktuSelesai.focus();
            return false;
          }
        }
        
        // Show loading state
        if (btnSubmit && !btnSubmit.disabled) {
          btnSubmit.disabled = true;
          btnSubmit.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Menyimpan...';
        }
      });
    }
    
    // Disable weekend dates in date picker (optional enhancement)
    // This will gray out weekend dates in the date picker
    if (tanggalInput) {
      tanggalInput.addEventListener('input', function() {
        updateHari();
      });
    }
    
    // Initial preview update
    updatePreview();
  });
  </script>
</body>
</html>