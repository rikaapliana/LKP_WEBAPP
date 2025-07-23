<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth();

include '../../../includes/db.php';
$activePage = 'absensi'; 
$baseURL = '../';

// Ambil ID instruktur yang sedang login
$stmt = $conn->prepare("SELECT id_instruktur, nama FROM instruktur WHERE id_user = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$instrukturData = $stmt->get_result()->fetch_assoc();

if (!$instrukturData) {
    $_SESSION['error'] = "Data instruktur tidak ditemukan!";
    header("Location: ../dashboard.php");
    exit();
}

$id_instruktur = $instrukturData['id_instruktur'];
$nama_instruktur = $instrukturData['nama'];

// Handle AJAX untuk save absensi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_absensi') {
    header('Content-Type: application/json');
    
    try {
        $id_siswa = (int)$_POST['id_siswa'];
        $id_jadwal = (int)$_POST['id_jadwal'];
        $status = $_POST['status'];
        $waktu_absen = date('Y-m-d H:i:s');
        
        // Validasi jadwal milik instruktur
        $checkJadwal = $conn->prepare("SELECT j.id_jadwal FROM jadwal j 
                                      JOIN kelas k ON j.id_kelas = k.id_kelas 
                                      WHERE j.id_jadwal = ? AND k.id_instruktur = ?");
        $checkJadwal->bind_param("ii", $id_jadwal, $id_instruktur);
        $checkJadwal->execute();
        if ($checkJadwal->get_result()->num_rows == 0) {
            throw new Exception("Jadwal tidak valid atau bukan jadwal yang Anda ampu!");
        }
        
        // Validasi siswa ada di kelas
        $checkSiswa = $conn->prepare("SELECT s.id_siswa FROM siswa s 
                                     JOIN jadwal j ON s.id_kelas = j.id_kelas
                                     WHERE s.id_siswa = ? AND j.id_jadwal = ? AND s.status_aktif = 'aktif'");
        $checkSiswa->bind_param("ii", $id_siswa, $id_jadwal);
        $checkSiswa->execute();
        if ($checkSiswa->get_result()->num_rows == 0) {
            throw new Exception("Siswa tidak valid atau tidak ada di kelas ini!");
        }
        
        // Cek apakah sudah ada record absensi
        $checkAbsensi = $conn->prepare("SELECT id_absen FROM absensi_siswa WHERE id_siswa = ? AND id_jadwal = ?");
        $checkAbsensi->bind_param("ii", $id_siswa, $id_jadwal);
        $checkAbsensi->execute();
        $existingAbsensi = $checkAbsensi->get_result()->fetch_assoc();
        
        if ($existingAbsensi) {
            // Update existing
            $stmt = $conn->prepare("UPDATE absensi_siswa SET status = ?, waktu_absen = ? WHERE id_absen = ?");
            $stmt->bind_param("ssi", $status, $waktu_absen, $existingAbsensi['id_absen']);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO absensi_siswa (id_siswa, id_jadwal, status, waktu_absen) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $id_siswa, $id_jadwal, $status, $waktu_absen);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'status' => $status]);
        } else {
            throw new Exception("Gagal menyimpan absensi: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get filters
$filterTanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$filterJadwal = isset($_GET['jadwal']) ? (int)$_GET['jadwal'] : '';

// Query jadwal hari ini yang diampu instruktur
$jadwalQuery = "SELECT j.*, k.nama_kelas, g.nama_gelombang,
                CONCAT(j.waktu_mulai, ' - ', j.waktu_selesai) as waktu_jadwal
                FROM jadwal j 
                JOIN kelas k ON j.id_kelas = k.id_kelas
                LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                WHERE k.id_instruktur = ? AND j.tanggal = ?
                ORDER BY j.waktu_mulai";
$jadwalStmt = $conn->prepare($jadwalQuery);
$jadwalStmt->bind_param("is", $id_instruktur, $filterTanggal);
$jadwalStmt->execute();
$jadwalResult = $jadwalStmt->get_result();

// Query siswa di jadwal yang dipilih
$siswaResult = null;
$selectedJadwal = null;

if (!empty($filterJadwal)) {
    // Get info jadwal terpilih
    $selectedJadwalQuery = "SELECT j.*, k.nama_kelas, g.nama_gelombang 
                            FROM jadwal j 
                            JOIN kelas k ON j.id_kelas = k.id_kelas
                            LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                            WHERE j.id_jadwal = ? AND k.id_instruktur = ?";
    $selectedJadwalStmt = $conn->prepare($selectedJadwalQuery);
    $selectedJadwalStmt->bind_param("ii", $filterJadwal, $id_instruktur);
    $selectedJadwalStmt->execute();
    $selectedJadwal = $selectedJadwalStmt->get_result()->fetch_assoc();
    
    // Get siswa di jadwal tersebut
    if ($selectedJadwal) {
        $siswaQuery = "SELECT s.*, k.nama_kelas, g.nama_gelombang,
                       ab.status as status_absensi, ab.waktu_absen
                       FROM siswa s
                       JOIN kelas k ON s.id_kelas = k.id_kelas
                       LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                       LEFT JOIN absensi_siswa ab ON s.id_siswa = ab.id_siswa AND ab.id_jadwal = ?
                       WHERE s.id_kelas = ? AND s.status_aktif = 'aktif'
                       ORDER BY s.nama";
        
        $siswaStmt = $conn->prepare($siswaQuery);
        $siswaStmt->bind_param("ii", $filterJadwal, $selectedJadwal['id_kelas']);
        $siswaStmt->execute();
        $siswaResult = $siswaStmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Absensi Kelas - Instruktur</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/instruktur.php'; ?>

    <div class="flex-fill main-content">
      <!-- TOP NAVBAR -->
      <nav class="top-navbar">
        <div class="container-fluid px-3 px-md-4">
          <div class="d-flex align-items-center">
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <div class="page-info">
                <h2 class="page-title mb-1">ABSENSI KELAS</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Absensi Kelas</li>
                  </ol>
                </nav>
              </div>
            </div>
            
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
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Main Content Card -->
        <div class="card content-card">
          <!-- Header dengan Cetak Data -->
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-calendar-check me-2"></i>Input Absensi Siswa
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <button type="button" class="btn btn-cetak-soft" onclick="cetakLaporanAbsensi()" id="btnCetakAbsensi">
                  <i class="bi bi-printer me-2"></i>Cetak Data
                </button>
              </div>
            </div>
          </div>

          <!-- Filter Controls dengan Quick Actions -->
          <div class="p-3 border-bottom">
            <form method="GET" id="filterForm">
              <div class="row align-items-end g-3">
                <!-- Filter Tanggal -->
                <div class="col-md-2">
                  <label for="filterTanggal" class="form-label small text-muted mb-1">Tanggal:</label>
                  <input type="date" name="tanggal" id="filterTanggal" class="form-control form-control-sm" 
                         value="<?= $filterTanggal ?>" onchange="document.getElementById('filterForm').submit();">
                </div>
                
                <!-- Filter Jadwal (dikecilkan) -->
                <div class="col-md-4">
                  <label for="filterJadwal" class="form-label small text-muted mb-1">Pilih Jadwal Kelas:</label>
                  <select name="jadwal" id="filterJadwal" class="form-select form-select-sm" onchange="document.getElementById('filterForm').submit();">
                    <option value="">-- Pilih Jadwal --</option>
                    <?php if ($jadwalResult->num_rows > 0): ?>
                      <?php while($jadwal = $jadwalResult->fetch_assoc()): ?>
                        <option value="<?= $jadwal['id_jadwal'] ?>" <?= ($filterJadwal == $jadwal['id_jadwal']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($jadwal['nama_kelas']) ?>
                          <?php if($jadwal['nama_gelombang']): ?>
                            (<?= htmlspecialchars($jadwal['nama_gelombang']) ?>)
                          <?php endif; ?>
                        </option>
                      <?php endwhile; ?>
                    <?php endif; ?>
                  </select>
                </div>
                
                <!-- Quick Actions (Semua Hadir + Simpan Semua) -->
                <div class="col-md-6">
                  <?php if (!empty($filterJadwal) && $siswaResult && $siswaResult->num_rows > 0): ?>
                  <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="selectAllHadir()">
                      <i class="bi bi-check-circle me-1"></i>Semua Hadir
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="saveAllAbsensi()">
                      <i class="bi bi-check-all me-1"></i>Simpan Semua
                    </button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </form>
          </div>

          <!-- Content Area -->
          <div class="card-body">
            <?php if (empty($filterJadwal)): ?>
              <!-- Belum pilih jadwal -->
              <div class="text-center py-5">
                <i class="bi bi-calendar-event display-4 text-muted mb-3 d-block"></i>
                <h5>Pilih Jadwal Kelas</h5>
                <p class="text-muted mb-3">
                  Pilih tanggal dan jadwal kelas untuk mulai input absensi siswa
                </p>
                <?php if ($jadwalResult->num_rows == 0): ?>
                  <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Tidak ada jadwal untuk tanggal <?= date('d M Y', strtotime($filterTanggal)) ?>
                  </div>
                <?php endif; ?>
              </div>
            
            <?php elseif (!$selectedJadwal): ?>
              <!-- Jadwal tidak valid -->
              <div class="text-center py-5">
                <i class="bi bi-exclamation-triangle display-4 text-warning mb-3 d-block"></i>
                <h5>Jadwal Tidak Valid</h5>
                <p class="text-muted">Jadwal yang dipilih tidak ditemukan atau bukan jadwal kelas yang Anda ampu</p>
              </div>
            
            <?php else: ?>
              <!-- Info Jadwal Terpilih -->
              <div class="alert alert-info mb-4">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <h6 class="mb-1 text-muted">
                      <i class="bi bi-calendar-event me-2"></i>
                      <?= htmlspecialchars($selectedJadwal['nama_kelas']) ?>
                      <?php if($selectedJadwal['nama_gelombang']): ?>
                        <span class="text-muted">(<?= htmlspecialchars($selectedJadwal['nama_gelombang']) ?>)</span>
                      <?php endif; ?>
                    </h6>
                    <small class="text-muted">
                      <i class="bi bi-clock me-1"></i>
                      <?= date('d M Y', strtotime($selectedJadwal['tanggal'])) ?> • 
                      <?= $selectedJadwal['waktu_mulai'] ?> - <?= $selectedJadwal['waktu_selesai'] ?>
                    </small>
                  </div>
                </div>
              </div>

              <!-- Table Absensi -->
              <?php if ($siswaResult && $siswaResult->num_rows > 0): ?>
                <div class="table-responsive">
                  <table class="custom-table mb-0" id="absensiTable">
                    <thead class="sticky-top">
                      <tr>
                        <th style="min-width: 180px;">Nama Siswa</th>
                        <th class="text-center" style="width: 120px;">NIK</th>
                        <th class="text-center" style="width: 100px;">Hadir</th>
                        <th class="text-center" style="width: 100px;">Izin</th>
                        <th class="text-center" style="width: 100px;">Sakit</th>
                        <th class="text-center" style="width: 100px;">Alpha</th>
                        <th class="text-center" style="width: 150px;">Waktu Absen</th>
                        <th class="text-center" style="width: 80px;">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php while ($siswa = $siswaResult->fetch_assoc()): ?>
                        <tr data-siswa="<?= $siswa['id_siswa'] ?>" data-jadwal="<?= $filterJadwal ?>">
                          <!-- Nama Siswa -->
                          <td class="align-middle">
                            <div class="fw-medium"><?= htmlspecialchars($siswa['nama']) ?></div>
                            <small class="text-muted">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                          </td>
                          
                          <!-- NIK -->
                          <td class="text-center align-middle">
                            <small class="text-muted"><?= htmlspecialchars($siswa['nik']) ?></small>
                          </td>
                          
                          <!-- Radio Button Status -->
                          <td class="text-center align-middle">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="hadir" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'hadir') ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center align-middle">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="izin" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'izin') ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center align-middle">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="sakit" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'sakit') ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center align-middle">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="tanpa keterangan" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'tanpa keterangan') ? 'checked' : '' ?>>
                          </td>
                          
                          <!-- Waktu Absen -->
                          <td class="text-center align-middle">
                            <span class="waktu-absen-display">
                              <?php if($siswa['waktu_absen']): ?>
                                <small class="text-muted">
                                  <?= date('H:i', strtotime($siswa['waktu_absen'])) ?>
                                </small>
                              <?php else: ?>
                                <small class="text-muted">-</small>
                              <?php endif; ?>
                            </span>
                          </td>
                          
                          <!-- Aksi -->
                          <td class="text-center align-middle">
                            <button type="button" class="btn btn-sm btn-success save-row" 
                                    onclick="saveAbsensiSiswa(<?= $siswa['id_siswa'] ?>, <?= $filterJadwal ?>)">
                              <i class="bi bi-check"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="text-center py-5">
                  <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                  <h5>Belum Ada Siswa</h5>
                  <p class="text-muted">Tidak ada siswa aktif di kelas ini</p>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  // Save individual absensi
  function saveAbsensiSiswa(idSiswa, idJadwal) {
    const row = document.querySelector(`tr[data-siswa="${idSiswa}"]`);
    const statusRadio = row.querySelector(`input[name="status_${idSiswa}"]:checked`);
    const saveBtn = row.querySelector('.save-row');
    
    if (!statusRadio) {
      Swal.fire({
        title: 'Perhatian!',
        text: 'Pilih status absensi terlebih dahulu',
        icon: 'warning'
      });
      return;
    }
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
    
    const formData = new FormData();
    formData.append('action', 'save_absensi');
    formData.append('id_siswa', idSiswa);
    formData.append('id_jadwal', idJadwal);
    formData.append('status', statusRadio.value);
    
    fetch('', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const waktuDisplay = row.querySelector('.waktu-absen-display');
        const currentTime = new Date().toLocaleTimeString('id-ID', {
          hour: '2-digit',
          minute: '2-digit'
        });
        waktuDisplay.innerHTML = `<small class="text-muted">${currentTime}</small>`;
        
        row.classList.add('table-success');
        setTimeout(() => row.classList.remove('table-success'), 2000);
        
        Swal.fire({
          title: 'Berhasil!',
          text: 'Absensi berhasil disimpan',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });
      } else {
        Swal.fire({
          title: 'Error!',
          text: data.message || 'Gagal menyimpan absensi',
          icon: 'error'
        });
      }
    })
    .catch(error => {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error!',
        text: 'Terjadi kesalahan koneksi',
        icon: 'error'
      });
    })
    .finally(() => {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="bi bi-check"></i>';
    });
  }
  
  // Save all absensi
  function saveAllAbsensi() {
    const rows = document.querySelectorAll('#absensiTable tbody tr[data-siswa]');
    let totalRows = rows.length;
    let savedRows = 0;
    let hasError = false;
    
    if (totalRows === 0) {
      Swal.fire({
        title: 'Tidak Ada Data',
        text: 'Tidak ada siswa untuk disimpan',
        icon: 'info'
      });
      return;
    }
    
    // Check missing status
    let missingStatus = [];
    rows.forEach(row => {
      const idSiswa = row.dataset.siswa;
      const statusRadio = row.querySelector(`input[name="status_${idSiswa}"]:checked`);
      if (!statusRadio) {
        const namaSiswa = row.querySelector('.fw-medium').textContent;
        missingStatus.push(namaSiswa);
      }
    });
    
    if (missingStatus.length > 0) {
      Swal.fire({
        title: 'Status Belum Lengkap',
        html: `Siswa berikut belum memiliki status absensi:<br><br>${missingStatus.join('<br>')}`,
        icon: 'warning'
      });
      return;
    }
    
    Swal.fire({
      title: 'Menyimpan Data...',
      text: `Menyimpan absensi untuk ${totalRows} siswa`,
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    rows.forEach(row => {
      const idSiswa = row.dataset.siswa;
      const idJadwal = row.dataset.jadwal;
      const statusRadio = row.querySelector(`input[name="status_${idSiswa}"]:checked`);
      
      const formData = new FormData();
      formData.append('action', 'save_absensi');
      formData.append('id_siswa', idSiswa);
      formData.append('id_jadwal', idJadwal);
      formData.append('status', statusRadio.value);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        savedRows++;
        
        if (data.success) {
          const waktuDisplay = row.querySelector('.waktu-absen-display');
          const currentTime = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
          });
          waktuDisplay.innerHTML = `<small class="text-muted">${currentTime}</small>`;
          row.classList.add('table-success');
        } else {
          hasError = true;
          row.classList.add('table-danger');
        }
        
        if (savedRows === totalRows) {
          Swal.close();
          
          if (hasError) {
            Swal.fire({
              title: 'Sebagian Berhasil',
              text: 'Beberapa absensi berhasil disimpan, tapi ada yang gagal',
              icon: 'warning'
            });
          } else {
            Swal.fire({
              title: 'Semua Berhasil!',
              text: `${totalRows} absensi siswa berhasil disimpan`,
              icon: 'success',
              timer: 2000,
              showConfirmButton: false
            });
          }
          
          setTimeout(() => {
            rows.forEach(row => {
              row.classList.remove('table-success', 'table-danger');
            });
          }, 3000);
        }
      })
      .catch(error => {
        savedRows++;
        hasError = true;
        console.error('Error:', error);
        
        if (savedRows === totalRows) {
          Swal.close();
          Swal.fire({
            title: 'Ada Kesalahan',
            text: 'Terjadi kesalahan saat menyimpan beberapa absensi',
            icon: 'error'
          });
        }
      });
    });
  }
  
  // Quick select functions
  function selectAllHadir() {
    const hadirRadios = document.querySelectorAll('input[value="hadir"]');
    hadirRadios.forEach(radio => {
      radio.checked = true;
      radio.dispatchEvent(new Event('change'));
    });
  }
  
  // Event listeners
  document.addEventListener('DOMContentLoaded', function() {
    const statusRadios = document.querySelectorAll('.status-radio');
    
    // Auto-change detection
    statusRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        const row = this.closest('tr');
        row.style.backgroundColor = '#fff3cd'; // warning background
        setTimeout(() => {
          row.style.backgroundColor = '';
        }, 2000);
      });
    });
    
    // Initialize tooltips
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
  });
  
  // Keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    // Ctrl+S untuk save all
    if (e.ctrlKey && e.key === 's') {
      e.preventDefault();
      const saveAllBtn = document.querySelector('[onclick="saveAllAbsensi()"]');
      if (saveAllBtn && !saveAllBtn.disabled) {
        saveAllAbsensi();
      }
    }
    
    // Ctrl+H untuk select all hadir
    if (e.ctrlKey && e.key === 'h') {
      e.preventDefault();
      selectAllHadir();
    }
  });
  
  // Fungsi cetak laporan absensi
  function cetakLaporanAbsensi() {
    const btnCetak = document.getElementById('btnCetakAbsensi');
    
    btnCetak.disabled = true;
    btnCetak.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Memproses...';
    
    let url = 'cetak_laporan.php?';
    const params = [];
    
    if ('<?= $filterTanggal ?>') params.push('tanggal=' + encodeURIComponent('<?= $filterTanggal ?>'));
    if ('<?= $filterJadwal ?>') params.push('jadwal=' + encodeURIComponent('<?= $filterJadwal ?>'));
    
    url += params.join('&');
    
    const pdfWindow = window.open(url, '_blank');
    
    setTimeout(() => {
      btnCetak.disabled = false;
      btnCetak.innerHTML = '<i class="bi bi-printer me-2"></i>Cetak Data';
      
      if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
        Swal.fire({
          title: 'Pop-up Diblokir!',
          text: 'Browser memblokir pop-up. Silakan izinkan popup untuk mengunduh laporan PDF.',
          icon: 'warning'
        });
      }
    }, 2000);
  }
  </script>

</body>
</html>