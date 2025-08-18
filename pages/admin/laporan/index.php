<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'laporan';
$baseURL = '../';

// Ambil statistik untuk dashboard
try {
    // Count total data untuk masing-masing modul
    $stats = [];
    
    // Statistik Pengguna
    $userQuery = "SELECT COUNT(*) as total FROM user";
    $userResult = mysqli_query($conn, $userQuery);
    $stats['pengguna'] = mysqli_fetch_assoc($userResult)['total'] ?? 0;
    
    // Statistik Siswa
    $siswaQuery = "SELECT COUNT(*) as total FROM siswa";
    $siswaResult = mysqli_query($conn, $siswaQuery);
    $stats['siswa'] = mysqli_fetch_assoc($siswaResult)['total'] ?? 0;
    
    // Statistik Instruktur
    $instrukturQuery = "SELECT COUNT(*) as total FROM instruktur";
    $instrukturResult = mysqli_query($conn, $instrukturQuery);
    $stats['instruktur'] = mysqli_fetch_assoc($instrukturResult)['total'] ?? 0;
    
    // Statistik Gelombang
    $gelombangQuery = "SELECT COUNT(*) as total FROM gelombang";
    $gelombangResult = mysqli_query($conn, $gelombangQuery);
    $stats['gelombang'] = mysqli_fetch_assoc($gelombangResult)['total'] ?? 0;
    
    // Statistik Kelas
    $kelasQuery = "SELECT COUNT(*) as total FROM kelas";
    $kelasResult = mysqli_query($conn, $kelasQuery);
    $stats['kelas'] = mysqli_fetch_assoc($kelasResult)['total'] ?? 0;
    
    // Statistik Absensi (bulan ini)
    $absensiSiswaQuery = "SELECT COUNT(*) as total FROM absensi_siswa WHERE MONTH(waktu_absen) = MONTH(CURRENT_DATE()) AND YEAR(waktu_absen) = YEAR(CURRENT_DATE())";
    $absensiSiswaResult = mysqli_query($conn, $absensiSiswaQuery);
    $stats['absensi_siswa'] = mysqli_fetch_assoc($absensiSiswaResult)['total'] ?? 0;
    
    $absensiInstrukturQuery = "SELECT COUNT(*) as total FROM absensi_instruktur WHERE MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())";
    $absensiInstrukturResult = mysqli_query($conn, $absensiInstrukturQuery);
    $stats['absensi_instruktur'] = mysqli_fetch_assoc($absensiInstrukturResult)['total'] ?? 0;
    
} catch (Exception $e) {
    $error = "Terjadi kesalahan: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pusat Laporan - Dashboard</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <style>
    /* Custom styles for a more formal report center */
    .page-header {
      background-color: var(--bs-light);
      border: 1px solid var(--bs-border-color);
      border-radius: 0.75rem;
      padding: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .report-card {
      border: 1px solid var(--bs-border-color);
      border-radius: 0.75rem;
      transition: all 0.25s ease-in-out;
      background-color: var(--bs-body-bg);
    }
    
    .report-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--bs-box-shadow);
      border-color: var(--bs-primary);
    }
    
    .report-icon {
      flex-shrink: 0;
      width: 40px;
      height: 40px;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      color: white;
    }
    
    /* Using solid colors from the original palette */
    .icon-absensi    { background-color: #0c63e4; }
    .icon-pengguna   { background-color: #6f42c1; }
    .icon-siswa      { background-color: #fd7e14; }
    .icon-instruktur { background-color: #dc3545; }
    .icon-gelombang  { background-color: #198754; }
    .icon-kelas      { background-color: #0dcaf0; }
    .icon-jadwal     { background-color: #6610f2; } /* Reusing a color */
    .icon-nilai      { background-color: #d63384; } /* Reusing a color */
    .icon-pendaftar  { background-color: #0d6efd; } /* Reusing a color */

    .stat-text {
      color: var(--bs-secondary-color);
      font-size: 0.9rem;
    }
  </style>
</head>

<body>
  <div class="d-flex">
    <?php include '../../../includes/sidebar/admin.php'; ?>

    <div class="flex-fill main-content">
      <nav class="top-navbar">
        <div class="container-fluid px-3 px-md-4">
          <div class="d-flex align-items-center">
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              <div class="page-info">
                <h2 class="page-title mb-1">Pusat Laporan</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pusat Laporan</li>
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

      <main class="container-fluid mt-4">
        <?php if (isset($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="page-header text-center">
          <h3 class="mb-1 fw-bold">
            <i class="bi bi-file-earmark-bar-graph me-2"></i>Pusat Laporan Sistem
          </h3>
          <p class="text-muted mb-0">
            Pilih dan cetak laporan yang dibutuhkan dari berbagai modul yang tersedia.
          </p>
        </div>

        <div class="row">
          <?php
            $reports = [
              ['title' => 'Laporan Pengguna',   'desc' => 'Data semua akun sistem',   'icon' => 'people',           'class' => 'icon-pengguna',   'stat' => ($stats['pengguna'] ?? 0) . ' akun',           'link' => '../pengguna/cetak_laporan.php'],
              ['title' => 'Laporan Instruktur', 'desc' => 'Data semua pengajar',      'icon' => 'person-workspace', 'class' => 'icon-instruktur', 'stat' => ($stats['instruktur'] ?? 0) . ' instruktur', 'link' => '../instruktur/cetak_laporan.php'],
              ['title' => 'Laporan Kelas',      'desc' => 'Data semua ruang kelas',   'icon' => 'door-open',        'class' => 'icon-kelas',      'stat' => ($stats['kelas'] ?? 0) . ' kelas',          'link' => '../kelas/cetak_laporan.php'],
              ['title' => 'Laporan Pendaftar',  'desc' => 'Data semua calon siswa',   'icon' => 'person-plus',      'class' => 'icon-pendaftar',  'stat' => 'Semua periode',                      'link' => '../pendaftar/cetak_laporan.php'],
              ['title' => 'Laporan Gelombang',  'desc' => 'Data periode pelatihan',   'icon' => 'layers',           'class' => 'icon-gelombang',  'stat' => ($stats['gelombang'] ?? 0) . ' gelombang',    'link' => '../gelombang/cetak_laporan.php'],
              ['title' => 'Laporan Siswa',      'desc' => 'Data semua pelajar aktif', 'icon' => 'mortarboard',      'class' => 'icon-siswa',      'stat' => ($stats['siswa'] ?? 0) . ' siswa',          'link' => '../siswa/cetak_laporan.php'],
              ['title' => 'Laporan Jadwal',     'desc' => 'Jadwal mengajar per kelas','icon' => 'calendar3',        'class' => 'icon-jadwal',     'stat' => 'Semua jadwal',                       'link' => '../jadwal/cetak_laporan.php'],
              ['title' => 'Laporan Nilai',      'desc' => 'Hasil penilaian siswa',    'icon' => 'trophy',           'class' => 'icon-nilai',      'stat' => 'Semua data nilai',                   'link' => '../nilai/cetak_laporan.php'],
            ];
          ?>

          <?php foreach ($reports as $report): ?>
          <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card report-card h-100 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                  <div class="report-icon <?= $report['class'] ?> me-3">
                    <i class="bi bi-<?= $report['icon'] ?>"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="card-title mb-0 fw-bold"><?= $report['title'] ?></h6>
                    <small class="text-muted"><?= $report['desc'] ?></small>
                  </div>
                </div>
                <p class="stat-text mb-3">
                  Total: <strong><?= $report['stat'] ?></strong>
                </p>
                <a href="<?= $report['link'] ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-auto">
                  <i class="bi bi-printer me-1"></i> Cetak Laporan
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          
          <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
            <div class="card report-card h-100 shadow-sm">
              <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                  <div class="report-icon icon-absensi me-3">
                    <i class="bi bi-clipboard-check"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="card-title mb-0 fw-bold">Laporan Absensi</h6>
                    <small class="text-muted">Data kehadiran</small>
                  </div>
                </div>
                <p class="stat-text mb-3">
                  Bulan ini: <strong><?= number_format(($stats['absensi_siswa'] ?? 0) + ($stats['absensi_instruktur'] ?? 0)) ?> record</strong>
                </p>
                <div class="d-grid gap-2 mt-auto">
                  <a href="../absensi/cetak_laporan.php?tipe=siswa" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-printer me-1"></i> Laporan Siswa
                  </a>
                  <a href="../absensi/cetak_laporan.php?tipe=instruktur" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer me-1"></i> Laporan Instruktur
                  </a>
                </div>
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Animasi fade-in sederhana untuk kartu saat halaman dimuat
    const cards = document.querySelectorAll('.report-card');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(15px)';
      setTimeout(() => {
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, index * 80);
    });
  });
  </script>
</body>
</html>