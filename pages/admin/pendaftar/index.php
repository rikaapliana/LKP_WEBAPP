<?php
session_start();  
require_once '../../../includes/auth.php';  
requireAdminAuth();

include '../../../includes/db.php';
$activePage = 'pendaftar'; 
$baseURL = '../';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $recordsPerPage;

// Count total records untuk pagination
$countQuery = "SELECT COUNT(*) as total FROM pendaftar p";
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Ambil data pendaftar dengan pagination
$query = "SELECT p.* 
          FROM pendaftar p 
          ORDER BY p.id_pendaftar DESC
          LIMIT $recordsPerPage OFFSET $offset";
$result = mysqli_query($conn, $query);

// Hitung statistik pendaftar
$queryBelumVerifikasi = "SELECT COUNT(*) as total FROM pendaftar WHERE status_pendaftaran = 'Belum di Verifikasi'";
$resultBelumVerifikasi = mysqli_query($conn, $queryBelumVerifikasi);
$pendaftarBelumVerifikasi = mysqli_fetch_assoc($resultBelumVerifikasi)['total'];

$queryTerverifikasi = "SELECT COUNT(*) as total FROM pendaftar WHERE status_pendaftaran = 'Terverifikasi'";
$resultTerverifikasi = mysqli_query($conn, $queryTerverifikasi);
$pendaftarTerverifikasi = mysqli_fetch_assoc($resultTerverifikasi)['total'];

$queryDiterima = "SELECT COUNT(*) as total FROM pendaftar WHERE status_pendaftaran = 'Diterima'";
$resultDiterima = mysqli_query($conn, $queryDiterima);
$pendaftarDiterima = mysqli_fetch_assoc($resultDiterima)['total'];

// Untuk dropdown jam pilihan
$jamQuery = "SELECT DISTINCT jam_pilihan FROM pendaftar WHERE jam_pilihan IS NOT NULL ORDER BY jam_pilihan";
$jamResult = mysqli_query($conn, $jamQuery);

// Untuk dropdown status
$statusOptions = ['Belum di Verifikasi', 'Terverifikasi', 'Diterima', 'Ditolak'];

// Ambil data kelas untuk modal transfer
$kelasQuery = "SELECT k.*, g.nama_gelombang, 
               (SELECT COUNT(*) FROM siswa s WHERE s.id_kelas = k.id_kelas AND s.status_aktif = 'aktif') as siswa_terdaftar
               FROM kelas k 
               LEFT JOIN gelombang g ON k.id_gelombang = g.id_gelombang 
               WHERE g.status = 'aktif'
               ORDER BY k.nama_kelas";
$kelasResult = mysqli_query($conn, $kelasQuery);

