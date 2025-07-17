<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'kelas'; 
$baseURL = '../';

// Validasi parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID kelas tidak valid!";
    header("Location: index.php");
    exit;
}

$id_kelas = (int)$_GET['id'];

// Ambil data kelas dengan join ke tabel gelombang dan instruktur
$query = "SELECT k.*, g.nama_gelombang, g.tahun, g.status as status_gelombang,
          i.nama as nama_instruktur, i.nik as nik_instruktur,
          COUNT(DISTINCT s.id_siswa) as total_siswa
          FROM kelas k 
          LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang
          LEFT JOIN instruktur i ON k.id_instruktur = i.id_instruktur
          LEFT JOIN siswa s ON k.id_kelas = s.id_kelas
          WHERE k.id_kelas = ?
          GROUP BY k.id_kelas";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_kelas);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Data kelas tidak ditemukan!";
    header("Location: index.php");
    exit;
}

$kelas = mysqli_fetch_assoc($result);

// Ambil daftar siswa di kelas ini
$siswaQuery = "SELECT s.*, u.username 
               FROM siswa s 
               LEFT JOIN user u ON s.id_user = u.id_user
               WHERE s.id_kelas = ?
               ORDER BY s.nama ASC";
$siswaStmt = mysqli_prepare($conn, $siswaQuery);
mysqli_stmt_bind_param($siswaStmt, "i", $id_kelas);
mysqli_stmt_execute($siswaStmt);
$siswaResult = mysqli_stmt_get_result($siswaStmt);

$siswa_list = [];
while ($siswa = mysqli_fetch_assoc($siswaResult)) {
    $siswa_list[] = $siswa;
}
mysqli_stmt_close($siswaStmt);

// Hitung statistik tambahan
$sisa_kapasitas = $kelas['kapasitas'] - $kelas['total_siswa'];
$persentase_terisi = $kelas['kapasitas'] > 0 ? round(($kelas['total_siswa'] / $kelas['kapasitas']) * 100) : 0;

// Ambil data jadwal kelas (jika ada)
$jadwalQuery = "SELECT j.*, i.nama as nama_instruktur_jadwal,
                DATE_FORMAT(j.tanggal, '%d/%m/%Y') as tanggal_formatted,
                TIME_FORMAT(j.waktu_mulai, '%H:%i') as waktu_mulai_formatted,
                TIME_FORMAT(j.waktu_selesai, '%H:%i') as waktu_selesai_formatted
                FROM jadwal j
                LEFT JOIN instruktur i ON j.id_instruktur = i.id_instruktur
                WHERE j.id_kelas = ?
                ORDER BY j.tanggal DESC, j.waktu_mulai ASC
                LIMIT 10";
$jadwalStmt = mysqli_prepare($conn, $jadwalQuery);
mysqli_stmt_bind_param($jadwalStmt, "i", $id_kelas);
mysqli_stmt_execute($jadwalStmt);
$jadwalResult = mysqli_stmt_get_result($jadwalStmt);

$jadwal_list = [];
while ($jadwal = mysqli_fetch_assoc($jadwalResult)) {
    $jadwal_list[] = $jadwal;
}
mysqli_stmt_close($jadwalStmt);

