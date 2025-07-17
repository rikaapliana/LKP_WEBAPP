<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Simple version - tanpa JavaScript kompleks
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 'preview') {
        // Step 1: Generate Preview
        $kelas_id = $_POST['kelas_id'];
        $instruktur_id = $_POST['instruktur_id'];
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $waktu_mulai = $_POST['waktu_mulai'];
        $waktu_selesai = $_POST['waktu_selesai'];
        $hari_aktif = isset($_POST['hari_aktif']) ? $_POST['hari_aktif'] : [];
        
        // Validasi hari aktif hanya untuk step preview
        if (empty($hari_aktif)) {
            $_SESSION['error'] = "Pilih minimal satu hari aktif!";
        } else {
            // Generate jadwal
            $jadwal_list = [];
            $current = new DateTime($tanggal_mulai);
            $end = new DateTime($tanggal_selesai);
            
            $hariNama = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'
            ];
            
            while ($current <= $end) {
                $day_num = $current->format('N'); // 1=Monday
                if (in_array($day_num, $hari_aktif)) {
                    $jadwal_list[] = [
                        'tanggal' => $current->format('Y-m-d'),
                        'hari' => $hariNama[$day_num] ?? $current->format('l'),
                        'waktu_mulai' => $waktu_mulai,
                        'waktu_selesai' => $waktu_selesai
                    ];
                }
                $current->add(new DateInterval('P1D'));
            }
            
            // Store for next step
            $_SESSION['preview_data'] = $jadwal_list;
            $_SESSION['generate_form'] = $_POST;
        }
        
    } elseif (isset($_POST['step']) && $_POST['step'] == 'generate') {
        // Step untuk kembali ke form - JANGAN lakukan validasi
        // Hapus preview data untuk kembali ke form
        unset($_SESSION['preview_data']);
        // Tetap simpan form data agar tidak hilang
        // $_SESSION['generate_form'] sudah ada dari sebelumnya
        
    } elseif (isset($_POST['step']) && $_POST['step'] == 'save') {
        // Step 2: Save to Database
        $kelas_id = $_POST['kelas_id'];
        $instruktur_id = $_POST['instruktur_id'];
        $jadwal_data = $_SESSION['preview_data'] ?? [];
        
        $success = 0;
        $failed = 0;
        $error_messages = [];
        
        foreach ($jadwal_data as $jadwal) {
            $tanggal = mysqli_real_escape_string($conn, $jadwal['tanggal']);
            $waktu_mulai = mysqli_real_escape_string($conn, $jadwal['waktu_mulai']);
            $waktu_selesai = mysqli_real_escape_string($conn, $jadwal['waktu_selesai']);
            
            // Pastikan format time
            if (strlen($waktu_mulai) == 5) $waktu_mulai .= ':00';
            if (strlen($waktu_selesai) == 5) $waktu_selesai .= ':00';
            
            $query = "INSERT INTO jadwal (id_kelas, id_instruktur, tanggal, waktu_mulai, waktu_selesai) 
                     VALUES ('$kelas_id', '$instruktur_id', '$tanggal', '$waktu_mulai', '$waktu_selesai')";
            
            if (mysqli_query($conn, $query)) {
                $success++;
            } else {
                $failed++;
                $error_messages[] = mysqli_error($conn);
            }
        }
        
        // Clear session data
        unset($_SESSION['preview_data']);
        unset($_SESSION['generate_form']);
        
        if ($success > 0) {
            $_SESSION['success'] = "Berhasil membuat $success jadwal baru!";
        }
        if ($failed > 0) {
            $_SESSION['error'] = "Gagal membuat $failed jadwal. " . implode(', ', $error_messages);
        }
        
        header('Location: index.php');
        exit;
    }
}

// Get data for form
$kelasQuery = "SELECT k.*, g.nama_gelombang FROM kelas k LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

$instrukturQuery = "SELECT * FROM instruktur ORDER BY nama";
$instrukturResult = mysqli_query($conn, $instrukturQuery);