// Function helper untuk URL pagination
function buildUrlWithFilters($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Data Pendaftar</title>
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
                <h2 class="page-title mb-1">DATA PENDAFTAR</h2>
                <nav aria-label="breadcrumb">
                  <ol class="breadcrumb page-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                      <a href="../dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                      <a href="#">Manajemen Siswa</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Data Pendaftar</li>
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
          <div class="col-md-3 mb-3">
            <div class="card stats-card stats-card-mobile">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center stats-card-content">
                  <div class="flex-grow-1 stats-text-content">
                    <h6 class="mb-1 stats-title">Total Pendaftar</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($totalRecords) ?></h3>
                    <small class="text-muted stats-subtitle">Keseluruhan pendaftar</small>
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
                    <h6 class="mb-1 stats-title">Belum Verifikasi</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($pendaftarBelumVerifikasi) ?></h3>
                    <small class="text-muted stats-subtitle">Menunggu review</small>
                  </div>
                  <div class="stats-icon bg-warning-light stats-icon-mobile">
                    <i class="bi bi-clock text-warning"></i>
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
                    <h6 class="mb-1 stats-title">Terverifikasi</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($pendaftarTerverifikasi) ?></h3>
                    <small class="text-muted stats-subtitle">Siap jadi siswa</small>
                  </div>
                  <div class="stats-icon bg-success-light stats-icon-mobile">
                    <i class="bi bi-shield-check text-success"></i>
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
                    <h6 class="mb-1 stats-title">Diterima</h6>
                    <h3 class="mb-0 stats-number"><?= number_format($pendaftarDiterima) ?></h3>
                    <small class="text-muted stats-subtitle">Sudah jadi siswa</small>
                  </div>
                  <div class="stats-icon bg-primary-light stats-icon-mobile">
                    <i class="bi bi-person-check text-primary"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Content Card -->
        <div class="card content-card">
          <div class="section-header">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="mb-0 text-dark">
                  <i class="bi bi-pencil-square me-2"></i>Kelola Data Pendaftar
                </h5>
              </div>
              <div class="col-md-6 text-md-end">
                <div class="btn-group ms-2">
                  <button type="button" class="btn btn-secondary-formal dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i>
                    Export Data
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item" href="export-pdf.php" target="_blank">
                        <i class="bi bi-file-pdf text-danger me-2"></i>
                        Export ke PDF
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="export-excel.php">
                        <i class="bi bi-file-excel text-success me-2"></i>
                        Export ke Excel
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Search/Filter Controls -->
          <div class="p-3 border-bottom">
            <div class="row align-items-center">  
              <div class="col-12">
                <div class="d-flex flex-wrap align-items-center gap-2 controls-container">
                  <!-- Search Box -->
                  <div class="d-flex align-items-center search-container">
                    <label for="searchInput" class="me-2 mb-0 search-label">
                      <small>Search:</small>
                    </label>
                    <input type="search" id="searchInput" class="form-control form-control-sm search-input" />
                  </div>
                  
                  <!-- Sort Button -->
                  <div class="dropdown">
                    <button class="btn btn-light btn-icon position-relative control-btn" 
                            type="button" 
                            data-bs-toggle="dropdown" 
                            data-bs-display="static"
                            aria-expanded="false"
                            title="Sort">
                      <i class="bi bi-arrow-down-up"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 200px;">
                      <li><h6 class="dropdown-header">Sort by</h6></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="asc">
                          <i class="bi bi-sort-alpha-down me-2"></i>Nama A-Z
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nama" data-order="desc">
                          <i class="bi bi-sort-alpha-up me-2"></i>Nama Z-A
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="tanggal" data-order="desc">
                          <i class="bi bi-calendar-check me-2"></i>Terbaru
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="tanggal" data-order="asc">
                          <i class="bi bi-calendar-x me-2"></i>Terlama
                        </a>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="nik" data-order="asc">
                          <i class="bi bi-credit-card me-2"></i>NIK
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item sort-option" href="#" data-sort="status" data-order="asc">
                          <i class="bi bi-shield-check me-2"></i>Status
                        </a>
                      </li>
                    </ul>
                  </div>
                  
                  <!-- Filter Button -->
                  <div class="dropdown">
                    <button class="btn btn-light btn-icon position-relative control-btn" 
                            type="button" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false"
                            id="filterButton"
                            title="Filter">
                      <i class="bi bi-funnel"></i>
                      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="filterBadge">
                        0
                      </span>
                    </button>
                    
                    <!-- Filter Dropdown -->
                    <div class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 300px;">
                      <h6 class="mb-3 fw-bold">
                        <i class="bi bi-funnel me-2"></i>Filter Data
                      </h6>
                      
                      <!-- Filter Status -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Status Pendaftaran</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                          <option value="">Semua Status</option>
                          <?php foreach($statusOptions as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      
                      <!-- Filter Jam Pilihan -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Jam Pilihan</label>
                        <select class="form-select form-select-sm" id="filterJam">
                          <option value="">Semua Jam</option>
                          <?php 
                          if ($jamResult) {
                            mysqli_data_seek($jamResult, 0);
                            while($jam = mysqli_fetch_assoc($jamResult)): ?>
                              <option value="<?= htmlspecialchars($jam['jam_pilihan']) ?>">
                                <?= htmlspecialchars($jam['jam_pilihan']) ?>
                              </option>
                            <?php endwhile;
                          } ?>
                        </select>
                      </div>
                      
                      <!-- Filter Jenis Kelamin -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Jenis Kelamin</label>
                        <select class="form-select form-select-sm" id="filterJK">
                          <option value="">Semua</option>
                          <option value="Laki-Laki">Laki-Laki</option>
                          <option value="Perempuan">Perempuan</option>
                        </select>
                      </div>

                      <!-- Filter Pendidikan -->
                      <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Pendidikan</label>
                        <select class="form-select form-select-sm" id="filterPendidikan">
                          <option value="">Semua Pendidikan</option>
                          <option value="SD">SD</option>
                          <option value="SLTP">SLTP</option>
                          <option value="SLTA">SLTA</option>
                          <option value="D1">D1</option>
                          <option value="D2">D2</option>
                          <option value="S1">S1</option>
                          <option value="S2">S2</option>
                          <option value="S3">S3</option>
                        </select>
                      </div>
                      
                      <hr class="my-3">
                      
                      <!-- Filter Buttons -->
                      <div class="row g-2">
                        <div class="col-6">
                          <button class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center" 
                                  id="applyFilter" 
                                  type="button"
                                  style="height: 36px;">
                            <i class="bi bi-check-lg me-1"></i>
                            <span>Terapkan</span>
                          </button>
                        </div>
                        <div class="col-6">
                          <button class="btn btn-light btn-sm w-100 d-flex align-items-center justify-content-center" 
                                  id="resetFilter" 
                                  type="button"
                                  style="height: 36px;">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            <span>Reset</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Result Info -->
                  <div class="ms-auto result-info d-flex align-items-center">
                    <label class="me-2 mb-0 search-label">
                      <small>Show:</small>
                    </label>
                    <div class="info-badge">
                      <span class="info-count"><?= (($currentPage - 1) * $recordsPerPage) + 1 ?>-<?= min($currentPage * $recordsPerPage, $totalRecords) ?></span>
                      <span class="info-separator">dari</span>
                      <span class="info-total"><?= number_format($totalRecords) ?></span>
                      <span class="info-label">data</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Table -->
          <div class="table-responsive" style="overflow-x: auto; overflow-y: visible;">
            <table class="custom-table mb-0" id="pendaftarTable">
              <thead class="sticky-top">
                <tr>
                  <th>No</th>
                  <th>NIK</th>
                  <th>Nama</th>
                  <th>Tempat Lahir</th>
                  <th>Tanggal Lahir</th>
                  <th>Jenis Kelamin</th>
                  <th>Pendidikan</th>
                  <th>Telepon</th>
                  <th>Email</th>
                  <th>Alamat</th>
                  <th>Jam Pilihan</th>
                  <th>Foto</th>
                  <th>KTP</th>
                  <th>KK</th>
                  <th>Ijazah</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php 
                  $no = ($currentPage - 1) * $recordsPerPage + 1;
                  while ($pendaftar = mysqli_fetch_assoc($result)): 
                  ?>
                    <tr>
                      <!-- No -->
                      <td class="text-center align-middle" style="text-align: center !important;"><?= $no++ ?></td>
                      
                      <!-- NIK -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Nama -->
                      <td class="align-middle text-nowrap">
                        <div class="fw-medium"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></div>
                      </td>

                        <!-- Tempat Lahir -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($pendaftar['tempat_lahir']) ?></small>
                      </td>
                      
                      <!-- Tanggal Lahir -->
                      <td class="align-middle">
                        <small><?= date('d/m/Y', strtotime($pendaftar['tanggal_lahir'])) ?></small>
                      </td>
                      
                      <!-- Jenis Kelamin -->
                      <td class="align-middle">
                        <?php if($pendaftar['jenis_kelamin'] == 'Laki-Laki'): ?>
                          <span>Laki-Laki</span>
                        <?php else: ?>
                         <span>Perempuan</span>
                        <?php endif; ?>
                      </td>
                      
                     
                      <!-- Pendidikan -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($pendaftar['pendidikan_terakhir'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Telepon -->
                      <td class="align-middle">
                        <small><?= htmlspecialchars($pendaftar['no_hp'] ?? '-') ?></small>
                      </td>
                      
                      <!-- Email -->
                      <td class="align-middle">
                        <small class="text-muted text-truncate d-inline-block" 
                               style="max-width: 150px;" 
                               title="<?= htmlspecialchars($pendaftar['email'] ?? '-') ?>">
                          <?= htmlspecialchars($pendaftar['email'] ?? '-') ?>
                        </small>
                      </td>
                      
                      <!-- Alamat -->
                      <td class="align-middle">
                        <small class="text-muted text-truncate d-inline-block" 
                               style="max-width: 200px;" 
                               title="<?= htmlspecialchars($pendaftar['alamat_lengkap'] ?? '-') ?>">
                          <?= htmlspecialchars($pendaftar['alamat_lengkap'] ?? '-') ?>
                        </small>
                      </td>
                      
                      <!-- Jam Pilihan -->
                      <td class="align-middle text-nowrap">
                        <span>
                          <?= htmlspecialchars($pendaftar['jam_pilihan'] ?? '-') ?>
                        </span>
                      </td>
                      
                      <!-- Foto -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php if($pendaftar['pas_foto'] && file_exists('../../../uploads/pas_foto_pendaftar/'.$pendaftar['pas_foto'])): ?>
                          <img src="../../../uploads/pas_foto_pendaftar/<?= $pendaftar['pas_foto'] ?>" 
                               alt="Foto" 
                               class="rounded photo-preview" 
                               style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e9ecef; margin: 0 auto; display: block;" 
                               title="<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>">
                        <?php else: ?>
                          <div class="photo-preview-placeholder" 
                               style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 6px; color: #6c757d; margin: 0 auto;">
                            <i class="bi bi-person-fill"></i>
                          </div>
                        <?php endif; ?>
                      </td>

                      <!-- KTP -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php if($pendaftar['ktp'] && file_exists('../../../uploads/ktp_pendaftar/'.$pendaftar['ktp'])): ?>
                          <a href="../../../uploads/ktp_pendaftar/<?= $pendaftar['ktp'] ?>" 
                             target="_blank" 
                             class="btn btn-sm btn-outline-danger" 
                             title="Download KTP"
                             download="KTP_<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>.pdf">
                            <i class="bi bi-file-pdf"></i>
                          </a>
                        <?php else: ?>
                          <span class="text-muted">
                            <i class="bi bi-file-x" title="Belum upload KTP"></i>
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Kartu Keluarga -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php if($pendaftar['kk'] && file_exists('../../../uploads/kk_pendaftar/'.$pendaftar['kk'])): ?>
                          <a href="../../../uploads/kk_pendaftar/<?= $pendaftar['kk'] ?>" 
                             target="_blank" 
                             class="btn btn-sm btn-outline-danger" 
                             title="Download Kartu Keluarga"
                             download="KK_<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>.pdf">
                            <i class="bi bi-file-pdf"></i>
                          </a>
                        <?php else: ?>
                          <span class="text-muted">
                            <i class="bi bi-file-x" title="Belum upload KK"></i>
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Ijazah -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php if($pendaftar['ijazah'] && file_exists('../../../uploads/ijazah_pendaftar/'.$pendaftar['ijazah'])): ?>
                          <a href="../../../uploads/ijazah_pendaftar/<?= $pendaftar['ijazah'] ?>" 
                             target="_blank" 
                             class="btn btn-sm btn-outline-danger" 
                             title="Download Ijazah"
                             download="Ijazah_<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>.pdf">
                            <i class="bi bi-file-pdf"></i>
                          </a>
                        <?php else: ?>
                          <span class="text-muted">
                            <i class="bi bi-file-x" title="Belum upload Ijazah"></i>
                          </span>
                        <?php endif; ?>
                      </td>
                      
                      <!-- Status -->
                      <td class="text-center align-middle" style="text-align: center !important;">
                        <?php 
                        $status = $pendaftar['status_pendaftaran'];
                        switch($status) {
                          case 'Belum di Verifikasi':
                            echo '<span class="badge bg-warning px-2 py-1">
                            <i class="bi bi-clock me-1"></i>Belum Verifikasi</span>';
                            break;
                          case 'Terverifikasi':
                            echo '<span class="badge bg-primary px-2 py-1">
                            <i class="bi bi-check-circle me-1"></i> Terverifikasi</span>';
                            break;
                          case 'Diterima':
                            echo '<span class="badge bg-success px-2 py-1">
                            <i class="bi bi-check-circle me-1"></i></i>Diterima</span>';
                            break;
                          default:
                            echo '<span class="badge badge-secondary">-</span>';
                        }
                        ?>
                      </td>
   
                      <!-- Aksi -->
                      <td class="text-center align-middle">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="detail.php?id=<?= $pendaftar['id_pendaftar'] ?>" 
                             class="btn btn-action btn-view btn-sm" 
                             data-bs-toggle="tooltip" 
                             title="Detail">
                            <i class="bi bi-eye"></i>
                          </a>
                          
                          <?php if($pendaftar['status_pendaftaran'] == 'Belum di Verifikasi'): ?>
                            <button type="button" 
                                    class="btn btn-action btn-edit btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalVerifikasi<?= $pendaftar['id_pendaftar'] ?>"
                                    title="Verifikasi">
                              <i class="bi bi-shield-check"></i>
                            </button>
                          <?php endif; ?>
                          
                          <?php if($pendaftar['status_pendaftaran'] == 'Terverifikasi'): ?>
                            <button type="button" 
                                    class="btn btn-action btn-success btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalTransfer<?= $pendaftar['id_pendaftar'] ?>"
                                    title="Transfer ke Siswa">
                              <i class="bi bi-arrow-right-circle"></i>
                            </button>
                          <?php endif; ?>
                          
                          <button type="button" 
                                  class="btn btn-action btn-delete btn-sm" 
                                  data-bs-toggle="modal" 
                                  data-bs-target="#modalHapus<?= $pendaftar['id_pendaftar'] ?>"
                                  title="Hapus">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Modal Verifikasi -->
                    <?php if($pendaftar['status_pendaftaran'] == 'Belum di Verifikasi'): ?>
                    <div class="modal fade" id="modalVerifikasi<?= $pendaftar['id_pendaftar'] ?>" tabindex="-1" aria-labelledby="modalVerifikasiLabel<?= $pendaftar['id_pendaftar'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                          <div class="modal-header bg-primary text-white border-0">
                            <div class="w-100">
                              <h5 class="modal-title" id="modalVerifikasiLabel<?= $pendaftar['id_pendaftar'] ?>">
                                <i class="bi bi-shield-check me-2"></i>Verifikasi Pendaftar
                              </h5>
                              <small>Review dan ubah status pendaftaran</small>
                            </div>
                          </div>
                          
                          <div class="modal-body">
                            <div class="text-center mb-3">
                              <?php if($pendaftar['pas_foto'] && file_exists('../../../uploads/pas_foto_pendaftar/'.$pendaftar['pas_foto'])): ?>
                                <img src="../../../uploads/pas_foto_pendaftar/<?= $pendaftar['pas_foto'] ?>" 
                                     alt="Foto" 
                                     class="rounded-circle mb-2"
                                     style="width: 80px; height: 80px; object-fit: cover;">
                              <?php else: ?>
                                <div class="bg-secondary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" 
                                     style="width: 80px; height: 80px;">
                                  <i class="bi bi-person-fill text-white fs-2"></i>
                                </div>
                              <?php endif; ?>
                              <h6 class="fw-bold"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></h6>
                              <small class="text-muted">NIK: <?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></small>
                            </div>
                            
                            <form method="POST" action="update_status.php">
                              <input type="hidden" name="id_pendaftar" value="<?= $pendaftar['id_pendaftar'] ?>">
                              
                              <div class="mb-3">
                                <label class="form-label">Status Baru:</label>
                                <select name="status_pendaftaran" class="form-select" required>
                                  <option value="Terverifikasi">Terverifikasi</option>
                                  <option value="Ditolak">Ditolak</option>
                                </select>
                              </div>
                              
                              <div class="mb-3">
                                <label class="form-label">Catatan (opsional):</label>
                                <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                              </div>
                              
                              <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">
                                  <i class="bi bi-check-lg me-1"></i>Update Status
                                </button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Modal Transfer ke Siswa -->
                    <?php if($pendaftar['status_pendaftaran'] == 'Terverifikasi'): ?>
                    <div class="modal fade" id="modalTransfer<?= $pendaftar['id_pendaftar'] ?>" tabindex="-1" aria-labelledby="modalTransferLabel<?= $pendaftar['id_pendaftar'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow-lg">
                          <div class="modal-header bg-success text-white border-0">
                            <div class="w-100">
                              <h5 class="modal-title" id="modalTransferLabel<?= $pendaftar['id_pendaftar'] ?>">
                                <i class="bi bi-arrow-right-circle me-2"></i>Transfer ke Siswa
                              </h5>
                              <small>Jadikan pendaftar sebagai siswa aktif</small>
                            </div>
                          </div>
                          
                          <div class="modal-body">
                            <!-- Info Pendaftar -->
                            <div class="row mb-4">
                              <div class="col-auto">
                                <?php if($pendaftar['pas_foto'] && file_exists('../../../uploads/pas_foto_pendaftar/'.$pendaftar['pas_foto'])): ?>
                                  <img src="../../../uploads/pas_foto_pendaftar/<?= $pendaftar['pas_foto'] ?>" 
                                       alt="Foto" 
                                       class="rounded"
                                       style="width: 80px; height: 80px; object-fit: cover;">
                                <?php else: ?>
                                  <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                       style="width: 80px; height: 80px;">
                                    <i class="bi bi-person-fill text-white fs-2"></i>
                                  </div>
                                <?php endif; ?>
                              </div>
                              <div class="col">
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($pendaftar['nama_pendaftar']) ?></h6>
                                <div class="text-muted small">
                                  <div><i class="bi bi-credit-card me-1"></i>NIK: <?= htmlspecialchars($pendaftar['nik'] ?? '-') ?></div>
                                  <div><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($pendaftar['email'] ?? '-') ?></div>
                                  <div><i class="bi bi-clock me-1"></i>Jam Pilihan: <?= htmlspecialchars($pendaftar['jam_pilihan'] ?? '-') ?></div>
                                </div>
                              </div>
                            </div>
                            
                            <form method="POST" action="transfer.php">
                              <input type="hidden" name="id_pendaftar" value="<?= $pendaftar['id_pendaftar'] ?>">
                              
                              <div class="mb-3">
                                <label class="form-label">Pilih Kelas:</label>
                                <select name="id_kelas" class="form-select" required>
                                  <option value="">-- Pilih Kelas --</option>
                                  <?php 
                                  if ($kelasResult) {
                                    mysqli_data_seek($kelasResult, 0);
                                    while($kelas = mysqli_fetch_assoc($kelasResult)): 
                                      $sisa_kapasitas = $kelas['kapasitas'] - $kelas['siswa_terdaftar'];
                                  ?>
                                    <option value="<?= $kelas['id_kelas'] ?>" 
                                            <?= ($sisa_kapasitas <= 0) ? 'disabled' : '' ?>>
                                      <?= htmlspecialchars($kelas['nama_kelas']) ?> 
                                      (<?= htmlspecialchars($kelas['nama_gelombang']) ?>) 
                                      - Sisa: <?= $sisa_kapasitas ?>/<?= $kelas['kapasitas'] ?>
                                      <?= ($sisa_kapasitas <= 0) ? ' - PENUH' : '' ?>
                                    </option>
                                  <?php endwhile; } ?>
                                </select>
                                <small class="text-muted">Pilih kelas sesuai dengan jam pilihan pendaftar</small>
                              </div>
                              
                              <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Proses Transfer:</strong>
                                <ul class="mb-0 mt-2">
                                  <li>Data pendaftar akan dipindah ke tabel siswa</li>
                                  <li>Username dan password otomatis dibuat</li>
                                  <li>Email credentials dikirim ke pendaftar</li>
                                  <li>Status berubah menjadi "Diterima"</li>
                                </ul>
                              </div>
                              
                              <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-success">
                                  <i class="bi bi-arrow-right-circle me-1"></i>Transfer ke Siswa
                                </button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Modal Konfirmasi Hapus -->
                    <div class="modal fade" id="modalHapus<?= $pendaftar['id_pendaftar'] ?>" tabindex="-1" aria-labelledby="modalHapusLabel<?= $pendaftar['id_pendaftar'] ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content border-0 shadow-lg">
                          
                          <!-- Modal Header -->
                          <div class="modal-header bg-danger text-white border-0">
                            <div class="w-100">
                              <div class="warning-icon">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                              </div>
                              <h5 class="modal-title" id="modalHapusLabel<?= $pendaftar['id_pendaftar'] ?>">
                                Konfirmasi Hapus
                              </h5>
                              <small>Tindakan ini tidak dapat dibatalkan</small>
                            </div>
                          </div>
                          
                          <!-- Modal Body -->
                          <div class="modal-body">
                            <p>Anda yakin ingin menghapus data pendaftar:</p>
                            
                            <div class="student-preview">
                              <!-- Foto Pendaftar -->
                              <?php if($pendaftar['pas_foto'] && file_exists('../../../uploads/pas_foto_pendaftar/'.$pendaftar['pas_foto'])): ?>
                                <img src="../../../uploads/pas_foto_pendaftar/<?= $pendaftar['pas_foto'] ?>" 
                                     alt="Foto Pendaftar" 
                                     class="rounded-circle">
                              <?php else: ?>
                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center">
                                  <i class="bi bi-person-fill text-white fs-4"></i>
                                </div>
                              <?php endif; ?>
                              
                              <!-- Info Pendaftar -->
                              <div class="text-center">
                                <div class="fw-bold">
                                  <?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>
                                </div>
                                <div class="text-muted">
                                  NIK: <?= htmlspecialchars($pendaftar['nik'] ?? '-') ?>
                                </div>
                              </div>
                            </div>
                            
                            <div class="alert alert-warning">
                              <i class="bi bi-info-circle me-2"></i>
                              Data dan semua file terkait akan dihapus permanen
                            </div>
                          </div>
                          
                          <!-- Modal Footer -->
                          <div class="modal-footer border-0">
                            <div class="row g-2 w-100">
                              <div class="col-6">
                                <button type="button" 
                                        class="btn btn-secondary w-100" 
                                        data-bs-dismiss="modal">
                                  <i class="bi bi-x-lg"></i>
                                  Batal
                                </button>
                              </div>
                              <div class="col-6">
                                <button type="button" 
                                        class="btn btn-danger w-100" 
                                        onclick="confirmDelete(<?= $pendaftar['id_pendaftar'] ?>, '<?= htmlspecialchars($pendaftar['nama_pendaftar']) ?>')">
                                  <i class="bi bi-trash"></i>
                                  Hapus
                                </button>
                              </div>
                            </div>
                          </div>
                          
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="16" class="text-center">
                      <div class="empty-state py-5">
                        <i class="bi bi-person-plus display-4 text-muted mb-3 d-block"></i>
                        <h5>Belum Ada Data Pendaftar</h5>
                        <p class="mb-3 text-muted">Data pendaftar akan muncul di sini setelah ada yang mendaftar</p>
                        <a href="../../../pendaftaran.php" class="btn btn-primary" target="_blank">
                          <i class="bi bi-plus-circle me-2"></i>Buka Form Pendaftaran
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

         <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
          <div class="card-footer">
            <div class="d-flex justify-content-end align-items-center">
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                  <!-- Previous Button -->
                  <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage > 1) ? buildUrlWithFilters($currentPage - 1) : '#' ?>">
                      <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>
                  
                  <?php
                  // Calculate pagination range
                  $startPage = max(1, $currentPage - 2);
                  $endPage = min($totalPages, $currentPage + 2);
                  
                  // Adjust range if we're near the beginning or end
                  if ($endPage - $startPage < 4) {
                    if ($startPage == 1) {
                      $endPage = min($totalPages, $startPage + 4);
                    } else {
                      $startPage = max(1, $endPage - 4);
                    }
                  }
                  ?>
                  
                  <!-- First page if not in range -->
                  <?php if ($startPage > 1): ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= buildUrlWithFilters(1) ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                      <li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <!-- Page numbers -->
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildUrlWithFilters($i) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  
                  <!-- Last page if not in range -->
                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>
                    <?php endif; ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= buildUrlWithFilters($totalPages) ?>"><?= $totalPages ?></a>
                    </li>
                  <?php endif; ?>
                  
                  <!-- Next Button -->
                  <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage < $totalPages) ? buildUrlWithFilters($currentPage + 1) : '#' ?>">
                      <i class="bi bi-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../../../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../../../assets/js/scripts.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('pendaftarTable');
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('.empty-state'));
    const filterButton = document.getElementById('filterButton');
    const filterBadge = document.getElementById('filterBadge');
    
    const originalOrder = [...rows];
    let activeFilters = 0;

    // Force dropdown positioning
    function forceDropdownPositioning() {
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.style.setProperty('position', 'absolute', 'important');
        menu.style.setProperty('top', '100%', 'important');
        menu.style.setProperty('bottom', 'auto', 'important');
        menu.style.setProperty('transform', 'none', 'important');
        menu.style.setProperty('z-index', '1055', 'important');
        menu.style.setProperty('margin-top', '2px', 'important');
        
        if (menu.classList.contains('dropdown-menu-end')) {
          menu.style.setProperty('right', '0', 'important');
          menu.style.setProperty('left', 'auto', 'important');
        }
      });
    }

    // Sort functionality
    function initializeSortOptions() {
      document.querySelectorAll('.sort-option').forEach(item => {
        item.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          document.querySelectorAll('.sort-option').forEach(opt => {
            opt.classList.remove('active');
            opt.style.backgroundColor = '';
            opt.style.color = '';
          });
          
          this.classList.add('active');
          this.style.backgroundColor = '#0d6efd';
          this.style.color = 'white';
          
          const sortType = this.dataset.sort;
          const sortOrder = this.dataset.order;
          
          sortTable(sortType, sortOrder);
          
          setTimeout(() => {
            const dropdownToggle = this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
            if (dropdownToggle) {
              const dropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
              if (dropdown) dropdown.hide();
            }
          }, 150);
        });
      });
    }
    
    function sortTable(type, order) {
      let sortedRows;
      
      try {
        switch(type) {
          case 'nama':
            sortedRows = [...rows].sort((a, b) => {
              const aName = (a.cells[2]?.textContent || '').trim().toLowerCase();
              const bName = (b.cells[2]?.textContent || '').trim().toLowerCase();
              return order === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
            });
            break;
            
          case 'tanggal':
            sortedRows = [...rows].sort((a, b) => {
              const parseDate = (dateStr) => {
                try {
                  const parts = dateStr.split('/');
                  if (parts.length === 3) {
                    return new Date(parts[2], parts[1] - 1, parts[0]);
                  }
                  return new Date(0);
                } catch (e) {
                  return new Date(0);
                }
              };
              
              const aDateCell = a.cells[3]?.querySelector('small.text-muted');
              const bDateCell = b.cells[3]?.querySelector('small.text-muted');
              const aDate = parseDate((aDateCell?.textContent || '').trim());
              const bDate = parseDate((bDateCell?.textContent || '').trim());
              return order === 'asc' ? aDate - bDate : bDate - aDate;
            });
            break;
            
          case 'nik':
            sortedRows = [...rows].sort((a, b) => {
              const aNik = (a.cells[1]?.textContent || '').trim();
              const bNik = (b.cells[1]?.textContent || '').trim();
              return order === 'asc' ? aNik.localeCompare(bNik) : bNik.localeCompare(aNik);
            });
            break;
            
          case 'status':
            sortedRows = [...rows].sort((a, b) => {
              const aStatus = (a.cells[14]?.textContent || '').trim();
              const bStatus = (b.cells[14]?.textContent || '').trim();
              return order === 'asc' ? aStatus.localeCompare(bStatus) : bStatus.localeCompare(aStatus);
            });
            break;
            
          default:
            sortedRows = [...originalOrder];
        }
        
        const fragment = document.createDocumentFragment();
        sortedRows.forEach(row => fragment.appendChild(row));
        tbody.appendChild(fragment);
        
        updateRowNumbers();
        
      } catch (error) {
        console.error('Sort error:', error);
        const fragment = document.createDocumentFragment();
        originalOrder.forEach(row => fragment.appendChild(row));
        tbody.appendChild(fragment);
        updateRowNumbers();
      }
    }

    // Filter functionality
    const searchInput = document.getElementById('searchInput');
    const filterStatus = document.getElementById('filterStatus');
    const filterJam = document.getElementById('filterJam');
    const filterJK = document.getElementById('filterJK');
    const filterPendidikan = document.getElementById('filterPendidikan');
    const applyFilterBtn = document.getElementById('applyFilter');
    const resetFilterBtn = document.getElementById('resetFilter');
    
    let searchTimeout;
    if (searchInput) {
      searchInput.addEventListener('input', function(e) {
        e.stopPropagation();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          applyFilters();
        }, 300);
      });
    }
    
    function applyFilters() {
      const searchTerm = (searchInput?.value || '').toLowerCase().trim();
      const statusFilter = filterStatus?.value || '';
      const jamFilter = filterJam?.value || '';
      const jkFilter = filterJK?.value || '';
      const pendidikanFilter = filterPendidikan?.value || '';
      
      let visibleCount = 0;
      activeFilters = 0;
      
      if (statusFilter) activeFilters++;
      if (jamFilter) activeFilters++;
      if (jkFilter) activeFilters++;
      if (pendidikanFilter) activeFilters++;
      
      updateFilterBadge();
      
      rows.forEach(row => {
        try {
          const nama = (row.cells[2]?.textContent || '').toLowerCase();
          const nik = (row.cells[1]?.textContent || '').toLowerCase();
          const email = (row.cells[7]?.textContent || '').toLowerCase();
          const tempat = (row.cells[3]?.querySelector('small.fw-medium')?.textContent || '').toLowerCase();
          
          const statusElement = row.cells[14]?.querySelector('.badge');
          let status = '';
          if (statusElement) {
            const statusText = statusElement.textContent.trim();
            if (statusText.includes('Belum')) status = 'Belum di Verifikasi';
            else if (statusText.includes('Terverifikasi')) status = 'Terverifikasi';
            else if (statusText.includes('Diterima')) status = 'Diterima';
            else if (statusText.includes('Ditolak')) status = 'Ditolak';
          }
          
          const jamElement = row.cells[9]?.querySelector('.badge');
          const jam = jamElement ? jamElement.textContent.replace(/.*\s/, '').trim() : '';
          
          const jkElement = row.cells[4]?.querySelector('.badge');
          let jk = '';
          if (jkElement) {
            const jkText = jkElement.textContent.trim();
            jk = jkText.includes('L') ? 'Laki-Laki' : 'Perempuan';
          }
          
          const pendidikan = (row.cells[5]?.textContent || '').trim();
          
          let showRow = true;
          
          if (searchTerm && 
              !nama.includes(searchTerm) && 
              !nik.includes(searchTerm) && 
              !email.includes(searchTerm) && 
              !tempat.includes(searchTerm)) {
            showRow = false;
          }
          
          if (statusFilter && status !== statusFilter) showRow = false;
          if (jamFilter && jam !== jamFilter) showRow = false;
          if (jkFilter && jk !== jkFilter) showRow = false;
          if (pendidikanFilter && pendidikan !== pendidikanFilter) showRow = false;
          
          row.style.display = showRow ? '' : 'none';
          if (showRow) visibleCount++;
          
        } catch (error) {
          console.error('Filter error for row:', error);
          row.style.display = '';
          visibleCount++;
        }
      });
      
      updateRowNumbers();
    }
    
    function updateFilterBadge() {
      if (!filterBadge || !filterButton) return;
      
      if (activeFilters > 0) {
        filterBadge.textContent = activeFilters;
        filterBadge.classList.remove('d-none');
        filterButton.classList.add('btn-primary');
        filterButton.classList.remove('btn-light');
      } else {
        filterBadge.classList.add('d-none');
        filterButton.classList.remove('btn-primary');
        filterButton.classList.add('btn-light');
      }
    }
    
    function updateRowNumbers() {
      let counter = <?= ($currentPage - 1) * $recordsPerPage + 1 ?>;
      rows.forEach(row => {
        if (row.style.display !== 'none') {
          row.cells[0].textContent = counter++;
        }
      });
    }

    // Event listeners
    if (applyFilterBtn) {
      applyFilterBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        applyFilters();
        setTimeout(() => {
          const dropdown = bootstrap.Dropdown.getInstance(filterButton);
          if (dropdown) dropdown.hide();
        }, 100);
      });
    }
    
    if (resetFilterBtn) {
      resetFilterBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (searchInput) searchInput.value = '';
        if (filterStatus) filterStatus.value = '';
        if (filterJam) filterJam.value = '';
        if (filterJK) filterJK.value = '';
        if (filterPendidikan) filterPendidikan.value = '';
        applyFilters();
      });
    }
    
    const filterDropdown = document.querySelector('.dropdown-menu.p-3');
    if (filterDropdown) {
      filterDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }

    // Dropdown event handlers
    document.addEventListener('show.bs.dropdown', function (e) {
      forceDropdownPositioning();
    });
    
    document.addEventListener('shown.bs.dropdown', function (e) {
      const dropdown = e.target.nextElementSibling;
      if (dropdown && dropdown.classList.contains('dropdown-menu')) {
        dropdown.style.setProperty('position', 'absolute', 'important');
        dropdown.style.setProperty('top', '100%', 'important');
        dropdown.style.setProperty('bottom', 'auto', 'important');
        dropdown.style.setProperty('transform', 'none', 'important');
        dropdown.style.setProperty('z-index', '1055', 'important');
        dropdown.style.setProperty('margin-top', '2px', 'important');
        
        if (dropdown.classList.contains('dropdown-menu-end')) {
          dropdown.style.setProperty('right', '0', 'important');
          dropdown.style.setProperty('left', 'auto', 'important');
        }
      }
    });

    // Initialize everything
    forceDropdownPositioning();
    initializeSortOptions();
    
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList' || mutation.type === 'attributes') {
          forceDropdownPositioning();
        }
      });
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'style']
    });
    
    try {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    } catch (e) {
      console.log('Tooltip initialization skipped');
    }
    
    window.addEventListener('resize', forceDropdownPositioning);
    window.addEventListener('scroll', forceDropdownPositioning);
  });

  // Fungsi konfirmasi hapus
  function confirmDelete(id, nama) {
    // Tutup modal Bootstrap terlebih dahulu
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalHapus' + id));
    if (modal) {
      modal.hide();
    }
    
    // Tampilkan loading pada tombol
    const deleteBtn = document.querySelector(`#modalHapus${id} .btn-danger`);
    if (deleteBtn) {
      deleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Memproses...';
      deleteBtn.disabled = true;
    }
    
    // Tunggu modal tertutup, lalu redirect
    setTimeout(() => {
      window.location.href = `hapus.php?id=${id}&confirm=delete`;
    }, 1000);
  }
  </script>
</body>
</html>