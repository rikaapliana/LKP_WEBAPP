<?php
session_start();
require_once '../../../includes/auth.php';
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pengaturan';
$baseURL = '../';

// Ambil statistik gelombang
$statsQuery = "SELECT 
    COUNT(*) as total_gelombang,
    COUNT(CASE WHEN status = 'aktif' THEN 1 END) as gelombang_aktif,
    COUNT(CASE WHEN status = 'selesai' THEN 1 END) as gelombang_selesai
FROM gelombang";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Ambil status formulir pendaftaran aktif
$formulirQuery = "SELECT g.nama_gelombang, p.status_pendaftaran, p.tanggal_buka, p.tanggal_tutup,
    g.id_gelombang
FROM gelombang g
JOIN pengaturan_pendaftaran p ON g.id_gelombang = p.id_gelombang
WHERE p.status_pendaftaran = 'dibuka'
LIMIT 1";
$formulirResult = mysqli_query($conn, $formulirQuery);
$formulirAktif = mysqli_fetch_assoc($formulirResult);

// Hitung total pendaftar untuk gelombang aktif (estimasi)
$totalPendaftarAktifQuery = "SELECT COUNT(*) as total FROM pendaftar 
WHERE status_pendaftaran IN ('Belum di Verifikasi', 'Terverifikasi', 'Diterima')";
$totalPendaftarAktifResult = mysqli_query($conn, $totalPendaftarAktifQuery);
$pendaftarAktif = mysqli_fetch_assoc($totalPendaftarAktifResult)['total'];

// Hitung total pendaftar keseluruhan
$totalPendaftarQuery = "SELECT COUNT(*) as total FROM pendaftar";
$totalPendaftarResult = mysqli_query($conn, $totalPendaftarQuery);
$totalPendaftar = mysqli_fetch_assoc($totalPendaftarResult)['total'];

