<?php
session_start();
require_once '../../../includes/auth.php';
requireInstrukturAuth();

include '../../../includes/db.php';
$activePage = 'absensi'; 
$baseURL = '../';

// Set timezone Indonesia - Makassar (WITA)
date_default_timezone_set('Asia/Makassar');

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
            throw new Exception("Jadwal tidak valid!");
        }
        
        // Validasi siswa
        $checkSiswa = $conn->prepare("SELECT s.id_siswa FROM siswa s 
                                     JOIN jadwal j ON s.id_kelas = j.id_kelas
                                     WHERE s.id_siswa = ? AND j.id_jadwal = ? AND s.status_aktif = 'aktif'");
        $checkSiswa->bind_param("ii", $id_siswa, $id_jadwal);
        $checkSiswa->execute();
        if ($checkSiswa->get_result()->num_rows == 0) {
            throw new Exception("Siswa tidak valid!");
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
            throw new Exception("Gagal menyimpan absensi");
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Ambil daftar kelas yang diampu instruktur
$kelasQuery = "SELECT k.id_kelas, k.nama_kelas, g.nama_gelombang,
               COUNT(DISTINCT s.id_siswa) as total_siswa,
               COUNT(DISTINCT j.id_jadwal) as total_jadwal,
               COUNT(DISTINCT CASE WHEN j.tanggal <= CURDATE() THEN j.id_jadwal END) as jadwal_terlaksana,
               MAX(j.tanggal) as tanggal_selesai,
               CASE WHEN MAX(j.tanggal) < CURDATE() THEN 'selesai' ELSE 'berjalan' END as status_kelas
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
               LEFT JOIN siswa s ON k.id_kelas = s.id_kelas AND s.status_aktif = 'aktif'
               LEFT JOIN jadwal j ON k.id_kelas = j.id_kelas
               WHERE k.id_instruktur = ?
               GROUP BY k.id_kelas
               ORDER BY k.nama_kelas";

$kelasStmt = $conn->prepare($kelasQuery);
$kelasStmt->bind_param("i", $id_instruktur);
$kelasStmt->execute();
$kelasResult = $kelasStmt->get_result();

// Filter parameter
$selected_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Inisialisasi semua variable
$jadwalList = [];
$siswaData = [];
$kelasInfo = null;
$selectedJadwal = null;

if ($selected_kelas) {
    // Ambil info kelas
    mysqli_data_seek($kelasResult, 0);
    while ($kelas = $kelasResult->fetch_assoc()) {
        if ($kelas['nama_kelas'] == $selected_kelas) {
            $kelasInfo = $kelas;
            break;
        }
    }
    
    if ($kelasInfo) {
        // Ambil jadwal hari ini yang bisa diisi absensi
        $today = date('Y-m-d');
        $jadwalQuery = "SELECT j.*, 
                        CONCAT(j.waktu_mulai, ' - ', j.waktu_selesai) as waktu_jadwal,
                        (SELECT COUNT(*) FROM jadwal WHERE id_kelas = ? AND tanggal <= j.tanggal) as pertemuan_ke
                        FROM jadwal j 
                        WHERE j.id_kelas = ? AND j.tanggal = ?
                        ORDER BY j.waktu_mulai ASC";
        
        $jadwalStmt = $conn->prepare($jadwalQuery);
        $jadwalStmt->bind_param("iis", $kelasInfo['id_kelas'], $kelasInfo['id_kelas'], $today);
        $jadwalStmt->execute();
        $jadwalResult = $jadwalStmt->get_result();
        
        while ($jadwal = $jadwalResult->fetch_assoc()) {
            $jadwalList[] = $jadwal;
        }
        
        // Jika ada jadwal hari ini, ambil jadwal pertama dan data siswa
        if (!empty($jadwalList)) {
            $selectedJadwal = $jadwalList[0];
            
            $siswaQuery = "SELECT s.*, 
                           ab.status as status_absensi, 
                           ab.waktu_absen
                           FROM siswa s
                           LEFT JOIN absensi_siswa ab ON s.id_siswa = ab.id_siswa AND ab.id_jadwal = ?
                           WHERE s.id_kelas = ? AND s.status_aktif = 'aktif'
                           ORDER BY s.nama";
            
            $siswaStmt = $conn->prepare($siswaQuery);
            $siswaStmt->bind_param("ii", $selectedJadwal['id_jadwal'], $kelasInfo['id_kelas']);
            $siswaStmt->execute();
            $siswaResult = $siswaStmt->get_result();
            
            while ($siswa = $siswaResult->fetch_assoc()) {
                $siswaData[] = $siswa;
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
  <title>Absensi Kelas - LKP Pradata Komputer</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    /* Badge Styles - Konsisten dengan referensi */
    .badge.badge-active {
      background-color: #d1edff;
      color: #0c63e4;
      border: 1px solid #b6d7ff;
    }

    .badge.badge-inactive {
      background-color: #f8d7da;
      color: #842029;
      border: 1px solid #f5c6cb;
    }

    .badge.bg-success {
      background-color: #d1f2eb !important;
      color: #0f5132;
      border: 1px solid #badbcc;
    }

    .badge.bg-danger {
      background-color: #f8d7da !important;
      color: #842029;
      border: 1px solid #f5c6cb;
    }

    /* Button Styles - Konsisten dengan referensi */
    .btn-action.btn-view {
      background-color: #0c63e4;
      border-color: #0c63e4;
      color: white;
    }

    .btn-action.btn-view:hover {
      background-color: #0a58ca;
      border-color: #0a53be;
      color: white;
    }

    .btn-primary {
      background-color: #0c63e4;
      border-color: #0c63e4;
    }

    .btn-primary:hover {
      background-color: #0a58ca;
      border-color: #0a53be;
    }

    .btn-outline-primary {
      color: #0c63e4;
      border-color: #0c63e4;
    }

    .btn-outline-primary:hover {
      background-color: #0c63e4;
      border-color: #0c63e4;
    }

    .btn-outline-success {
      color: #0f5132;
      border-color: #198754;
    }

    .btn-outline-success:hover {
      background-color: #198754;
      border-color: #198754;
    }

    /* Custom Table Styles */
    .custom-table {
      border-collapse: separate;
      border-spacing: 0;
    }

    .custom-table th {
      font-weight: 600;
      background-color: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
      padding: 0.75rem 0.5rem;
    }

    .custom-table td {
      vertical-align: middle;
      padding: 0.75rem 0.5rem;
      border-bottom: 1px solid #dee2e6;
    }

    .custom-table tbody tr:hover {
      background-color: #f8f9fa;
    }

    /* Card & Layout Styles */
    .kelas-card {
      transition: all 0.3s ease;
      cursor: pointer;
      border: 1px solid #dee2e6;
    }
    
    .kelas-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .kelas-card.selected {
      border-color: #0c63e4;
      box-shadow: 0 0 0 0.2rem rgba(12, 99, 228, 0.25);
    }

    /* Progress & Status */
    .progress-thin {
      height: 6px;
    }

    .status-badge {
      font-size: 0.75rem;
      padding: 0.25rem 0.5rem;
    }

    /* Quick Actions Section */
    .absensi-section {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 1.5rem;
      margin-top: 1rem;
      border: 1px solid #e9ecef;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
    }

    .empty-state i {
      opacity: 0.5;
    }

    .empty-state h5 {
      color: #6c757d;
      margin-bottom: 1rem;
    }

    /* Color consistency */
    .text-primary {
      color: #0c63e4 !important;
    }

    .bg-primary {
      background-color: #0c63e4 !important;
    }

    .border-primary {
      border-color: #0c63e4 !important;
    }

    /* Radio button styling */
    .form-check-input:checked {
      background-color: #0c63e4;
      border-color: #0c63e4;
    }

    /* Card header styling */
    .card-header.bg-primary {
      background-color: #0c63e4 !important;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .jadwal-info {
        text-align: center;
      }
      
      .absensi-section .d-flex {
        flex-direction: column;
        gap: 1rem;
      }
      
      .btn-group {
        width: 100%;
      }
    }
  </style>
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
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Step 1: Pilih Kelas -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 text-dark">
              <i class="bi bi-person-check text-primary me-2"></i>
              Pilih Kelas yang Anda Ampu
            </h5>
            <small class="text-muted">Instruktur: <?= htmlspecialchars($nama_instruktur) ?></small>
          </div>
          <div class="card-body">
            <?php if ($kelasResult->num_rows > 0): ?>
              <div class="row g-3">
                <?php 
                mysqli_data_seek($kelasResult, 0);
                while ($kelas = $kelasResult->fetch_assoc()): 
                  $progress_pct = $kelas['total_jadwal'] > 0 ? 
                                 round(($kelas['jadwal_terlaksana'] / $kelas['total_jadwal']) * 100, 1) : 0;
                ?>
                  <div class="col-md-6 col-lg-4">
                    <div class="card kelas-card h-100 <?= $selected_kelas == $kelas['nama_kelas'] ? 'selected' : '' ?>"
                         onclick="selectKelas('<?= htmlspecialchars($kelas['nama_kelas']) ?>')">
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <h6 class="mb-0 fw-bold"><?= htmlspecialchars($kelas['nama_kelas']) ?></h6>
                          <span class="badge <?= $kelas['status_kelas'] == 'selesai' ? 'bg-danger' : 'bg-success' ?> status-badge">
                            <?= $kelas['status_kelas'] == 'selesai' ? 'SELESAI' : 'BERJALAN' ?>
                          </span>
                        </div>
                        
                        <?php if($kelas['nama_gelombang']): ?>
                          <small class="text-muted mb-2 d-block"><?= htmlspecialchars($kelas['nama_gelombang']) ?></small>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                          <div class="row text-center">
                            <div class="col-4">
                              <div class="fw-bold text-primary"><?= $kelas['total_siswa'] ?></div>
                              <small class="text-muted">Siswa</small>
                            </div>
                            <div class="col-4">
                              <div class="fw-bold text-info"><?= $kelas['jadwal_terlaksana'] ?></div>
                              <small class="text-muted">Terlaksana</small>
                            </div>
                            <div class="col-4">
                              <div class="fw-bold text-secondary"><?= $kelas['total_jadwal'] ?></div>
                              <small class="text-muted">Total</small>
                            </div>
                          </div>
                        </div>
                        
                        <div class="mb-2">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Progress</small>
                            <small class="fw-medium"><?= $progress_pct ?>%</small>
                          </div>
                          <div class="progress progress-thin">
                            <div class="progress-bar <?= $kelas['status_kelas'] == 'selesai' ? 'bg-success' : 'bg-primary' ?>" 
                                 style="width: <?= $progress_pct ?>%"></div>
                          </div>
                        </div>
                        
                        <?php if($selected_kelas == $kelas['nama_kelas']): ?>
                          <div class="text-center mt-2">
                            <i class="bi bi-check-circle text-success me-1"></i>
                            <small class="text-success fw-bold">Terpilih</small>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="text-center py-4">
                <div class="empty-state">
                  <i class="bi bi-inbox display-4 text-muted mb-3 d-block"></i>
                  <h5>Belum Ada Kelas</h5>
                  <p class="text-muted mb-0">Anda belum mengampu kelas apapun saat ini.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Step 2: Input Absensi (jika kelas dipilih) -->
        <?php if ($selected_kelas && $kelasInfo): ?>
          
          <!-- Header Info & Cetak - HANYA tampil jika ada jadwal hari ini -->
          <?php if (!empty($jadwalList) && $selectedJadwal): ?>
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 py-3">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <h5 class="mb-1">
                    <i class="bi bi-calendar-event me-2"></i>
                    Jadwal Hari Ini - <?= date('d M Y') ?>
                  </h5>
                  <p class="mb-2 opacity-90">
                    <i class="bi bi-clock me-1"></i>
                    <?= htmlspecialchars($selectedJadwal['waktu_mulai']) ?> - <?= htmlspecialchars($selectedJadwal['waktu_selesai']) ?>
                    <span class="ms-3">
                      <i class="bi bi-hash me-1"></i>
                      Pertemuan ke-<?= $selectedJadwal['pertemuan_ke'] ?>
                    </span>
                  </p>
                </div>
                <div class="col-md-4 text-md-end">
                  <button type="button" class="btn btn-action btn-view" onclick="cetakLaporanKelas()">
                    <i class="bi bi-printer me-1"></i>Cetak Laporan
                  </button>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($kelasInfo['status_kelas'] == 'selesai'): ?>
            <!-- Kelas Sudah Selesai -->
            <div class="card border-0 shadow-sm">
              <div class="card-body text-center py-5">
                <div class="empty-state">
                  <i class="bi bi-check-circle display-1 text-success mb-3 d-block"></i>
                  <h4 class="text-success mb-3">Kelas Sudah Selesai</h4>
                  <p class="text-muted mb-4">
                    Kelas <strong><?= htmlspecialchars($selected_kelas) ?></strong> telah menyelesaikan semua jadwal pembelajaran.<br>
                    <?php if($kelasInfo['tanggal_selesai']): ?>
                    Selesai pada: <strong><?= date('d M Y', strtotime($kelasInfo['tanggal_selesai'])) ?></strong>
                    <?php endif; ?>
                  </p>
                  <div class="row justify-content-center">
                    <div class="col-md-6">
                      <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Anda dapat mencetak laporan absensi lengkap untuk semua pertemuan yang telah terlaksana.
                      </div>
                      <button type="button" class="btn btn-primary" onclick="cetakLaporanKelas()">
                        <i class="bi bi-printer me-1"></i>Cetak Laporan Lengkap
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          <?php elseif (empty($jadwalList)): ?>
            <!-- Tidak Ada Jadwal Hari Ini -->
            <div class="card border-0 shadow-sm">
              <div class="card-body text-center py-5">
                <div class="empty-state">
                  <i class="bi bi-calendar-x display-1 text-muted mb-3 d-block"></i>
                  <h4>Tidak Ada Jadwal Hari Ini</h4>
                  <p class="text-muted mb-4">
                    Tidak ada jadwal untuk kelas <strong><?= htmlspecialchars($selected_kelas) ?></strong> pada hari ini.<br>
                    Silakan pilih kelas lain atau kembali di hari yang memiliki jadwal.
                  </p>
                  <div class="row justify-content-center">
                    <div class="col-md-6">
                      <div class="alert alert-warning">
                        <i class="bi bi-calendar-week me-2"></i>
                        Progress: <?= $kelasInfo['jadwal_terlaksana'] ?>/<?= $kelasInfo['total_jadwal'] ?> pertemuan terlaksana
                      </div>
                      <?php if($kelasInfo['jadwal_terlaksana'] > 0): ?>
                      <button type="button" class="btn btn-outline-primary" onclick="cetakLaporanKelas()">
                        <i class="bi bi-printer me-1"></i>Cetak Laporan yang Ada
                      </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
           </div> 

          <?php else: ?>
            <!-- Ada Jadwal Hari Ini - Input Absensi -->
            <div class="card border-0 shadow-sm">
              <?php if (!empty($siswaData)): ?>
                <div class="table-responsive">
                  <table class="table custom-table mb-0" id="absensiTable">
                    <thead>
                      <tr>
                        <th style="width: 40px;">NO</th>
                        <th style="min-width: 200px;">Nama Siswa</th>
                        <th class="text-center" style="width: 100px;">Hadir</th>
                        <th class="text-center" style="width: 100px;">Izin</th>
                        <th class="text-center" style="width: 100px;">Sakit</th>
                        <th class="text-center" style="width: 100px;">Alpha</th>
                        <th class="text-center" style="width: 120px;">Waktu</th>
                        <th class="text-center" style="width: 80px;">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $no = 1;
                      foreach ($siswaData as $siswa): 
                      ?>
                        <tr data-siswa="<?= $siswa['id_siswa'] ?>" data-jadwal="<?= $selectedJadwal['id_jadwal'] ?>">
                          <td class="text-center fw-bold"><?= $no++ ?></td>
                          
                          <td>
                            <div class="fw-medium"><?= htmlspecialchars($siswa['nama']) ?></div>
                            <small class="text-muted">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                          </td>
                          
                          <!-- Radio Buttons -->
                          <td class="text-center">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="hadir" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'hadir') ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="izin" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'izin') ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="sakit" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'sakit') ? 'checked' : '' ?>>
                          </td>
                          <td class="text-center">
                            <input type="radio" class="form-check-input status-radio" 
                                   name="status_<?= $siswa['id_siswa'] ?>" 
                                   value="tanpa keterangan" 
                                   data-siswa="<?= $siswa['id_siswa'] ?>"
                                   <?= ($siswa['status_absensi'] == 'tanpa keterangan') ? 'checked' : '' ?>>
                          </td>
                          
                          <!-- Waktu Absen -->
                          <td class="text-center">
                            <span class="waktu-absen-display">
                              <?php if($siswa['waktu_absen']): ?>
                                <small class="text-success fw-medium">
                                  <i class="bi bi-clock me-1"></i>
                                  <?= date('H:i', strtotime($siswa['waktu_absen'])) ?>
                                </small>
                              <?php else: ?>
                                <small class="text-muted">-</small>
                              <?php endif; ?>
                            </span>
                          </td>
                          
                          <!-- Aksi -->
                          <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary save-row" 
                                    onclick="saveAbsensiSiswa(<?= $siswa['id_siswa'] ?>, <?= $selectedJadwal['id_jadwal'] ?>)">
                              <i class="bi bi-save"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                
                <!-- Footer Actions -->
                <div class="card-footer bg-light">
                  <div class="row align-items-center">
                    <div class="col-md-6">
                      <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Gunakan <kbd>Ctrl+H</kbd> untuk semua hadir, <kbd>Ctrl+S</kbd> untuk simpan semua.
                      </small>
                    </div>
                    <div class="col-md-6 text-md-end">
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-simpan px-4" onclick="saveAllAbsensi()">
                         <i class="bi bi-check-lg me-1"></i>Simpan Semua
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                
              <?php else: ?>
                <div class="card-body text-center py-5">
                  <div class="empty-state">
                    <i class="bi bi-people display-4 text-muted mb-3 d-block"></i>
                    <h5>Belum Ada Siswa</h5>
                    <p class="text-muted mb-0">Tidak ada siswa aktif di kelas ini</p>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        <?php elseif (!$selected_kelas): ?>
          <!-- Belum Pilih Kelas -->
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
              <div class="empty-state">
                <i class="bi bi-arrow-up display-3 text-primary mb-3 d-block"></i>
                <h5>Mulai dengan Memilih Kelas</h5>
                <p class="text-muted">
                  Silakan pilih salah satu kelas di atas untuk mulai input absensi atau melihat laporan.
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  // Function untuk pilih kelas
  function selectKelas(namaKelas) {
    window.location.href = 'index.php?kelas=' + encodeURIComponent(namaKelas);
  }

  // Save individual absensi
  function saveAbsensiSiswa(idSiswa, idJadwal) {
    const row = document.querySelector(`tr[data-siswa="${idSiswa}"]`);
    const statusRadio = row.querySelector(`input[name="status_${idSiswa}"]:checked`);
    const saveBtn = row.querySelector('.save-row');
    
    if (!statusRadio) {
      Swal.fire({
        title: 'Pilih Status!',
        text: 'Pilih status absensi terlebih dahulu',
        icon: 'warning',
        timer: 2000
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
        // Update waktu absen
        const waktuDisplay = row.querySelector('.waktu-absen-display');
        
        // Buat waktu sekarang dengan timezone Makassar (WITA)
        const now = new Date();
        const makassarTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Makassar"}));
        const currentTime = makassarTime.toLocaleTimeString('id-ID', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: false
        });
        
        waktuDisplay.innerHTML = `
          <small class="text-success fw-medium">
            <i class="bi bi-clock me-1"></i>
            ${currentTime}
          </small>
        `;
        
        // Row animation
        row.style.backgroundColor = '#d1edff';
        setTimeout(() => row.style.backgroundColor = '', 2000);
        
        // Success notification
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Tersimpan!',
          showConfirmButton: false,
          timer: 1500
        });
      } else {
        Swal.fire({
          title: 'Gagal!',
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
      saveBtn.innerHTML = '<i class="bi bi-save"></i>';
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
        html: `Siswa berikut belum memiliki status absensi:<br><br><strong>${missingStatus.join('<br>')}</strong>`,
        icon: 'warning',
        confirmButtonText: 'OK, Saya Mengerti'
      });
      return;
    }
    
    // Konfirmasi sebelum simpan semua
    Swal.fire({
      title: 'Simpan Semua Absensi?',
      text: `Akan menyimpan absensi untuk ${totalRows} siswa`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, Simpan!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        // Show loading
        Swal.fire({
          title: 'Menyimpan...',
          text: `Memproses ${totalRows} data absensi`,
          allowOutsideClick: false,
          showConfirmButton: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
        
        // Process each row
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
              // Update waktu
              const waktuDisplay = row.querySelector('.waktu-absen-display');
              
              // Buat waktu sekarang dengan timezone Makassar (WITA)
              const now = new Date();
              const makassarTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Makassar"}));
              const currentTime = makassarTime.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
              });
              
              waktuDisplay.innerHTML = `
                <small class="text-success fw-medium">
                  <i class="bi bi-clock me-1"></i>
                  ${currentTime}
                </small>
              `;
              
              row.style.backgroundColor = '#d1f2eb';
            } else {
              hasError = true;
              row.style.backgroundColor = '#f8d7da';
            }
            
            // Check if all done
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
              
              // Reset backgrounds after delay
              setTimeout(() => {
                rows.forEach(row => {
                  row.style.backgroundColor = '';
                });
              }, 3000);
            }
          })
          .catch(error => {
            savedRows++;
            hasError = true;
            console.error('Error:', error);
            row.style.backgroundColor = '#f8d7da';
            
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
    });
  }
  
  // Quick select functions
  function selectAllStatus(status) {
    const radios = document.querySelectorAll(`input[value="${status}"]`);
    radios.forEach(radio => {
      radio.checked = true;
      // Trigger visual feedback
      const row = radio.closest('tr');
      row.style.backgroundColor = '#fff3cd';
      setTimeout(() => row.style.backgroundColor = '', 1500);
    });
    
    // Show notification
    const statusText = {
      'hadir': 'Hadir',
      'izin': 'Izin', 
      'sakit': 'Sakit',
      'tanpa keterangan': 'Alpha'
    };
    
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: `Semua siswa dipilih: ${statusText[status]}`,
      showConfirmButton: false,
      timer: 2000
    });
  }
  
  function resetAllStatus() {
    const radios = document.querySelectorAll('.status-radio');
    radios.forEach(radio => {
      radio.checked = false;
    });
    
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: 'Status absensi direset',
      showConfirmButton: false,
      timer: 1500
    });
  }

  // Function cetak laporan kelas (semua jadwal terlaksana)
  // Function cetak laporan kelas (tanpa loading)
