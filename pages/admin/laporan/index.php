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
  <title>Laporan - Dashboard</title>
  <link rel="icon" type="image/png" href="../../../assets/img/favicon.png"/>
  <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="../../../assets/css/bootstrap-icons.css" />
  <link rel="stylesheet" href="../../../assets/css/fonts.css" />
  <link rel="stylesheet" href="../../../assets/css/styles.css" />
  
  <style>
    .laporan-card {
      transition: all 0.3s ease;
      border: 1px solid #dee2e6;
      border-radius: 12px;
      overflow: hidden;
    }
    
    .laporan-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      border-color: #0c63e4;
    }
    
    .laporan-card .card-header {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      color: #495057;
      padding: 0.75rem 1rem;
      border: none;
      border-bottom: 1px solid #dee2e6;
    }
    
    .laporan-card .card-body {
      padding: 1rem;
    }
    
    .laporan-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
      margin: 0 auto 1rem auto;
    }
    
    .icon-absensi { background: linear-gradient(135deg, #0c63e4, #3d8bfd); }
    .icon-pengguna { background: linear-gradient(135deg, #6f42c1, #8e58d4); }
    .icon-siswa { background: linear-gradient(135deg, #fd7e14, #ff922b); }
    .icon-instruktur { background: linear-gradient(135deg, #dc3545, #e55353); }
    .icon-gelombang { background: linear-gradient(135deg, #198754, #20c997); }
    .icon-kelas { background: linear-gradient(135deg, #0dcaf0, #31d2f2); }
    
    .btn-cetak {
      background-color: #0c63e4;
      border-color: #0c63e4;
      border-radius: 6px;
      padding: 0.5rem 1rem;
      font-weight: 500;
      transition: all 0.3s ease;
      color: white;
    }
    
    .btn-cetak:hover {
      background-color: #0a58ca;
      border-color: #0a53be;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(12,99,228,0.3);
      color: white;
    }
    
    .stat-badge {
      background: rgba(12,99,228,0.1);
      color: #0c63e4;
      padding: 0.2rem 0.6rem;
      border-radius: 15px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    
  .welcome-header {
  background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  text-align: center;
  box-shadow: 0 2px 8px rgba(12, 99, 228, 0.1);
}
  </style>
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
                <h2 class="page-title mb-1">PUSAT LAPORAN</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Pusat Laporan</li>
                  </ol>
                </nav>
              </div>
            </div>
            
            <!-- Right: Optional Info -->
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
        <!-- Alert Error -->
        <?php if (isset($error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

       <!-- Welcome Header -->
        <div class="welcome-header">
          <h3 class="mb-2">
            <i class="bi bi-file-earmark-bar-graph fs-3 opacity-75"></i>
            PUSAT LAPORAN LKP
          </h3>
          <p class="text-muted mb-0">
            Kelola dan cetak berbagai laporan data sistem. Total <strong> 11 jenis laporan</strong> tersedia.
          </p>
        </div>

       <!-- Info Alert -->
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
          <div class="d-flex align-items-start">
            <div class="flex-grow-1">
              <div class="mb-2">
                <i class="bi bi-info-circle me-2"></i>
                <small class="fw-bold">Informasi Laporan</small>
              </div>
              <small class="d-block">
                • Semua laporan akan dibuka di tab baru dalam format PDF<br>
                • Untuk laporan dengan data lebih spesifik, gunakan filter di halaman masing-masing modul
              </small>
            </div>
            <small class="text-muted ms-3">
              <i class="bi bi-clock me-1"></i>
              Terakhir diperbarui: <?= date('d/m/Y H:i') ?>
            </small>
          </div>
        </div>

        <!-- Laporan Cards - Grid Compact -->
        <div class="row">
          <!-- Laporan Pengguna -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-pengguna me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-people"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Pengguna</h6>
                    <small class="text-muted">Data akun sistem</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Total: <span class="stat-badge"><?= number_format($stats['pengguna'] ?? 0) ?> akun</span></small>
                <a href="../pengguna/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

          <!-- Laporan Instruktur -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-instruktur me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-person-workspace"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Instruktur</h6>
                    <small class="text-muted">Data pengajar</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Total: <span class="stat-badge"><?= number_format($stats['instruktur'] ?? 0) ?> instruktur</span></small>
                <a href="../instruktur/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

            <!-- Laporan Kelas -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-kelas me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-door-open"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Kelas</h6>
                    <small class="text-muted">Data ruang kelas</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Total: <span class="stat-badge"><?= number_format($stats['kelas'] ?? 0) ?> kelas</span></small>
                <a href="../kelas/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

            <!-- Laporan Pendaftar -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-absensi me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-person-plus"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Pendaftar</h6>
                    <small class="text-muted">Data calon siswa</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Semua periode pendaftaran</small>
                <a href="../pendaftar/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>


          <!-- BARU: Laporan Gelombang -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-gelombang me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-layers"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Gelombang</h6>
                    <small class="text-muted">Data periode pelatihan</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Total: <span class="stat-badge"><?= number_format($stats['gelombang'] ?? 0) ?> gelombang</span></small>
                <a href="../gelombang/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

          <!-- Laporan Siswa -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-siswa me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-mortarboard"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Siswa</h6>
                    <small class="text-muted">Data pelajar aktif</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Total: <span class="stat-badge"><?= number_format($stats['siswa'] ?? 0) ?> siswa</span></small>
                <a href="../siswa/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

          <!-- Laporan Jadwal -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-pengguna me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-calendar3"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Jadwal</h6>
                    <small class="text-muted">Jadwal mengajar</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Semua jadwal kelas</small>
                <a href="../jadwal/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

          <!-- Laporan Absensi -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-absensi me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-clipboard-check"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Absensi</h6>
                    <small class="text-muted">Data kehadiran</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Bulan ini: <span class="stat-badge"><?= number_format(($stats['absensi_siswa'] ?? 0) + ($stats['absensi_instruktur'] ?? 0)) ?> record</span></small>
                <div class="d-grid gap-1">
                  <a href="../absensi/cetak_laporan.php?tipe=siswa" target="_blank" 
                     class="btn btn-cetak-soft btn-sm">
                    <i class="bi bi-printer me-1"></i>Siswa
                  </a>
                  <a href="../absensi/cetak_laporan.php?tipe=instruktur" target="_blank" 
                     class="btn btn-cetak-soft btn-sm">
                    <i class="bi bi-printer me-1"></i>Instruktur
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Laporan Nilai -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-instruktur me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-trophy"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Nilai</h6>
                    <small class="text-muted">Hasil penilaian</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Semua nilai siswa</small>
                <a href="../nilai/cetak_laporan.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-printer me-1"></i>Cetak
                </a>
              </div>
            </div>
          </div>

          <!-- Laporan Hasil Evaluasi -->
          <div class="col-lg-4 col-md-6 mb-3">
            <div class="card laporan-card h-100">
              <div class="card-header py-2">
                <div class="d-flex align-items-center">
                  <div class="laporan-icon icon-pengguna me-3" style="width: 35px; height: 35px; font-size: 14px;">
                    <i class="bi bi-bar-chart"></i>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-0 text-dark fw-bold">Laporan Evaluasi</h6>
                    <small class="text-muted">Feedback siswa</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-2">
                <small class="text-muted d-block mb-2">Hasil evaluasi & grafik</small>
                <a href="../analisis-evaluasi/index.php" target="_blank" 
                   class="btn btn-cetak-soft btn-sm w-100">
                  <i class="bi bi-graph-up me-1"></i>Lihat
                </a>
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
    // Add smooth animation untuk cards
    const cards = document.querySelectorAll('.laporan-card');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      
      setTimeout(() => {
        card.style.transition = 'all 0.5s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, index * 100);
    });

    // Add click effect untuk buttons
    const buttons = document.querySelectorAll('.btn-cetak');
    buttons.forEach(button => {
      button.addEventListener('click', function(e) {
        // Add ripple effect
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        this.appendChild(ripple);
        
        setTimeout(() => {
          ripple.remove();
        }, 600);
      });
    });
  });
  </script>

  <style>
  .ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: scale(0);
    animation: ripple-animation 0.6s linear;
    pointer-events: none;
  }
  
  @keyframes ripple-animation {
    to {
      transform: scale(2);
      opacity: 0;
    }
  }
  </style>
</body>
</html>