// Hitung total jadwal
$totalJadwalQuery = "SELECT COUNT(*) as total FROM jadwal WHERE id_kelas = ?";
$totalJadwalStmt = mysqli_prepare($conn, $totalJadwalQuery);
mysqli_stmt_bind_param($totalJadwalStmt, "i", $id_kelas);
mysqli_stmt_execute($totalJadwalStmt);
$totalJadwalResult = mysqli_stmt_get_result($totalJadwalStmt);
$totalJadwalData = mysqli_fetch_assoc($totalJadwalResult);
$total_jadwal = $totalJadwalData['total'];
mysqli_stmt_close($totalJadwalStmt);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detail Kelas - <?= htmlspecialchars($kelas['nama_kelas']) ?></title>
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
              <h2 class="page-title mb-1">DETAIL KELAS</h2>
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

      <!-- Class Header Card -->
      <div class="card content-card mb-4">
        <div class="card-body p-4">
          <div class="row align-items-start">
            <div class="col-auto">
              <div class="class-icon">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 100px; height: 100px; border: 4px solid #f8f9fa;">
                  <i class="bi bi-building text-white" style="font-size: 3rem;"></i>
                </div>
              </div>
            </div>
            <div class="col">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h3 class="mb-1 fw-bold"><?= htmlspecialchars($kelas['nama_kelas']) ?></h3>
                  <p class="text-muted mb-2">
                    <?= htmlspecialchars($kelas['nama_gelombang']) ?> 
                    <?= $kelas['tahun'] ? '(' . htmlspecialchars($kelas['tahun']) . ')' : '' ?>
                  </p>
                  <div class="d-flex gap-3">
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-building me-1"></i>
                      Kelas
                    </span>
                    <span class="badge bg-<?= $kelas['status_gelombang'] == 'aktif' ? 'success' : ($kelas['status_gelombang'] == 'dibuka' ? 'warning' : 'secondary') ?> fs-6 px-3 py-2">
                      <i class="bi bi-circle-fill me-1"></i>
                      <?= ucfirst(htmlspecialchars($kelas['status_gelombang'])) ?>
                    </span>
                    <?php if ($kelas['total_siswa'] >= $kelas['kapasitas']): ?>
                      <span class="badge bg-danger fs-6 px-3 py-2">
                        <i class="bi bi-person-fill-check me-1"></i>
                        Penuh
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex gap-3">
                  <a href="index.php" class="btn btn-kembali px-4">
                    Kembali
                  </a>
                  <a href="edit.php?id=<?= $kelas['id_kelas'] ?>" class="btn btn-edit px-4">
                    <i class="bi bi-pencil me-1"></i>Edit Data
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <!-- Data Kelas -->
        <div class="col-lg-8">
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-building me-2"></i>Data Kelas
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Nama Kelas</label>
                    <div class="fw-medium"><?= htmlspecialchars($kelas['nama_kelas']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Gelombang</label>
                    <div class="fw-medium">
                      <?= htmlspecialchars($kelas['nama_gelombang']) ?>
                      <?= $kelas['tahun'] ? ' (' . htmlspecialchars($kelas['tahun']) . ')' : '' ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Kapasitas</label>
                    <div class="fw-medium"><?= $kelas['kapasitas'] ?> siswa</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <label class="form-label small text-muted mb-1">Instruktur</label>
                    <div class="fw-medium">
                      <?= $kelas['nama_instruktur'] ? htmlspecialchars($kelas['nama_instruktur']) : 'Belum ditentukan' ?>
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
                <i class="bi bi-people me-2"></i>Daftar Siswa (<?= $kelas['total_siswa'] ?> orang)
              </h5>
            </div>
            <div class="card-body">
              <?php if(!empty($siswa_list)): ?>
                <div class="row g-3">
                  <?php foreach($siswa_list as $siswa): ?>
                    <div class="col-md-6">
                      <div class="card border border-success border-opacity-25 h-100">
                        <div class="card-body p-3">
                          <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                              <i class="bi bi-person text-success"></i>
                            </div>
                            <div class="flex-grow-1">
                              <h6 class="mb-1 fw-bold"><?= htmlspecialchars($siswa['nama']) ?></h6>
                              <small class="text-muted">
                                NIK: <?= htmlspecialchars($siswa['nik'] ?? '-') ?>
                                <?php if($siswa['username']): ?>
                                  <span class="badge bg-success bg-opacity-10 text-success ms-2">
                                    <i class="bi bi-person-check"></i> Akun
                                  </span>
                                <?php endif; ?>
                              </small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-center p-4 text-muted">
                  <i class="bi bi-people display-6 mb-3 d-block opacity-50"></i>
                  <h6 class="text-muted">Belum Ada Siswa</h6>
                  <small>Kelas ini belum memiliki siswa yang terdaftar</small>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Jadwal Kelas -->
          <div class="card content-card mb-4">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-calendar-event me-2"></i>Jadwal Terbaru
              </h5>
            </div>
            <div class="card-body">
              <?php if(!empty($jadwal_list)): ?>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="table-light">
                      <tr>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Instruktur</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach(array_slice($jadwal_list, 0, 5) as $jadwal): ?>
                        <tr>
                          <td class="fw-medium"><?= $jadwal['tanggal_formatted'] ?></td>
                          <td>
                            <span class="badge bg-light text-dark">
                              <?= $jadwal['waktu_mulai_formatted'] ?> - <?= $jadwal['waktu_selesai_formatted'] ?>
                            </span>
                          </td>
                          <td><?= htmlspecialchars($jadwal['nama_instruktur_jadwal'] ?? 'Belum ditentukan') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php if(count($jadwal_list) > 5): ?>
                  <div class="text-center mt-3">
                    <small class="text-muted">Menampilkan 5 dari <?= count($jadwal_list) ?> jadwal</small>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="text-center p-4 text-muted">
                  <i class="bi bi-calendar-x display-6 mb-3 d-block opacity-50"></i>
                  <h6 class="text-muted">Belum Ada Jadwal</h6>
                  <small>Kelas ini belum memiliki jadwal pembelajaran</small>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Info Sidebar -->
          <div class="card content-card">
            <div class="section-header">
              <h5 class="mb-0 text-dark">
                <i class="bi bi-info-circle me-2"></i>Statistik
              </h5>
            </div>
            <div class="card-body">
              <div class="row g-3 text-center mb-4">
                <div class="col-6">
                  <div class="p-3 bg-light rounded">
                    <i class="bi bi-people text-secondary fs-2 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold text-dark"><?= $kelas['total_siswa'] ?></h4>
                    <small class="text-muted">Siswa Aktif</small>
                  </div>
                </div>
                <div class="col-6">
                  <div class="p-3 bg-light rounded">
                    <i class="bi bi-calendar-event text-secondary fs-2 mb-2 d-block"></i>
                    <h4 class="mb-1 fw-bold text-dark"><?= $total_jadwal ?></h4>
                    <small class="text-muted">Total Jadwal</small>
                  </div>
                </div>
              </div>

              <!-- Progress Kapasitas -->
              <div class="mb-4">
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-speedometer2 me-2"></i>Kapasitas Kelas
                </h6>
                <div class="text-center">
                  <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar bg-<?= $persentase_terisi >= 100 ? 'danger' : ($persentase_terisi >= 80 ? 'warning' : 'primary') ?>" 
                         role="progressbar" 
                         style="width: <?= $persentase_terisi ?>%"
                         aria-valuenow="<?= $persentase_terisi ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                      <?= $persentase_terisi ?>%
                    </div>
                  </div>
                  <div class="row text-center">
                    <div class="col-4">
                      <h5 class="mb-0 fw-bold text-success"><?= $kelas['total_siswa'] ?></h5>
                      <small class="text-muted">Terisi</small>
                    </div>
                    <div class="col-4">
                      <h5 class="mb-0 fw-bold text-<?= $sisa_kapasitas <= 0 ? 'danger' : 'primary' ?>"><?= max(0, $sisa_kapasitas) ?></h5>
                      <small class="text-muted">Sisa</small>
                    </div>
                    <div class="col-4">
                      <h5 class="mb-0 fw-bold text-dark"><?= $kelas['kapasitas'] ?></h5>
                      <small class="text-muted">Kapasitas</small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Divider -->
              <hr class="my-4">

              <!-- Status Instruktur -->
              <div>
                <h6 class="fw-bold mb-3">
                  <i class="bi bi-person-workspace me-2"></i>Status Instruktur
                </h6>
                <?php if($kelas['nama_instruktur']): ?>
                  <div class="text-center p-3">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-person-check text-success fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-success">Instruktur Tersedia</h6>
                    <small class="text-muted">
                      <strong><?= htmlspecialchars($kelas['nama_instruktur']) ?></strong>
                    </small>
                    <?php if($kelas['nik_instruktur']): ?>
                      <br><small class="text-muted">NIK: <?= htmlspecialchars($kelas['nik_instruktur']) ?></small>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="text-center p-3">
                    <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                      <i class="bi bi-person-x text-warning fs-4"></i>
                    </div>
                    <h6 class="fw-bold mb-1 text-warning">Belum Ada Instruktur</h6>
                    <small class="text-muted">Kelas belum memiliki instruktur yang ditugaskan</small>
                  </div>
                <?php endif; ?>
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