function cetakLaporanKelas() {
  const namaKelas = '<?= htmlspecialchars($selected_kelas ?? '') ?>';
  
  if (!namaKelas) {
    Swal.fire({
      title: 'Pilih Kelas!',
      text: 'Silakan pilih kelas terlebih dahulu',
      icon: 'warning'
    });
    return;
  }
  
  // Langsung buka tanpa loading
  const url = `cetak_laporan.php?kelas=${encodeURIComponent(namaKelas)}`;
  const pdfWindow = window.open(url, '_blank');
  
  // Check if popup was blocked (tanpa delay)
  setTimeout(() => {
    if (!pdfWindow || pdfWindow.closed || typeof pdfWindow.closed == 'undefined') {
      Swal.fire({
        title: 'Pop-up Diblokir!',
        text: 'Browser memblokir pop-up. Silakan izinkan popup untuk mengunduh laporan PDF.',
        icon: 'warning',
        confirmButtonText: 'OK'
      });
    }
  }, 500);
}
  // Event listeners
  document.addEventListener('DOMContentLoaded', function() {
    // Auto-change detection
    const statusRadios = document.querySelectorAll('.status-radio');
    statusRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        const row = this.closest('tr');
        row.style.backgroundColor = '#fff3cd';
        setTimeout(() => row.style.backgroundColor = '', 2000);
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
      selectAllStatus('hadir');
    }
    
    // Ctrl+P untuk cetak laporan
    if (e.ctrlKey && e.key === 'p') {
      e.preventDefault();
      cetakLaporanKelas();
    }
  });
  </script>

</body>
</html>