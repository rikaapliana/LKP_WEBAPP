<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'jadwal'; 
$baseURL = '../';

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID jadwal tidak valid!";
    header("Location: index.php");
    exit;
}

$id_jadwal = (int)$_GET['id'];

// Ambil data jadwal dengan join ke tabel kelas, gelombang, dan instruktur
$query = "SELECT j.*, 
          k.nama_kelas, 
          g.nama_gelombang, g.tahun as tahun_gelombang,
          i.nama as nama_instruktur, i.nik as nik_instruktur,
          DAYNAME(j.tanggal) as hari_nama,
          CASE DAYNAME(j.tanggal)
            WHEN 'Monday' THEN 'Senin'
            WHEN 'Tuesday' THEN 'Selasa' 
            WHEN 'Wednesday' THEN 'Rabu'
            WHEN 'Thursday' THEN 'Kamis'
            WHEN 'Friday' THEN 'Jumat'
            WHEN 'Saturday' THEN 'Sabtu'
            WHEN 'Sunday' THEN 'Minggu'
          END as hari_indonesia
          FROM jadwal j 
          LEFT JOIN kelas k ON j.id_kelas = k.id_kelas
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang  
          LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
          WHERE j.id_jadwal = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_jadwal);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data jadwal tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$jadwal = mysqli_fetch_assoc($result);

// Hitung status jadwal
$tanggalJadwal = strtotime($jadwal['tanggal']);
$today = strtotime(date('Y-m-d'));
$isToday = $tanggalJadwal == $today;
$isPast = $tanggalJadwal < $today;
$isUpcoming = $tanggalJadwal > $today;

// Status untuk tampilan
if ($isToday) {
    $status = 'today';
    $statusText = 'Hari Ini';
    $statusClass = 'bg-warning';
    $statusIcon = 'bi-clock';
} elseif ($isPast) {
    $status = 'past';
    $statusText = 'Selesai';
    $statusClass = 'bg-success';
    $statusIcon = 'bi-check-circle';
} else {
    $status = 'upcoming';
    $statusText = 'Terjadwal';
    $statusClass = 'bg-primary';
    $statusIcon = 'bi-calendar-check';
}

// Hitung jumlah siswa di kelas
$siswaQuery = "SELECT COUNT(*) as total FROM siswa WHERE id_kelas = ? AND status_aktif = 'aktif'";
$siswaStmt = mysqli_prepare($conn, $siswaQuery);
mysqli_stmt_bind_param($siswaStmt, "i", $jadwal['id_kelas']);
mysqli_stmt_execute($siswaStmt);
$siswaResult = mysqli_stmt_get_result($siswaStmt);
$siswaData = mysqli_fetch_assoc($siswaResult);
$total_siswa = $siswaData['total'];
mysqli_stmt_close($siswaStmt);

// Ambil data siswa yang terdaftar di kelas
$siswaListQuery = "SELECT s.nama, s.nik FROM siswa s WHERE s.id_kelas = ? AND s.status_aktif = 'aktif' ORDER BY s.nama LIMIT 10";
$siswaListStmt = mysqli_prepare($conn, $siswaListQuery);
mysqli_stmt_bind_param($siswaListStmt, "i", $jadwal['id_kelas']);
mysqli_stmt_execute($siswaListStmt);
$siswaListResult = mysqli_stmt_get_result($siswaListStmt);
$siswaList = [];
while ($row = mysqli_fetch_assoc($siswaListResult)) {
    $siswaList[] = $row;
}
mysqli_stmt_close($siswaListStmt);