// Ambil gelombang terbaru
$gelombangTerbaruQuery = "SELECT * FROM gelombang ORDER BY tahun DESC, gelombang_ke DESC LIMIT 5";
$gelombangTerbaruResult = mysqli_query($conn, $gelombangTerbaruQuery);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengaturan Sistem - LKP Pradata Komputer</title>
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
            <div class="d-flex align-items-center flex-grow-1">
              <button class="btn btn-link text-dark p-2 me-3 sidebar-toggle" type="button" id="sidebarToggle">
                <i class="bi bi-list fs-4"></i>
              </button>
              
              <div class="page-info">
                <h2 class="page-title mb-1">PENGATURAN SISTEM</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
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

        <!-- Welcome Section -->
        <div class="welcome-card corporate mb-4">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="d-flex align-items-center mb-3">
                <div>
                  <h2 class="mb-1 font-roboto">Pusat Pengaturan Sistem</h2>
                  <p class="mb-0 opacity-90">
                    Kelola <strong>Gelombang</strong> dan <strong>Formulir Pendaftaran</strong><br>
                    <strong>LKP Pradata Komputer Kabupaten Tabalong</strong>
                  </p>
                </div>
              </div>
              
              <!-- Status Info -->
              <div class="row mt-3">
                <div class="col-auto">
                  <?php if ($formulirAktif): ?>
                    <span class="badge bg-success px-3 py-2">
                      <i class="bi bi-door-open me-1"></i>
                      Formulir AKTIF: <?= htmlspecialchars($formulirAktif['nama_gelombang']) ?>
                    </span>
                  <?php else: ?>
                    <span class="badge bg-secondary px-3 py-2">
                      <i class="bi bi-door-closed me-1"></i>
                      Tidak Ada Formulir Aktif
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <i class="bi bi-gear-fill fs-1 opacity-75"></i>
            </div>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Total Gelombang</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['total_gelombang']) ?></h3>
                    <small class="text-muted stats-subtitle">Keseluruhan gelombang</small>
                  </div>
                  <div class="stats-icon bg-primary-light stats-icon-mobile">
                    <i class="bi bi-layers text-primary"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Gelombang Aktif</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($stats['gelombang_aktif']) ?></h3>
                    <small class="text-muted stats-subtitle">Sedang berjalan</small>
                  </div>
                  <div class="stats-icon bg-success-light stats-icon-mobile">
                    <i class="bi bi-play-circle text-success"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Total Pendaftar</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($totalPendaftar) ?></h3>
                    <small class="text-muted stats-subtitle">Keseluruhan</small>
                  </div>
                  <div class="stats-icon bg-info-light stats-icon-mobile">
                    <i class="bi bi-person-plus text-info"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Formulir Aktif</h6>
                    <h3 class="mb-0 stats-number"><?= $formulirAktif ? number_format($pendaftarAktif) : '0' ?></h3>
                    <small class="text-muted stats-subtitle">Pendaftar active</small>
                  </div>
                  <div class="stats-icon bg-warning-light stats-icon-mobile">
                    <i class="bi bi-clipboard-check text-warning"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Menu Cards -->
        <div class="row mb-4">
          <div class="col-lg-6 mb-4">
            <a href="gelombang/" class="text-decoration-none">
              <div class="card content-card h-100 hover-lift">
                <div class="card-body text-center py-5">
                  <div class="mb-4">
                    <i class="bi bi-layers display-1 text-primary"></i>
                  </div>
                  <h3 class="card-title mb-3 text-dark">Kelola Gelombang</h3>
                  <p class="text-muted mb-4">
                    Tambah, edit, dan kelola gelombang pelatihan.<br>
                    Atur status dan periode gelombang.
                  </p>
                  <div class="row text-center">
                    <div class="col-4">
                      <div class="border-end">
                        <h5 class="mb-1 text-primary"><?= $stats['total_gelombang'] ?></h5>
                        <small class="text-muted">Total</small>
                      </div>
                    </div>
                    <div class="col-4">
                      <div class="border-end">
                        <h5 class="mb-1 text-success"><?= $stats['gelombang_aktif'] ?></h5>
                        <small class="text-muted">Aktif</small>
                      </div>
                    </div>
                    <div class="col-4">
                      <h5 class="mb-1 text-secondary"><?= $stats['gelombang_selesai'] ?></h5>
                      <small class="text-muted">Selesai</small>
                    </div>
                  </div>
                </div>
              </div>
            </a>
          </div>

          <div class="col-lg-6 mb-4">
            <a href="formulir/" class="text-decoration-none">
              <div class="card content-card h-100 hover-lift">
                <div class="card-body text-center py-5">
                  <div class="mb-4">
                    <i class="bi bi-clipboard-check display-1 text-success"></i>
                  </div>
                  <h3 class="card-title mb-3 text-dark">Kelola Formulir Pendaftaran</h3>
                  <p class="text-muted mb-4">
                    Buka/tutup formulir pendaftaran per gelombang.<br>
                    Monitor dan atur kuota pendaftar.
                  </p>
                  <div class="row text-center">
                    <div class="col-6">
                      <div class="border-end">
                        <?php if ($formulirAktif): ?>
                          <h5 class="mb-1 text-success">AKTIF</h5>
                          <small class="text-muted">Status</small>
                        <?php else: ?>
                          <h5 class="mb-1 text-secondary">TUTUP</h5>
                          <small class="text-muted">Status</small>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="col-6">
                      <h5 class="mb-1 text-info"><?= $formulirAktif ? $pendaftarAktif : '0' ?></h5>
                      <small class="text-muted">Pendaftar</small>
                    </div>
                  </div>
                </div>
              </div>
            </a>
          </div>
        </div>

        <!-- Quick Actions & Recent Activity -->
        <div class="row">
          <div class="col-lg-8 mb-4">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-layers me-2"></i>Gelombang Terbaru
                </h5>
              </div>
              
              <div class="card-body">
                <?php if (mysqli_num_rows($gelombangTerbaruResult) > 0): ?>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Nama Gelombang</th>
                          <th>Tahun</th>
                          <th>Status</th>
                          <th>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while ($gelombang = mysqli_fetch_assoc($gelombangTerbaruResult)): ?>
                        <tr>
                          <td class="fw-semibold"><?= htmlspecialchars($gelombang['nama_gelombang']) ?></td>
                          <td><?= $gelombang['tahun'] ?></td>
                          <td>
                            <?php 
                            $statusClass = 'secondary';
                            $statusText = 'Draft';
                            
                            switch($gelombang['status']) {
                              case 'aktif':
                                $statusClass = 'success';
                                $statusText = 'Aktif';
                                break;
                              case 'dibuka':
                                $statusClass = 'primary';
                                $statusText = 'Dibuka';
                                break;
                              case 'selesai':
                                $statusClass = 'secondary';
                                $statusText = 'Selesai';
                                break;
                            }
                            ?>
                            <span class="badge bg-<?= $statusClass ?>">
                              <?= $statusText ?>
                            </span>
                          </td>
                          <td>
                            <div class="btn-group btn-group-sm">
                              <a href="gelombang/edit.php?id=<?= $gelombang['id_gelombang'] ?>" 
                                 class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil"></i>
                              </a>
                              <a href="formulir/detail.php?id=<?= $gelombang['id_gelombang'] ?>" 
                                 class="btn btn-outline-success btn-sm">
                                <i class="bi bi-gear"></i>
                              </a>
                            </div>
                          </td>
                        </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <div class="text-center mt-3">
                    <a href="gelombang/" class="btn btn-outline-primary">
                      <i class="bi bi-eye me-1"></i>Lihat Semua Gelombang
                    </a>
                  </div>
                <?php else: ?>
                  <div class="text-center py-4">
                    <i class="bi bi-layers fs-1 text-muted mb-3"></i>
                    <p class="text-muted mb-3">Belum ada gelombang yang dibuat</p>
                    <a href="gelombang/tambah.php" class="btn btn-primary">
                      <i class="bi bi-plus-circle me-1"></i>Buat Gelombang Pertama
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-lg-4 mb-4">
            <div class="card content-card">
              <div class="section-header">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-lightning me-2"></i>Quick Actions
                </h5>
              </div>
              
              <div class="card-body">
                <div class="d-grid gap-3">
                  <a href="gelombang/tambah.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Buat Gelombang Baru
                  </a>
                  
                  <a href="formulir/" class="btn btn-success">
                    <i class="bi bi-toggle-on me-2"></i>Kelola Formulir
                  </a>
                  
                  <a href="../pendaftar/" class="btn btn-info">
                    <i class="bi bi-people me-2"></i>Lihat Pendaftar
                  </a>
                  
                  <a href="../kelas/" class="btn btn-warning">
                    <i class="bi bi-building me-2"></i>Kelola Kelas
                  </a>
                </div>
                
                <hr class="my-3">
                
                <!-- Status Formulir -->
                <div class="alert alert-light border">
                  <h6 class="alert-heading mb-2">
                    <i class="bi bi-info-circle me-1"></i>Status Formulir
                  </h6>
                  <?php if ($formulirAktif): ?>
                    <p class="mb-1"><strong>Formulir Aktif:</strong></p>
                    <p class="mb-1"><?= htmlspecialchars($formulirAktif['nama_gelombang']) ?></p>
                    <small class="text-muted">
                      <i class="bi bi-calendar me-1"></i>
                      Dibuka: <?= date('d/m/Y H:i', strtotime($formulirAktif['tanggal_buka'])) ?>
                    </small>
                  <?php else: ?>
                    <p class="mb-0 text-muted">Tidak ada formulir yang sedang aktif</p>
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
  
  <style>
  .hover-lift {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
  }
  
  .hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
  }
  
  .content-card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  }
  </style>
</body>
</html>