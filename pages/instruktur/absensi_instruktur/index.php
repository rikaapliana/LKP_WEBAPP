<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth();

include '../../../includes/db.php';
$activePage = 'absensi-instruktur'; 
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

// Handle AJAX untuk save absensi instruktur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_absensi_instruktur') {
    header('Content-Type: application/json');
    
    try {
        $id_jadwal = (int)$_POST['id_jadwal'];
        $status = $_POST['status'];
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
        $tanggal = date('Y-m-d');
        $waktu = date('Y-m-d H:i:s');
        
        // Validasi jadwal milik instruktur hari ini
        $checkJadwal = $conn->prepare("SELECT j.*, k.nama_kelas FROM jadwal j 
                                      JOIN kelas k ON j.id_kelas = k.id_kelas 
                                      WHERE j.id_jadwal = ? AND k.id_instruktur = ? AND j.tanggal = ?");
        $checkJadwal->bind_param("iis", $id_jadwal, $id_instruktur, $tanggal);
        $checkJadwal->execute();
        $jadwalData = $checkJadwal->get_result()->fetch_assoc();
        
        if (!$jadwalData) {
            throw new Exception("Jadwal tidak valid atau bukan jadwal Anda hari ini!");
        }
        
        // Cek apakah sudah absen untuk jadwal ini hari ini
        $checkAbsensi = $conn->prepare("SELECT id_absen FROM absensi_instruktur WHERE id_instruktur = ? AND id_jadwal = ? AND tanggal = ?");
        $checkAbsensi->bind_param("iis", $id_instruktur, $id_jadwal, $tanggal);
        $checkAbsensi->execute();
        $existingAbsensi = $checkAbsensi->get_result()->fetch_assoc();
        
        if ($existingAbsensi) {
            throw new Exception("Anda sudah absen untuk jadwal ini hari ini!");
        }
        
        // Determine status otomatis berdasarkan waktu jika status hadir
        $finalStatus = $status;
        $isLate = false;
        
        if ($status == 'hadir') {
            $jadwalStart = strtotime($jadwalData['tanggal'] . ' ' . $jadwalData['waktu_mulai']);
            $currentTime = time();
            $toleranceTime = $jadwalStart + (30 * 60); // 30 menit setelah jadwal mulai
            
            if ($currentTime > $toleranceTime) {
                $isLate = true;
            }
        }
        
        // Insert absensi baru
        $stmt = $conn->prepare("INSERT INTO absensi_instruktur (id_instruktur, id_jadwal, tanggal, waktu, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $id_instruktur, $id_jadwal, $tanggal, $waktu, $finalStatus, $keterangan);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'status' => $finalStatus,
                'waktu' => date('H:i', strtotime($waktu)),
                'is_late' => $isLate,
                'message' => 'Absensi berhasil disimpan!'
            ]);
        } else {
            throw new Exception("Gagal menyimpan absensi: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Get tanggal hari ini
$tanggalHariIni = date('Y-m-d');

// Query jadwal hari ini yang diampu instruktur
$jadwalQuery = "SELECT j.*, k.nama_kelas, g.nama_gelombang,
                CONCAT(j.waktu_mulai, ' - ', j.waktu_selesai) as waktu_jadwal,
                ai.status as status_absensi, ai.waktu as waktu_absen, ai.keterangan
                FROM jadwal j 
                JOIN kelas k ON j.id_kelas = k.id_kelas
                LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
                LEFT JOIN absensi_instruktur ai ON j.id_jadwal = ai.id_jadwal AND ai.tanggal = ?
                WHERE k.id_instruktur = ? AND j.tanggal = ?
                ORDER BY j.waktu_mulai";
$jadwalStmt = $conn->prepare($jadwalQuery);
$jadwalStmt->bind_param("sis", $tanggalHariIni, $id_instruktur, $tanggalHariIni);
$jadwalStmt->execute();
$jadwalResult = $jadwalStmt->get_result();

// Hitung statistik
$totalJadwal = $jadwalResult->num_rows;
$hadirCount = 0;
$izinCount = 0;
$sakitCount = 0;
$alphaCount = 0;
$belumAbsenCount = 0;

// Reset pointer dan hitung statistik
mysqli_data_seek($jadwalResult, 0);
while ($row = $jadwalResult->fetch_assoc()) {
    if (empty($row['status_absensi'])) {
        $belumAbsenCount++;
    } else {
        switch ($row['status_absensi']) {
            case 'hadir': $hadirCount++; break;
            case 'izin': $izinCount++; break;
            case 'sakit': $sakitCount++; break;
            case 'tanpa keterangan': $alphaCount++; break;
        }
    }
}

// Reset pointer lagi untuk tampilan
mysqli_data_seek($jadwalResult, 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Absensi Instruktur - LKP Pradata Komputer</title>
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
                <h2 class="page-title mb-1">ABSENSI SAYA</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Absensi Saya</li>
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
          <!-- Header -->
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-8">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-person-check me-2"></i>Absensi Instruktur
                </h5>
                <small class="text-muted">
                  Selamat datang, <?= htmlspecialchars($nama_instruktur) ?> • <?= date('l, d F Y') ?>
                </small>
              </div>
              <div class="col-md-4 text-md-end">
                <span class="badge bg-primary px-3 py-2">
                  <i class="bi bi-clock me-1"></i>
                  <span id="currentTime"><?= date('H:i') ?></span>
                </span>
              </div>
            </div>
          </div>

          <?php if ($totalJadwal > 0): ?>
            <!-- Info Alert -->
            <div class="p-3 border-bottom">
              <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Informasi:</strong> Toleransi keterlambatan 30 menit • Setiap jadwal memerlukan absensi terpisah
              </div>
            </div>

            <!-- Content Area -->
            <div class="card-body">
              <div class="row g-4">
                <?php while ($jadwal = $jadwalResult->fetch_assoc()): ?>
                  <?php
                  // Tentukan status display
                  $isAbsen = !empty($jadwal['status_absensi']);
                  $statusClass = 'secondary';
                  $statusText = 'Belum Absen';
                  $statusIcon = 'clock';
                  
                  // Cek apakah sudah lewat jadwal
                  $jadwalEnd = strtotime($jadwal['tanggal'] . ' ' . $jadwal['waktu_selesai']);
                  $currentTime = time();
                  $isExpired = $currentTime > $jadwalEnd;
                  
                  if ($isAbsen) {
                    switch ($jadwal['status_absensi']) {
                      case 'hadir':
                        $statusClass = 'success';
                        $statusText = 'Hadir';
                        $statusIcon = 'check-circle';
                        
                        // Cek keterlambatan
                        $jadwalStart = strtotime($jadwal['tanggal'] . ' ' . $jadwal['waktu_mulai']);
                        $absenTime = strtotime($jadwal['waktu_absen']);
                        $toleranceTime = $jadwalStart + (30 * 60);
                        
                        if ($absenTime > $toleranceTime) {
                          $statusText = 'Hadir (Terlambat)';
                          $statusClass = 'warning';
                          $statusIcon = 'exclamation-triangle';
                        }
                        break;
                      case 'izin':
                        $statusClass = 'info';
                        $statusText = 'Izin';
                        $statusIcon = 'info-circle';
                        break;
                      case 'sakit':
                        $statusClass = 'warning';
                        $statusText = 'Sakit';
                        $statusIcon = 'heart-pulse';
                        break;
                      case 'tanpa keterangan':
                        $statusClass = 'danger';
                        $statusText = 'Tanpa Keterangan';
                        $statusIcon = 'x-circle';
                        break;
                    }
                  } elseif ($isExpired) {
                    $statusClass = 'danger';
                    $statusText = 'Tidak Hadir';
                    $statusIcon = 'x-circle';
                  }
                  ?>
                  
                  <div class="col-lg-6">
                    <div class="card h-100">
                      <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <h6 class="mb-1 fw-bold text-dark">
                              <?= htmlspecialchars($jadwal['nama_kelas']) ?>
                            </h6>
                            <?php if($jadwal['nama_gelombang']): ?>
                              <small class="text-muted">
                                <?= htmlspecialchars($jadwal['nama_gelombang']) ?>
                              </small>
                            <?php endif; ?>
                          </div>
                          <span class="badge bg-<?= $statusClass ?> px-2 py-1">
                            <i class="bi bi-<?= $statusIcon ?> me-1"></i>
                            <?= $statusText ?>
                          </span>
                        </div>
                      </div>
                      
                      <div class="card-body">
                        <div class="mb-3">
                          <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-clock me-2 text-muted"></i>
                            <strong><?= $jadwal['waktu_mulai'] ?> - <?= $jadwal['waktu_selesai'] ?></strong>
                          </div>
                          
                          <?php if ($isAbsen): ?>
                            <div class="d-flex align-items-center mb-2">
                              <i class="bi bi-check-circle me-2 text-success"></i>
                              <small class="text-muted">
                                Absen: <?= date('H:i', strtotime($jadwal['waktu_absen'])) ?>
                              </small>
                            </div>
                            
                            <?php if (!empty($jadwal['keterangan'])): ?>
                              <div class="d-flex align-items-start">
                                <i class="bi bi-chat-left-text me-2 text-muted"></i>
                                <small class="text-muted">
                                  <?= htmlspecialchars($jadwal['keterangan']) ?>
                                </small>
                              </div>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                        
                        <?php if (!$isAbsen && !$isExpired): ?>
                          <!-- Button Absensi -->
                          <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" 
                                    onclick="showAbsenModal(<?= $jadwal['id_jadwal'] ?>, '<?= htmlspecialchars($jadwal['nama_kelas']) ?>', '<?= $jadwal['waktu_mulai'] ?>')">
                              <i class="bi bi-check-circle me-2"></i>Check-in Sekarang
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" 
                                    onclick="showAbsenModal(<?= $jadwal['id_jadwal'] ?>, '<?= htmlspecialchars($jadwal['nama_kelas']) ?>', '<?= $jadwal['waktu_mulai'] ?>', 'other')">
                              <i class="bi bi-exclamation-circle me-1"></i>Izin/Sakit
                            </button>
                          </div>
                        <?php elseif ($isExpired && !$isAbsen): ?>
                          <div class="text-center">
                            <small class="text-danger">
                              <i class="bi bi-clock-history me-1"></i>
                              Jadwal sudah berakhir
                            </small>
                          </div>
                        <?php else: ?>
                          <div class="text-center">
                            <small class="text-success">
                              <i class="bi bi-check-all me-1"></i>
                              Absensi sudah tercatat
                            </small>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>

          <?php else: ?>
            <!-- No Schedule -->
            <div class="card-body">
              <div class="text-center py-5">
                <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                <h5>Tidak Ada Jadwal Hari Ini</h5>
                <p class="text-muted mb-3">
                  Anda tidak memiliki jadwal mengajar untuk hari ini.<br>
                  Silakan cek kembali jadwal Anda atau hubungi admin.
                </p>
                <a href="<?= $baseURL ?>jadwal/index.php" class="btn btn-primary">
                  <i class="bi bi-calendar-event me-2"></i>Lihat Jadwal Lengkap
                </a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Absensi -->
  <div class="modal fade" id="absenModal" tabindex="-1" aria-labelledby="absenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="absenModalLabel">
            <i class="bi bi-person-check me-2"></i>Absensi Instruktur
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <h6 id="modalKelas" class="text-dark"></h6>
            <small class="text-muted">
              <i class="bi bi-clock me-1"></i>
              <span id="modalWaktu"></span> • 
              <span id="modalTanggal"><?= date('d M Y') ?></span>
            </small>
          </div>
          
          <form id="absenForm">
            <input type="hidden" id="modalJadwalId" name="id_jadwal">
            
            <div class="mb-3">
              <label class="form-label">Status Kehadiran *</label>
              <div class="row g-2">
                <div class="col-6">
                  <input type="radio" class="btn-check" name="status" id="statusHadir" value="hadir" autocomplete="off">
                  <label class="btn btn-outline-success w-100" for="statusHadir">
                    <i class="bi bi-check-circle me-1"></i>Hadir
                  </label>
                </div>
                <div class="col-6">
                  <input type="radio" class="btn-check" name="status" id="statusIzin" value="izin" autocomplete="off">
                  <label class="btn btn-outline-info w-100" for="statusIzin">
                    <i class="bi bi-info-circle me-1"></i>Izin
                  </label>
                </div>
                <div class="col-6">
                  <input type="radio" class="btn-check" name="status" id="statusSakit" value="sakit" autocomplete="off">
                  <label class="btn btn-outline-warning w-100" for="statusSakit">
                    <i class="bi bi-heart-pulse me-1"></i>Sakit
                  </label>
                </div>
                <div class="col-6">
                  <input type="radio" class="btn-check" name="status" id="statusAlpha" value="tanpa keterangan" autocomplete="off">
                  <label class="btn btn-outline-danger w-100" for="statusAlpha">
                    <i class="bi bi-x-circle me-1"></i>Alpha
                  </label>
                </div>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="2" 
                        placeholder="Tambahkan keterangan jika diperlukan..."></textarea>
            </div>
            
            <div class="alert alert-info">
              <small>
                <i class="bi bi-info-circle me-1"></i>
                <strong>Catatan:</strong> Anda akan dianggap terlambat jika check-in lebih dari 30 menit setelah jadwal dimulai.
              </small>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-lg me-1"></i>Batal
          </button>
          <button type="button" class="btn btn-primary" onclick="saveAbsensi()">
            <i class="bi bi-check-lg me-1"></i>Simpan Absensi
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  // Show modal absensi
  function showAbsenModal(idJadwal, namaKelas, waktuMulai, defaultStatus = 'hadir') {
    document.getElementById('modalJadwalId').value = idJadwal;
    document.getElementById('modalKelas').textContent = namaKelas;
    document.getElementById('modalWaktu').textContent = waktuMulai;
    
    // Reset form
    document.getElementById('absenForm').reset();
    
    // Set default status
    if (defaultStatus === 'hadir') {
      document.getElementById('statusHadir').checked = true;
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('absenModal'));
    modal.show();
  }
  
  // Save absensi
  function saveAbsensi() {
    const form = document.getElementById('absenForm');
    const formData = new FormData(form);
    const saveBtn = document.querySelector('.modal-footer .btn-primary');
    
    // Validasi status
    const status = formData.get('status');
    if (!status) {
      Swal.fire({
        title: 'Perhatian!',
        text: 'Pilih status kehadiran terlebih dahulu',
        icon: 'warning'
      });
      return;
    }
    
    // Loading state
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';
    
    // Add action
    formData.append('action', 'save_absensi_instruktur');
    
    fetch('', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('absenModal'));
        modal.hide();
        
        // Show success message
        let message = data.message;
        if (data.is_late) {
          message += ' (Anda terlambat dari jadwal yang ditentukan)';
        }
        
        Swal.fire({
          title: 'Berhasil!',
          text: message,
          icon: data.is_late ? 'warning' : 'success',
          timer: 2000,
          showConfirmButton: false
        }).then(() => {
          // Reload page to update UI
          location.reload();
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
      // Reset button
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Simpan Absensi';
    });
  }
  
  // Update clock
  function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit'
    });
    document.getElementById('currentTime').textContent = timeString;
  }
  
  // Event listeners
  document.addEventListener('DOMContentLoaded', function() {
    // Update clock every minute
    updateClock();
    setInterval(updateClock, 60000);
    
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
  </script>

</body>
</html>