// Hitung durasi jadwal
$waktu_mulai = new DateTime($jadwal['waktu_mulai']);
$waktu_selesai = new DateTime($jadwal['waktu_selesai']);
$durasi = $waktu_mulai->diff($waktu_selesai);
$durasi_text = $durasi->h . ' jam ' . $durasi->i . ' menit';
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Jadwal - <?= htmlspecialchars($jadwal['nama_kelas']) ?></title>
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
              <h2 class="page-title mb-1">DETAIL JADWAL</h2>
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
                  <li class="breadcrumb-item active" aria-current="page">Detail</li>
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
      <!-- Alert -->
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle me-2"></i>
          <?= $_SESSION['success'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <!-- Profile Header Card -->
      <div class="card content-card mb-4">
        <div class="card-body p-4">
          <div class="row align-items-start">
            <div class="col-auto">
              <div class="schedule-icon">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                  <i class="bi <?= $statusIcon ?> text-secondary" style="font-size: 3rem;"></i>
                </div>
              </div>
            </div>
            <div class="col">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1 fw-bold"><?= htmlspecialchars($jadwal['nama_kelas']) ?></h3>
                  <p class="text-muted mb-2">
                    <?= date('l, d F Y', strtotime($jadwal['tanggal'])) ?> • 
                    <?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> - <?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?>
                  </p>
                  <div class="d-flex gap-3">
                    <span class="badge <?= $statusClass ?> fs-6 px-3 py-2">
                      <i class="bi <?= $statusIcon ?> me-1"></i>
                      <?= $statusText ?>
                    </span>
                    <?php if($jadwal['nama_gelombang']): ?>
                      <span class="badge bg-secondary fs-6 px-3 py-2">
                        <i class="bi bi-collection me-1"></i>
                        <?= htmlspecialchars($jadwal['nama_gelombang']) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $jadwal['id_jadwal'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Data Jadwal -->
        <div class="col-lg-8">
          <!-- Informasi Jadwal -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-calendar-week me-2"></i>Informasi Jadwal
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Nama Kelas</label>
                    <div class="fw-medium"><?= htmlspecialchars($jadwal['nama_kelas']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Gelombang</label>
                    <div class="fw-medium">
                      <?= htmlspecialchars($jadwal['nama_gelombang'] ?? '-') ?>
                      <?= $jadwal['tahun_gelombang'] ? ' (' . htmlspecialchars($jadwal['tahun_gelombang']) . ')' : '' ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Tanggal</label>
                    <div class="fw-medium"><?= date('d F Y', strtotime($jadwal['tanggal'])) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Hari</label>
                    <div class="fw-medium"><?= htmlspecialchars($jadwal['hari_indonesia']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Waktu Mulai</label>
                    <div class="fw-medium"><?= date('H:i', strtotime($jadwal['waktu_mulai'])) ?> WIB</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Waktu Selesai</label>
                    <div class="fw-medium"><?= date('H:i', strtotime($jadwal['waktu_selesai'])) ?> WIB</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Durasi</label>
                    <div class="fw-medium"><?= $durasi_text ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <div class="fw-medium">
                      <span class="badge <?= $statusClass ?> px-2 py-1">
                        <i class="bi <?= $statusIcon ?> me-1"></i>
                        <?= $statusText ?>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Daftar Siswa -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-people me-2"></i>Daftar Siswa Kelas (<?= $total_siswa ?> orang)
              </h5>
            </div>
            <div class="card-body">
              <?php if(!empty($siswaList)): ?>
                <div class="list-group list-group-flush">
                  <?php foreach($siswaList as $index => $siswa): ?>
                    <div class="list-group-item d-flex align-items-center px-0 py-2">
                      <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 32px; height: 32px;">
                        <span class="fw-bold text-primary small"><?= $index + 1 ?></span>
                      </div>
                      <div class="flex-grow-1">
                        <div class="fw-medium"><?= htmlspecialchars($siswa['nama']) ?></div>
                        <small class="text-muted">NIK: <?= htmlspecialchars($siswa['nik']) ?></small>
                      </div>
                      <div class="text-end">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                          <i class="bi bi-check-circle me-1"></i>Aktif
                        </span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  
                  <?php if($total_siswa > 10): ?>
                    <div class="list-group-item text-center py-2 bg-light border-bottom-0">
                      <small class="text-muted">
                        <i class="bi bi-three-dots me-1"></i>
                        Dan <?= $total_siswa - 10 ?> siswa lainnya
                      </small>
                    </div>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="text-center p-4 text-muted">
                  <i class="bi bi-people display-6 mb-3 d-block opacity-50"></i>
                  <h6 class="text-muted">Tidak Ada Siswa</h6>
                  <small>Belum ada siswa yang terdaftar di kelas ini</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Info Jadwal -->
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-info-circle me-2"></i>Info & Instruktur
              </h5>
            </div>
            <div class="card-body">
              <!-- Info Instruktur -->
              <div class="mb-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-person-badge me-2"></i>Instruktur
                </h6>
                <?php if($jadwal['nama_instruktur']): ?>
                  <div class="text-center p-3">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-person-fill text-secondary fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($jadwal['nama_instruktur']) ?></h6>
                    <small class="text-muted">NIK: <?= htmlspecialchars($jadwal['nik_instruktur'] ?? '-') ?></small>
                  </div>
                <?php else: ?>
                  <div class="text-center p-3">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-person-x text-muted fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-muted">Belum Ada Instruktur</h6>
                    <small class="text-muted">Belum ditentukan</small>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Divider -->
              <hr class="my-4">
              
              <!-- Statistik -->
              <div>
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-graph-up me-2"></i>Statistik Pembelajaran
                </h6>
                <div class="list-group list-group-flush">
                  <!-- Total Siswa -->
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                      <i class="bi bi-people text-primary me-2"></i>
                      <span class="text-dark">Total Siswa</span>
                    </div>
                    <span class="badge bg-primary rounded-pill"><?= $total_siswa ?></span>
                  </div>

                  <!-- Durasi -->
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                      <i class="bi bi-clock text-info me-2"></i>
                      <span class="text-dark">Durasi</span>
                    </div>
                    <span class="text-muted"><?= $durasi_text ?></span>
                  </div>

                  <!-- Status Waktu -->
                  <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                    <div>
                      <i class="bi <?= $statusIcon ?> text-secondary me-2"></i>
                      <span class="text-dark">Status</span>
                    </div>
                    <?php if($isToday): ?>
                      <small class="text-primary">Hari ini</small>
                    <?php elseif($isPast): ?>
                      <small class="text-success">Selesai</small>
                    <?php else: ?>
                      <?php 
                      $days_diff = ceil(($tanggalJadwal - $today) / (60 * 60 * 24));
                      ?>
                      <small class="text-muted"><?= $days_diff ?> hari lagi</small>
                    <?php endif; ?>
                  </div>
                </div>
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
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>
</body>
</html>