// Restore form data if coming from preview
$formData = $_SESSION['generate_form'] ?? [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Generate Jadwal Otomatis</title>
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
                <h2 class="page-title mb-1">GENERATE JADWAL</h2>
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
                    <li class="breadcrumb-item active" aria-current="page">Generate Otomatis</li>
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
          <!-- Form Generate -->
          <div class="col-lg-8">
            <div class="card content-card">
              <?php if(isset($_SESSION['preview_data']) && !empty($_SESSION['preview_data'])): ?>
                <!-- Preview Section -->
                <div class="section-header">
                  <h5 class="mb-0 text-dark">
                    <i class="bi bi-calendar-check me-2"></i>Preview Jadwal yang Akan Dibuat
                  </h5>
                </div>

                <div class="card-body">
                  <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong><?= count($_SESSION['preview_data']) ?></strong> jadwal akan dibuat untuk periode 
                    <?= date('d/m/Y', strtotime($formData['tanggal_mulai'])) ?> - 
                    <?= date('d/m/Y', strtotime($formData['tanggal_selesai'])) ?>
                  </div>

                  <div class="table-responsive">
                    <table class="custom-table mb-0">
                      <thead>
                        <tr>
                          <th>No</th>
                          <th>Tanggal</th>
                          <th>Hari</th>
                          <th>Waktu</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php $no = 1; foreach($_SESSION['preview_data'] as $jadwal): ?>
                        <tr>
                          <td class="text-center"><?= $no++ ?></td>
                          <td><?= date('d/m/Y', strtotime($jadwal['tanggal'])) ?></td>
                          <td><?= $jadwal['hari'] ?></td>
                          <td>
                            <span class="badge bg-info text-dark">
                              <?= $jadwal['waktu_mulai'] ?> - <?= $jadwal['waktu_selesai'] ?>
                            </span>
                          </td>
                          <td>
                            <span class="badge bg-success">
                              <i class="bi bi-check-circle me-1"></i>Siap Dibuat
                            </span>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="card-footer">
                  <div class="d-flex justify-content-between">
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="step" value="generate">
                      <button type="submit" class="btn btn-kembali">
                        <i class="bi bi-arrow-left me-2"></i>Ubah Parameter
                      </button>
                    </form>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="step" value="save">
                      <input type="hidden" name="kelas_id" value="<?= htmlspecialchars($formData['kelas_id']) ?>">
                      <input type="hidden" name="instruktur_id" value="<?= htmlspecialchars($formData['instruktur_id']) ?>">
                      <button type="submit" class="btn btn-simpan">
                        <i class="bi bi-check-circle me-1"></i>Simpan Jadwal
                      </button>
                    </form>
                  </div>
                </div>
              <?php else: ?>
                <!-- Generate Form -->
                <div class="section-header">
                  <h5 class="mb-0 text-dark">
                    <i class="bi bi-calendar-range me-2"></i>Generate Jadwal Otomatis
                  </h5>
                </div>

                <div class="card-body">
                  <form method="POST" id="generateForm">
                    <input type="hidden" name="step" value="preview">
                    <div class="row g-3">
                      <!-- Pilih Kelas -->
                      <div class="col-md-6">
                        <label class="form-label required">Kelas</label>
                        <select name="kelas_id" class="form-select" required>
                          <option value="">Pilih Kelas</option>
                          <?php while($kelas = mysqli_fetch_assoc($kelasResult)): ?>
                            <option value="<?= $kelas['id_kelas'] ?>" 
                                    <?= (isset($formData['kelas_id']) && $formData['kelas_id'] == $kelas['id_kelas']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($kelas['nama_kelas']) ?>
                              <?php if($kelas['nama_gelombang']): ?>
                                (<?= htmlspecialchars($kelas['nama_gelombang']) ?>)
                              <?php endif; ?>
                            </option>
                          <?php endwhile; ?>
                        </select>
                      </div>

                      <!-- Pilih Instruktur -->
                      <div class="col-md-6">
                        <label class="form-label required">Instruktur</label>
                        <select name="instruktur_id" class="form-select" required>
                          <option value="">Pilih Instruktur</option>
                          <?php while($instruktur = mysqli_fetch_assoc($instrukturResult)): ?>
                            <option value="<?= $instruktur['id_instruktur'] ?>"
                                    <?= (isset($formData['instruktur_id']) && $formData['instruktur_id'] == $instruktur['id_instruktur']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($instruktur['nama']) ?>
                            </option>
                          <?php endwhile; ?>
                        </select>
                      </div>

                      <!-- Periode -->
                      <div class="col-md-6">
                        <label class="form-label required">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" class="form-control" 
                               value="<?= $formData['tanggal_mulai'] ?? date('Y-m-d') ?>" required>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label required">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" class="form-control" 
                               value="<?= $formData['tanggal_selesai'] ?? date('Y-m-d', strtotime('+1 month')) ?>" required>
                      </div>

                      <!-- Waktu -->
                      <div class="col-md-6">
                        <label class="form-label required">Waktu Mulai</label>
                        <select name="waktu_mulai" class="form-select" required>
                          <option value="">Pilih Waktu</option>
                          <option value="08:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '08:00:00') ? 'selected' : '' ?>>08:00</option>
                          <option value="09:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '09:00:00') ? 'selected' : '' ?>>09:00</option>
                          <option value="10:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '10:00:00') ? 'selected' : '' ?>>10:00</option>
                          <option value="11:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '11:00:00') ? 'selected' : '' ?>>11:00</option>
                          <option value="13:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '13:00:00') ? 'selected' : '' ?>>13:00</option>
                          <option value="14:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '14:00:00') ? 'selected' : '' ?>>14:00</option>
                          <option value="15:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '15:00:00') ? 'selected' : '' ?>>15:00</option>
                          <option value="16:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '16:00:00') ? 'selected' : '' ?>>16:00</option>
                          <option value="17:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '17:00:00') ? 'selected' : '' ?>>17:00</option>
                          <option value="19:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '19:00:00') ? 'selected' : '' ?>>19:00</option>
                          <option value="20:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '20:00:00') ? 'selected' : '' ?>>20:00</option>
                          <option value="21:00:00" <?= (isset($formData['waktu_mulai']) && $formData['waktu_mulai'] == '21:00:00') ? 'selected' : '' ?>>21:00</option>
                        </select>
                      </div>

                      <div class="col-md-6">
                        <label class="form-label required">Waktu Selesai</label>
                        <select name="waktu_selesai" class="form-select" required>
                          <option value="">Pilih Waktu</option>
                          <option value="09:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '09:00:00') ? 'selected' : '' ?>>09:00</option>
                          <option value="10:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '10:00:00') ? 'selected' : '' ?>>10:00</option>
                          <option value="11:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '11:00:00') ? 'selected' : '' ?>>11:00</option>
                          <option value="12:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '12:00:00') ? 'selected' : '' ?>>12:00</option>
                          <option value="14:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '14:00:00') ? 'selected' : '' ?>>14:00</option>
                          <option value="15:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '15:00:00') ? 'selected' : '' ?>>15:00</option>
                          <option value="16:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '16:00:00') ? 'selected' : '' ?>>16:00</option>
                          <option value="17:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '17:00:00') ? 'selected' : '' ?>>17:00</option>
                          <option value="18:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '18:00:00') ? 'selected' : '' ?>>18:00</option>
                          <option value="20:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '20:00:00') ? 'selected' : '' ?>>20:00</option>
                          <option value="21:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '21:00:00') ? 'selected' : '' ?>>21:00</option>
                          <option value="22:00:00" <?= (isset($formData['waktu_selesai']) && $formData['waktu_selesai'] == '22:00:00') ? 'selected' : '' ?>>22:00</option>
                        </select>
                      </div>

                      <!-- Hari Aktif -->
                      <div class="col-12">
                        <label class="form-label required">Hari Aktif</label>
                        <div class="row g-2">
                          <div class="col-auto">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="hari_aktif[]" 
                                     value="1" id="hari1" <?= (!isset($formData['hari_aktif']) || in_array('1', $formData['hari_aktif'] ?? [1,2,3,4,5])) ? 'checked' : '' ?>>
                              <label class="form-check-label" for="hari1">Senin</label>
                            </div>
                          </div>
                          <div class="col-auto">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="hari_aktif[]" 
                                     value="2" id="hari2" <?= (!isset($formData['hari_aktif']) || in_array('2', $formData['hari_aktif'] ?? [1,2,3,4,5])) ? 'checked' : '' ?>>
                              <label class="form-check-label" for="hari2">Selasa</label>
                            </div>
                          </div>
                          <div class="col-auto">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="hari_aktif[]" 
                                     value="3" id="hari3" <?= (!isset($formData['hari_aktif']) || in_array('3', $formData['hari_aktif'] ?? [1,2,3,4,5])) ? 'checked' : '' ?>>
                              <label class="form-check-label" for="hari3">Rabu</label>
                            </div>
                          </div>
                          <div class="col-auto">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="hari_aktif[]" 
                                     value="4" id="hari4" <?= (!isset($formData['hari_aktif']) || in_array('4', $formData['hari_aktif'] ?? [1,2,3,4,5])) ? 'checked' : '' ?>>
                              <label class="form-check-label" for="hari4">Kamis</label>
                            </div>
                          </div>
                          <div class="col-auto">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="hari_aktif[]" 
                                     value="5" id="hari5" <?= (!isset($formData['hari_aktif']) || in_array('5', $formData['hari_aktif'] ?? [1,2,3,4,5])) ? 'checked' : '' ?>>
                              <label class="form-check-label" for="hari5">Jumat</label>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Button -->
                      <div class="col-12">
                        <hr class="my-4">
                         <div class="d-flex justify-content-end gap-2">
                            <a href="index.php" class="btn btn-kembali px-3">
                            Kembali
                            </a>
                            <button type="submit" class="btn btn-simpan px-4">
                              <i class="bi bi-eye me-1"></i>Preview Jadwal
                            </button>
                </div>
                      </div>
                    </div>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Info Panel -->
          <div class="col-lg-4">
            <div class="card content-card">
              <div class="section-header">
                <h6 class="mb-0 text-dark">
                  <i class="bi bi-info-circle me-2"></i>Panduan Generate
                </h6>
              </div>
              <div class="card-body">
                <div class="alert alert-info">
                  <h6 class="alert-heading">
                    <i class="bi bi-lightbulb me-2"></i>Tips Generate Jadwal
                  </h6>
                  <ul class="mb-0 small">
                    <li>Pilih kelas dan instruktur yang akan diampu</li>
                    <li>Tentukan periode jadwal yang ingin dibuat</li>
                    <li>Waktu per sesi adalah 1 jam</li>
                    <li>Sistem akan skip weekend otomatis</li>
                    <li>Preview terlebih dahulu sebelum menyimpan</li>
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
    // Auto-set waktu selesai berdasarkan waktu mulai
    const waktuMulai = document.querySelector('select[name="waktu_mulai"]');
    const waktuSelesai = document.querySelector('select[name="waktu_selesai"]');
    
    if (waktuMulai && waktuSelesai) {
      waktuMulai.addEventListener('change', function() {
        const mulai = this.value; // Format: HH:MM:SS
        if (mulai) {
          // Konversi waktu mulai ke jam + 1 untuk waktu selesai
          const [jam, menit, detik] = mulai.split(':');
          const jamSelesai = (parseInt(jam) + 1).toString().padStart(2, '0');
          const waktuSelesaiValue = `${jamSelesai}:${menit}:${detik}`;
          
          // Set value waktu selesai
          waktuSelesai.value = waktuSelesaiValue;
        }
      });
    }
    
    // Validasi tanggal
    const tanggalMulai = document.querySelector('input[name="tanggal_mulai"]');
    const tanggalSelesai = document.querySelector('input[name="tanggal_selesai"]');
    
    if (tanggalMulai && tanggalSelesai) {
      tanggalMulai.addEventListener('change', function() {
        const mulai = new Date(this.value);
        const selesai = new Date(tanggalSelesai.value);
        
        if (selesai <= mulai) {
          // Set tanggal selesai minimal 1 minggu setelah tanggal mulai
          const newSelesai = new Date(mulai);
          newSelesai.setDate(newSelesai.getDate() + 7);
          tanggalSelesai.value = newSelesai.toISOString().split('T')[0];
        }
      });
      
      tanggalSelesai.addEventListener('change', function() {
        const mulai = new Date(tanggalMulai.value);
        const selesai = new Date(this.value);
        
        if (selesai <= mulai) {
          alert('Tanggal selesai harus lebih besar dari tanggal mulai');
          this.value = '';
        }
      });
    }
    
    // Form validation before submit - HANYA untuk step preview
    const form = document.querySelector('#generateForm');
    if (form) {
      form.addEventListener('submit', function(e) {
        const hariAktif = document.querySelectorAll('input[name="hari_aktif[]"]:checked');
        if (hariAktif.length === 0) {
          e.preventDefault();
          alert('Pilih minimal satu hari aktif!');
          return false;
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Memproses...';
        }
      });
    }
  });
  </script>
</body>
</html>